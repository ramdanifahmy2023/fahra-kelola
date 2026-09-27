<?php

/**
 * Owns the lightweight scheduler state. It only enqueues work; API calls stay
 * inside the CLI worker so page requests never wait for Shopee.
 */
class BackgroundSync extends BaseModel {
  private $schemaReady = false;

  private $definitions = [
    'orders' => ['interval' => 180, 'mode' => 'diff'],
    'chat' => ['interval' => 30, 'mode' => 'diff'],
    'products' => ['interval' => 900, 'mode' => 'diff'],
    'promotions' => ['interval' => 600, 'mode' => 'diff'],
    'ads' => ['interval' => 900, 'mode' => 'diff'],
    'customers' => ['interval' => 1800, 'mode' => 'diff'],
    'shops' => ['interval' => 3600, 'mode' => 'diff'],
    'packages' => ['interval' => 3600, 'mode' => 'diff']
  ];

  public function ensureSchema() {
    if ($this->schemaReady) return;
    $this->db->query("CREATE TABLE IF NOT EXISTS sync_schedules (
      shop_id INT NOT NULL,
      sync_type VARCHAR(50) NOT NULL,
      interval_seconds INT NOT NULL DEFAULT 900,
      mode VARCHAR(16) NOT NULL DEFAULT 'diff',
      enabled TINYINT(1) NOT NULL DEFAULT 1,
      next_run_at DATETIME NULL,
      last_enqueued_at DATETIME NULL,
      last_success_at DATETIME NULL,
      last_error TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id, sync_type),
      KEY sync_schedules_due (enabled, next_run_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
    $this->schemaReady = true;
  }

  public function ensureSchedules() {
    $this->ensureSchema();
    $this->db->query('SELECT id FROM shops ORDER BY id ASC');
    foreach ($this->db->getAll() as $shop) {
      foreach ($this->definitions as $type => $definition) {
        $this->db->query("INSERT INTO sync_schedules (shop_id, sync_type, interval_seconds, mode, next_run_at) VALUES (:shop_id, :sync_type, :interval_seconds, :mode, NOW()) ON DUPLICATE KEY UPDATE interval_seconds = VALUES(interval_seconds), mode = VALUES(mode)");
        $this->db->bind('shop_id', (int)$shop['id']);
        $this->db->bind('sync_type', $type);
        $this->db->bind('interval_seconds', (int)$definition['interval']);
        $this->db->bind('mode', $definition['mode']);
        $this->db->exe();
      }
    }
  }

  public function enqueueDue($limit = 50) {
    $this->ensureSchedules();
    $limit = max(1, min(200, (int)$limit));
    $this->db->query("SELECT * FROM sync_schedules WHERE enabled = 1 AND (next_run_at IS NULL OR next_run_at <= NOW()) ORDER BY COALESCE(next_run_at, NOW()), shop_id, sync_type LIMIT {$limit}");
    $due = $this->db->getAll();
    $sync = new SyncJob();
    $enqueued = [];
    foreach ($due as $row) {
      $type = (string)$row['sync_type'];
      $jobId = $type === 'orders'
        ? $sync->enqueue((int)$row['shop_id'], (string)$row['mode'])
        : $sync->enqueueType((int)$row['shop_id'], $type, (string)$row['mode']);
      if ($jobId > 0) {
        $this->db->query("UPDATE sync_schedules SET last_enqueued_at = NOW(), next_run_at = DATE_ADD(NOW(), INTERVAL interval_seconds SECOND), last_error = NULL WHERE shop_id = :shop_id AND sync_type = :sync_type");
        $this->db->bind('shop_id', (int)$row['shop_id']);
        $this->db->bind('sync_type', $type);
        $this->db->exe();
        $enqueued[] = ['shop_id' => (int)$row['shop_id'], 'sync_type' => $type, 'job_id' => $jobId];
      }
    }
    return $enqueued;
  }

  public function markResult($shopId, $syncType, $ok, $error = null) {
    $this->ensureSchema();
    $this->db->query("UPDATE sync_schedules SET last_success_at = CASE WHEN :ok = 1 THEN NOW() ELSE last_success_at END, last_error = CASE WHEN :ok2 = 1 THEN NULL ELSE :error END WHERE shop_id = :shop_id AND sync_type = :sync_type");
    $this->db->bind('ok', $ok ? 1 : 0);
    $this->db->bind('ok2', $ok ? 1 : 0);
    $this->db->bind('error', $error);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('sync_type', (string)$syncType);
    $this->db->exe();
  }

  public function status($shopId = 0) {
    $this->ensureSchedules();
    $where = '';
    if ((int)$shopId > 0) $where = ' WHERE s.shop_id = :shop_id';
    $this->db->query("SELECT s.*, j.id AS job_id, j.status AS job_status, j.last_error AS job_error, j.started_at AS job_started_at, j.completed_at AS job_completed_at FROM sync_schedules s LEFT JOIN sync_jobs j ON j.id = (SELECT MAX(j2.id) FROM sync_jobs j2 WHERE j2.shop_id = s.shop_id AND CAST(j2.sync_type AS BINARY) = CAST(s.sync_type AS BINARY)) {$where} ORDER BY s.shop_id, s.sync_type");
    if ($where) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }
}
