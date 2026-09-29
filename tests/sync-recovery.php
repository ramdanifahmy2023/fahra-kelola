<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/helpers/SyncQueue.php';
require '../app/helpers/SyncOutcome.php';
require '../app/models/BackgroundSync.php';
require '../app/models/SyncJob.php';
require '../app/models/ChatMonitor.php';
$db = new Database();
$checks = 0;
function checkSync($condition, $message) {
  global $checks;
  $checks++;
  if (!$condition) throw new RuntimeException($message);
}
function sqlSync($sql) { global $db; $db->query($sql); return $db->getAll(); }
$tables = ['sync_jobs', 'sync_job_orders', 'sync_runs', 'sync_schedules'];
try {
  foreach ($tables as $table) {
    $schema = sqlSync("SHOW CREATE TABLE {$table}")[0]['Create Table'];
    $schema = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $schema);
    $schema = str_replace(",\n)", "\n)", $schema);
    $db->query($schema); $db->exe();
  }
  sqlSync("INSERT INTO sync_jobs (id,shop_id,channel_id,sync_type,status,idempotency_key,page_number,updated_at) VALUES (1,1,1,'orders','running','test1',0,'2026-01-01'),(2,2,1,'orders','running','test2',1,'2026-01-02'),(3,3,1,'orders','running','test3',0,'2026-01-01')");
  sqlSync("INSERT INTO sync_job_orders (id,job_id,order_id,status,lease_until,next_retry_at) VALUES (1,1,9001,'running',DATE_SUB(NOW(),INTERVAL 1 DAY),NULL),(2,1,9002,'running',DATE_ADD(NOW(),INTERVAL 10 MINUTE),NULL),(3,3,9003,'retry',NULL,DATE_ADD(NOW(),INTERVAL 1 HOUR))");
  $queue = new SyncQueue($db);
  checkSync($queue->claimJob(3) === null, 'Future detail retries do not claim parent');
  checkSync($queue->claimJob(1,2) === null, 'Shop filter applies before claim');
  checkSync((int)$queue->claimJob()['id'] === 1, 'Oldest runnable job chosen');
  checkSync($queue->claimJob(1) === null, 'Leased job cannot be claimed twice');
  $tasks = $queue->claimOrders(1);
  checkSync(count($tasks) === 1 && (int)$tasks[0]['id'] === 1, 'Expired running detail reclaimed; live lease untouched');
  checkSync(count($queue->claimOrders(1)) === 0, 'Claimed detail not duplicated');
  $lease = sqlSync('SELECT lease_until FROM sync_job_orders WHERE id=2')[0]['lease_until'];
  $queue->heartbeat(1,[1]);
  checkSync(sqlSync('SELECT lease_until FROM sync_job_orders WHERE id=2')[0]['lease_until'] === $lease, 'Heartbeat touches only owned batch');
  sqlSync("UPDATE sync_job_orders SET status='done',lease_until=NULL WHERE id=1");
  sqlSync("UPDATE sync_jobs SET lease_until=NULL WHERE id=1");
  checkSync((int)$queue->claimJob()['id'] === 2, 'Other shop gets next turn');
  checkSync($queue->claimJob(1) === null, 'Parent with only leased details does not spin');
  sqlSync("UPDATE sync_job_orders SET status='done',lease_until=NULL WHERE id=2");
  checkSync((int)$queue->claimJob(1)['id'] === 1, 'Drained job can be finalized');
  sqlSync("UPDATE sync_jobs SET lease_until=NULL,next_retry_at=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE id=1");
  checkSync($queue->claimJob(1) === null, 'Explicit job selection respects backoff');
  sqlSync("INSERT INTO sync_jobs (id,shop_id,channel_id,sync_type,status,idempotency_key,page_number,updated_at) VALUES (4,4,1,'orders','running','test4',1,'2026-01-01'),(5,5,1,'orders','running','test5',1,'2026-01-02')");
  checkSync((int)$queue->claimJob()['id'] === 4, 'Old runnable page picked');
  sqlSync('UPDATE sync_jobs SET lease_until=NULL WHERE id=4');
  checkSync((int)$queue->claimJob()['id'] === 5, 'Continuing pages rotate between shops');
  class TestSyncJob extends SyncJob {
    public function __construct($db) { $this->db=$db; }
  }
  $jobModel = new TestSyncJob($db);
  sqlSync('UPDATE sync_jobs SET attempts=1487565 WHERE id=4');
  $jobModel->releaseJob(4, 'queued', 'Index temporarily unavailable');
  checkSync((int)sqlSync('SELECT TIMESTAMPDIFF(MINUTE,NOW(),next_retry_at) AS wait_minutes FROM sync_jobs WHERE id=4')[0]['wait_minutes'] <= 60, 'Legacy huge attempt count does not overflow exponential backoff');
  sqlSync('UPDATE sync_job_orders SET attempts=1487565 WHERE id=1');
  $jobModel->finishOrder(1, 'retry', 'Detail temporarily unavailable');
  checkSync((int)sqlSync('SELECT TIMESTAMPDIFF(MINUTE,NOW(),next_retry_at) AS wait_minutes FROM sync_job_orders WHERE id=1')[0]['wait_minutes'] <= 60, 'Detail exponential backoff is bounded before exponentiation');
  $reports=[];
  foreach (['daily','weekly','monthly'] as $p) foreach (['product','shop','live'] as $c) $reports[$p][$c]=['available'=>true];
  checkSync(SyncOutcome::ads($reports)['ok'], 'All fresh reports count as successful');
  $reports['daily']['product']=['available'=>false,'error_message'=>'Laporan ditolak (90309999).'];
  checkSync(!SyncOutcome::ads($reports)['ok'] && str_contains(SyncOutcome::ads($reports)['error'],'90309999'), 'Meta success cannot hide report failure');
  $reports['daily']['product']=['available'=>true,'stale'=>true];
  checkSync(!SyncOutcome::ads($reports)['ok'], 'Retained old report is not sync success');
  checkSync(!SyncOutcome::ads([])['ok'], 'Missing reports are failure');
  checkSync(SyncOutcome::retryDelay('user_is_forbidden')===900, 'Forbidden access gets cooldown');
  class TestBackgroundSync extends BackgroundSync {
    public function __construct($db) { $this->db=$db; }
    public function ensureSchema() {}
  }
  $background=new TestBackgroundSync($db);
  $background->ensureSchedules();
  sqlSync("UPDATE sync_schedules SET interval_seconds=1234,enabled=0,last_error='Prior failure' WHERE shop_id=1 AND sync_type='chat'");
  $background->ensureSchedules();
  $s=sqlSync("SELECT * FROM sync_schedules WHERE shop_id=1 AND sync_type='chat'")[0];
  checkSync((int)$s['interval_seconds']===1234 && (int)$s['enabled']===0, 'Scheduler preserves user interval and pause');
  $background->markResult(2,'chat',false,'user_is_forbidden');
  $s=sqlSync("SELECT *,TIMESTAMPDIFF(SECOND,NOW(),next_run_at) AS delay FROM sync_schedules WHERE shop_id=2 AND sync_type='chat'")[0];
  checkSync((int)$s['delay']>=899 && $s['last_error']==='user_is_forbidden' && $s['last_success_at']===null, 'Failure keeps last success and schedules cooldown');
  $background->markResult(2,'chat',true);
  $s=sqlSync("SELECT *,TIMESTAMPDIFF(SECOND,NOW(),next_run_at) AS delay FROM sync_schedules WHERE shop_id=2 AND sync_type='chat'")[0];
  checkSync($s['last_error']===null && $s['last_success_at']!==null && (int)$s['delay']<=30, 'Successful retry clears error and restores normal interval');
  class ChatWriteRecorder {
    public $queries=[];
    public function query($sql) { $this->queries[]=$sql; }
    public function bind($key,$value) {}
    public function exe() {}
  }
  $recorder=new ChatWriteRecorder();
  $chat=(new ReflectionClass(ChatMonitor::class))->newInstanceWithoutConstructor();
  (new ReflectionProperty(BaseModel::class,'db'))->setValue($chat,$recorder);
  $saveError=new ReflectionMethod(ChatMonitor::class,'saveError');
  $outcome=$saveError->invoke($chat,['id'=>1],true,'user_is_forbidden');
  checkSync($outcome['status']==='error' && !$outcome['ok'], 'Chat permission failure is not an expired shop session');
  checkSync(count(array_filter($recorder->queries,static function($sql){return str_contains($sql,'UPDATE shops');}))===0, 'Chat failure never overwrites global shop health');
  checkSync($saveError->invoke($chat,['id'=>1],true,'user_is_unauthorized')['status']==='expired', 'Actual chat expiry still reported');
  echo "PASS: {$checks} isolated database and outcome checks\n";
} finally {
  foreach (array_reverse($tables) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
