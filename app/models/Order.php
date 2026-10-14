<?php

class Order extends BaseModel {
  protected $table = 'orders';

  private function statusFilterSql(string $filter): string {
    return match ($filter) {
      'waiting' => " AND LOWER(TRIM(COALESCE(status_type,''))) IN ('unpaid','to ship','pending')",
      'processing' => " AND LOWER(TRIM(COALESCE(status_type,''))) IN ('ready_to_ship','processed','shipping','shipped')",
      'completed' => " AND LOWER(TRIM(COALESCE(status_type,''))) IN ('completed','delivered','order received','order_received','to confirm receive','to_confirm_receive')",
      'cancelled' => " AND LOWER(TRIM(COALESCE(status_type,''))) IN ('cancelled','canceled')",
      'advance' => " AND order_type = 'advance'",
      'unknown' => " AND (TRIM(COALESCE(status_type,'')) = '' OR LOWER(TRIM(status_type)) NOT IN ('unpaid','to ship','pending','ready_to_ship','processed','shipping','shipped','completed','delivered','order received','order_received','to confirm receive','to_confirm_receive','cancelled','canceled'))",
      default => '',
    };
  }

  public function countByCreatedDateRange($shopId, $startDate, $endDate, string $filter = '') {
    [$startDate, $endDate] = $this->dateRange($startDate, $endDate);
    $filterSql = $this->statusFilterSql($filter);
    $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE shop_id = :shop_id AND (created_at IS NULL OR (created_at >= :start_date AND created_at < :end_date)){$filterSql}");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $startDate);
    $this->db->bind('end_date', $endDate);
    return $this->db->single()['total'];
  }

  public function countDatedByCreatedDateRange(int $shopId, string $startDate, string $endDate): int {
    [$startDate, $endDate] = $this->dateRange($startDate, $endDate);
    $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE shop_id = :shop_id AND created_at >= :start_date AND created_at < :end_date");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $startDate);
    $this->db->bind('end_date', $endDate);
    return (int)($this->db->single()['total'] ?? 0);
  }

  public function findByCreatedDateRangePaginated($shopId, $startDate, $endDate, $limit = 10, $offset = 0, string $filter = '') {
    [$startDate, $endDate] = $this->dateRange($startDate, $endDate);
    $limit = (int)$limit;
    $offset = (int)$offset;
    $filterSql = $this->statusFilterSql($filter);
    $this->db->query("SELECT * FROM {$this->table} WHERE shop_id = :shop_id AND (created_at IS NULL OR (created_at >= :start_date AND created_at < :end_date)){$filterSql} ORDER BY CASE WHEN created_at IS NULL THEN 0 ELSE 1 END, CASE WHEN created_at IS NULL THEN id END DESC, created_at DESC LIMIT {$limit} OFFSET {$offset}");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $startDate);
    $this->db->bind('end_date', $endDate);
    return $this->db->getAll();
  }

  public function summaryByCreatedDateRange(int $shopId, string $startDate, string $endDate): array {
    [$start, $end] = $this->dateRange($startDate, $endDate);
    $this->db->query("SELECT COUNT(*) AS total,
      SUM(order_type = 'advance') AS advance_count,
      SUM(created_at IS NOT NULL) AS dated_count,
      SUM(created_at IS NULL) AS undated_count,
      SUM(LOWER(TRIM(COALESCE(status_type,''))) IN ('unpaid','to ship','pending')) AS waiting_count,
      SUM(LOWER(TRIM(COALESCE(status_type,''))) IN ('ready_to_ship','processed','shipping','shipped')) AS processing_count,
      SUM(LOWER(TRIM(COALESCE(status_type,''))) IN ('completed','delivered','order received','order_received','to confirm receive','to_confirm_receive')) AS completed_count,
      SUM(LOWER(TRIM(COALESCE(status_type,''))) IN ('cancelled','canceled')) AS cancelled_count,
      SUM(TRIM(COALESCE(status_type,'')) = '') AS unknown_count
      FROM {$this->table}
      WHERE shop_id = :shop_id AND (created_at IS NULL OR (created_at >= :start_date AND created_at < :end_date))");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $start);
    $this->db->bind('end_date', $end);
    $counts = $this->db->single() ?: [];
    $this->db->query("SELECT DATE(created_at) AS day, COUNT(*) AS total FROM {$this->table}
      WHERE shop_id = :shop_id AND created_at >= :start_date AND created_at < :end_date
      GROUP BY DATE(created_at) ORDER BY day ASC");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $start);
    $this->db->bind('end_date', $end);
    $trendRows = $this->db->getAll();
    $trendCounts = [];
    foreach ($trendRows as $row) $trendCounts[(string)$row['day']] = (int)$row['total'];
    $total = (int)($counts['total'] ?? 0);
    $waiting = (int)($counts['waiting_count'] ?? 0);
    $processing = (int)($counts['processing_count'] ?? 0);
    $completed = (int)($counts['completed_count'] ?? 0);
    $cancelled = (int)($counts['cancelled_count'] ?? 0);
    $unknown = (int)($counts['unknown_count'] ?? 0);
    return [
      'total' => $total,
      'advance' => (int)($counts['advance_count'] ?? 0),
      'dated' => (int)($counts['dated_count'] ?? 0),
      'undated' => (int)($counts['undated_count'] ?? 0),
      'waiting' => $waiting,
      'processing' => $processing,
      'completed' => $completed,
      'cancelled' => $cancelled,
      'unknown' => $unknown,
      'other' => max(0, $total - $waiting - $processing - $completed - $cancelled - $unknown),
      'trend' => $trendCounts,
    ];
  }

  private function dateRange($startDate, $endDate) {
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, $timezone);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, $timezone);
    return [$start->format('Y-m-d H:i:s'), $end->modify('+1 day')->format('Y-m-d H:i:s')];
  }
}
