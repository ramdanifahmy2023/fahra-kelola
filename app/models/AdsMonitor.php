<?php

class AdsMonitor extends BaseModel {
  protected $table = 'ad_shop_snapshots';

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
    $this->db->query("CREATE TABLE IF NOT EXISTS ad_performance_snapshots (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      shop_id INT NOT NULL,
      period_start DATETIME NOT NULL,
      period_end DATETIME NOT NULL,
      timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
      campaign_type VARCHAR(64) NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'ok',
      impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
      clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
      ctr DECIMAL(12,4) NOT NULL DEFAULT 0,
      orders BIGINT UNSIGNED NOT NULL DEFAULT 0,
      items_sold BIGINT UNSIGNED NOT NULL DEFAULT 0,
      sales DECIMAL(20,2) NOT NULL DEFAULT 0,
      ad_cost DECIMAL(20,2) NOT NULL DEFAULT 0,
      roas DECIMAL(12,4) NULL,
      broad_sales DECIMAL(20,2) NOT NULL DEFAULT 0,
      broad_orders BIGINT UNSIGNED NOT NULL DEFAULT 0,
      broad_items_sold BIGINT UNSIGNED NOT NULL DEFAULT 0,
      broad_roas DECIMAL(12,4) NULL,
      raw_payload LONGTEXT NULL,
      fetched_at DATETIME NOT NULL,
      error_message TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY ad_performance_window (shop_id, period_start, period_end, campaign_type),
      KEY ad_performance_shop_period (shop_id, period_start),
      KEY ad_performance_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) {
      $where = ' WHERE id = :shop_id';
    }
    $this->db->query("SELECT id, name, sync_status, cookie FROM shops{$where} ORDER BY name ASC");
    if ($where) {
      $this->db->bind('shop_id', (int)$shopId);
    }
    return $this->db->getAll();
  }

  public function syncShop(array $shop) {
    $this->ensureSchema();
    require_once __DIR__ . '/ShopeeCurl.php';
    $shopee = new ShopeeCurl();
    $cookie = trim($shop['cookie'] ?? '');
    if ($cookie === '') {
      $this->saveError($shop, 'expired', 'Cookie toko kosong. Masukkan cookie Shopee terbaru.');
      return false;
    }
    $session = $shopee->check($cookie);
    if (!isset($session['shop']['id'])) {
      $message = (string)($session['message'] ?? 'user_is_unauthorized');
      $status = !empty($session['success']) && $session['success'] === false ? 'error' : 'expired';
      $friendly = $status === 'expired'
        ? 'Sesi Shopee toko habis atau tidak valid (' . $message . '). Perbarui cookie toko.'
        : 'Verifikasi sesi toko gagal karena koneksi atau respons Shopee tidak tersedia (' . $message . ').';
      $this->saveError($shop, $status, $friendly);
      return false;
    }
    $payload = $shopee->getAdsSummary($cookie);
    if ($payload === false) {
      $this->saveError($shop, 'error', 'Endpoint metrik iklan tidak tersedia. Sesi dasar toko masih valid.');
      return false;
    }

    $performance = is_array($payload['performance'] ?? null) ? $payload['performance'] : [];
    $performanceStatus = !empty($performance['available'])
      ? (!empty($performance['partial']) ? 'partial' : 'ok')
      : 'error';
    $performanceErrors = array_values((array)($performance['errors'] ?? []));
    $this->persistPerformanceSnapshots((int)$shop['id'], $performance);
    $publicPayload = $payload;
    foreach ((array)($publicPayload['performance']['channels'] ?? []) as $key => $channel) {
      unset($publicPayload['performance']['channels'][$key]['raw_metrics']);
    }

    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, :status, :payload, :error_message, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), payload = VALUES(payload), error_message = VALUES(error_message), synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $performanceStatus);
    $this->db->bind('payload', json_encode($publicPayload, JSON_UNESCAPED_UNICODE));
    $this->db->bind('error_message', $performanceErrors ? implode(' ', $performanceErrors) : null);
    $this->db->exe();
    return $performanceStatus !== 'error';
  }

  public function syncShopFromSniper($shopId) {
    $this->ensureSchema();
    $this->db->query('SELECT id, name FROM shops WHERE id = :shop_id LIMIT 1');
    $this->db->bind('shop_id', (int)$shopId);
    $shop = $this->db->single();
    if (!is_array($shop)) return ['ok' => false, 'message' => 'Toko tidak ditemukan.'];
    require_once __DIR__ . '/ShopeeCurl.php';
    require_once __DIR__ . '/SniperMcpClient.php';
    $shopee = new ShopeeCurl();
    $period = $shopee->weeklyAdsPeriod();
    $reports = (new SniperMcpClient())->weeklyReports($period);
    if (empty($reports['available'])) {
      return ['ok' => false, 'message' => $reports['error'] ?? 'History report Sniper belum tersedia untuk periode ini.'];
    }
    $labels = [
      'product_homepage_v2' => 'Produk',
      'shop_homepage' => 'Shop',
      'live_stream_homepage' => 'Live stream'
    ];
    $channels = [];
    foreach ((array)($reports['reports'] ?? []) as $type => $report) {
      if (!isset($labels[$type]) || !is_array($report['aggregate'] ?? null)) continue;
      $channels[$type] = $shopee->normalizeCapturedAdsMetrics($report['aggregate'], $labels[$type]);
      $channels[$type]['captured_at'] = $report['created_at'] ?? null;
    }
    if (!$channels) return ['ok' => false, 'message' => 'History report Sniper tidak memiliki channel yang dipetakan.'];
    $totals = ['impressions'=>0,'clicks'=>0,'ctr'=>0,'orders'=>0,'items_sold'=>0,'sales'=>0,'ad_cost'=>0,'roas'=>null,'broad_sales'=>0,'broad_orders'=>0,'broad_items_sold'=>0,'broad_roas'=>null,'atc'=>0,'checkout'=>0];
    foreach ($channels as $metrics) {
      foreach (['impressions','clicks','orders','items_sold','sales','ad_cost','broad_sales','broad_orders','broad_items_sold','atc','checkout'] as $field) $totals[$field] += (float)($metrics[$field] ?? 0);
    }
    $totals['impressions'] = (int)$totals['impressions']; $totals['clicks'] = (int)$totals['clicks'];
    $totals['orders'] = (int)$totals['orders']; $totals['items_sold'] = (int)$totals['items_sold'];
    $totals['broad_orders'] = (int)$totals['broad_orders']; $totals['broad_items_sold'] = (int)$totals['broad_items_sold'];
    $totals['atc'] = (int)$totals['atc']; $totals['checkout'] = (int)$totals['checkout'];
    $totals['sales'] = round($totals['sales'], 2); $totals['ad_cost'] = round($totals['ad_cost'], 2); $totals['broad_sales'] = round($totals['broad_sales'], 2);
    $totals['ctr'] = $totals['impressions'] > 0 ? round(($totals['clicks'] / $totals['impressions']) * 100, 2) : 0;
    $totals['roas'] = $totals['ad_cost'] > 0 ? round($totals['sales'] / $totals['ad_cost'], 2) : null;
    $totals['broad_roas'] = $totals['ad_cost'] > 0 ? round($totals['broad_sales'] / $totals['ad_cost'], 2) : null;
    $performance = ['available'=>true,'partial'=>count($channels) < 3,'period'=>['from'=>$period['from'],'to'=>$period['to'],'label'=>'7 hari terakhir'],'timezone'=>'Asia/Jakarta','totals'=>$totals,'channels'=>$channels,'errors'=>[],'source'=>'xyz_sniper_mcp'];
    $this->persistPerformanceSnapshots((int)$shop['id'], $performance);
    $publicPayload = ['performance'=>$performance];
    foreach ($publicPayload['performance']['channels'] as $key => $channel) unset($publicPayload['performance']['channels'][$key]['raw_metrics']);
    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id"); $this->db->bind('shop_id', (int)$shop['id']); $this->db->exe();
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, 'ok', :payload, NULL, NOW()) ON DUPLICATE KEY UPDATE status = 'ok', payload = VALUES(payload), error_message = NULL, synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']); $this->db->bind('payload', json_encode($publicPayload, JSON_UNESCAPED_UNICODE)); $this->db->exe();
    return ['ok' => true, 'channels' => array_keys($channels), 'period' => $performance['period']];
  }

  private function persistPerformanceSnapshots($shopId, array $performance) {
    $period = is_array($performance['period'] ?? null) ? $performance['period'] : [];
    $from = (string)($period['from'] ?? '');
    $to = (string)($period['to'] ?? '');
    if ($from === '' || $to === '') return;
    $periodStart = $from . ' 00:00:00';
    $periodEnd = $to . ' 23:59:59';
    $fetchedAt = gmdate('Y-m-d H:i:s');
    foreach ((array)($performance['channels'] ?? []) as $campaignType => $metrics) {
      $this->db->query("INSERT INTO ad_performance_snapshots (
        shop_id, period_start, period_end, timezone, campaign_type, status,
        impressions, clicks, ctr, orders, items_sold, sales, ad_cost, roas,
        broad_sales, broad_orders, broad_items_sold, broad_roas,
        raw_payload, fetched_at, error_message
      ) VALUES (
        :shop_id, :period_start, :period_end, :timezone, :campaign_type, 'ok',
        :impressions, :clicks, :ctr, :orders, :items_sold, :sales, :ad_cost, :roas,
        :broad_sales, :broad_orders, :broad_items_sold, :broad_roas,
        :raw_payload, :fetched_at, NULL
      ) ON DUPLICATE KEY UPDATE
        status = 'ok', impressions = VALUES(impressions), clicks = VALUES(clicks),
        ctr = VALUES(ctr), orders = VALUES(orders), items_sold = VALUES(items_sold),
        sales = VALUES(sales), ad_cost = VALUES(ad_cost), roas = VALUES(roas),
        broad_sales = VALUES(broad_sales), broad_orders = VALUES(broad_orders),
        broad_items_sold = VALUES(broad_items_sold), broad_roas = VALUES(broad_roas),
        raw_payload = VALUES(raw_payload), fetched_at = VALUES(fetched_at), error_message = NULL");
      $this->db->bind('shop_id', (int)$shopId);
      $this->db->bind('period_start', $periodStart);
      $this->db->bind('period_end', $periodEnd);
      $this->db->bind('timezone', (string)($performance['timezone'] ?? 'Asia/Jakarta'));
      $this->db->bind('campaign_type', (string)$campaignType);
      $this->db->bind('impressions', (int)($metrics['impressions'] ?? 0));
      $this->db->bind('clicks', (int)($metrics['clicks'] ?? 0));
      $this->db->bind('ctr', (float)($metrics['ctr'] ?? 0));
      $this->db->bind('orders', (int)($metrics['orders'] ?? 0));
      $this->db->bind('items_sold', (int)($metrics['items_sold'] ?? 0));
      $this->db->bind('sales', (float)($metrics['sales'] ?? 0));
      $this->db->bind('ad_cost', (float)($metrics['ad_cost'] ?? 0));
      $this->db->bind('roas', isset($metrics['roas']) ? (float)$metrics['roas'] : null);
      $this->db->bind('broad_sales', (float)($metrics['broad_sales'] ?? 0));
      $this->db->bind('broad_orders', (int)($metrics['broad_orders'] ?? 0));
      $this->db->bind('broad_items_sold', (int)($metrics['broad_items_sold'] ?? 0));
      $this->db->bind('broad_roas', isset($metrics['broad_roas']) ? (float)$metrics['broad_roas'] : null);
      $this->db->bind('raw_payload', json_encode($metrics['raw_metrics'] ?? [], JSON_UNESCAPED_UNICODE));
      $this->db->bind('fetched_at', $fetchedAt);
      $this->db->exe();
    }
  }

  private function saveError(array $shop, $status, $message) {
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

  public function summary($shopId = null, $refresh = true) {
    $this->ensureSchema();
    $shops = $this->shops($shopId);
    $result = [];
    foreach ($shops as $shop) {
      $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
      $this->db->bind('shop_id', (int)$shop['id']);
      $snapshot = $this->db->single();
      $snapshotPayload = !empty($snapshot['payload']) ? json_decode($snapshot['payload'], true) : [];
      $isSniperSnapshot = is_array($snapshotPayload)
        && (($snapshotPayload['performance']['source'] ?? null) === 'xyz_sniper_mcp');
      // MariaDB server stores NOW() in UTC in this deployment; parse it explicitly
      // so a fresh snapshot is not marked stale because of the PHP app timezone.
      $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      if ($refresh && !$isSniperSnapshot && (!is_array($snapshot) || $age >= 300) && !empty($shop['cookie'])) {
        $this->syncShop($shop);
        $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', (int)$shop['id']);
        $snapshot = $this->db->single();
        $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      }
      $payload = !empty($snapshot['payload']) ? json_decode($snapshot['payload'], true) : [];
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
        'stale' => !empty($snapshot['synced_at']) && $age >= 300,
        'metrics' => is_array($payload) ? $payload : []
      ];
    }
    return $result;
  }
}
