<?php

/**
 * Owns the lightweight scheduler state. It only enqueues work; API calls stay
 * inside the CLI worker so page requests never wait for Shopee.
 */
class BackgroundSync extends BaseModel {
  private $schemaReady = false;

  private $definitions = [
    'finance' => ['interval' => 600, 'full_interval' => 0, 'mode' => 'diff'],
    'orders' => ['interval' => 180, 'full_interval' => 43200, 'mode' => 'diff'],
    'chat' => ['interval' => 30, 'full_interval' => 0, 'mode' => 'diff'],
    'products' => ['interval' => 900, 'full_interval' => 86400, 'mode' => 'diff'],
    'promotions' => ['interval' => 600, 'full_interval' => 0, 'mode' => 'diff'],
    'ads' => ['interval' => 900, 'full_interval' => 0, 'mode' => 'diff'],
    'ads_topups' => ['interval' => 86400, 'full_interval' => 0, 'mode' => 'diff'],
    'performance' => ['interval' => 1800, 'full_interval' => 0, 'mode' => 'diff'],
    'customers' => ['interval' => 1800, 'full_interval' => 0, 'mode' => 'diff'],
    'shops' => ['interval' => 3600, 'full_interval' => 0, 'mode' => 'diff'],
    'packages' => ['interval' => 3600, 'full_interval' => 0, 'mode' => 'diff']
  ];

  public function ensureSchema() {
    if ($this->schemaReady) return;
    $this->db->query("CREATE TABLE IF NOT EXISTS sync_schedules (
      shop_id INT NOT NULL,
      sync_type VARCHAR(50) NOT NULL,
      interval_seconds INT NOT NULL DEFAULT 900,
      full_interval_seconds INT NOT NULL DEFAULT 0,
      mode VARCHAR(16) NOT NULL DEFAULT 'diff',
      enabled TINYINT(1) NOT NULL DEFAULT 1,
      next_run_at DATETIME NULL,
      last_enqueued_at DATETIME NULL,
      last_success_at DATETIME NULL,
      last_full_at DATETIME NULL,
      last_error TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id, sync_type),
      KEY sync_schedules_due (enabled, next_run_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
    $this->db->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sync_schedules' AND COLUMN_NAME = 'last_full_at'");
    if (empty($this->db->single()['c'])) {
      $this->db->query("ALTER TABLE sync_schedules ADD COLUMN last_full_at DATETIME NULL AFTER last_success_at");
      $this->db->exe();
    }
    $this->db->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sync_schedules' AND COLUMN_NAME = 'full_interval_seconds'");
    if (empty($this->db->single()['c'])) {
      $this->db->query("ALTER TABLE sync_schedules ADD COLUMN full_interval_seconds INT NOT NULL DEFAULT 0 AFTER interval_seconds");
      $this->db->exe();
    }
    $this->schemaReady = true;
  }

  public function ensureSchedules() {
    $this->ensureSchema();
    $this->db->query('SELECT id FROM shops ORDER BY id ASC');
    foreach ($this->db->getAll() as $shop) {
      foreach ($this->definitions as $type => $definition) {
      $this->db->query("INSERT INTO sync_schedules (shop_id, sync_type, interval_seconds, full_interval_seconds, mode, next_run_at) VALUES (:shop_id, :sync_type, :interval_seconds, :full_interval_seconds, :mode, NOW()) ON DUPLICATE KEY UPDATE full_interval_seconds = VALUES(full_interval_seconds)");
        $this->db->bind('shop_id', (int)$shop['id']);
        $this->db->bind('sync_type', $type);
        $this->db->bind('interval_seconds', (int)$definition['interval']);
        $this->db->bind('full_interval_seconds', (int)$definition['full_interval']);
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
      $mode = (string)$row['mode'];
      $fullDue = in_array($type, ['orders', 'products'], true)
        && (int)($row['full_interval_seconds'] ?? 0) > 0
        && (empty($row['last_full_at']) || strtotime((string)$row['last_full_at'] . ' UTC') <= time() - (int)$row['full_interval_seconds']);
      if ($fullDue && $type !== 'orders') $mode = 'full';
      $jobId = $type === 'orders'
        ? $sync->enqueue((int)$row['shop_id'], 'diff')
        : $sync->enqueueType((int)$row['shop_id'], $type, $mode);
      if ($type === 'orders' && ($fullDue || $mode === 'full')) $sync->enqueue((int)$row['shop_id'], 'full');
      if ($jobId > 0) {
        $this->db->query("UPDATE sync_schedules SET last_enqueued_at = NOW(), next_run_at = DATE_ADD(NOW(), INTERVAL interval_seconds SECOND) WHERE shop_id = :shop_id AND sync_type = :sync_type");
        $this->db->bind('shop_id', (int)$row['shop_id']);
        $this->db->bind('sync_type', $type);
        $this->db->exe();
        $enqueued[] = ['shop_id' => (int)$row['shop_id'], 'sync_type' => $type, 'mode' => $mode, 'job_id' => $jobId];
      }
    }
    return $enqueued;
  }

  public function requestShopHealth($minAgeSeconds = 300) {
    $this->ensureSchedules();
    $minAgeSeconds = max(60, min(86400, (int)$minAgeSeconds));
    $this->db->query("UPDATE sync_schedules SET next_run_at = NOW() WHERE sync_type = 'shops' AND enabled = 1 AND (last_enqueued_at IS NULL OR last_enqueued_at <= DATE_SUB(NOW(), INTERVAL {$minAgeSeconds} SECOND))");
    $this->db->exe();
  }

  public function markResult($shopId, $syncType, $ok, $error = null, $mode = 'diff') {
    $this->ensureSchema();
    require_once __DIR__ . '/../helpers/SyncOutcome.php';
    if (!$ok && !$error) $error = 'Sinkronisasi gagal; data sumber belum berhasil diperbarui.';
    $this->db->query("UPDATE sync_schedules SET last_success_at = CASE WHEN :ok = 1 THEN NOW() ELSE last_success_at END, last_full_at = CASE WHEN :ok2 = 1 AND :mode = 'full' THEN NOW() ELSE last_full_at END, last_error = CASE WHEN :ok3 = 1 THEN NULL ELSE :error END, next_run_at = CASE WHEN sync_type = 'orders' AND :result_mode = 'full' THEN next_run_at ELSE DATE_ADD(NOW(), INTERVAL GREATEST(interval_seconds, :retry_delay) SECOND) END WHERE shop_id = :shop_id AND sync_type = :sync_type");
    $this->db->bind('result_mode', (string)$mode);
    $this->db->bind('retry_delay', $ok ? 0 : SyncOutcome::retryDelay($error));
    $this->db->bind('ok', $ok ? 1 : 0);
    $this->db->bind('ok2', $ok ? 1 : 0);
    $this->db->bind('ok3', $ok ? 1 : 0);
    $this->db->bind('mode', (string)$mode);
    $this->db->bind('error', $error);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('sync_type', (string)$syncType);
    $this->db->exe();
  }

  public function status($shopId = 0) {
    $this->ensureSchedules();
    $where = '';
    if ((int)$shopId > 0) $where = ' WHERE s.shop_id = :shop_id';
    $this->db->query("SELECT s.*, sh.name AS shop_name, j.id AS job_id, j.status AS job_status, j.last_error AS job_error, j.started_at AS job_started_at, j.completed_at AS job_completed_at FROM sync_schedules s LEFT JOIN shops sh ON sh.id = s.shop_id LEFT JOIN sync_jobs j ON j.id = (SELECT MAX(j2.id) FROM sync_jobs j2 WHERE j2.shop_id = s.shop_id AND CAST(j2.sync_type AS BINARY) = CAST(s.sync_type AS BINARY)) {$where} ORDER BY s.shop_id, s.sync_type");
    if ($where) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }

  public function configure($shopId, $syncType, $intervalSeconds, $enabled = 1) {
    $this->ensureSchema();
    $allowed = ['orders', 'chat', 'products', 'promotions', 'ads', 'ads_topups', 'performance', 'customers', 'shops', 'packages', 'finance'];
    if (!in_array($syncType, $allowed, true)) return false;
    $intervalSeconds = max(30, min(86400, (int)$intervalSeconds));
    $this->db->query("UPDATE sync_schedules SET interval_seconds = :interval_seconds, enabled = :enabled, next_run_at = CASE WHEN :enabled2 = 1 THEN NOW() ELSE next_run_at END WHERE shop_id = :shop_id AND sync_type = :sync_type");
    $this->db->bind('interval_seconds', $intervalSeconds);
    $this->db->bind('enabled', $enabled ? 1 : 0);
    $this->db->bind('enabled2', $enabled ? 1 : 0);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('sync_type', $syncType);
    $this->db->exe();
    return true;
  }
}
