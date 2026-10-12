<?php

class SyncQueue {
  private $db;

  public function __construct($db) {
    $this->db = $db;
  }

  private function hasColumn(string $table, string $column): bool {
    try {
      $this->db->query("SHOW COLUMNS FROM {$table} LIKE :column");
      $this->db->bind('column', $column);
      return (bool)$this->db->single();
    } catch (Throwable $error) {
      return false;
    }
  }

  public function claimJob($jobId = null, $shopId = null) {
    $db = $this->db;
    $filter = ($jobId ? ' AND j.id = :job_id' : '') . ($shopId ? ' AND j.shop_id = :shop_id' : '');
    $db->begin();
    try {
      if ($this->hasColumn('sync_jobs', 'max_attempts')) {
        $db->query("UPDATE sync_jobs SET status = 'failed', next_retry_at = NULL, lease_until = NULL, permanent_at = NOW(), completed_at = COALESCE(completed_at, NOW()), last_error = COALESCE(last_error, 'Batas percobaan sinkronisasi tercapai.') WHERE status IN ('queued','running') AND attempts >= GREATEST(1, max_attempts) AND attempts < 1000000 AND (lease_until IS NULL OR lease_until < NOW())");
        $db->exe();
        $db->query("UPDATE sync_runs r JOIN sync_jobs j ON (r.job_id = j.id OR r.sync_job_id = j.id) SET r.status = 'failed', r.last_error = COALESCE(j.last_error, 'Batas percobaan sinkronisasi tercapai.'), r.completed_at = COALESCE(r.completed_at, NOW()) WHERE j.status = 'failed' AND j.permanent_at IS NOT NULL AND j.attempts >= GREATEST(1, j.max_attempts) AND j.attempts < 1000000 AND r.status IN ('queued','running','retry')");
        $db->exe();
      }
      $db->query("SELECT j.* FROM sync_jobs j WHERE j.status IN ('queued','running')
        AND (j.next_retry_at IS NULL OR j.next_retry_at <= NOW())
        AND (j.lease_until IS NULL OR j.lease_until < NOW()) {$filter}
        AND (j.sync_type <> 'orders' OR j.page_number > 0
          OR NOT EXISTS (SELECT 1 FROM sync_job_orders t WHERE t.job_id = j.id AND t.status IN ('queued','retry','running'))
          OR EXISTS (SELECT 1 FROM sync_job_orders t WHERE t.job_id = j.id AND t.status IN ('queued','retry','running')
            AND (t.next_retry_at IS NULL OR t.next_retry_at <= NOW()) AND (t.lease_until IS NULL OR t.lease_until < NOW())))
        ORDER BY CASE WHEN j.updated_at <= DATE_SUB(NOW(), INTERVAL 120 SECOND) THEN 0
          WHEN j.sync_type = 'chat' OR (j.sync_type = 'orders' AND j.mode = 'diff') THEN 1
          WHEN j.sync_type = 'orders' AND j.mode = 'full' THEN 3 ELSE 2 END,
          j.updated_at ASC, j.id ASC LIMIT 1 FOR UPDATE");
      if ($jobId) $db->bind('job_id', (int)$jobId);
      if ($shopId) $db->bind('shop_id', (int)$shopId);
      $job = $db->single();
      if ($job) {
        $owner = bin2hex(random_bytes(24));
        $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
        $db->query("UPDATE sync_jobs SET status = 'running', lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE)" . ($ownerColumn ? ", lease_owner = :owner" : '') . ", started_at = COALESCE(started_at, NOW()), attempts = attempts + 1 WHERE id = :id");
        if ($ownerColumn) $db->bind('owner', $owner);
        $db->bind('id', (int)$job['id']);
        $db->exe();
        $db->query("UPDATE sync_runs SET status = 'running', started_at = COALESCE(started_at, NOW()) WHERE job_id = :id OR sync_job_id = :id2");
        $db->bind('id', (int)$job['id']);
        $db->bind('id2', (int)$job['id']);
        $db->exe();
        $job['status'] = 'running';
        if ($ownerColumn) $job['lease_owner'] = $owner;
      }
      $db->commit();
      return $job ?: null;
    } catch (Throwable $error) {
      $db->rollback();
      throw $error;
    }
  }

  public function claimOrders($jobId, $limit = 10, $leaseOwner = null) {
    $db = $this->db;
    $limit = max(1, min(50, (int)$limit));
    $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
    $parentOwnerFilter = $leaseOwner !== null && $ownerColumn ? ' AND j.lease_owner = :parent_lease_owner' : '';
    $db->begin();
    try {
      if ($this->hasColumn('sync_job_orders', 'max_attempts')) {
        $parentSweep = " AND EXISTS (SELECT 1 FROM sync_jobs sweep_job WHERE sweep_job.id = sync_job_orders.job_id AND sweep_job.status = 'running' AND (sweep_job.lease_until IS NULL OR sweep_job.lease_until >= NOW())";
        if ($leaseOwner !== null && $ownerColumn) $parentSweep .= " AND sweep_job.lease_owner = :sweep_parent_owner";
        $parentSweep .= ')';
        $db->query("UPDATE sync_job_orders SET status = 'failed', next_retry_at = NULL, lease_until = NULL, permanent_at = NOW(), completed_at = COALESCE(completed_at, NOW()), last_error = COALESCE(last_error, 'Batas percobaan detail tercapai.') WHERE job_id = :job_id AND status IN ('queued','retry','running') AND attempts >= GREATEST(1, max_attempts) AND attempts < 1000000 AND (lease_until IS NULL OR lease_until < NOW()){$parentSweep}");
        $db->bind('job_id', (int)$jobId);
        if ($leaseOwner !== null && $ownerColumn) $db->bind('sweep_parent_owner', (string)$leaseOwner);
        $db->exe();
      }
      $db->query("SELECT t.* FROM sync_job_orders t JOIN sync_jobs j ON j.id = t.job_id AND j.status = 'running'{$parentOwnerFilter} WHERE t.job_id = :job_id AND t.status IN ('queued','retry','running')
        AND (t.next_retry_at IS NULL OR t.next_retry_at <= NOW()) AND (t.lease_until IS NULL OR t.lease_until < NOW())
        AND (j.lease_until IS NULL OR j.lease_until >= NOW())
        ORDER BY t.id ASC LIMIT {$limit} FOR UPDATE");
      $db->bind('job_id', (int)$jobId);
      if ($parentOwnerFilter) $db->bind('parent_lease_owner', (string)$leaseOwner);
      $rows = $db->getAll();
      $claimed = [];
      foreach ($rows as $row) {
        $taskOwnerColumn = $this->hasColumn('sync_job_orders', 'lease_owner');
        $guard = $leaseOwner !== null && $ownerColumn ? " AND EXISTS (SELECT 1 FROM sync_jobs parent_job WHERE parent_job.id = sync_job_orders.job_id AND parent_job.status = 'running' AND parent_job.lease_owner = :parent_lease_owner2 AND (parent_job.lease_until IS NULL OR parent_job.lease_until >= NOW()))" : '';
        $db->query("UPDATE sync_job_orders SET status = 'running', attempts = attempts + 1, lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE)" . ($taskOwnerColumn ? ", lease_owner = (SELECT lease_owner FROM sync_jobs WHERE id = :job_id)" : '') . " WHERE id = :id{$guard}");
        if ($taskOwnerColumn) $db->bind('job_id', (int)$jobId);
        if ($guard) $db->bind('parent_lease_owner2', (string)$leaseOwner);
        $db->bind('id', (int)$row['id']);
        $db->exe();
        if ($db->row() > 0) $claimed[] = $row;
      }
      $db->commit();
      return $claimed;
    } catch (Throwable $error) {
      $db->rollback();
      throw $error;
    }
  }

  public function heartbeat($jobId, array $taskIds, $leaseOwner = null) {
    $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
    $taskOwnerColumn = $this->hasColumn('sync_job_orders', 'lease_owner');
    if ($leaseOwner === null && $ownerColumn) {
      $this->db->query("SELECT lease_owner FROM sync_jobs WHERE id = :id LIMIT 1");
      $this->db->bind('id', (int)$jobId);
      $leaseOwner = $this->db->single()['lease_owner'] ?? null;
    }
    $ownerWhere = $leaseOwner !== null && $ownerColumn ? ' AND lease_owner = :lease_owner' : '';
    $this->db->query("UPDATE sync_jobs SET lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE id = :id AND status = 'running' AND (lease_until IS NULL OR lease_until >= NOW()){$ownerWhere}");
    $this->db->bind('id', (int)$jobId);
    if ($ownerWhere) $this->db->bind('lease_owner', (string)$leaseOwner);
    $this->db->exe();
    $this->db->query("UPDATE sync_runs r JOIN sync_jobs j ON j.sync_run_id = r.id SET r.heartbeat_at = NOW() WHERE j.id = :job_id AND j.status = 'running'" . ($ownerWhere ? ' AND j.lease_owner = :lease_owner3' : ''));
    $this->db->bind('job_id', (int)$jobId);
    if ($ownerWhere) $this->db->bind('lease_owner3', (string)$leaseOwner);
    $this->db->exe();
    if (!$taskIds) return;
    $ids = implode(',', array_map('intval', $taskIds));
    $taskOwnerWhere = $leaseOwner !== null && $taskOwnerColumn ? ' AND lease_owner = :lease_owner2' : '';
    $parentGuard = $leaseOwner !== null && $ownerColumn ? " AND EXISTS (SELECT 1 FROM sync_jobs parent_job WHERE parent_job.id = sync_job_orders.job_id AND parent_job.status = 'running' AND parent_job.lease_owner = :parent_lease_owner AND (parent_job.lease_until IS NULL OR parent_job.lease_until >= NOW()))" : '';
    $this->db->query("UPDATE sync_job_orders SET lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE job_id = :id AND status = 'running' AND (lease_until IS NULL OR lease_until >= NOW()) AND id IN ({$ids}){$taskOwnerWhere}{$parentGuard}");
    $this->db->bind('id', (int)$jobId);
    if ($taskOwnerWhere) $this->db->bind('lease_owner2', (string)$leaseOwner);
    if ($parentGuard) $this->db->bind('parent_lease_owner', (string)$leaseOwner);
    $this->db->exe();
  }
}
