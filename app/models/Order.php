<?php

class Order extends BaseModel {
  protected $table = 'orders';

  public function countByCreatedDateRange($shopId, $startDate, $endDate) {
    [$startDate, $endDate] = $this->dateRange($startDate, $endDate);
    $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE shop_id = :shop_id AND (created_at IS NULL OR (created_at >= :start_date AND created_at < :end_date))");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $startDate);
    $this->db->bind('end_date', $endDate);
    return $this->db->single()['total'];
  }

  public function findByCreatedDateRangePaginated($shopId, $startDate, $endDate, $limit = 10, $offset = 0) {
    [$startDate, $endDate] = $this->dateRange($startDate, $endDate);
    $limit = (int)$limit;
    $offset = (int)$offset;
    $this->db->query("SELECT * FROM {$this->table} WHERE shop_id = :shop_id AND (created_at IS NULL OR (created_at >= :start_date AND created_at < :end_date)) ORDER BY CASE WHEN created_at IS NULL THEN 0 ELSE 1 END, CASE WHEN created_at IS NULL THEN id END DESC, created_at DESC LIMIT {$limit} OFFSET {$offset}");
    $this->db->bind('shop_id', $shopId);
    $this->db->bind('start_date', $startDate);
    $this->db->bind('end_date', $endDate);
    return $this->db->getAll();
  }

  private function dateRange($startDate, $endDate) {
    $timezone = new DateTimeZone('Asia/Jakarta');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, $timezone);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, $timezone);
    return [$start->format('Y-m-d H:i:s'), $end->modify('+1 day')->format('Y-m-d H:i:s')];
  }
}
