<?php

class SyncQueue {
  private $db;

  public function __construct($db) {
    $this->db = $db;
  }

  public function claimJob($jobId = null, $shopId = null) {
    $db = $this->db;
    $filter = ($jobId ? ' AND j.id = :job_id' : '') . ($shopId ? ' AND j.shop_id = :shop_id' : '');
    $db->begin();
    try {
      $db->query("SELECT j.* FROM sync_jobs j WHERE j.status IN ('queued','running')
        AND (j.next_retry_at IS NULL OR j.next_retry_at <= NOW())
        AND (j.lease_until IS NULL OR j.lease_until < NOW()) {$filter}
        AND (j.sync_type <> 'orders' OR j.page_number > 0
          OR NOT EXISTS (SELECT 1 FROM sync_job_orders t WHERE t.job_id = j.id AND t.status IN ('queued','retry','running'))
          OR EXISTS (SELECT 1 FROM sync_job_orders t WHERE t.job_id = j.id AND t.status IN ('queued','retry','running')
            AND (t.next_retry_at IS NULL OR t.next_retry_at <= NOW()) AND (t.lease_until IS NULL OR t.lease_until < NOW())))
        ORDER BY j.updated_at ASC, j.id ASC LIMIT 1 FOR UPDATE");
      if ($jobId) $db->bind('job_id', (int)$jobId);
      if ($shopId) $db->bind('shop_id', (int)$shopId);
      $job = $db->single();
      if ($job) {
        $db->query("UPDATE sync_jobs SET status = 'running', lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE), started_at = COALESCE(started_at, NOW()), attempts = attempts + 1 WHERE id = :id");
        $db->bind('id', (int)$job['id']);
        $db->exe();
        $db->query("UPDATE sync_runs SET status = 'running', started_at = COALESCE(started_at, NOW()) WHERE job_id = :id OR sync_job_id = :id2");
        $db->bind('id', (int)$job['id']);
        $db->bind('id2', (int)$job['id']);
        $db->exe();
        $job['status'] = 'running';
      }
      $db->commit();
      return $job ?: null;
    } catch (Throwable $error) {
      $db->rollback();
      throw $error;
    }
  }

  public function claimOrders($jobId, $limit = 10) {
    $db = $this->db;
    $limit = max(1, min(50, (int)$limit));
    $db->begin();
    try {
      $db->query("SELECT * FROM sync_job_orders WHERE job_id = :job_id AND status IN ('queued','retry','running')
        AND (next_retry_at IS NULL OR next_retry_at <= NOW()) AND (lease_until IS NULL OR lease_until < NOW())
        ORDER BY id ASC LIMIT {$limit} FOR UPDATE");
      $db->bind('job_id', (int)$jobId);
      $rows = $db->getAll();
      foreach ($rows as $row) {
        $db->query("UPDATE sync_job_orders SET status = 'running', attempts = attempts + 1, lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE id = :id");
        $db->bind('id', (int)$row['id']);
        $db->exe();
      }
      $db->commit();
      return $rows;
    } catch (Throwable $error) {
      $db->rollback();
      throw $error;
    }
  }

  public function heartbeat($jobId, array $taskIds) {
    $this->db->query("UPDATE sync_jobs SET lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE id = :id AND status = 'running'");
    $this->db->bind('id', (int)$jobId);
    $this->db->exe();
    if (!$taskIds) return;
    $ids = implode(',', array_map('intval', $taskIds));
    $this->db->query("UPDATE sync_job_orders SET lease_until = DATE_ADD(NOW(), INTERVAL 20 MINUTE) WHERE job_id = :id AND status = 'running' AND id IN ({$ids})");
    $this->db->bind('id', (int)$jobId);
    $this->db->exe();
  }
}
