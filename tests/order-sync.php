<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/SyncJob.php';
require '../app/models/ShopeeCurl.php';
require '../app/models/BackgroundSync.php';
require '../app/helpers/OrderSyncPolicy.php';
require '../app/helpers/SyncWorkerTasks.php';
$db = new Database();
$checks = 0;
function checkOrder($value, $message) {
  global $checks;
  $checks++;
  if (!$value) throw new RuntimeException($message);
}
function orderSql($sql) {
  global $db;
  $db->query($sql);
  return $db->getAll();
}
class OrderTestJob extends SyncJob {
  public function __construct($db) { $this->db = $db; }
  public function ensureSchema() {}
}
class OrderTestSource extends ShopeeCurl {
  public $response;
  public $detail;
  public function getOrderIndexList($cookie, $pageNumber = 1, $pageSize = 40, $nextPageSentinel = '') { return $this->response; }
  public function getOneOrder($cookie, $orderId) { if ($this->detail === null) throw new RuntimeException('Fresh detail should not be requested'); return $this->detail; }
}
$now = strtotime('2026-09-29 00:00:00 UTC');
$known = ['1' => ['created_at' => '2026-09-28 00:00:00', 'detail_synced_at' => '2026-09-29 00:00:00']];
checkOrder(OrderSyncPolicy::continueIndex('diff', [1,2], $known, 'opaque', $now), 'Mixed known/new page continues');
checkOrder(OrderSyncPolicy::continueIndex('diff', [1], $known, 'opaque', $now), 'Known recent page continues within overlap');
$known['1']['created_at'] = '2026-09-01 00:00:00';
checkOrder(!OrderSyncPolicy::continueIndex('diff', [1], $known, 'opaque', $now), 'Known old boundary stops recent discovery');
checkOrder(OrderSyncPolicy::continueIndex('full', [1], $known, 'opaque', $now), 'History ignores known boundary');
checkOrder(!OrderSyncPolicy::continueIndex('full', [1], $known, '', $now), 'Missing cursor ends traversal');
checkOrder(!OrderSyncPolicy::needsDetail(['status_type'=>'completed','detail_synced_at'=>'2026-09-28 23:00:00'], $now), 'Fresh terminal details retained');
checkOrder(!OrderSyncPolicy::needsDetail(['status_type'=>'Selesai','detail_synced_at'=>'2026-09-28 23:00:00'], $now), 'Indonesian completed status uses terminal refresh interval');
checkOrder(!OrderSyncPolicy::needsDetail(['status_type'=>'Dibatalkan','detail_synced_at'=>'2026-09-28 23:00:00'], $now), 'Indonesian cancelled status uses terminal refresh interval');
checkOrder(OrderSyncPolicy::needsDetail(['status_type'=>'shipped','detail_synced_at'=>'2026-09-28 23:56:00'], $now), 'Active details refresh after three minutes');
checkOrder(OrderSyncPolicy::needsDetail(['status_type'=>'Perlu Dikirim','detail_synced_at'=>'2026-09-28 23:56:00'], $now), 'Indonesian active status refreshes after three minutes');
checkOrder(normalizeStatus('Selesai')==='completed' && normalizeStatus('Dibatalkan')==='cancelled', 'Stored sync state recognizes localized terminal statuses');
checkOrder(normalizeStatus('Perlu Dikirim')==='active' && normalizeStatus('Belum Bayar')==='active', 'Stored sync state recognizes localized active statuses');
$pdoError = new PDOException('Do not persist this driver message');
$pdoError->errorInfo = ['42S22', 1054, 'Do not persist this SQL message'];
checkOrder(syncWorkerErrorSummary('Worker order index error', $pdoError) === 'Worker order index error: PDOException SQLSTATE 42S22 driver 1054', 'Worker stores safe PDO diagnostics but not raw driver messages');
checkOrder(!OrderSyncPolicy::needsDetail(['sync_next_retry_at'=>'2026-09-29 01:00:00'], $now), 'Retry backoff preserved');
$tables = ['orders', 'order_items', 'sync_jobs', 'sync_job_orders', 'sync_runs', 'sync_pages', 'sync_schedules'];
try {
  foreach ($tables as $table) {
    $schema = orderSql("SHOW CREATE TABLE {$table}")[0]['Create Table'];
    $schema = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $schema);
    $db->query(str_replace(",\n)", "\n)", $schema)); $db->exe();
  }
  $sync = new OrderTestJob($db);
  $full = $sync->enqueue(1, 'full');
  $diff = $sync->enqueue(1, 'diff');
  checkOrder($full !== $diff, 'History cannot block creation of recent job');
  checkOrder($sync->enqueue(1, 'full') === $full && $sync->enqueue(1, 'diff') === $diff, 'One active job per mode');
  orderSql("INSERT INTO orders (id,shop_id,status_type,created_at,detail_synced_at) VALUES
    (1,1,'shipped','2026-09-01','2026-09-01'),
    (2,1,'completed','2026-09-01','2026-09-01'),
    (3,2,'shipped','2026-09-01','2026-09-01'),
    (6,1,'Perlu Dikirim','2026-09-01','2026-09-01'),
    (7,1,'Selesai','2026-09-01','2026-09-01'),
    (8,1,'Dibatalkan','2026-09-01','2026-09-01')");
  $job = orderSql("SELECT * FROM sync_jobs WHERE id={$diff}")[0];
  OrderSyncPolicy::queueActive($db, $sync, $job);
  $tasks = orderSql('SELECT order_id FROM sync_job_orders');
  checkOrder(array_map('intval',array_column($tasks,'order_id')) === [1,6], 'Old active orders refresh; English and Indonesian terminal orders plus other shops are excluded');
  OrderSyncPolicy::queueActive($db, $sync, $job);
  checkOrder(count(orderSql('SELECT id FROM sync_job_orders')) === 2, 'Active refresh is idempotent');
  $source = new OrderTestSource();
  $source->response = ['code'=>0,'data'=>['index_list'=>[['order_id'=>1],['order_id'=>4]],'pagination'=>['next_page_sentinel'=>'opaque']]];
  checkOrder(processIndex($db, $sync, $source, $job, ['id'=>1,'cookie'=>'fixture']), 'Real index path accepts valid mixed page');
  checkOrder((int)orderSql("SELECT page_number FROM sync_jobs WHERE id={$diff}")[0]['page_number'] === 2, 'Known ID does not stop discovery of next page');
  $source->response = ['code'=>0,'data'=>[]];
  checkOrder(!processIndex($db, $sync, $source, $job, ['id'=>1,'cookie'=>'fixture']), 'Malformed success response is retried, not completed');
  orderSql("UPDATE orders SET detail_synced_at=NOW() WHERE id=1");
  $task = orderSql('SELECT * FROM sync_job_orders WHERE order_id=1')[0];
  $requestCount = 0;
  checkOrder(processOrder($db, $sync, $source, $task, ['id'=>1,'cookie'=>'fixture'], false, $requestCount), 'Detail refreshed by another job is skipped');
  checkOrder($requestCount === 0, 'Skipped detail is not counted as an API request');
  orderSql('UPDATE sync_jobs SET next_retry_at=NULL,lease_until=NULL,updated_at=NOW()');
  checkOrder((int)$sync->claimJob()['id'] === $diff, 'Recent orders receive priority over history');
  orderSql("UPDATE sync_jobs SET updated_at=DATE_SUB(NOW(),INTERVAL 3 MINUTE) WHERE id={$full}");
  checkOrder((int)$sync->claimJob()['id'] === $full, 'Aged history gets a turn');
  orderSql("INSERT INTO orders (id,shop_id,status_type,created_at,detail_synced_at) VALUES (5,1,'shipped','2026-09-01','2026-09-01')");
  $sync->queueOrder($full, 5);
  $detailTaskId = (int)orderSql("SELECT id FROM sync_job_orders WHERE job_id={$full} AND order_id=5")[0]['id'];
  $source->detail = ['order_sn'=>'O5','status_info_v2'=>['status'=>'shipped'],'create_time'=>strtotime('2026-09-01 00:00:00 UTC'),'order_items'=>[['item_id'=>10,'model_id'=>31,'amount'=>2,'order_price'=>9000,'product'=>['name'=>'Variants'],'item_model'=>['name'=>'Red']]]];
  $detailTask = orderSql("SELECT * FROM sync_job_orders WHERE id={$detailTaskId}")[0]; $requestCount=0;
  checkOrder(processOrder($db,$sync,$source,$detailTask,['id'=>1,'cookie'=>'fixture'],false,$requestCount), 'Order detail stores the current variant item');
  $item = orderSql('SELECT product_id,model_id,quantity FROM order_items WHERE order_id=5')[0];
  checkOrder((int)$item['product_id']===10 && (int)$item['model_id']===31 && (int)$item['quantity']===2, 'Variant model ID and quantity are stored');
  orderSql("UPDATE sync_job_orders SET status='queued',completed_at=NULL WHERE id={$detailTaskId}");
  orderSql("UPDATE orders SET detail_synced_at='2026-09-01',status_type='shipped' WHERE id=5");
  $source->detail['order_items']=[['item_id'=>10,'model_id'=>32,'amount'=>3,'order_price'=>10000,'product'=>['name'=>'Variants'],'item_model'=>['name'=>'Blue']]];
  checkOrder(processOrder($db,$sync,$source,$detailTask,['id'=>1,'cookie'=>'fixture'],false,$requestCount), 'Order detail refresh replaces stale variant item');
  $items = orderSql('SELECT product_id,model_id,quantity FROM order_items WHERE order_id=5');
  checkOrder(count($items)===1 && (int)$items[0]['model_id']===32 && (int)$items[0]['quantity']===3, 'Refresh leaves one authoritative variant row');
  echo "PASS: {$checks} order synchronization checks\n";
} finally {
  foreach (array_reverse($tables) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
