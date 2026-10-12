<?php

class PackageSynchronizer {
  private $db;
  private $source;

  public function __construct($db, $source) {
    $this->db = $db;
    $this->source = $source;
  }

  public function refresh($orderId, $cookie, $shopId = 0, $leaseOwner = null, $leaseJobId = 0) {
    $package = $this->source->getPackage($cookie, $orderId);
    if (!is_array($package) || !is_array($package['order_info']['package_list'] ?? null)) return false;
    if ($leaseOwner !== null) {
      $this->db->query("SELECT id FROM sync_jobs WHERE id = :job_id AND status = 'running' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW()) LIMIT 1");
      $this->db->bind('job_id', (int)$leaseJobId);
      $this->db->bind('lease_owner', (string)$leaseOwner);
      if (!$this->db->single()) return false;
    }
    $first = $package['order_info']['package_list'][0] ?? [];
    $scope = (int)$shopId > 0 ? ' AND shop_id = :shop_id' : '';
    $this->db->query("UPDATE orders SET shipping_cargo = COALESCE(NULLIF(:cargo, ''), shipping_cargo),
      tracking_number = COALESCE(NULLIF(:tracking, ''), tracking_number), package_synced_at = NOW() WHERE id = :id{$scope}");
    $this->db->bind('cargo', (string)($first['channel_id'] ?? ''));
    $this->db->bind('tracking', (string)($first['third_party_tn'] ?? ''));
    $this->db->bind('id', (int)$orderId);
    if ((int)$shopId > 0) $this->db->bind('shop_id', (int)$shopId);
    $this->db->exe();
    return $this->db->row() > 0;
  }

  public function run(array $job, array $shop, $limit = 5, $rateMs = 350) {
    $limit = max(1, min(5, (int)$limit));
    $owner = isset($job['lease_owner']) && $job['lease_owner'] !== '' ? (string)$job['lease_owner'] : null;
    $jobId = (int)($job['id'] ?? 0);
    $this->db->query("SELECT id FROM orders WHERE shop_id = :shop_id AND id > :cursor
      AND deleted_at IS NULL AND detail_synced_at IS NOT NULL
      AND (package_synced_at IS NULL OR (package_synced_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND (shipping_cargo IS NULL OR shipping_cargo = '' OR tracking_number IS NULL OR tracking_number = '')))
      ORDER BY id ASC LIMIT {$limit}");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('cursor', (int)($job['page_sentinel'] ?? 0));
    $rows = $this->db->getAll();
    foreach ($rows as $row) {
      if ($owner !== null) {
        $this->db->query("SELECT id FROM sync_jobs WHERE id = :job_id AND status = 'running' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW()) LIMIT 1");
        $this->db->bind('job_id', $jobId);
        $this->db->bind('lease_owner', $owner);
        if (!$this->db->single()) break;
      }
      try {
        // refresh() re-checks the same lease after the remote API call so a
        // stale worker cannot publish package data after fencing.
        $ok = $this->refresh((int)$row['id'], (string)$shop['cookie'], (int)$shop['id'], $owner, $jobId);
      } catch (Throwable $error) {
        $ok = false;
      }
      $this->db->begin();
      try {
        $ownerWhere = $owner !== null ? ' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW())' : '';
        $this->db->query('UPDATE sync_jobs SET page_sentinel = :cursor WHERE id = :id' . $ownerWhere);
        $this->db->bind('cursor', (string)$row['id']);
        $this->db->bind('id', (int)$job['id']);
        if ($owner !== null) $this->db->bind('lease_owner', $owner);
        $this->db->exe();
        $field = $ok ? 'detail_success' : 'detail_failed';
        $this->db->query("UPDATE sync_runs SET {$field} = {$field} + 1, api_requests = api_requests + 1, heartbeat_at = NOW() WHERE job_id = :id" . ($owner !== null ? ' AND EXISTS (SELECT 1 FROM sync_jobs sj WHERE sj.id = :job_id2 AND sj.lease_owner = :lease_owner2 AND sj.status = \'running\' AND (sj.lease_until IS NULL OR sj.lease_until >= NOW()))' : ''));
        $this->db->bind('id', (int)$job['id']);
        if ($owner !== null) {
          $this->db->bind('job_id2', (int)$job['id']);
          $this->db->bind('lease_owner2', $owner);
        }
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
