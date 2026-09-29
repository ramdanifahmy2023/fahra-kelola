<?php

class AdsMonitor extends BaseModel {
  protected $table = 'ad_shop_snapshots';
  private $lastSyncError = null;

  public function lastSyncError() {
    return $this->lastSyncError;
  }

  public function ensureSchema() {
    $this->db->query("CREATE TABLE IF NOT EXISTS ad_shop_snapshots (
      shop_id INT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'unknown',
      payload LONGTEXT NULL,
      error_message TEXT NULL,
      synced_at DATETIME NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id),
      KEY ad_shop_snapshots_status (status),
      KEY ad_shop_snapshots_synced (synced_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) {
      $where = ' WHERE id = :shop_id';
    }
    $this->db->query("SELECT id, shop_id, name, sync_status, cookie FROM shops{$where} ORDER BY name ASC");
    if ($where) {
      $this->db->bind('shop_id', (int)$shopId);
    }
    return $this->db->getAll();
  }

  public function syncShop(array $shop) {
    $this->lastSyncError = null;
    $this->ensureSchema();
    require_once __DIR__ . '/ShopeeCurl.php';
    $shopee = new ShopeeCurl();
    $cookie = trim($shop['cookie'] ?? '');
    if ($cookie === '') {
      $this->saveError($shop, 'expired', 'Cookie toko kosong. Masukkan cookie Shopee terbaru.');
      return false;
    }
    $session = $shopee->check($cookie);
    if (empty($session['shop']['id'])) {
      $message = (string)($session['message'] ?? 'user_is_unauthorized');
      $status = !empty($session['success']) && $session['success'] === false ? 'error' : 'expired';
      $friendly = $status === 'expired'
        ? 'Sesi Shopee toko habis atau tidak valid (' . $message . '). Perbarui cookie toko.'
        : 'Verifikasi sesi toko gagal karena koneksi atau respons Shopee tidak tersedia (' . $message . ').';
      $this->saveError($shop, $status, $friendly);
      return false;
    }
    $sourceShopId = (int)$session['shop']['id'];
    if ($sourceShopId !== (int)($shop['shop_id'] ?? 0)) {
      $this->saveError($shop, 'error', 'Identitas sesi Shopee tidak cocok dengan toko yang tersimpan. Periksa cookie toko.');
      return false;
    }
    $payload = $shopee->getAdsSummary($cookie);
    if ($payload === false) {
      $this->saveError($shop, 'error', 'Endpoint metrik iklan tidak tersedia. Sesi dasar toko masih valid.');
      return false;
    }

    require_once __DIR__ . '/../helpers/AdsPerformance.php';
    require_once __DIR__ . '/../helpers/SyncOutcome.php';
    $outcome = SyncOutcome::ads($payload['performance_reports'] ?? []);
    $this->lastSyncError = $outcome['error'];
    $payload['source_shop_id'] = $sourceShopId;
    $previous = $this->findBy('shop_id', (int)$shop['id']);
    $previousPayload = json_decode($previous['payload'] ?? '{}', true) ?: [];
    foreach (($payload['performance_reports'] ?? []) as $period => $channels) {
      foreach ($channels as $channel => $report) {
        $report['source_shop_id'] = $sourceShopId;
        $payload['performance_reports'][$period][$channel] = AdsPerformance::retainLastGood($report, $previousPayload['performance_reports'][$period][$channel] ?? null);
      }
    }
    $payload['performance'] = $payload['performance_reports']['daily']['product'] ?? null;

    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, :status, :payload, :error, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), payload = VALUES(payload), error_message = VALUES(error_message), synced_at = NOW()");
    $this->db->bind('status', $outcome['ok'] ? 'ok' : 'partial');
    $this->db->bind('error', $this->lastSyncError);
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('payload', json_encode($payload, JSON_UNESCAPED_UNICODE));
    $this->db->exe();
    return $outcome['ok'];
  }

  private function saveError(array $shop, $status, $message) {
    $this->lastSyncError = $message;
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, :status, NULL, :error_message, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $status);
    $this->db->bind('error_message', $message);
    $this->db->exe();
    $this->db->query("UPDATE shops SET sync_status = :sync_status WHERE id = :shop_id");
    $this->db->bind('sync_status', $status === 'expired' ? 'expired' : 'connected');
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
  }

  public function syncAll() {
    $count = 0;
    foreach ($this->shops() as $shop) {
      if ($this->syncShop($shop)) {
        $count++;
      }
    }
    return $count;
  }

  public function summary($shopId = null, $refresh = true, $period = 'daily', $channel = 'product') {
    require_once __DIR__ . '/../helpers/AdsPerformance.php';
    $range = AdsPerformance::range($period);
    if (!isset(AdsPerformance::CHANNELS[$channel])) throw new InvalidArgumentException('Channel iklan tidak valid.');
    $this->ensureSchema();
    $shops = $this->shops($shopId);
    require_once __DIR__ . '/AdsBrowserReport.php';
    $browserReports = new AdsBrowserReport();
    $browserReports->ensureSchema();
    $result = [];
    foreach ($shops as $shop) {
      $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
      $this->db->bind('shop_id', (int)$shop['id']);
      $snapshot = $this->db->single();
      // MariaDB server stores NOW() in UTC in this deployment; parse it explicitly
      // so a fresh snapshot is not marked stale because of the PHP app timezone.
      $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      if ($refresh && (!is_array($snapshot) || $age >= 900) && !empty($shop['cookie'])) {
        $this->syncShop($shop);
        $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', (int)$shop['id']);
        $snapshot = $this->db->single();
        $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      }
      $payload = !empty($snapshot['payload']) ? json_decode($snapshot['payload'], true) : [];
      $payload = is_array($payload) ? $payload : [];
      $report = $payload['performance_reports'][$period][$channel] ?? null;
      if (!is_array($report) || ($report['start_date'] ?? '') !== $range['start_date'] || ($report['end_date'] ?? '') !== $range['end_date']
        || (!empty($report['available']) && (($report['mapping_version'] ?? null) !== AdsPerformance::MAPPING_VERSION || ($report['source_shop_id'] ?? 0) !== (int)$shop['shop_id']))) {
        $report = AdsPerformance::normalize([], $range, $channel);
        $report['attempted_at'] = null;
        $report['error_message'] = 'Laporan periode ini menunggu sinkronisasi berikutnya.';
      }
      $browserReport = $browserReports->forRange($shop, $range, $channel);
      if ($browserReport && (empty($report['available']) || strtotime($browserReport['fetched_at']) > strtotime($report['fetched_at'] ?? '1970-01-01'))) {
        $report = $browserReport;
      }
      $reportAge = !empty($report['fetched_at']) ? time() - strtotime($report['fetched_at']) : PHP_INT_MAX;
      $serverFailed = ($report['collection_method'] ?? '') !== 'browser_capture' && in_array($snapshot['status'] ?? '', ['error', 'expired'], true);
      $report['stale'] = !empty($report['stale']) || (!empty($report['available']) && ($reportAge >= 900 || $serverFailed));
      $payload['performance'] = $report;
      unset($payload['performance_reports']);
      if (($snapshot['status'] ?? '') === 'expired') {
        $shop['sync_status'] = 'expired';
      } elseif (($snapshot['status'] ?? '') === 'ok') {
        $shop['sync_status'] = 'connected';
      }
      if (trim($shop['cookie'] ?? '') === '') {
        $shop['sync_status'] = 'expired';
        if (empty($snapshot['error_message'])) {
          $snapshot['error_message'] = 'Cookie toko kosong. Masukkan cookie Shopee terbaru.';
        }
      }
      $sessionStatus = $shop['sync_status'] ?? 'unknown';
      $sessionExpired = $sessionStatus === 'expired' || (($snapshot['status'] ?? '') === 'expired');
      $result[] = [
        'shop_id' => (int)$shop['id'],
        'shop_name' => $shop['name'] ?? '',
        'shop_status' => $shop['sync_status'] ?? 'unknown',
        'session_status' => $sessionStatus,
        'session_expired' => $sessionExpired,
        'status' => $snapshot['status'] ?? 'pending',
        'error_message' => $snapshot['error_message'] ?? null,
        'synced_at' => $snapshot['synced_at'] ?? null,
        'stale' => !empty($snapshot['synced_at']) && $age >= 900,
        'metrics' => is_array($payload) ? $payload : []
      ];
    }
    return $result;
  }

  private function ensureTopupSchema() {
    $migration = file_get_contents(__DIR__ . '/../../database/migrations/20260929_ad_balance_topups.sql');
    foreach (array_filter(array_map('trim', explode(';', (string)$migration))) as $statement) {
      $this->db->query($statement);
      $this->db->exe();
    }
  }

  private function topupRows(array $data) {
    $containers = [$data, $data['data'] ?? null];
    foreach ($containers as $container) {
      if (!is_array($container)) continue;
      $pageInfo = $container['page_info'] ?? null;
      if (is_array($pageInfo) && (int)($pageInfo['total'] ?? $pageInfo['total_count'] ?? -1) === 0) return [];
      foreach (['order_list', 'orders', 'list', 'items', 'order_data'] as $key) {
        $rows = $container[$key] ?? null;
        if (is_array($rows)) return $rows;
      }
      if (isset($container[0]) && is_array($container[0]) && array_key_exists('order_id', $container[0])) return $container;
      foreach ($container as $rows) {
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) continue;
        if (array_key_exists('order_id', $rows[0]) && array_key_exists('actual_price', $rows[0])) return $rows;
      }
    }
    return false;
  }

  private function saveTopupSyncState($shopId, $sourceShopId, $nextPage, $backfillComplete, $error = null, $synced = false) {
    $this->db->query('INSERT INTO ad_topup_sync_state (shop_id, source_shop_id, next_page_number, backfill_complete, synced_at, last_error)
      VALUES (:shop_id, :source_shop_id, :next_page, :complete, CASE WHEN :synced = 1 THEN UTC_TIMESTAMP() ELSE NULL END, :error)
      ON DUPLICATE KEY UPDATE source_shop_id = VALUES(source_shop_id), next_page_number = VALUES(next_page_number),
      backfill_complete = VALUES(backfill_complete), synced_at = CASE WHEN :synced_update = 1 THEN UTC_TIMESTAMP() ELSE synced_at END, last_error = VALUES(last_error)');
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('source_shop_id', (int)$sourceShopId);
    $this->db->bind('next_page', (int)$nextPage);
    $this->db->bind('complete', $backfillComplete ? 1 : 0);
    $this->db->bind('synced', $synced ? 1 : 0);
    $this->db->bind('synced_update', $synced ? 1 : 0);
    $this->db->bind('error', $error);
    $this->db->exe();
  }

  public function syncTopups(array $shop, ShopeeCurl $shopee, $pageLimit = 20) {
    $this->ensureTopupSchema();
    $shopId = (int)($shop['id'] ?? 0);
    $sourceShopId = (int)($shop['shop_id'] ?? 0);
    if ($shopId < 1 || $sourceShopId < 1) {
      return [false, 'Cookie atau identitas toko kosong.', true];
    }
    if (trim((string)($shop['cookie'] ?? '')) === '') {
      $error = 'Cookie toko kosong. Perbarui cookie melalui halaman Toko.';
      $this->saveTopupSyncState($shopId, $sourceShopId, 1, false, $error, false);
      return [false, $error, true];
    }

    $session = $shopee->check($shop['cookie']);
    if ((int)($session['shop']['id'] ?? 0) !== $sourceShopId) {
      $this->saveTopupSyncState($shopId, $sourceShopId, 1, false, 'Sesi Shopee tidak cocok dengan toko.', false);
      return [false, 'Sesi Shopee tidak cocok dengan toko.', true];
    }

    $this->db->query('SELECT next_page_number, backfill_complete FROM ad_topup_sync_state WHERE shop_id = :shop_id LIMIT 1');
    $this->db->bind('shop_id', $shopId);
    $state = $this->db->single();
    $backfillComplete = !empty($state['backfill_complete']);
    $page = $backfillComplete ? 1 : max(1, (int)($state['next_page_number'] ?? 1));
    $cutoff = (new DateTimeImmutable('2026-08-01 00:00:00', new DateTimeZone('Asia/Jakarta')))->getTimestamp();
    $pageSize = 24;
    $pageLimit = max(1, min(50, (int)$pageLimit));
    $pagesFetched = 0;
    $stop = false;

    while ($pagesFetched < $pageLimit) {
      $response = $shopee->getCompletedAdsTopups($shop['cookie'], $page, $pageSize);
      if (!is_array($response)) {
        $error = 'Pesanan topup iklan gagal dimuat dari Seller Centre.';
        $this->saveTopupSyncState($shopId, $sourceShopId, $page, $backfillComplete, $error, false);
        return [false, $error, true];
      }
      $rows = $this->topupRows($response);
      $payloadData = is_array($response['data'] ?? null) ? $response['data'] : [];
      $pageInfo = is_array($payloadData['page_info'] ?? null) ? $payloadData['page_info'] : [];
      $total = (int)($pageInfo['total'] ?? $pageInfo['total_count'] ?? 0);
      if ($rows === false || (!$rows && $total > 0 && !$backfillComplete)) {
        $error = 'Respons pesanan topup iklan tidak memiliki daftar pesanan yang dikenali.';
        $this->saveTopupSyncState($shopId, $sourceShopId, $page, false, $error, false);
        return [false, $error, true];
      }

      $oldestTime = PHP_INT_MAX;
      $changedCount = 0;
      foreach ($rows as $row) {
        $orderId = (string)($row['order_id'] ?? '');
        $actualPrice = $row['actual_price'] ?? null;
        $createdAt = (int)($row['create_time'] ?? 0);
        if ($createdAt > 1000000000000) $createdAt = (int)floor($createdAt / 1000);
        if ($createdAt < 1 || $orderId === '' || !is_numeric($actualPrice) || (float)$actualPrice < 0) continue;
        $status = strtolower(trim((string)($row['status'] ?? 'completed')));
        if ($status !== '' && !in_array($status, ['completed', 'complete', '4'], true)) continue;
        $oldestTime = min($oldestTime, $createdAt);
        if ($createdAt < $cutoff) continue;
        $payment = is_array($row['payment'] ?? null) ? $row['payment'] : [];
        $rowHash = hash('sha256', json_encode([
          'order_id' => $orderId,
          'actual_price' => $actualPrice,
          'tax_amount' => $row['tax_amount'] ?? null,
          'status' => $status,
          'create_time' => $createdAt,
          'payment' => $payment
        ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        $this->db->query('INSERT INTO ad_balance_topups
          (shop_id, source_shop_id, order_id, order_sn, order_status, actual_price, original_price, discount_price, tax_amount, tax_rate, payment_channel, payment_type, occurred_at_utc, source_hash)
          VALUES (:shop_id, :source_shop_id, :order_id, :order_sn, :status, :actual_price, :original_price, :discount_price, :tax_amount, :tax_rate, :payment_channel, :payment_type, :occurred_at, :source_hash)
          ON DUPLICATE KEY UPDATE source_shop_id = VALUES(source_shop_id), order_sn = VALUES(order_sn), order_status = VALUES(order_status),
          actual_price = VALUES(actual_price), original_price = VALUES(original_price), discount_price = VALUES(discount_price), tax_amount = VALUES(tax_amount),
          tax_rate = VALUES(tax_rate), payment_channel = VALUES(payment_channel), payment_type = VALUES(payment_type), occurred_at_utc = VALUES(occurred_at_utc), source_hash = VALUES(source_hash)');
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('source_shop_id', $sourceShopId);
        $this->db->bind('order_id', $orderId);
        $this->db->bind('order_sn', $row['order_sn'] ?? null);
        $this->db->bind('status', $status ?: 'completed');
        $this->db->bind('actual_price', (float)$actualPrice);
        $this->db->bind('original_price', is_numeric($row['original_price'] ?? null) ? (float)$row['original_price'] : null);
        $this->db->bind('discount_price', is_numeric($row['discount_price'] ?? null) ? (float)$row['discount_price'] : null);
        $this->db->bind('tax_amount', is_numeric($row['tax_amount'] ?? null) ? (float)$row['tax_amount'] : null);
        $this->db->bind('tax_rate', is_numeric($row['tax_rate'] ?? null) ? (float)$row['tax_rate'] : null);
        $this->db->bind('payment_channel', $payment['channel_name'] ?? null);
        $this->db->bind('payment_type', isset($payment['payment_type']) ? (string)$payment['payment_type'] : null);
        $this->db->bind('occurred_at', gmdate('Y-m-d H:i:s', $createdAt));
        $this->db->bind('source_hash', $rowHash);
        $this->db->exe();
        $changedCount += $this->db->row() > 0 ? 1 : 0;
      }

      $pagesFetched++;
      $cutoffReached = $oldestTime < $cutoff;
      $lastPage = !$rows || count($rows) < $pageSize || ($total > 0 && $page * $pageSize >= $total);
      if (!$backfillComplete && ($cutoffReached || $lastPage)) {
        $backfillComplete = true;
        $page = 1;
        $stop = true;
      } elseif ($backfillComplete && ($changedCount === 0 || $lastPage)) {
        $page = 1;
        $stop = true;
      } else {
        $page++;
      }

      $this->saveTopupSyncState($shopId, $sourceShopId, $page, $backfillComplete, null, $stop);
      if ($stop) break;
    }

    return [true, null, $backfillComplete];
  }

  private function topupDate($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d') !== $value) return null;
    return $date;
  }

  private function topupPeriodRange($period, $startDate = null, $endDate = null) {
    $timezone = new DateTimeZone('Asia/Jakarta');
    $availableStart = new DateTimeImmutable('2026-08-01 00:00:00', $timezone);
    $today = new DateTimeImmutable('today', $timezone);
    $period = in_array($period, ['all', 'this_month', 'last_month', 'last_3_months', 'custom'], true) ? $period : 'all';

    if ($period === 'custom') {
      $start = $this->topupDate($startDate);
      $end = $this->topupDate($endDate);
      if (!$start || !$end || $start < $availableStart || $end < $start || $end > $today) {
        throw new InvalidArgumentException('Pilih rentang tanggal yang valid, mulai 1 Agustus 2026 sampai hari ini.');
      }
    } else {
      if ($period === 'this_month') {
        $start = $today->modify('first day of this month');
      } elseif ($period === 'last_month') {
        $start = $today->modify('first day of previous month');
        $today = $start->modify('last day of this month');
        if ($today < $availableStart) {
          throw new InvalidArgumentException('Data topup tersedia mulai Agustus 2026.');
        }
      } elseif ($period === 'last_3_months') {
        $start = $today->modify('first day of this month')->modify('-2 months');
      } else {
        $start = $availableStart;
      }
      if ($start < $availableStart) $start = $availableStart;
      $end = $today;
    }

    return [$period, $start->setTime(0, 0), $end->setTime(0, 0)];
  }

  public function topupSummary($shopId = null, $period = 'all', $startDate = null, $endDate = null) {
    $this->ensureTopupSchema();
    $timezone = new DateTimeZone('Asia/Jakarta');
    $now = new DateTimeImmutable('now', $timezone);
    [$period, $start, $end] = $this->topupPeriodRange($period, $startDate, $endDate);
    $utc = new DateTimeZone('UTC');
    $startUtc = $start->setTimezone($utc)->format('Y-m-d H:i:s');
    $endUtc = $end->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
    $firstMonth = $start->modify('first day of this month');
    $endMonth = $end->modify('first day of next month');
    $monthMap = [];
    $monthNames = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    for ($month = $firstMonth; $month < $endMonth; $month = $month->modify('+1 month')) {
      $key = $month->format('Y-m');
      $monthMap[$key] = ['month' => $key, 'label' => $monthNames[(int)$month->format('n')] . ' ' . $month->format('Y'), 'total' => 0, 'transactions' => 0];
    }

    $this->db->query('SELECT id, shop_id, name FROM shops ORDER BY name ASC');
    $shopOptions = $this->db->getAll();
    $shops = (int)$shopId > 0
      ? array_values(array_filter($shopOptions, static function ($shop) use ($shopId) { return (int)$shop['id'] === (int)$shopId; }))
      : $shopOptions;
    $shopStates = [];
    foreach ($shops as $shop) {
      $this->db->query('SELECT next_page_number, backfill_complete, synced_at, last_error FROM ad_topup_sync_state WHERE shop_id = :shop_id AND source_shop_id = :source_shop_id LIMIT 1');
      $this->db->bind('shop_id', (int)$shop['id']);
      $this->db->bind('source_shop_id', (int)$shop['shop_id']);
      $state = $this->db->single() ?: [];
      $shopStates[] = [
        'shop_id' => (int)$shop['id'],
        'shop_name' => $shop['name'] ?? '',
        'backfill_complete' => !empty($state['backfill_complete']),
        'next_page_number' => (int)($state['next_page_number'] ?? 1),
        'synced_at' => $state['synced_at'] ?? null,
        'error_message' => $state['last_error'] ?? null
      ];
      $this->db->query("SELECT DATE_FORMAT(DATE_ADD(occurred_at_utc, INTERVAL 7 HOUR), '%Y-%m') AS month_key, SUM(actual_price) AS total, COUNT(*) AS transactions
        FROM ad_balance_topups WHERE shop_id = :shop_id AND source_shop_id = :source_shop_id
        AND occurred_at_utc >= :start_utc AND occurred_at_utc < :end_utc
        GROUP BY month_key ORDER BY month_key ASC");
      $this->db->bind('shop_id', (int)$shop['id']);
      $this->db->bind('source_shop_id', (int)$shop['shop_id']);
      $this->db->bind('start_utc', $startUtc);
      $this->db->bind('end_utc', $endUtc);
      foreach ($this->db->getAll() as $row) {
        $key = (string)$row['month_key'];
        if (!isset($monthMap[$key])) continue;
        $monthMap[$key]['total'] += (float)$row['total'];
        $monthMap[$key]['transactions'] += (int)$row['transactions'];
      }
    }

    return [
      'period' => $period,
      'start_date' => $start->format('Y-m-d'),
      'end_date' => $end->format('Y-m-d'),
      'start_month' => $firstMonth->format('Y-m'),
      'end_month' => $end->format('Y-m'),
      'months' => array_values($monthMap),
      'shop_options' => array_map(static function ($shop) {
        return ['shop_id' => (int)$shop['id'], 'shop_name' => $shop['name'] ?? ''];
      }, $shopOptions),
      'shops' => $shopStates,
      'backfill_complete' => count($shopStates) > 0 && count(array_filter($shopStates, static function ($state) { return $state['backfill_complete']; })) === count($shopStates),
      'refreshed_at' => $now->format(DateTimeInterface::ATOM)
    ];
  }
}
