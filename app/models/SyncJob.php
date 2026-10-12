<?php

class SyncJob extends BaseModel {
  protected $table = 'sync_jobs';

  private $schemaReady = false;
  private $columnCache = [];

  private function hasColumn(string $table, string $column): bool {
    $key = $table . '.' . $column;
    if (array_key_exists($key, $this->columnCache)) return $this->columnCache[$key];
    try {
      $this->db->query("SHOW COLUMNS FROM {$table} LIKE :column");
      $this->db->bind('column', $column);
      $this->columnCache[$key] = (bool)$this->db->single();
    } catch (Throwable $error) {
      $this->columnCache[$key] = false;
    }
    return $this->columnCache[$key];
  }

  public function ensureSchema() {
    if ($this->schemaReady) {
      return;
    }
    $tables = [
      "CREATE TABLE IF NOT EXISTS sync_jobs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        shop_id INT NOT NULL,
        channel_id SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        sync_type VARCHAR(50) NOT NULL DEFAULT 'orders',
        idempotency_key VARCHAR(190) NOT NULL,
        sync_run_id BIGINT UNSIGNED NULL,
        mode VARCHAR(16) NOT NULL DEFAULT 'diff',
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        page_sentinel TEXT NULL,
        page_number INT NOT NULL DEFAULT 1,
        total_indexed INT NOT NULL DEFAULT 0,
        total_detail INT NOT NULL DEFAULT 0,
        processed_detail INT NOT NULL DEFAULT 0,
        failed_detail INT NOT NULL DEFAULT 0,
        attempts INT NOT NULL DEFAULT 0,
        max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8,
        next_retry_at DATETIME NULL,
        lease_until DATETIME NULL,
        lease_owner CHAR(64) NULL,
        last_error TEXT NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        permanent_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY sync_jobs_idempotency (shop_id, sync_type, idempotency_key),
        KEY sync_jobs_shop_status (shop_id, status),
        KEY sync_jobs_retry (status, next_retry_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS sync_runs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sync_job_id BIGINT UNSIGNED NOT NULL,
        shop_id INT NOT NULL,
        channel_id SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        sync_type VARCHAR(50) NOT NULL DEFAULT 'orders',
        job_id BIGINT UNSIGNED NOT NULL,
        phase VARCHAR(24) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'running',
        fetched_pages INT NOT NULL DEFAULT 0,
        indexed_orders INT NOT NULL DEFAULT 0,
        detail_success INT NOT NULL DEFAULT 0,
        detail_failed INT NOT NULL DEFAULT 0,
        api_requests INT NOT NULL DEFAULT 0,
        heartbeat_at DATETIME NULL,
        last_error TEXT NULL,
        started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        PRIMARY KEY (id),
        KEY sync_runs_job (job_id, status)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS sync_pages (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sync_run_id BIGINT UNSIGNED NOT NULL,
        job_id BIGINT UNSIGNED NOT NULL,
        page_number INT NOT NULL,
        `cursor` TEXT NULL,
        sentinel TEXT NULL,
        next_sentinel TEXT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        attempts INT NOT NULL DEFAULT 0,
        orders_indexed INT NOT NULL DEFAULT 0,
        next_retry_at DATETIME NULL,
        lease_until DATETIME NULL,
        last_error TEXT NULL,
        started_at DATETIME NULL,
        completed_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY sync_pages_job_page (job_id, page_number),
        KEY sync_pages_claim (status, next_retry_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
      "CREATE TABLE IF NOT EXISTS sync_job_orders (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        job_id BIGINT UNSIGNED NOT NULL,
        order_id BIGINT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        attempts INT NOT NULL DEFAULT 0,
        max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8,
        next_retry_at DATETIME NULL,
        lease_until DATETIME NULL,
        lease_owner CHAR(64) NULL,
        last_error TEXT NULL,
        completed_at DATETIME NULL,
        permanent_at DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY sync_job_orders_unique (job_id, order_id),
        KEY sync_job_orders_claim (job_id, status, next_retry_at)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    ];
    foreach ($tables as $sql) {
      $this->db->query($sql);
      $this->db->exe();
    }
    $tableColumns = [
      'sync_jobs' => [
        'channel_id' => "SMALLINT UNSIGNED NOT NULL DEFAULT 1",
        'sync_type' => "VARCHAR(50) NOT NULL DEFAULT 'orders'",
        'idempotency_key' => "VARCHAR(190) NOT NULL DEFAULT ''",
        'sync_run_id' => "BIGINT UNSIGNED NULL",
        'mode' => "VARCHAR(16) NOT NULL DEFAULT 'diff'",
        'page_sentinel' => "TEXT NULL",
        'page_number' => "INT NOT NULL DEFAULT 1",
        'total_indexed' => "INT NOT NULL DEFAULT 0",
        'total_detail' => "INT NOT NULL DEFAULT 0",
        'processed_detail' => "INT NOT NULL DEFAULT 0",
        'failed_detail' => "INT NOT NULL DEFAULT 0",
        'attempts' => "INT NOT NULL DEFAULT 0",
        'max_attempts' => "SMALLINT UNSIGNED NOT NULL DEFAULT 8",
        'lease_until' => "DATETIME NULL",
        'lease_owner' => "CHAR(64) NULL",
        'last_error' => "TEXT NULL",
        'completed_at' => "DATETIME NULL",
        'permanent_at' => "DATETIME NULL"
      ],
      'sync_runs' => [
        'sync_job_id' => "BIGINT UNSIGNED NULL",
        'shop_id' => "INT NULL",
        'channel_id' => "SMALLINT UNSIGNED NULL",
        'sync_type' => "VARCHAR(50) NULL",
        'job_id' => "BIGINT UNSIGNED NULL",
        'phase' => "VARCHAR(24) NULL",
        'fetched_pages' => "INT NOT NULL DEFAULT 0",
        'indexed_orders' => "INT NOT NULL DEFAULT 0",
        'detail_success' => "INT NOT NULL DEFAULT 0",
        'detail_failed' => "INT NOT NULL DEFAULT 0",
        'api_requests' => "INT NOT NULL DEFAULT 0",
        'heartbeat_at' => "DATETIME NULL",
        'last_error' => "TEXT NULL",
        'completed_at' => "DATETIME NULL"
      ],
      'sync_pages' => [
        'sync_run_id' => "BIGINT UNSIGNED NULL",
        'job_id' => "BIGINT UNSIGNED NULL",
        'sentinel' => "TEXT NULL",
        'next_sentinel' => "TEXT NULL",
        'attempts' => "INT NOT NULL DEFAULT 0",
        'orders_indexed' => "INT NOT NULL DEFAULT 0",
        'next_retry_at' => "DATETIME NULL",
        'lease_until' => "DATETIME NULL",
        'last_error' => "TEXT NULL",
        'completed_at' => "DATETIME NULL",
        'cursor' => "TEXT NULL"
      ],
      'sync_job_orders' => [
        'max_attempts' => "SMALLINT UNSIGNED NOT NULL DEFAULT 8",
        'lease_owner' => "CHAR(64) NULL",
        'permanent_at' => "DATETIME NULL"
      ]
    ];
    foreach ($tableColumns as $table => $columns) {
      foreach ($columns as $column => $definition) {
        $this->db->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column");
        $this->db->bind('table_name', $table);
        $this->db->bind('column', $column);
        $exists = $this->db->single();
        if (empty($exists['c'])) {
          $this->db->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
          $this->db->exe();
        }
      }
    }
    $columns = [
      'sync_status' => "VARCHAR(24) NULL",
      'sync_attempts' => "INT NOT NULL DEFAULT 0",
      'sync_next_retry_at' => "DATETIME NULL",
      'sync_last_error' => "TEXT NULL",
      'detail_synced_at' => "DATETIME NULL",
      'package_synced_at' => "DATETIME NULL",
      'income_synced_at' => "DATETIME NULL"
    ];
    foreach ($columns as $column => $definition) {
      $this->db->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = :column");
      $this->db->bind('column', $column);
      $exists = $this->db->single();
      if (empty($exists['c'])) {
        $this->db->query("ALTER TABLE orders ADD COLUMN {$column} {$definition}");
        $this->db->exe();
      }
    }
    $this->columnCache = [];
    $this->schemaReady = true;
  }

  public function enqueue($shopId, $mode = 'diff') {
    $this->ensureSchema();
    $shopId = (int)$shopId;
    $mode = $mode === 'full' ? 'full' : 'diff';
    $lock = 'shopdash:sync:enqueue:orders:' . $shopId . ':' . $mode;
    $this->db->query('SELECT GET_LOCK(:name, 5) AS acquired');
    $this->db->bind('name', $lock);
    if (empty($this->db->single()['acquired'])) return 0;
    try {
      $this->db->begin();
      try {
        $this->db->query("SELECT id, status FROM sync_jobs WHERE shop_id = :shop_id AND sync_type = 'orders' AND mode = :mode AND status IN ('queued','running') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('mode', $mode);
        $existing = $this->db->single();
        if ($existing) {
          $this->db->commit();
          return (int)$existing['id'];
        }
        $this->db->query("SELECT channel_id FROM shops WHERE id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', $shopId);
        $shopRow = $this->db->single();
        $channelId = (int)($shopRow['channel_id'] ?? 1);
        $idempotency = 'order-detail:' . $shopId . ':' . $mode . ':' . bin2hex(random_bytes(8));
        $this->db->query("INSERT INTO sync_jobs (shop_id, channel_id, sync_type, idempotency_key, mode, status) VALUES (:shop_id, :channel_id, 'orders', :idempotency_key, :mode, 'queued')");
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('channel_id', $channelId);
        $this->db->bind('idempotency_key', $idempotency);
        $this->db->bind('mode', $mode);
        $this->db->exe();
        $jobId = (int)$this->db->lastId();
        $this->db->query("INSERT INTO sync_runs (sync_job_id, shop_id, channel_id, sync_type, job_id, phase, status, started_at) VALUES (:sync_job_id, :shop_id, :channel_id, 'orders', :job_id, 'index', 'queued', NOW())");
        $this->db->bind('sync_job_id', $jobId);
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('channel_id', $channelId);
        $this->db->bind('job_id', $jobId);
        $this->db->exe();
        $runId = (int)$this->db->lastId();
        $this->db->query("UPDATE sync_jobs SET sync_run_id = :run_id WHERE id = :job_id");
        $this->db->bind('run_id', $runId);
        $this->db->bind('job_id', $jobId);
        $this->db->exe();
        $this->db->query("INSERT INTO sync_pages (sync_run_id, job_id, page_number, `cursor`, status, started_at) VALUES (:sync_run_id, :job_id, 1, NULL, 'queued', NOW())");
        $this->db->bind('sync_run_id', $runId);
        $this->db->bind('job_id', $jobId);
        $this->db->exe();
        $this->db->commit();
        return $jobId;
      } catch (Throwable $error) {
        $this->db->rollback();
        throw $error;
      }
    } finally {
      $this->db->query('SELECT RELEASE_LOCK(:name)');
      $this->db->bind('name', $lock);
      $this->db->single();
    }
  }

  /**
   * Enqueue a non-order sync unit. One active job is allowed per shop/type;
   * the stable idempotency key prevents duplicate scheduler ticks.
   */
  public function enqueueType($shopId, $syncType, $mode = 'diff') {
    if (strtolower(trim((string)$syncType)) !== 'chat') return $this->enqueueTypeUnit($shopId, $syncType, $mode);
    $lock = 'shopdash:chat:enqueue:' . (int)$shopId;
    $this->db->query('SELECT GET_LOCK(:name, 5) AS acquired');
    $this->db->bind('name', $lock);
    if (empty($this->db->single()['acquired'])) return 0;
    try { return $this->enqueueTypeUnit($shopId, $syncType, $mode); }
    finally {
      $this->db->query('SELECT RELEASE_LOCK(:name)');
      $this->db->bind('name', $lock); $this->db->single();
    }
  }

  private function enqueueTypeUnit($shopId, $syncType, $mode) {
    $this->ensureSchema();
    $shopId = (int)$shopId;
    $syncType = strtolower(trim((string)$syncType));
    $allowed = ['products', 'ads', 'ads_topups', 'promotions', 'performance', 'chat', 'customers', 'shops', 'packages', 'finance'];
    if ($shopId < 1 || !in_array($syncType, $allowed, true)) return 0;
    $mode = $mode === 'full' ? 'full' : 'diff';
    $lock = 'shopdash:sync:enqueue:' . $syncType . ':' . $shopId . ':' . $mode;
    $this->db->query('SELECT GET_LOCK(:name, 5) AS acquired');
    $this->db->bind('name', $lock);
    if (empty($this->db->single()['acquired'])) return 0;
    try {
      $this->db->begin();
      try {
        $this->db->query("SELECT id FROM sync_jobs WHERE shop_id = :shop_id AND sync_type = :sync_type AND status IN ('queued','running') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('sync_type', $syncType);
        $existing = $this->db->single();
        if ($existing) {
          $this->db->commit();
          return (int)$existing['id'];
        }

        $this->db->query("SELECT channel_id FROM shops WHERE id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', $shopId);
        $shop = $this->db->single();
        if (!$shop) {
          $this->db->commit();
          return 0;
        }
        $channelId = (int)($shop['channel_id'] ?? 1);
        $bucket = (int)floor(time() / 60);
        $idempotency = $syncType . ':' . $shopId . ':' . $mode . ':' . $bucket;
        if ($syncType === 'chat') $idempotency .= ':' . bin2hex(random_bytes(8));
        $this->db->query("INSERT INTO sync_jobs (shop_id, channel_id, sync_type, idempotency_key, mode, status) VALUES (:shop_id, :channel_id, :sync_type, :idempotency_key, :mode, 'queued') ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)");
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('channel_id', $channelId);
        $this->db->bind('sync_type', $syncType);
        $this->db->bind('idempotency_key', $idempotency);
        $this->db->bind('mode', $mode);
        $this->db->exe();
        $jobId = (int)$this->db->lastId();
        if ($jobId < 1) {
          $this->db->commit();
          return 0;
        }
        $this->db->query("INSERT INTO sync_runs (sync_job_id, shop_id, channel_id, sync_type, job_id, phase, status, started_at) VALUES (:sync_job_id, :shop_id, :channel_id, :sync_type, :job_id, 'sync', 'queued', NOW())");
        $this->db->bind('sync_job_id', $jobId);
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('channel_id', $channelId);
        $this->db->bind('sync_type', $syncType);
        $this->db->bind('job_id', $jobId);
        $this->db->exe();
        $runId = (int)$this->db->lastId();
        $this->db->query("UPDATE sync_jobs SET sync_run_id = :run_id WHERE id = :job_id");
        $this->db->bind('run_id', $runId);
        $this->db->bind('job_id', $jobId);
        $this->db->exe();
        $this->db->commit();
        return $jobId;
      } catch (Throwable $error) {
        $this->db->rollback();
        throw $error;
      }
    } finally {
      $this->db->query('SELECT RELEASE_LOCK(:name)');
      $this->db->bind('name', $lock);
      $this->db->single();
    }
  }

  public function statusByType($shopId, $syncType = null) {
    $this->ensureSchema();
    $where = 'j.shop_id = :shop_id';
    if ($syncType) $where .= ' AND j.sync_type = :sync_type';
    $this->db->query("SELECT j.* FROM sync_jobs j WHERE {$where} ORDER BY j.id DESC LIMIT 1");
    $this->db->bind('shop_id', (int)$shopId);
    if ($syncType) $this->db->bind('sync_type', (string)$syncType);
    return $this->db->single() ?: null;
  }

  public function status($shopId, $jobId = null) {
    $this->ensureSchema();
    $where = $jobId ? 'j.id = :job_id' : 'j.shop_id = :shop_id';
    $this->db->query("SELECT j.*, COALESCE(SUM(CASE WHEN o.status = 'done' THEN 1 ELSE 0 END), 0) AS detail_done, COALESCE(SUM(CASE WHEN o.status = 'failed' THEN 1 ELSE 0 END), 0) AS detail_failed, COUNT(o.id) AS detail_total FROM sync_jobs j LEFT JOIN sync_job_orders o ON o.job_id = j.id WHERE {$where} GROUP BY j.id ORDER BY j.id DESC LIMIT 1");
    if ($jobId) {
      $this->db->bind('job_id', (int)$jobId);
    } else {
      $this->db->bind('shop_id', (int)$shopId);
    }
    $row = $this->db->single();
    if (!$row) {
      return null;
    }
    $row['detail_done'] = (int)$row['detail_done'];
    $row['detail_failed'] = (int)$row['detail_failed'];
    $row['detail_total'] = (int)$row['detail_total'];
    return $row;
  }

  public function claimJob($jobId = null, $shopId = null) {
    $this->ensureSchema();
    require_once __DIR__ . '/../helpers/SyncQueue.php';
    return (new SyncQueue($this->db))->claimJob($jobId, $shopId);
  }

  public function ownsLease($jobId, $leaseOwner = null): bool {
    if ($leaseOwner === null || !$this->hasColumn('sync_jobs', 'lease_owner')) return true;
    $this->db->query("SELECT id FROM sync_jobs WHERE id = :job_id AND status = 'running' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW()) LIMIT 1");
    $this->db->bind('job_id', (int)$jobId);
    $this->db->bind('lease_owner', (string)$leaseOwner);
    return (bool)$this->db->single();
  }

  /** Reset the retry budget after a worker has made durable forward progress. */
  public function noteProgress($jobId, $leaseOwner = null): bool {
    $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
    $ownerFilter = $leaseOwner !== null && $ownerColumn ? ' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW())' : '';
    $this->db->query("UPDATE sync_jobs SET attempts = 0, next_retry_at = NULL, last_error = NULL WHERE id = :job_id AND status = 'running'{$ownerFilter}");
    $this->db->bind('job_id', (int)$jobId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    $this->db->exe();
    return !$ownerFilter || $this->db->row() > 0;
  }

  public function releaseJob($jobId, $status = 'completed', $error = null, $leaseOwner = null) {
    $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
    $permanentColumn = $this->hasColumn('sync_jobs', 'permanent_at');
    $maxAttemptsColumn = $this->hasColumn('sync_jobs', 'max_attempts');
    $ownerFilter = $leaseOwner !== null && $ownerColumn ? ' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW())' : '';
    $this->db->query('SELECT attempts' . ($maxAttemptsColumn ? ', max_attempts' : '') . ' FROM sync_jobs WHERE id = :job_id' . $ownerFilter . ' LIMIT 1');
    $this->db->bind('job_id', (int)$jobId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    $row = $this->db->single();
    if (!$row) return false;
    $attempts = (int)($row['attempts'] ?? 0);
    // Keep the legacy overflow guard: old rows with a corrupted, enormous
    // attempt counter continue with the capped delay and can be inspected.
    $maxAttempts = max(1, min(1000, (int)($row['max_attempts'] ?? 8)));
    $exhausted = $status === 'queued' && $attempts >= $maxAttempts && $attempts < 1000000;
    $effective = $exhausted ? 'failed' : $status;
    $set = "status = :status, lease_until = NULL" . ($ownerColumn ? ', lease_owner = NULL' : '') . ", attempts = CASE WHEN :reset_attempts = 1 THEN 0 ELSE attempts END, last_error = :error, next_retry_at = CASE WHEN :status3 = 'queued' THEN DATE_ADD(NOW(), INTERVAL LEAST(60, POW(2, LEAST(attempts, 6))) MINUTE) ELSE NULL END";
    if ($permanentColumn) $set .= ", permanent_at = CASE WHEN :status4 = 'failed' AND :exhausted = 1 THEN NOW() ELSE permanent_at END";
    $set .= ", completed_at = CASE WHEN :status2 IN ('completed','failed') THEN NOW() ELSE completed_at END";
    $this->db->query("UPDATE sync_jobs SET {$set} WHERE id = :job_id{$ownerFilter}");
    $this->db->bind('status', $effective);
    $this->db->bind('status2', $effective);
    $this->db->bind('status3', $effective);
    $this->db->bind('reset_attempts', $status === 'running' && $error === null ? 1 : 0);
    if ($permanentColumn) {
      $this->db->bind('status4', $effective);
      $this->db->bind('exhausted', $exhausted ? 1 : 0);
    }
    $this->db->bind('error', $error);
    $this->db->bind('job_id', (int)$jobId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    $this->db->exe();
    if ($ownerFilter && $this->db->row() < 1) return false;
    $this->db->query("UPDATE sync_runs SET status = :status, last_error = :error, completed_at = CASE WHEN :status2 IN ('completed','failed') THEN NOW() ELSE completed_at END WHERE job_id = :job_id OR sync_job_id = :job_id2");
    $runStatus = $effective === 'queued' ? 'retry' : $effective;
    $this->db->bind('status', $runStatus);
    $this->db->bind('status2', $runStatus);
    $this->db->bind('error', $error);
    $this->db->bind('job_id', (int)$jobId);
    $this->db->bind('job_id2', (int)$jobId);
    $this->db->exe();
    return true;
  }

  public function addPage($jobId, $pageNumber, $sentinel, $nextSentinel, $count, $leaseOwner = null) {
    $ownerColumn = $this->hasColumn('sync_jobs', 'lease_owner');
    $ownerFilter = $leaseOwner !== null && $ownerColumn ? ' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW())' : '';
    $this->db->query("SELECT id FROM sync_jobs WHERE id = :job_id" . ($ownerFilter ? " AND status = 'running'{$ownerFilter}" : '') . " LIMIT 1");
    $this->db->bind('job_id', (int)$jobId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    if (!$this->db->single()) return false;
    $this->db->query("SELECT sync_run_id FROM sync_jobs WHERE id = :job_id");
    $this->db->bind('job_id', (int)$jobId);
    $run = $this->db->single();
    $runId = (int)($run['sync_run_id'] ?? 0);
    $this->db->query("INSERT INTO sync_pages (sync_run_id, job_id, page_number, `cursor`, sentinel, next_sentinel, status, orders_indexed, started_at, completed_at) VALUES (:sync_run_id, :job_id, :page_number, :cursor, :sentinel, :next_sentinel, 'done', :count, NOW(), NOW()) ON DUPLICATE KEY UPDATE next_sentinel = VALUES(next_sentinel), status = 'done', orders_indexed = VALUES(orders_indexed), completed_at = NOW()");
    foreach (['sync_run_id' => $runId, 'job_id' => $jobId, 'page_number' => $pageNumber, 'cursor' => $sentinel, 'sentinel' => $sentinel, 'next_sentinel' => $nextSentinel, 'count' => $count] as $key => $value) {
      $this->db->bind($key, $value);
    }
    $this->db->exe();
    return true;
  }

  public function queueOrder($jobId, $orderId) {
    $this->db->query("INSERT IGNORE INTO sync_job_orders (job_id, order_id) VALUES (:job_id, :order_id)");
    $this->db->bind('job_id', (int)$jobId);
    $this->db->bind('order_id', (int)$orderId);
    return $this->db->exe();
  }

  public function claimOrders($jobId, $limit = 10) {
    require_once __DIR__ . '/../helpers/SyncQueue.php';
    return (new SyncQueue($this->db))->claimOrders($jobId, $limit);
  }

  public function heartbeat($jobId, array $taskIds, $leaseOwner = null) {
    require_once __DIR__ . '/../helpers/SyncQueue.php';
    (new SyncQueue($this->db))->heartbeat($jobId, $taskIds, $leaseOwner);
  }

  public function finishOrder($taskId, $status, $error = null, $leaseOwner = null) {
    $retry = $status === 'retry';
    $ownerColumn = $this->hasColumn('sync_job_orders', 'lease_owner');
    $permanentColumn = $this->hasColumn('sync_job_orders', 'permanent_at');
    $maxAttemptsColumn = $this->hasColumn('sync_job_orders', 'max_attempts');
    $ownerFilter = $leaseOwner !== null && $ownerColumn ? ' AND lease_owner = :lease_owner AND (lease_until IS NULL OR lease_until >= NOW())' : '';
    $this->db->query('SELECT attempts' . ($maxAttemptsColumn ? ', max_attempts' : '') . ' FROM sync_job_orders WHERE id = :id' . $ownerFilter . ' LIMIT 1');
    $this->db->bind('id', (int)$taskId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    $row = $this->db->single();
    if (!$row) return false;
    $attempts = (int)($row['attempts'] ?? 0);
    $maxAttempts = max(1, min(1000, (int)($row['max_attempts'] ?? 8)));
    $exhausted = $retry && $attempts >= $maxAttempts && $attempts < 1000000;
    $effective = $exhausted ? 'failed' : $status;
    $set = "status = :status, lease_until = NULL" . ($ownerColumn ? ', lease_owner = NULL' : '') . ", last_error = :error, next_retry_at = CASE WHEN :retry = 1 THEN DATE_ADD(NOW(), INTERVAL LEAST(60, POW(2, LEAST(attempts, 6))) MINUTE) ELSE NULL END";
    if ($permanentColumn) $set .= ", permanent_at = CASE WHEN :exhausted = 1 THEN NOW() ELSE permanent_at END";
    $set .= ", completed_at = CASE WHEN :retry_done = 0 THEN NOW() ELSE completed_at END";
    $this->db->query("UPDATE sync_job_orders SET {$set} WHERE id = :id{$ownerFilter}");
    $this->db->bind('status', $effective);
    $this->db->bind('error', $error);
    $this->db->bind('retry', $effective === 'retry' ? 1 : 0);
    $this->db->bind('retry_done', $effective === 'retry' ? 1 : 0);
    if ($permanentColumn) $this->db->bind('exhausted', $exhausted ? 1 : 0);
    $this->db->bind('id', (int)$taskId);
    if ($ownerFilter) $this->db->bind('lease_owner', (string)$leaseOwner);
    $this->db->exe();
    return !$ownerFilter || $this->db->row() > 0;
  }
}
