<?php

require_once __DIR__ . '/OrderStatusPolicy.php';

function bindAll(Database $db, array $values): void {
  foreach ($values as $key => $value) {
    $db->bind($key, $value);
  }
}

function recordRun(Database $db, int $jobId, string $field, int $amount = 1, ?string $leaseOwner = null): void {
  $allowed = ['fetched_pages', 'indexed_orders', 'detail_success', 'detail_failed', 'api_requests'];
  if (!in_array($field, $allowed, true)) {
    return;
  }
  $ownerWhere = $leaseOwner !== null ? ' AND j.lease_owner = :lease_owner AND (j.lease_until IS NULL OR j.lease_until >= NOW())' : '';
  $db->query("UPDATE sync_runs r JOIN sync_jobs j ON j.sync_run_id = r.id SET r.{$field} = r.{$field} + :amount, r.heartbeat_at = NOW() WHERE j.id = :job_id AND j.status = 'running'{$ownerWhere}");
  $db->bind('amount', $amount);
  $db->bind('job_id', $jobId);
  if ($leaseOwner !== null) $db->bind('lease_owner', (string)$leaseOwner);
  $db->exe();
}

if (!class_exists('SyncIdentityTransientException')) {
  class SyncIdentityTransientException extends RuntimeException {}
}

/** Keep worker diagnostics useful without persisting raw database messages. */
function syncWorkerErrorSummary(string $context, Throwable $error): string {
  $type = get_class($error);
  if ($error instanceof PDOException) {
    $info = is_array($error->errorInfo ?? null) ? $error->errorInfo : [];
    $state = strtoupper((string)($info[0] ?? $error->getCode()));
    $driverCode = $info[1] ?? null;
    $diagnostics = [];
    if (preg_match('/^[A-Z0-9]{5}$/D', $state)) $diagnostics[] = 'SQLSTATE ' . $state;
    if (is_int($driverCode) || (is_string($driverCode) && ctype_digit($driverCode))) $diagnostics[] = 'driver ' . (int)$driverCode;
    if ($diagnostics) $type .= ' ' . implode(' ', $diagnostics);
  }
  return $context . ': ' . $type;
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

/** Verify the cookie still belongs to the local shop before publishing data. */
function verifyWorkerShopIdentity(ShopeeCurl $shopee, array $shop): void {
  $expected = (int)($shop['shop_id'] ?? 0);
  $cookie = trim((string)($shop['cookie'] ?? ''));
  if ($expected < 1 || $cookie === '') throw new RuntimeException('Identitas toko atau cookie tidak tersedia.');
  $session = $shopee->check($cookie);
  $actual = (int)($session['shop']['id'] ?? 0);
  if ($actual < 1) {
    $diagnostics = method_exists($shopee, 'getLastRequestDiagnostics') ? $shopee->getLastRequestDiagnostics() : [];
    $httpStatus = (int)($diagnostics['http_status'] ?? 0);
    $apiCode = (int)($diagnostics['api_code'] ?? 0);
    $apiError = (int)($diagnostics['api_error'] ?? 0);
    if (!empty($diagnostics['transport_error']) || $httpStatus >= 500 || $httpStatus === 429 || $apiCode === 429 || $apiError === 429) {
      throw new SyncIdentityTransientException('Pemeriksaan identitas toko gagal sementara.');
    }
    throw new RuntimeException('Cookie Shopee tidak cocok dengan toko yang diproses.');
  }
  if ($actual !== $expected) throw new RuntimeException('Cookie Shopee tidak cocok dengan toko yang diproses.');
}

function normalizeStatus(string $status): string {
  return OrderStatusPolicy::syncState($status);
}

function insertOrderShell(Database $db, int $shopId, array $ids): void {
  if (!$ids) {
    return;
  }
  $values = [];
  foreach (array_values($ids) as $index => $id) {
    $values[] = "(:id_{$index}, :shop_{$index}, 'pending')";
  }
  // Remote order IDs are globally unique in the source, but a stale or
  // mis-scoped response must never move an existing local order to another
  // shop.  INSERT IGNORE preserves the first observed owner.
  $db->query("INSERT IGNORE INTO orders (id, shop_id, sync_status) VALUES " . implode(',', $values));
  foreach (array_values($ids) as $index => $id) {
    $db->bind("id_{$index}", (int)$id);
    $db->bind("shop_{$index}", $shopId);
  }
  $db->exe();
}

function queueEligible(Database $db, SyncJob $sync, int $jobId, array $ids, string $mode = 'diff', int $shopId = 0): int {
  if (!$ids) {
    return 0;
  }
  $placeholders = [];
  foreach (array_values($ids) as $index => $id) {
    $placeholders[] = ":oid_{$index}";
  }
  $scope = $shopId > 0 ? ' AND shop_id = :shop_id' : '';
  $db->query("SELECT id, sync_status, status_type, detail_synced_at, sync_next_retry_at, deleted_at FROM orders WHERE id IN (" . implode(',', $placeholders) . "){$scope}
    AND NOT EXISTS (SELECT 1 FROM sync_job_orders other_tasks INNER JOIN sync_jobs other_jobs ON other_jobs.id = other_tasks.job_id
      WHERE other_tasks.order_id = orders.id AND other_jobs.id <> :sync_job_id AND other_jobs.status IN ('queued','running')
        AND other_tasks.status IN ('queued','retry','running')
        AND (other_tasks.status <> 'running' OR other_tasks.lease_until IS NULL OR other_tasks.lease_until >= NOW()))");
  if ($shopId > 0) $db->bind('shop_id', $shopId);
  $db->bind('sync_job_id', $jobId);
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
    if ($row && empty($row['deleted_at']) && OrderSyncPolicy::needsDetail($row)) {
      $sync->queueOrder($jobId, (int)$id);
      $queued++;
    }
  }
  return $queued;
}

function processIndex(Database $db, SyncJob $sync, ShopeeCurl $shopee, array $job, array $shop, ?string $leaseOwner = null): bool {
  if (!$sync->ownsLease((int)$job['id'], $leaseOwner)) return false;
  $page = (int)$job['page_number'];
  $sentinel = (string)($job['page_sentinel'] ?? '');
  recordRun($db, (int)$job['id'], 'api_requests', 1, $leaseOwner);
  $response = $shopee->getOrderIndexList($shop['cookie'], $page, 40, $sentinel);
  $indexData = is_array($response['data'] ?? null) ? $response['data'] : null;
  $pagination = is_array($indexData['pagination'] ?? null) ? $indexData['pagination'] : null;
  if (retryable($response) || !is_array($indexData) || !is_array($indexData['index_list'] ?? null) || !is_array($pagination) || !array_key_exists('next_page_sentinel', $pagination)) {
    $sync->releaseJob((int)$job['id'], 'queued', 'Shopee index request failed', $leaseOwner);
    return false;
  }
  if (!$sync->ownsLease((int)$job['id'], $leaseOwner)) return false;
  $list = $indexData['index_list'];
  $ids = [];
  foreach ($list as $item) {
    $remoteId = $item['order_id'] ?? null;
    if (is_int($remoteId) || (is_string($remoteId) && ctype_digit($remoteId))) {
      $remoteId = (int)$remoteId;
      if ($remoteId > 0) $ids[] = $remoteId;
    }
  }
  $ids = array_values(array_unique($ids));
  if (!$sync->ownsLease((int)$job['id'], $leaseOwner)) return false;
  $existingIds = [];
  if ($ids) {
    $existingPlaceholders = [];
    foreach (array_values($ids) as $index => $id) {
      $existingPlaceholders[] = ":existing_{$index}";
    }
    $db->query("SELECT id, created_at, detail_synced_at FROM orders WHERE shop_id = :shop_id AND id IN (" . implode(',', $existingPlaceholders) . ")");
    $db->bind('shop_id', (int)$shop['id']);
    foreach (array_values($ids) as $index => $id) {
      $db->bind("existing_{$index}", (int)$id);
    }
    foreach ($db->getAll() as $existing) {
      $existingIds[(string)$existing['id']] = $existing;
    }
  }
  $next = (string)($pagination['next_page_sentinel'] ?? '');
  $reportedTotal = null;
  foreach ([$indexData, $pagination] as $meta) {
    foreach (['total_count', 'total'] as $key) {
      if (array_key_exists($key, $meta) && is_numeric($meta[$key])) {
        $reportedTotal = (int)$meta[$key];
        break 2;
      }
    }
  }
  $hasExplicitEmptyEnd = ($reportedTotal !== null && $reportedTotal === 0)
    || (array_key_exists('has_next', $pagination) && !$pagination['has_next'])
    || (array_key_exists('has_more', $pagination) && !$pagination['has_more'])
    || (array_key_exists('has_next_page', $pagination) && !$pagination['has_next_page']);
  // An empty first page without an explicit zero-total/end marker is usually
  // a transient or truncated response. Retrying prevents a full sync from
  // being marked complete while indexing nothing.
  if ((int)$job['page_number'] === 1 && !$ids && $next === '' && !$hasExplicitEmptyEnd) {
    $sync->releaseJob((int)$job['id'], 'queued', 'Shopee returned an unverified empty order page', $leaseOwner);
    return false;
  }
  if ($next !== '' && $next === $sentinel) {
    $sync->releaseJob((int)$job['id'], 'queued', 'Shopee order cursor did not advance', $leaseOwner);
    return false;
  }
  $continue = OrderSyncPolicy::continueIndex((string)($job['mode'] ?? 'diff'), $ids, $existingIds, $next);
  // The worker passes one Database to SyncJob so the sync_pages foreign key
  // can read the sync_runs row updated in this same transaction.
  $db->begin();
  try {
    // Lock the parent row while publishing this page. A reclaimed worker then
    // cannot insert shells/tasks after a new worker has taken the lease.
    if ($leaseOwner !== null) {
      $db->query("SELECT id FROM sync_jobs WHERE id = :job_id AND status = 'running' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW()) FOR UPDATE");
      $db->bind('job_id', (int)$job['id']);
      $db->bind('lease_owner', (string)$leaseOwner);
      if (!$db->single()) { $db->rollback(); return false; }
    }
    insertOrderShell($db, (int)$shop['id'], $ids);
    queueEligible($db, $sync, (int)$job['id'], $ids, (string)($job['mode'] ?? 'diff'), (int)$shop['id']);
    recordRun($db, (int)$job['id'], 'fetched_pages', 1, $leaseOwner);
    recordRun($db, (int)$job['id'], 'indexed_orders', count($ids), $leaseOwner);
    if ((int)$job['page_number'] === 1 && ($job['mode'] ?? 'diff') === 'diff') OrderSyncPolicy::queueActive($db, $sync, $job);
    if (!$sync->addPage((int)$job['id'], $page, $sentinel, $next, count($ids), $leaseOwner)) { $db->rollback(); return false; }
    $ownerWhere = $leaseOwner !== null ? ' AND lease_owner = :lease_owner' : '';
    $db->query("UPDATE sync_jobs SET total_indexed = total_indexed + :count, page_number = :next_page, page_sentinel = :next_sentinel, attempts = 0, next_retry_at = NULL, last_error = NULL WHERE id = :job_id{$ownerWhere}");
    bindAll($db, ['count' => count($ids), 'next_page' => $page + 1, 'next_sentinel' => $next, 'job_id' => (int)$job['id']]);
    if ($leaseOwner !== null) $db->bind('lease_owner', (string)$leaseOwner);
    $db->exe();
    if ($leaseOwner !== null && $db->row() < 1) { $db->rollback(); return false; }
    if (!$continue) {
      $db->query("UPDATE sync_jobs SET page_number = 0, page_sentinel = NULL WHERE id = :job_id{$ownerWhere}");
      $db->bind('job_id', (int)$job['id']);
      if ($leaseOwner !== null) $db->bind('lease_owner', (string)$leaseOwner);
      $db->exe();
    }
    $db->commit();
  } catch (Throwable $error) {
    $db->rollback();
    throw $error;
  }
  return true;
}

function processOrder(Database $db, SyncJob $sync, ShopeeCurl $shopee, array $task, array $shop, bool $enrichPackages = false, int &$requestCount = 0, ?string $leaseOwner = null): bool {
  if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
  $orderId = (int)$task['order_id'];
  $db->query('SELECT detail_synced_at, status_type, sync_next_retry_at, shop_id, deleted_at FROM orders WHERE id = :id AND shop_id = :shop_id');
  $db->bind('id', $orderId);
  $db->bind('shop_id', (int)$shop['id']);
  $current = $db->single() ?: [];
  if (!$current) {
    $sync->finishOrder((int)$task['id'], 'failed', 'Pesanan tidak termasuk toko yang sedang diproses.', $leaseOwner);
    return false;
  }
  if (!empty($current['deleted_at'])) {
    $finished = $sync->finishOrder((int)$task['id'], 'done', null, $leaseOwner);
    if ($finished) $sync->noteProgress((int)($task['job_id'] ?? 0), $leaseOwner);
    return $finished;
  }
  if (!OrderSyncPolicy::needsDetail($current) && empty($current['sync_next_retry_at'])) {
    $finished = $sync->finishOrder((int)$task['id'], 'done', null, $leaseOwner);
    if ($finished) $sync->noteProgress((int)($task['job_id'] ?? 0), $leaseOwner);
    return $finished;
  }
  $requestCount++;
  $detail = $shopee->getOneOrder($shop['cookie'], $orderId);
  if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
  if (!$detail) {
    $leaseGuard = $leaseOwner !== null ? " AND EXISTS (SELECT 1 FROM sync_jobs sj WHERE sj.id = :parent_job_id AND sj.status = 'running' AND sj.lease_owner = :parent_lease_owner AND (sj.lease_until IS NULL OR sj.lease_until >= NOW()))" : '';
    $db->query("UPDATE orders SET sync_status = 'failed', sync_attempts = sync_attempts + 1, sync_next_retry_at = DATE_ADD(NOW(), INTERVAL LEAST(60, POW(2, LEAST(sync_attempts, 6))) MINUTE), sync_last_error = 'Shopee order detail failed' WHERE id = :order_id AND shop_id = :shop_id{$leaseGuard}");
    $db->bind('order_id', $orderId);
    $db->bind('shop_id', (int)$shop['id']);
    if ($leaseOwner !== null) { $db->bind('parent_job_id', (int)($task['job_id'] ?? 0)); $db->bind('parent_lease_owner', (string)$leaseOwner); }
    $db->exe();
    $sync->finishOrder((int)$task['id'], 'retry', 'Shopee order detail failed', $leaseOwner);
    return false;
  }
  $statusInfo = is_array($detail) && is_array($detail['status_info_v2'] ?? null) ? $detail['status_info_v2'] : (is_array($detail) && is_array($detail['status_info'] ?? null) ? $detail['status_info'] : null);
  $hasStatus = is_array($statusInfo) && trim((string)($statusInfo['status'] ?? '')) !== '';
  if (!is_array($detail) || trim((string)($detail['order_sn'] ?? '')) === '' || !$hasStatus) {
    $sync->finishOrder((int)$task['id'], 'retry', 'Respons detail pesanan tidak lengkap.', $leaseOwner);
    return false;
  }
  $orderItems = null;
  if (array_key_exists('order_items', $detail)) {
    if (!is_array($detail['order_items'])) {
      $sync->finishOrder((int)$task['id'], 'retry', 'Daftar item pesanan tidak lengkap.', $leaseOwner);
      return false;
    }
    foreach ($detail['order_items'] as $item) {
      if (!is_array($item) || (int)($item['item_id'] ?? 0) < 1) {
        $sync->finishOrder((int)$task['id'], 'retry', 'Daftar item pesanan tidak valid.', $leaseOwner);
        return false;
      }
    }
    $orderItems = $detail['order_items'];
  }
  $statusType = $detail['status_info_v2']['status'] ?? $detail['status_info']['status'] ?? '';
  $statusDesc = $detail['status_info_v2']['status_tooltip'] ?? $detail['status_info']['status_tooltip'] ?? '';
  $createdAt = !empty($detail['create_time']) ? date('Y-m-d H:i:s', (int)$detail['create_time']) : null;
  $db->query("SELECT shipping_cargo, tracking_number, created_at FROM orders WHERE id = :order_id AND shop_id = :shop_id LIMIT 1");
  $db->bind('order_id', $orderId);
  $db->bind('shop_id', (int)$shop['id']);
  $existingOrder = $db->single() ?: [];
  $shippingCargo = $detail['fulfillment_channel_id'] ?? $detail['checkout_channel_id'] ?? $detail['logistics_channel'] ?? null;
  $trackingNumber = $detail['tracking_number'] ?? null;
  if ($shippingCargo === null || $shippingCargo === '') {
    $shippingCargo = $existingOrder['shipping_cargo'] ?? null;
  }
  if ($trackingNumber === null || $trackingNumber === '') {
    $trackingNumber = $existingOrder['tracking_number'] ?? null;
  }
  if ($createdAt === null) $createdAt = $existingOrder['created_at'] ?? null;
  if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
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
    'detail_synced_at' => null
  ];
  $set = [];
  foreach (array_keys($data) as $column) {
    $set[] = "{$column} = :{$column}";
  }
  $leaseGuard = $leaseOwner !== null ? " AND EXISTS (SELECT 1 FROM sync_jobs sj WHERE sj.id = :parent_job_id AND sj.status = 'running' AND sj.lease_owner = :parent_lease_owner AND (sj.lease_until IS NULL OR sj.lease_until >= NOW()))" : '';
  $db->query("UPDATE orders SET " . implode(',', $set) . " WHERE id = :order_id AND shop_id = :shop_id{$leaseGuard}");
  bindAll($db, $data + ['order_id' => $orderId]);
  $db->bind('shop_id', (int)$shop['id']);
  if ($leaseOwner !== null) { $db->bind('parent_job_id', (int)($task['job_id'] ?? 0)); $db->bind('parent_lease_owner', (string)$leaseOwner); }
  $db->exe();
  if ($leaseOwner !== null && $db->row() < 1) return false;
  if ($enrichPackages && (empty($data['shipping_cargo']) || empty($data['tracking_number']))) {
    enrichPackage($db, $shopee, $orderId, $shop['cookie'], (int)$shop['id'], $leaseOwner, (int)($task['job_id'] ?? 0));
    if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
  }
  if (normalizeStatus($statusType) === 'completed') {
    $income = new OrderIncome();
    if ($income->needsSync($orderId)) {
      $requestCount++;
      $incomeData = $shopee->getOrderIncomeComponents($shop['cookie'], $orderId);
      if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
      if ($incomeData) {
        $income->upsertDetail($orderId, $incomeData);
      } else {
        $income->markChecked($orderId);
      }
    }
  }
  if ($orderItems !== null) {
    if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
    $items = [];
    foreach ($orderItems as $item) {
      $productId = (int)($item['item_id'] ?? 0);
      if ($productId < 1) continue;
      $product = is_array($item['product'] ?? null) ? $item['product'] : [];
      $itemModel = is_array($item['item_model'] ?? null) ? $item['item_model'] : [];
      $images = is_array($product['images'] ?? null) ? $product['images'] : [];
      $items[] = [$orderId, $productId, (int)($item['model_id'] ?? 0), $product['name'] ?? '', $itemModel['name'] ?? '', (int)($item['amount'] ?? 1), (int)($item['order_price'] ?? 0), $images[0] ?? ''];
    }
    $db->begin();
    try {
      $db->query('DELETE FROM order_items WHERE order_id=:order_id');
      $db->bind('order_id', $orderId);
      $db->exe();
      if ($items) {
        $values = [];
        foreach ($items as $index => $item) $values[] = "(:a{$index},:b{$index},:c{$index},:d{$index},:e{$index},:f{$index},:g{$index},:h{$index})";
        $db->query("INSERT INTO order_items (order_id, product_id, model_id, name, variation_name, quantity, price, image) VALUES " . implode(',', $values));
        foreach ($items as $index => $item) bindAll($db, ['a' . $index => $item[0], 'b' . $index => $item[1], 'c' . $index => $item[2], 'd' . $index => $item[3], 'e' . $index => $item[4], 'f' . $index => $item[5], 'g' . $index => $item[6], 'h' . $index => $item[7]]);
        $db->exe();
      }
      $db->commit();
    } catch (Throwable $error) {
      $db->rollback();
      throw $error;
    }
  }
  if (!$sync->ownsLease((int)($task['job_id'] ?? 0), $leaseOwner)) return false;
  $leaseGuard = $leaseOwner !== null ? " AND EXISTS (SELECT 1 FROM sync_jobs sj WHERE sj.id = :parent_job_id AND sj.status = 'running' AND sj.lease_owner = :parent_lease_owner AND (sj.lease_until IS NULL OR sj.lease_until >= NOW()))" : '';
  $db->query("UPDATE orders SET detail_synced_at = NOW() WHERE id = :order_id AND shop_id = :shop_id{$leaseGuard}");
  $db->bind('order_id', $orderId);
  $db->bind('shop_id', (int)$shop['id']);
  if ($leaseOwner !== null) { $db->bind('parent_job_id', (int)($task['job_id'] ?? 0)); $db->bind('parent_lease_owner', (string)$leaseOwner); }
  $db->exe();
  if ($leaseOwner !== null && $db->row() < 1) return false;
  $finished = $sync->finishOrder((int)$task['id'], 'done', null, $leaseOwner);
  if ($finished) $sync->noteProgress((int)($task['job_id'] ?? 0), $leaseOwner);
  return $finished;
}

function enrichPackage(Database $db, ShopeeCurl $shopee, int $orderId, string $cookie, int $shopId = 0, ?string $leaseOwner = null, int $leaseJobId = 0): bool {
  require_once __DIR__ . '/PackageSynchronizer.php';
  return (new PackageSynchronizer($db, $shopee))->refresh($orderId, $cookie, $shopId, $leaseOwner, $leaseJobId);
}

function processGenericJob(Database $db, array $job, array $shop, ShopeeCurl $shopee, int $rateMs, int $packageLimit): array {
  $type = (string)($job['sync_type'] ?? '');
  $shopId = (int)$job['shop_id'];
  if ($type === 'finance') {
    require_once __DIR__.'/../models/Finance.php';
    require_once __DIR__.'/FinanceApi.php';
    return (new Finance())->work($shop,new FinanceApi($shopee),3,$rateMs);
  }
  if ($type === 'products') {
    $result = (new ProductSync())->run($shop, (string)($job['mode'] ?? 'diff'), 2, $rateMs);
    return [!empty($result['ok']), $result['message'] ?? null, $result['complete'] ?? true];
  }
  if ($type === 'ads') {
    $monitor = new AdsMonitor();
    $ok = $monitor->syncShop($shop);
    return [$ok, $monitor->lastSyncError()];
  }
  if ($type === 'ads_topups') {
    return (new AdsMonitor())->syncTopups($shop, $shopee);
  }
  if ($type === 'performance') {
    $result = (new ShopPerformance())->syncShop($shop);
    return [!empty($result['ok']), $result['message'] ?? null];
  }
  if ($type === 'promotions') return [(new PromotionMonitor())->syncShop($shop), null];
  if ($type === 'chat') {
    $result = (new ChatMonitor())->syncShop($shopId);
    return [!empty($result['ok']), $result['message'] ?? null, $result['complete'] ?? true];
  }
  if ($type === 'customers') {
    $accountId = (int)($shop['account_id'] ?? 0);
    if ($accountId < 1) return [false, 'Akun toko tidak tersedia untuk sinkronisasi pelanggan.'];
    $result = (new Customer())->syncFromOrders($accountId, $shopId);
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
    require_once __DIR__ . '/PackageSynchronizer.php';
    return (new PackageSynchronizer($db, $shopee))->run($job, $shop, min(5, $packageLimit), $rateMs);
  }
  return [false, 'Tipe sinkronisasi tidak dikenali.'];
}
