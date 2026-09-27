<?php

/**
 * Local daily snapshots for Seller Centre store performance.
 * Reports read this table only; Shopee calls are made by the background worker.
 */
class ShopPerformance extends BaseModel {
  private $schemaReady = false;

  private $metrics = [
    'shop_pv', 'shop_uv', 'product_clicks', 'hybrid_uv',
    'paid_gmv', 'place_gmv', 'confirmed_gmv',
    'paid_orders', 'place_orders', 'confirmed_orders',
    'paid_sales_per_order', 'place_sales_per_order', 'confirmed_sales_per_order',
    'shop_uv_to_paid_buyers_rate', 'shop_uv_to_placed_buyers_rate', 'shop_uv_to_confirmed_buyers_rate',
    'product_clicks_to_placed_orders_rate', 'product_clicks_to_paid_orders_rate', 'product_clicks_to_confirmed_orders_rate'
  ];

  private $sumMetrics = [
    'shop_pv', 'shop_uv', 'product_clicks', 'hybrid_uv',
    'paid_gmv', 'place_gmv', 'confirmed_gmv', 'paid_orders', 'place_orders', 'confirmed_orders'
  ];

  public function ensureSchema() {
    if ($this->schemaReady) return;
    $this->db->query("CREATE TABLE IF NOT EXISTS shop_performance_daily (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      shop_id INT NOT NULL,
      metric_date DATE NOT NULL,
      source VARCHAR(40) NOT NULL DEFAULT 'homepage',
      shop_pv BIGINT NULL,
      shop_uv BIGINT NULL,
      product_clicks BIGINT NULL,
      hybrid_uv BIGINT NULL,
      paid_gmv BIGINT NULL,
      place_gmv BIGINT NULL,
      confirmed_gmv BIGINT NULL,
      paid_orders BIGINT NULL,
      place_orders BIGINT NULL,
      confirmed_orders BIGINT NULL,
      paid_sales_per_order DECIMAL(18,4) NULL,
      place_sales_per_order DECIMAL(18,4) NULL,
      confirmed_sales_per_order DECIMAL(18,4) NULL,
      shop_uv_to_paid_buyers_rate DECIMAL(18,8) NULL,
      shop_uv_to_placed_buyers_rate DECIMAL(18,8) NULL,
      shop_uv_to_confirmed_buyers_rate DECIMAL(18,8) NULL,
      product_clicks_to_placed_orders_rate DECIMAL(18,8) NULL,
      product_clicks_to_paid_orders_rate DECIMAL(18,8) NULL,
      product_clicks_to_confirmed_orders_rate DECIMAL(18,8) NULL,
      raw_payload LONGTEXT NULL,
      synced_at DATETIME NULL,
      PRIMARY KEY (id),
      UNIQUE KEY shop_performance_daily_unique (shop_id, metric_date, source),
      KEY shop_performance_daily_shop_date (shop_id, metric_date),
      KEY shop_performance_daily_synced (synced_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
    $this->schemaReady = true;
  }

  private function ranges($endDate = null) {
    $tz = new DateTimeZone('Asia/Jakarta');
    $end = $endDate ? DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, $tz) : new DateTimeImmutable('now', $tz);
    if (!$end) $end = new DateTimeImmutable('now', $tz);
    $end = $end->setTime(23, 59, 59);
    $currentStart = $end->modify('first day of this month')->setTime(0, 0, 0);
    $daysElapsed = (int)$currentStart->diff($end->setTime(0, 0, 0))->format('%a');
    $previousEnd = $currentStart->modify('-1 day')->setTime(23, 59, 59);
    $previousStart = $previousEnd->modify('-' . $daysElapsed . ' days')->setTime(0, 0, 0);
    return [
      'current' => ['start' => $currentStart, 'end' => $end],
      'previous' => ['start' => $previousStart, 'end' => $previousEnd]
    ];
  }

  private function numeric($value) {
    if (is_int($value) || is_float($value)) return $value;
    if (is_string($value) && is_numeric($value)) return (float)$value;
    return null;
  }

  private function pointDate($timestamp) {
    $timestamp = (int)$timestamp;
    if ($timestamp > 20000000000) $timestamp = (int)floor($timestamp / 1000);
    if ($timestamp < 1) return null;
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
  }

  private function normalizePoints(array $result) {
    $days = [];
    foreach ($this->metrics as $metric) {
      $definition = is_array($result[$metric] ?? null) ? $result[$metric] : [];
      $points = is_array($definition['points'] ?? null) ? $definition['points'] : [];
      foreach ($points as $point) {
        if (!is_array($point)) continue;
        $date = $this->pointDate($point['timestamp'] ?? $point['time'] ?? 0);
        if (!$date) continue;
        $value = $this->numeric($point['value'] ?? null);
        if ($value === null) continue;
        if (!isset($days[$date])) $days[$date] = [];
        if (in_array($metric, $this->sumMetrics, true)) {
          $days[$date][$metric] = ($days[$date][$metric] ?? 0) + $value;
        } else {
          $days[$date][$metric] = $value;
        }
      }
    }
    return $days;
  }

  private function saveDays($shopId, array $days, array $result) {
    foreach ($days as $date => $values) {
      $columns = ['shop_id', 'metric_date', 'source'];
      $params = ['shop_id' => (int)$shopId, 'metric_date' => $date, 'source' => 'homepage'];
      foreach ($this->metrics as $metric) {
        if (array_key_exists($metric, $values)) {
          $columns[] = $metric;
          $params[$metric] = $values[$metric];
        }
      }
      $columns[] = 'raw_payload';
      $columns[] = 'synced_at';
      $params['raw_payload'] = json_encode($result, JSON_UNESCAPED_UNICODE);
      $params['synced_at'] = date('Y-m-d H:i:s');
      $placeholders = array_map(static function ($column) { return ':' . $column; }, $columns);
      $updates = [];
      foreach ($columns as $column) {
        if (!in_array($column, ['shop_id', 'metric_date', 'source'], true)) $updates[] = $column . ' = VALUES(' . $column . ')';
      }
      $this->db->query("INSERT INTO shop_performance_daily (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ") ON DUPLICATE KEY UPDATE " . implode(',', $updates));
      foreach ($params as $key => $value) $this->db->bind($key, $value);
      $this->db->exe();
    }
  }

  public function syncShop(array $shop) {
    $this->ensureSchema();
    $cookie = trim((string)($shop['cookie'] ?? ''));
    if ($cookie === '') return ['ok' => false, 'message' => 'Cookie toko kosong.'];
    require_once __DIR__ . '/ShopeeCurl.php';
    $api = new ShopeeCurl();
    $ranges = $this->ranges();
    $synced = [];
    foreach ($ranges as $label => $range) {
      $response = $api->getShopPerformance($cookie, $range['start'], $range['end'], 'custom');
      if (empty($response['ok'])) {
        // Rekaman endpoint membuktikan period past30days; gunakan sebagai
        // fallback untuk rentang berjalan agar satu sesi gagal tidak merusak
        // seluruh worker. Rentang sebelumnya tetap ditandai belum tersedia.
        if ($label === 'current') {
          $response = $api->getShopPerformance($cookie, $range['start'], $range['end'], 'past30days');
        }
      }
      if (empty($response['ok'])) {
        if ($label === 'previous') {
          // Endpoint hasil rekaman hanya menyediakan rolling period (7/30 hari)
          // dan menolak rentang bulan arbitrer. Simpan periode berjalan dan
          // biarkan laporan menandai pembanding sebagai belum tersedia.
          continue;
        }
        $this->markShopStatus((int)$shop['id'], $response['message'] ?? 'Metrik performa gagal diambil.');
        return ['ok' => false, 'message' => $response['message'] ?? 'Metrik performa gagal diambil.', 'synced' => $synced];
      }
      $days = $this->normalizePoints($response['result']);
      $this->saveDays((int)$shop['id'], $days, $response['result']);
      $synced[$label] = count($days);
    }
    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    return ['ok' => true, 'synced' => $synced];
  }

  private function markShopStatus($shopId, $message) {
    $this->db->query("UPDATE shops SET sync_status = CASE WHEN sync_status = 'expired' THEN sync_status ELSE 'connected' END WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->exe();
  }

  private function rangeSql($start, $end, $alias = '') {
    $prefix = $alias ? $alias . '.' : '';
    return $prefix . "metric_date BETWEEN :start_date AND :end_date";
  }

  public function summary($endDate = null, $shopId = 0, $sort = 'confirmed_gmv') {
    $this->ensureSchema();
    $ranges = $this->ranges($endDate);
    $sortAllowed = ['confirmed_gmv', 'confirmed_orders', 'shop_uv', 'shop_pv', 'product_clicks', 'confirmed_sales_per_order'];
    if (!in_array($sort, $sortAllowed, true)) $sort = 'confirmed_gmv';
    $shopFilter = (int)$shopId > 0 ? ' AND d.shop_id = :shop_id' : '';
    $sortColumns = ['confirmed_gmv' => 'current_confirmed_gmv', 'confirmed_orders' => 'current_confirmed_orders', 'shop_uv' => 'current_shop_uv', 'shop_pv' => 'current_shop_pv', 'product_clicks' => 'current_product_clicks', 'confirmed_sales_per_order' => 'current_confirmed_gmv'];
    $orderBy = $sortColumns[$sort] ?? 'current_confirmed_gmv';
    $this->db->query("SELECT s.id AS shop_id, s.name AS shop_name, s.sync_status,
      SUM(CASE WHEN d.metric_date BETWEEN :cs AND :ce THEN COALESCE(d.confirmed_gmv,0) ELSE 0 END) AS current_confirmed_gmv,
      SUM(CASE WHEN d.metric_date BETWEEN :ps AND :pe THEN COALESCE(d.confirmed_gmv,0) ELSE 0 END) AS previous_confirmed_gmv,
      SUM(CASE WHEN d.metric_date BETWEEN :cs2 AND :ce2 THEN COALESCE(d.confirmed_orders,0) ELSE 0 END) AS current_confirmed_orders,
      SUM(CASE WHEN d.metric_date BETWEEN :ps2 AND :pe2 THEN COALESCE(d.confirmed_orders,0) ELSE 0 END) AS previous_confirmed_orders,
      SUM(CASE WHEN d.metric_date BETWEEN :cs3 AND :ce3 THEN COALESCE(d.shop_uv,0) ELSE 0 END) AS current_shop_uv,
      SUM(CASE WHEN d.metric_date BETWEEN :ps3 AND :pe3 THEN COALESCE(d.shop_uv,0) ELSE 0 END) AS previous_shop_uv,
      SUM(CASE WHEN d.metric_date BETWEEN :cs4 AND :ce4 THEN COALESCE(d.shop_pv,0) ELSE 0 END) AS current_shop_pv,
      SUM(CASE WHEN d.metric_date BETWEEN :ps4 AND :pe4 THEN COALESCE(d.shop_pv,0) ELSE 0 END) AS previous_shop_pv,
      SUM(CASE WHEN d.metric_date BETWEEN :cs5 AND :ce5 THEN COALESCE(d.product_clicks,0) ELSE 0 END) AS current_product_clicks,
      SUM(CASE WHEN d.metric_date BETWEEN :ps5 AND :pe5 THEN COALESCE(d.product_clicks,0) ELSE 0 END) AS previous_product_clicks,
      COUNT(DISTINCT CASE WHEN d.metric_date BETWEEN :cs6 AND :ce6 THEN d.metric_date END) AS current_days,
      COUNT(DISTINCT CASE WHEN d.metric_date BETWEEN :ps6 AND :pe6 THEN d.metric_date END) AS previous_days
      FROM shops s LEFT JOIN shop_performance_daily d ON d.shop_id = s.id AND d.source = 'homepage'
      WHERE 1=1 {$shopFilter} GROUP BY s.id, s.name, s.sync_status ORDER BY {$orderBy} DESC");
    $bind = [
      'cs' => $ranges['current']['start']->format('Y-m-d'), 'ce' => $ranges['current']['end']->format('Y-m-d'),
      'ps' => $ranges['previous']['start']->format('Y-m-d'), 'pe' => $ranges['previous']['end']->format('Y-m-d')
    ];
    for ($i = 2; $i <= 6; $i++) {
      $bind['cs' . $i] = $bind['cs']; $bind['ce' . $i] = $bind['ce']; $bind['ps' . $i] = $bind['ps']; $bind['pe' . $i] = $bind['pe'];
    }
    foreach ($bind as $key => $value) $this->db->bind($key, $value);
    if ($shopFilter) $this->db->bind('shop_id', (int)$shopId);
    $rows = $this->db->getAll();
    $rank = 0;
    foreach ($rows as &$row) {
      $rank++;
      $current = (float)$row['current_confirmed_gmv'];
      $previous = (float)$row['previous_confirmed_gmv'];
      $row['rank'] = $rank;
      $row['growth_percent'] = $previous > 0 ? (($current - $previous) / $previous) * 100 : null;
      $row['conversion_rate'] = (float)$row['current_shop_uv'] > 0 ? ((float)$row['current_confirmed_orders'] / (float)$row['current_shop_uv']) * 100 : null;
      $row['expected_days'] = (int)$ranges['current']['start']->diff($ranges['current']['end']->setTime(0, 0, 0))->format('%a') + 1;
      $row['coverage_percent'] = $row['expected_days'] > 0 ? min(100, ((int)$row['current_days'] / $row['expected_days']) * 100) : 0;
      $row['previous_coverage_percent'] = $row['expected_days'] > 0 ? min(100, ((int)$row['previous_days'] / $row['expected_days']) * 100) : 0;
      if ((int)$row['previous_days'] < (int)$row['expected_days']) $row['growth_percent'] = null;
      $row['session_expired'] = ($row['sync_status'] ?? '') === 'expired';
    }
    unset($row);
    $totals = ['confirmed_gmv' => 0, 'confirmed_orders' => 0, 'shop_uv' => 0, 'product_clicks' => 0, 'previous_confirmed_gmv' => 0];
    $totalColumns = ['confirmed_gmv' => 'current_confirmed_gmv', 'confirmed_orders' => 'current_confirmed_orders', 'shop_uv' => 'current_shop_uv', 'product_clicks' => 'current_product_clicks', 'previous_confirmed_gmv' => 'previous_confirmed_gmv'];
    foreach ($rows as $row) foreach ($totalColumns as $key => $column) $totals[$key] += (float)($row[$column] ?? 0);
    $comparisonComplete = !empty($rows) && count(array_filter($rows, static function ($row) { return (int)($row['previous_coverage_percent'] ?? 0) >= 100; })) === count($rows);
    $totals['growth_percent'] = $comparisonComplete && $totals['previous_confirmed_gmv'] > 0 ? (($totals['confirmed_gmv'] - $totals['previous_confirmed_gmv']) / $totals['previous_confirmed_gmv']) * 100 : null;
    $totals['conversion_rate'] = $totals['shop_uv'] > 0 ? ($totals['confirmed_orders'] / $totals['shop_uv']) * 100 : null;
    return ['ranges' => array_map(static function ($range) { return ['start' => $range['start']->format('Y-m-d'), 'end' => $range['end']->format('Y-m-d')]; }, $ranges), 'rows' => $rows, 'totals' => $totals, 'comparison_available' => $comparisonComplete, 'sort' => $sort, 'refreshed_at' => date('c')];
  }

  public function detail($shopId, $endDate = null) {
    $this->ensureSchema();
    $ranges = $this->ranges($endDate);
    $this->db->query("SELECT metric_date, confirmed_gmv, confirmed_orders, shop_uv, shop_pv, product_clicks FROM shop_performance_daily WHERE shop_id = :shop_id AND source = 'homepage' AND ((metric_date BETWEEN :cs AND :ce) OR (metric_date BETWEEN :ps AND :pe)) ORDER BY metric_date ASC");
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('cs', $ranges['current']['start']->format('Y-m-d')); $this->db->bind('ce', $ranges['current']['end']->format('Y-m-d'));
    $this->db->bind('ps', $ranges['previous']['start']->format('Y-m-d')); $this->db->bind('pe', $ranges['previous']['end']->format('Y-m-d'));
    return ['ranges' => array_map(static function ($range) { return ['start' => $range['start']->format('Y-m-d'), 'end' => $range['end']->format('Y-m-d')]; }, $ranges), 'rows' => $this->db->getAll()];
  }
}
