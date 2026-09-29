<?php

class AdsBrowserReport extends BaseModel {
  public function ensureSchema() {
    $this->db->query(file_get_contents(__DIR__ . '/../../database/migrations/20260929_ads_browser_reports.sql'));
    $this->db->exe();
  }

  public function save($shopId, array $report) {
    $this->db->query('INSERT INTO ad_browser_reports
      (shop_id, source_shop_id, channel, start_date, end_date, mapping_version, captured_at, payload)
      VALUES (:shop_id, :source_shop_id, :channel, :start_date, :end_date, :mapping_version, :captured_at, :payload)
      ON DUPLICATE KEY UPDATE
        payload = IF(VALUES(captured_at) > captured_at, VALUES(payload), payload),
        imported_at = IF(VALUES(captured_at) > captured_at, NOW(), imported_at),
        captured_at = GREATEST(captured_at, VALUES(captured_at))');
    foreach (['source_shop_id', 'channel', 'start_date', 'end_date', 'mapping_version'] as $key) $this->db->bind($key, $report[$key]);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('captured_at', gmdate('Y-m-d H:i:s', strtotime($report['fetched_at'])));
    $this->db->bind('payload', json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    $this->db->exe();
  }

  public function forRange(array $shop, array $range, $channel) {
    $this->db->query('SELECT payload FROM ad_browser_reports WHERE shop_id = :shop_id
      AND source_shop_id = :source_shop_id AND channel = :channel AND start_date = :start_date
      AND end_date = :end_date AND mapping_version = :mapping_version');
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('source_shop_id', (int)$shop['shop_id']);
    $this->db->bind('channel', $channel);
    $this->db->bind('start_date', $range['start_date']);
    $this->db->bind('end_date', $range['end_date']);
    $this->db->bind('mapping_version', AdsPerformance::MAPPING_VERSION);
    $row = $this->db->single();
    $report = $row ? json_decode($row['payload'], true) : null;
    return is_array($report) && !empty($report['available']) ? array_merge($report, $range) : null;
  }
}
