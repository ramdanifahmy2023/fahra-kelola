<?php

class PackageSynchronizer {
  private $db;
  private $source;

  public function __construct($db, $source) {
    $this->db = $db;
    $this->source = $source;
  }

  public function refresh($orderId, $cookie) {
    $package = $this->source->getPackage($cookie, $orderId);
    if (!is_array($package) || !is_array($package['order_info']['package_list'] ?? null)) return false;
    $first = $package['order_info']['package_list'][0] ?? [];
    $this->db->query("UPDATE orders SET shipping_cargo = COALESCE(NULLIF(:cargo, ''), shipping_cargo),
      tracking_number = COALESCE(NULLIF(:tracking, ''), tracking_number), package_synced_at = NOW() WHERE id = :id");
    $this->db->bind('cargo', (string)($first['channel_id'] ?? ''));
    $this->db->bind('tracking', (string)($first['third_party_tn'] ?? ''));
    $this->db->bind('id', (int)$orderId);
    $this->db->exe();
    return true;
  }

  public function run(array $job, array $shop, $limit = 5, $rateMs = 350) {
    $limit = max(1, min(5, (int)$limit));
    $this->db->query("SELECT id FROM orders WHERE shop_id = :shop_id AND id > :cursor
      AND deleted_at IS NULL AND detail_synced_at IS NOT NULL
      AND (package_synced_at IS NULL OR (package_synced_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND (shipping_cargo IS NULL OR shipping_cargo = '' OR tracking_number IS NULL OR tracking_number = '')))
      ORDER BY id ASC LIMIT {$limit}");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('cursor', (int)($job['page_sentinel'] ?? 0));
    $rows = $this->db->getAll();
    foreach ($rows as $row) {
      try {
        $ok = $this->refresh((int)$row['id'], (string)$shop['cookie']);
      } catch (Throwable $error) {
        $ok = false;
      }
      $this->db->begin();
      try {
        $this->db->query('UPDATE sync_jobs SET page_sentinel = :cursor WHERE id = :id');
        $this->db->bind('cursor', (string)$row['id']);
        $this->db->bind('id', (int)$job['id']);
        $this->db->exe();
        $field = $ok ? 'detail_success' : 'detail_failed';
        $this->db->query("UPDATE sync_runs SET {$field} = {$field} + 1, api_requests = api_requests + 1, heartbeat_at = NOW() WHERE job_id = :id");
        $this->db->bind('id', (int)$job['id']);
        $this->db->exe();
        $this->db->commit();
      } catch (Throwable $error) {
        $this->db->rollback();
        throw $error;
      }
      if ($rateMs > 0) usleep($rateMs * 1000);
    }
    if (count($rows) === $limit) return [true, null, false];
    $this->db->query('SELECT detail_failed FROM sync_runs WHERE job_id = :id');
    $this->db->bind('id', (int)$job['id']);
    $failed = (int)($this->db->single()['detail_failed'] ?? 0);
    return [$failed === 0, $failed ? "{$failed} detail paket gagal diperbarui; akan dicoba pada jadwal berikutnya." : null, true];
  }
}
