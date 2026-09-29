<?php

declare(strict_types=1);

$workerLock = fopen(sys_get_temp_dir() . '/shopdash-worker-' . sha1(dirname(__DIR__)) . '.lock', 'c');
if (!$workerLock || !flock($workerLock, LOCK_EX | LOCK_NB)) exit(0);

chdir(__DIR__ . '/../public');
require_once '../app/init.php';
require_once '../app/models/SyncJob.php';
require_once '../app/models/ShopeeCurl.php';
require_once '../app/models/OrderIncome.php';
require_once '../app/models/BackgroundSync.php';
require_once '../app/models/ProductSync.php';
require_once '../app/models/AdsMonitor.php';
require_once '../app/models/ShopPerformance.php';
require_once '../app/models/PromotionMonitor.php';
require_once '../app/models/ChatMonitor.php';
require_once '../app/models/Customer.php';
require_once '../app/helpers/OrderSyncPolicy.php';

$options = getopt('', ['job::', 'shop::', 'once', 'batch::', 'rate-ms::', 'packages', 'limit::']);
$requestedJob = isset($options['job']) ? (int)$options['job'] : null;
$requestedShop = isset($options['shop']) ? (int)$options['shop'] : null;
$batchSize = max(1, min(50, (int)($options['batch'] ?? 10)));
$rateMs = max(100, min(10000, (int)($options['rate-ms'] ?? 350)));
$once = array_key_exists('once', $options);
$packageMode = array_key_exists('packages', $options);
$packageLimit = max(1, min(500, (int)($options['limit'] ?? 50)));

$sync = new SyncJob();
$sync->ensureSchema();
$db = new Database();
$shopee = new ShopeeCurl();

require_once '../app/helpers/SyncWorkerTasks.php';

$loops = 0;
if ($packageMode) {
  $shopId = $requestedShop ?: 0;
  if ($shopId < 1) {
    fwrite(STDERR, "--packages requires --shop=<id>\n");
    exit(2);
  }
  $db->query("SELECT * FROM shops WHERE id = :shop_id LIMIT 1");
  $db->bind('shop_id', $shopId);
  $shop = $db->single();
  if (!$shop || empty($shop['cookie'])) {
    fwrite(STDERR, "Shop cookie unavailable\n");
    exit(1);
  }
  $db->query("SELECT id FROM orders WHERE shop_id = :shop_id AND deleted_at IS NULL AND detail_synced_at IS NOT NULL AND (package_synced_at IS NULL OR shipping_cargo IS NULL OR shipping_cargo = '' OR tracking_number IS NULL OR tracking_number = '') ORDER BY id ASC LIMIT {$packageLimit}");
  $db->bind('shop_id', $shopId);
  $packageIds = $db->getAll();
  foreach ($packageIds as $packageRow) {
    enrichPackage($db, $shopee, (int)$packageRow['id'], $shop['cookie']);
    usleep($rateMs * 1000);
  }
  fwrite(STDOUT, json_encode(['package_processed' => count($packageIds)]) . PHP_EOL);
  exit(0);
}
do {
  $job = $sync->claimJob($requestedJob, $requestedShop);
  if (!$job) {
    break;
  }
  $db->query("SELECT * FROM shops WHERE id = :shop_id LIMIT 1");
  $db->bind('shop_id', (int)$job['shop_id']);
  $shop = $db->single();
  if (!$shop || empty($shop['cookie'])) {
    (new BackgroundSync())->markResult((int)$job['shop_id'], (string)($job['sync_type'] ?? 'orders'), false, 'Shop cookie unavailable', (string)($job['mode'] ?? 'diff'));
    $sync->releaseJob((int)$job['id'], 'failed', 'Shop cookie unavailable');
    break;
  }
  $jobId = (int)$job['id'];
  if (($job['sync_type'] ?? 'orders') !== 'orders') {
    try {
      $genericResult = processGenericJob($db, $job, $shop, $shopee, $rateMs, $packageLimit);
      [$genericOk, $genericError] = $genericResult;
      $genericComplete = $genericResult[2] ?? true;
    } catch (Throwable $exception) {
      $genericOk = false;
      $genericComplete = true;
      $genericError = 'Worker error: ' . get_class($exception);
    }
    if ($genericOk && !$genericComplete) {
      $sync->releaseJob($jobId, 'running');
      $loops++;
      continue;
    }
    $background = new BackgroundSync();
    $background->markResult((int)$job['shop_id'], (string)$job['sync_type'], $genericOk, $genericError, (string)($job['mode'] ?? 'diff'));
    $sync->releaseJob($jobId, $genericOk ? 'completed' : 'failed', $genericError);
    $loops++;
    continue;
  }
  if ((int)$job['page_number'] > 0) {
    if (!processIndex($db, $sync, $shopee, $job, $shop)) {
      $loops++;
      continue;
    }
  }
  $tasks = $sync->claimOrders($jobId, $batchSize);
  foreach ($tasks as $task) {
    $sync->heartbeat($jobId, array_column($tasks, 'id'));
    try {
      $requestCount = 0;
      $orderOk = processOrder($db, $sync, $shopee, $task, $shop, false, $requestCount);
    } catch (Throwable $error) {
      $sync->finishOrder((int)$task['id'], 'retry', 'Worker order detail error: ' . get_class($error));
      $orderOk = false;
    }
    recordRun($db, $jobId, $orderOk ? 'detail_success' : 'detail_failed');
    recordRun($db, $jobId, 'api_requests', $requestCount);
    usleep($rateMs * 1000);
  }
  $db->query("SELECT COUNT(*) AS c FROM sync_job_orders WHERE job_id = :job_id AND status IN ('queued','retry','running')");
  $db->bind('job_id', $jobId);
  $remaining = (int)($db->single()['c'] ?? 0);
  $db->query("SELECT page_number FROM sync_jobs WHERE id = :job_id");
  $db->bind('job_id', $jobId);
  $pageState = (int)($db->single()['page_number'] ?? 0);
  if ($remaining === 0 && $pageState === 0) {
    (new BackgroundSync())->markResult((int)$job['shop_id'], 'orders', true, null, (string)($job['mode'] ?? 'diff'));
    $sync->releaseJob($jobId, 'completed');
  } else {
    $db->query("UPDATE sync_jobs SET lease_until = NULL WHERE id = :job_id");
    $db->bind('job_id', $jobId);
    $db->exe();
  }
  $loops++;
} while (!$once && $loops < 100);

if (PHP_SAPI === 'cli') {
  fwrite(STDOUT, json_encode(['processed_jobs' => $loops]) . PHP_EOL);
}
