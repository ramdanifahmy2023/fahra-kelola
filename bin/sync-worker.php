<?php

declare(strict_types=1);

chdir(__DIR__ . '/../public');
require_once '../app/init.php';
require_once '../app/models/SyncJob.php';
require_once '../app/models/ShopeeCurl.php';
require_once '../app/models/OrderIncome.php';
require_once '../app/models/BackgroundSync.php';
require_once '../app/models/ProductSync.php';
require_once '../app/models/AdsMonitor.php';
require_once '../app/models/PromotionMonitor.php';
require_once '../app/models/ChatMonitor.php';
require_once '../app/models/Customer.php';

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

function bindAll(Database $db, array $values): void {
  foreach ($values as $key => $value) {
    $db->bind($key, $value);
  }
}

function recordRun(Database $db, int $jobId, string $field, int $amount = 1): void {
  $allowed = ['fetched_pages', 'indexed_orders', 'detail_success', 'detail_failed', 'api_requests'];
  if (!in_array($field, $allowed, true)) {
    return;
  }
  $db->query("UPDATE sync_runs r JOIN sync_jobs j ON j.sync_run_id = r.id SET r.{$field} = r.{$field} + :amount, r.heartbeat_at = NOW() WHERE j.id = :job_id");
  $db->bind('amount', $amount);
  $db->bind('job_id', $jobId);
  $db->exe();
}

function retryable(array $response): bool {
  if (!$response) {
    return true;
  }
  if (isset($response['success']) && $response['success'] === false) {
    return true;
  }
  return isset($response['code']) && (int)$response['code'] !== 0;
}

function normalizeStatus(string $status): string {
  $value = strtolower(trim($status));
  if (in_array($value, ['completed', 'delivered', 'order received', 'order_received', 'to confirm receive'], true)) {
    return 'completed';
  }
  if ($value === 'cancelled' || $value === 'canceled') {
    return 'cancelled';
  }
  if ($value === 'ready_to_ship' || $value === 'processed' || $value === 'shipped' || $value === 'to_confirm_receive') {
    return 'active';
  }
  return 'pending';
}

function insertOrderShell(Database $db, int $shopId, array $ids): void {
  if (!$ids) {
    return;
  }
  $values = [];
  foreach (array_values($ids) as $index => $id) {
    $values[] = "(:id_{$index}, :shop_{$index}, 'pending')";
  }
  $db->query("INSERT INTO orders (id, shop_id, sync_status) VALUES " . implode(',', $values) . " ON DUPLICATE KEY UPDATE shop_id = VALUES(shop_id)");
  foreach (array_values($ids) as $index => $id) {
    $db->bind("id_{$index}", (int)$id);
    $db->bind("shop_{$index}", $shopId);
  }
  $db->exe();
}

function queueEligible(Database $db, SyncJob $sync, int $jobId, array $ids, string $mode = 'diff'): int {
  if (!$ids) {
    return 0;
  }
  $placeholders = [];
  foreach (array_values($ids) as $index => $id) {
    $placeholders[] = ":oid_{$index}";
  }
  $db->query("SELECT id, sync_status, status_type, detail_synced_at, sync_next_retry_at FROM orders WHERE id IN (" . implode(',', $placeholders) . ")");
  foreach (array_values($ids) as $index => $id) {
    $db->bind("oid_{$index}", (int)$id);
  }
  $existing = [];
  foreach ($db->getAll() as $row) {
    $existing[(string)$row['id']] = $row;
  }
  $queued = 0;
  foreach ($ids as $id) {
    $row = $existing[(string)$id] ?? null;
    $syncedAt = $row && !empty($row['detail_synced_at']) ? strtotime($row['detail_synced_at']) : 0;
    $age = $syncedAt > 0 ? max(0, time() - $syncedAt) : PHP_INT_MAX;
    $normalized = normalizeStatus((string)($row['status_type'] ?? ''));
    $ttl = $normalized === 'completed' || $normalized === 'cancelled' ? 86400 : 900;
    $stable = $mode !== 'full' && $row && $syncedAt > 0 && $age < $ttl;
    $retryAt = !empty($row['sync_next_retry_at']) ? strtotime($row['sync_next_retry_at']) : 0;
    $retryAllowed = empty($row['sync_next_retry_at']) || $retryAt <= time();
    if (!$stable && $retryAllowed) {
      $sync->queueOrder($jobId, (int)$id);
      $queued++;
    }
  }
  return $queued;
}

function processIndex(Database $db, SyncJob $sync, ShopeeCurl $shopee, array $job, array $shop): bool {
  $page = (int)$job['page_number'];
  $sentinel = (string)($job['page_sentinel'] ?? '');
  $response = $shopee->getOrderIndexList($shop['cookie'], $page, 40, $sentinel);
  if (retryable($response)) {
    $sync->releaseJob((int)$job['id'], 'queued', 'Shopee index request failed');
    return false;
  }
  $list = $response['data']['index_list'] ?? [];
  $ids = [];
  foreach ($list as $item) {
    if (!empty($item['order_id'])) {
      $ids[] = (int)$item['order_id'];
    }
  }
  $existingIds = [];
  if ($ids) {
    $existingPlaceholders = [];
    foreach (array_values($ids) as $index => $id) {
      $existingPlaceholders[] = ":existing_{$index}";
    }
    $db->query("SELECT id FROM orders WHERE shop_id = :shop_id AND id IN (" . implode(',', $existingPlaceholders) . ")");
    $db->bind('shop_id', (int)$shop['id']);
    foreach (array_values($ids) as $index => $id) {
      $db->bind("existing_{$index}", (int)$id);
    }
    foreach ($db->getAll() as $existing) {
      $existingIds[(string)$existing['id']] = true;
    }
  }
  insertOrderShell($db, (int)$shop['id'], $ids);
  queueEligible($db, $sync, (int)$job['id'], $ids, (string)($job['mode'] ?? 'diff'));
  recordRun($db, (int)$job['id'], 'fetched_pages');
  recordRun($db, (int)$job['id'], 'indexed_orders', count($ids));
  recordRun($db, (int)$job['id'], 'api_requests');
  $next = (string)($response['data']['pagination']['next_page_sentinel'] ?? '');
  $timestamp = (int)explode(',', $next)[0];
  $continue = $next !== '' && ($timestamp === 0 || $timestamp >= strtotime('-3 months')) && count($list) > 0;
  if (($job['mode'] ?? 'diff') !== 'full' && $existingIds) {
    $continue = false;
  }
  $sync->addPage((int)$job['id'], $page, $sentinel, $next, count($ids));
  $db->query("UPDATE sync_jobs SET total_indexed = total_indexed + :count, page_number = :next_page, page_sentinel = :next_sentinel WHERE id = :job_id");
  bindAll($db, ['count' => count($ids), 'next_page' => $page + 1, 'next_sentinel' => $next, 'job_id' => (int)$job['id']]);
  $db->exe();
  if (!$continue) {
    $db->query("UPDATE sync_jobs SET page_number = 0, page_sentinel = NULL WHERE id = :job_id");
    $db->bind('job_id', (int)$job['id']);
    $db->exe();
  }
  return true;
}

function processOrder(Database $db, SyncJob $sync, ShopeeCurl $shopee, array $task, array $shop, bool $enrichPackages = false): bool {
  $orderId = (int)$task['order_id'];
  $detail = $shopee->getOneOrder($shop['cookie'], $orderId);
  if (!$detail) {
    $db->query("UPDATE orders SET sync_status = 'failed', sync_attempts = sync_attempts + 1, sync_next_retry_at = DATE_ADD(NOW(), INTERVAL LEAST(60, POW(2, sync_attempts)) MINUTE), sync_last_error = 'Shopee order detail failed' WHERE id = :order_id");
    $db->bind('order_id', $orderId);
    $db->exe();
    $sync->finishOrder((int)$task['id'], 'retry', 'Shopee order detail failed');
    return false;
  }
  $statusType = $detail['status_info_v2']['status'] ?? $detail['status_info']['status'] ?? '';
  $statusDesc = $detail['status_info_v2']['status_tooltip'] ?? $detail['status_info']['status_tooltip'] ?? '';
  $createdAt = !empty($detail['create_time']) ? date('Y-m-d H:i:s', (int)$detail['create_time']) : null;
  $db->query("SELECT shipping_cargo, tracking_number FROM orders WHERE id = :order_id LIMIT 1");
  $db->bind('order_id', $orderId);
  $existingOrder = $db->single() ?: [];
  $shippingCargo = $detail['fulfillment_channel_id'] ?? $detail['checkout_channel_id'] ?? $detail['logistics_channel'] ?? null;
  $trackingNumber = $detail['tracking_number'] ?? null;
  if ($shippingCargo === null || $shippingCargo === '') {
    $shippingCargo = $existingOrder['shipping_cargo'] ?? null;
  }
  if ($trackingNumber === null || $trackingNumber === '') {
    $trackingNumber = $existingOrder['tracking_number'] ?? null;
  }
  $data = [
    'order_sn' => $detail['order_sn'] ?? null,
    'advance_booking_sn' => $detail['advance_booking_sn'] ?? null,
    'order_type' => !empty($detail['advance_booking_sn']) ? 'advance' : 'regular',
    'buyer_username' => $detail['buyer_user']['user_name'] ?? null,
    'total_price' => $detail['total_price'] ?? null,
    'payment_method' => $detail['payment_method'] ?? null,
    'status' => $detail['status'] ?? null,
    'status_type' => $statusType,
    'status_description' => $statusDesc,
    'shipping_cargo' => $shippingCargo,
    'tracking_number' => $trackingNumber,
    'ship_by_date' => $detail['ship_by_date'] ?? null,
    'shipping_name' => $detail['buyer_address_name'] ?? null,
    'shipping_phone' => $detail['buyer_address_phone'] ?? null,
    'shipping_address' => $detail['shipping_address'] ?? null,
    'created_at' => $createdAt,
    'raw_data' => json_encode($detail),
    'sync_status' => normalizeStatus($statusType),
    'sync_attempts' => 0,
    'sync_next_retry_at' => null,
    'sync_last_error' => null,
    'detail_synced_at' => date('Y-m-d H:i:s')
  ];
  $set = [];
  foreach (array_keys($data) as $column) {
    $set[] = "{$column} = :{$column}";
  }
  $db->query("UPDATE orders SET " . implode(',', $set) . " WHERE id = :order_id");
  bindAll($db, $data + ['order_id' => $orderId]);
  $db->exe();
  if ($enrichPackages && (empty($data['shipping_cargo']) || empty($data['tracking_number']))) {
    enrichPackage($db, $shopee, $orderId, $shop['cookie']);
  }
  if (normalizeStatus($statusType) === 'completed') {
    $income = new OrderIncome();
    if ($income->needsSync($orderId)) {
      $incomeData = $shopee->getOrderIncomeComponents($shop['cookie'], $orderId);
      if ($incomeData) {
        $income->upsertDetail($orderId, $incomeData);
      } else {
        $income->markChecked($orderId);
      }
    }
  }
  $existingKeys = [];
  $db->query("SELECT product_id, model_id FROM order_items WHERE order_id = :order_id");
  $db->bind('order_id', $orderId);
  foreach ($db->getAll() as $item) {
    $existingKeys[$item['product_id'] . ':' . ($item['model_id'] ?? 0)] = true;
  }
  $newItems = [];
  foreach (($detail['order_items'] ?? []) as $item) {
    $productId = (int)($item['item_id'] ?? 0);
    $modelId = (int)($item['model_id'] ?? 0);
    $key = $productId . ':' . $modelId;
    if ($productId < 1 || isset($existingKeys[$key])) {
      continue;
    }
    $existingKeys[$key] = true;
    $newItems[] = [$orderId, $productId, $modelId, $item['product']['name'] ?? '', $item['item_model']['name'] ?? '', (int)($item['amount'] ?? 1), (int)($item['order_price'] ?? 0), $item['product']['images'][0] ?? ''];
  }
  if ($newItems) {
    $values = [];
    foreach ($newItems as $index => $item) {
      $values[] = "(:a{$index},:b{$index},:c{$index},:d{$index},:e{$index},:f{$index},:g{$index},:h{$index})";
    }
    $db->query("INSERT INTO order_items (order_id, product_id, model_id, name, variation_name, quantity, price, image) VALUES " . implode(',', $values));
    foreach ($newItems as $index => $item) {
      bindAll($db, ['a' . $index => $item[0], 'b' . $index => $item[1], 'c' . $index => $item[2], 'd' . $index => $item[3], 'e' . $index => $item[4], 'f' . $index => $item[5], 'g' . $index => $item[6], 'h' . $index => $item[7]]);
    }
    $db->exe();
  }
  $sync->finishOrder((int)$task['id'], 'done');
  return true;
}

function enrichPackage(Database $db, ShopeeCurl $shopee, int $orderId, string $cookie): void {
  $package = $shopee->getPackage($cookie, $orderId);
  $first = $package['order_info']['package_list'][0] ?? null;
  if (!$first) {
    $db->query("UPDATE orders SET package_synced_at = NOW() WHERE id = :order_id AND package_synced_at IS NULL");
    $db->bind('order_id', $orderId);
    $db->exe();
    return;
  }
  $cargo = $first['channel_id'] ?? null;
  $tracking = $first['third_party_tn'] ?? null;
  $db->query("UPDATE orders SET shipping_cargo = COALESCE(NULLIF(:cargo, ''), shipping_cargo), tracking_number = COALESCE(NULLIF(:tracking, ''), tracking_number), package_synced_at = NOW() WHERE id = :order_id");
  bindAll($db, ['cargo' => (string)$cargo, 'tracking' => (string)$tracking, 'order_id' => $orderId]);
  $db->exe();
}

function processGenericJob(Database $db, array $job, array $shop, ShopeeCurl $shopee, int $rateMs, int $packageLimit): array {
  $type = (string)($job['sync_type'] ?? '');
  $shopId = (int)$job['shop_id'];
  if ($type === 'products') {
    $result = (new ProductSync())->run($shop, (string)($job['mode'] ?? 'diff'), 100, $rateMs);
    return [!empty($result['ok']), $result['message'] ?? null];
  }
  if ($type === 'ads') return [(new AdsMonitor())->syncShop($shop), null];
  if ($type === 'promotions') return [(new PromotionMonitor())->syncShop($shop), null];
  if ($type === 'chat') {
    $result = (new ChatMonitor())->syncShop($shopId);
    return [!empty($result['ok']), $result['message'] ?? null];
  }
  if ($type === 'customers') {
    $result = (new Customer())->syncFromOrders();
    return [is_array($result), null];
  }
  if ($type === 'shops') {
    $session = $shopee->check((string)$shop['cookie']);
    $ok = isset($session['shop']['id']);
    $db->query("UPDATE shops SET sync_status = :sync_status WHERE id = :shop_id");
    $db->bind('sync_status', $ok ? 'connected' : 'expired');
    $db->bind('shop_id', $shopId);
    $db->exe();
    return [$ok, $ok ? null : 'Sesi Shopee toko tidak valid.'];
  }
  if ($type === 'packages') {
    $db->query("SELECT id FROM orders WHERE shop_id = :shop_id AND deleted_at IS NULL AND detail_synced_at IS NOT NULL AND (package_synced_at IS NULL OR shipping_cargo IS NULL OR shipping_cargo = '' OR tracking_number IS NULL OR tracking_number = '') ORDER BY id ASC LIMIT {$packageLimit}");
    $db->bind('shop_id', $shopId);
    $rows = $db->getAll();
    foreach ($rows as $row) {
      enrichPackage($db, $shopee, (int)$row['id'], (string)$shop['cookie']);
      usleep($rateMs * 1000);
    }
    return [true, null];
  }
  return [false, 'Tipe sinkronisasi tidak dikenali.'];
}

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
  $job = $sync->claimJob($requestedJob);
  if (!$job || ($requestedShop && (int)$job['shop_id'] !== $requestedShop)) {
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
      [$genericOk, $genericError] = processGenericJob($db, $job, $shop, $shopee, $rateMs, $packageLimit);
    } catch (Throwable $exception) {
      $genericOk = false;
      $genericError = 'Worker error: ' . substr($exception->getMessage(), 0, 500);
    }
    $background = new BackgroundSync();
    $background->markResult((int)$job['shop_id'], (string)$job['sync_type'], $genericOk, $genericError, (string)($job['mode'] ?? 'diff'));
    $sync->releaseJob($jobId, $genericOk ? 'completed' : 'failed', $genericError);
    $loops++;
    continue;
  }
  if ((int)$job['page_number'] > 0) {
    processIndex($db, $sync, $shopee, $job, $shop);
  }
  $tasks = $sync->claimOrders($jobId, $batchSize);
  foreach ($tasks as $task) {
    $orderOk = processOrder($db, $sync, $shopee, $task, $shop, false);
    recordRun($db, $jobId, $orderOk ? 'detail_success' : 'detail_failed');
    recordRun($db, $jobId, 'api_requests');
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
