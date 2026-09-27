<?php

class ProductBoostMonitor extends BaseModel {
  protected $table = 'product_boost_runs';

  public function ensureSchema() {
    $this->db->query("CREATE TABLE IF NOT EXISTS product_boost_runs (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      shop_id INT NOT NULL,
      mode VARCHAR(16) NOT NULL DEFAULT 'manual',
      status VARCHAR(16) NOT NULL DEFAULT 'running',
      selected_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
      success_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
      failed_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
      unknown_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
      started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      completed_at DATETIME NULL,
      next_allowed_at DATETIME NULL,
      error_message TEXT NULL,
      PRIMARY KEY (id),
      KEY boost_runs_shop_started (shop_id, started_at),
      KEY boost_runs_next_allowed (shop_id, next_allowed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
    $this->db->query("CREATE TABLE IF NOT EXISTS product_boost_items (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      run_id BIGINT UNSIGNED NOT NULL,
      shop_id INT NOT NULL,
      product_id BIGINT NOT NULL,
      product_name VARCHAR(255) NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'pending',
      response_payload LONGTEXT NULL,
      error_message TEXT NULL,
      attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY boost_items_run_product (run_id, product_id),
      KEY boost_items_shop_attempted (shop_id, attempted_at),
      KEY boost_items_product (shop_id, product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  public function summary($shopId) {
    $this->ensureSchema();
    $this->db->query("SELECT MAX(attempted_at) AS last_attempted_at, MAX(CASE WHEN status IN ('success', 'unknown') THEN attempted_at END) AS last_consuming_at, SUM(CASE WHEN status IN ('success', 'unknown') AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR) THEN 1 ELSE 0 END) AS used_count FROM product_boost_items WHERE shop_id = :shop_id");
    $this->db->bind('shop_id', (int)$shopId);
    $row = $this->db->single() ?: [];
    $nextAllowedAt = null;
    if (!empty($row['last_consuming_at'])) {
      $nextAllowedAt = gmdate('Y-m-d H:i:s', strtotime($row['last_consuming_at'] . ' UTC') + (4 * 3600) + (15 * 60));
    }
    $now = time();
    $nextTimestamp = $nextAllowedAt ? strtotime($nextAllowedAt . ' UTC') : 0;
    return [
      'used_count' => min(5, (int)($row['used_count'] ?? 0)),
      'remaining_count' => max(0, 5 - min(5, (int)($row['used_count'] ?? 0))),
      'last_attempted_at' => $row['last_attempted_at'] ?? null,
      'last_consuming_at' => $row['last_consuming_at'] ?? null,
      'next_allowed_at' => $nextAllowedAt,
      'cooldown_active' => $nextTimestamp > $now,
      'cooldown_seconds' => max(0, $nextTimestamp - $now),
      'buffer_minutes' => 15
    ];
  }

  public function createRun($shopId, array $products) {
    $this->db->query("INSERT INTO {$this->table} (shop_id, mode, status, selected_count, started_at) VALUES (:shop_id, 'manual', 'running', :selected_count, UTC_TIMESTAMP())");
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('selected_count', count($products));
    $this->db->exe();
    $runId = (int)$this->db->lastId();
    foreach ($products as $product) {
      $this->db->query("INSERT INTO product_boost_items (run_id, shop_id, product_id, product_name, status, attempted_at) VALUES (:run_id, :shop_id, :product_id, :product_name, 'pending', UTC_TIMESTAMP())");
      $this->db->bind('run_id', $runId);
      $this->db->bind('shop_id', (int)$shopId);
      $this->db->bind('product_id', (int)$product['id']);
      $this->db->bind('product_name', $product['name'] ?? '');
      $this->db->exe();
    }
    return $runId;
  }

  public function reserveRun($shopId, array $products) {
    $this->ensureSchema();
    $this->db->begin();
    try {
      $this->db->query('SELECT id FROM shops WHERE id = :shop_id FOR UPDATE');
      $this->db->bind('shop_id', (int)$shopId);
      if (!$this->db->single()) {
        $this->db->commit();
        return ['error' => 'Toko tidak ditemukan.'];
      }

      $this->db->query("SELECT MAX(CASE WHEN status IN ('success', 'unknown') THEN attempted_at END) AS last_consuming_at, SUM(CASE WHEN status IN ('success', 'unknown') AND attempted_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 4 HOUR) THEN 1 ELSE 0 END) AS used_count FROM product_boost_items WHERE shop_id = :shop_id");
      $this->db->bind('shop_id', (int)$shopId);
      $row = $this->db->single() ?: [];
      $used = min(5, (int)($row['used_count'] ?? 0));
      $nextAllowedAt = !empty($row['last_consuming_at']) ? gmdate('Y-m-d H:i:s', strtotime($row['last_consuming_at'] . ' UTC') + (4 * 3600) + (15 * 60)) : null;
      if ($nextAllowedAt && strtotime($nextAllowedAt . ' UTC') > time()) {
        $this->db->commit();
        return ['error' => 'Cooldown toko masih aktif sampai ' . $nextAllowedAt . ' UTC.'];
      }
      if (count($products) > (5 - $used)) {
        $this->db->commit();
        return ['error' => 'Pilihan melebihi sisa kuota lokal toko.'];
      }

      $this->db->query("SELECT id FROM {$this->table} WHERE shop_id = :shop_id AND status = 'running' LIMIT 1");
      $this->db->bind('shop_id', (int)$shopId);
      if ($this->db->single()) {
        $this->db->commit();
        return ['error' => 'Toko ini sedang memproses batch lain. Tunggu sampai selesai.'];
      }

      $runId = $this->createRun($shopId, $products);
      $this->db->commit();
      return ['run_id' => $runId];
    } catch (Throwable $error) {
      $this->db->rollback();
      throw $error;
    }
  }

  public function recordItem($runId, $productId, $status, $payload = null, $message = null) {
    $this->db->query("UPDATE product_boost_items SET status = :status, response_payload = :payload, error_message = :message, attempted_at = UTC_TIMESTAMP() WHERE run_id = :run_id AND product_id = :product_id");
    $this->db->bind('status', $status);
    $this->db->bind('payload', $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null);
    $this->db->bind('message', $message);
    $this->db->bind('run_id', (int)$runId);
    $this->db->bind('product_id', (int)$productId);
    $this->db->exe();
  }

  public function finishRun($runId, $errorMessage = null) {
    $this->db->query("SELECT SUM(status = 'success') AS success_count, SUM(status = 'failed') AS failed_count, SUM(status = 'unknown') AS unknown_count, MAX(CASE WHEN status IN ('success', 'unknown') THEN attempted_at END) AS last_consuming_at FROM product_boost_items WHERE run_id = :run_id");
    $this->db->bind('run_id', (int)$runId);
    $counts = $this->db->single() ?: [];
    $nextAllowedAt = !empty($counts['last_consuming_at']) ? gmdate('Y-m-d H:i:s', strtotime($counts['last_consuming_at'] . ' UTC') + (4 * 3600) + (15 * 60)) : null;
    $status = ((int)($counts['unknown_count'] ?? 0) > 0) ? 'partial' : (((int)($counts['failed_count'] ?? 0) > 0 && (int)($counts['success_count'] ?? 0) === 0) ? 'failed' : 'completed');
    $this->db->query("UPDATE {$this->table} SET status = :status, success_count = :success_count, failed_count = :failed_count, unknown_count = :unknown_count, completed_at = UTC_TIMESTAMP(), next_allowed_at = :next_allowed_at, error_message = :error_message WHERE id = :run_id");
    $this->db->bind('status', $status);
    $this->db->bind('success_count', (int)($counts['success_count'] ?? 0));
    $this->db->bind('failed_count', (int)($counts['failed_count'] ?? 0));
    $this->db->bind('unknown_count', (int)($counts['unknown_count'] ?? 0));
    $this->db->bind('next_allowed_at', $nextAllowedAt);
    $this->db->bind('error_message', $errorMessage);
    $this->db->bind('run_id', (int)$runId);
    $this->db->exe();
    return [
      'status' => $status,
      'success_count' => (int)($counts['success_count'] ?? 0),
      'failed_count' => (int)($counts['failed_count'] ?? 0),
      'unknown_count' => (int)($counts['unknown_count'] ?? 0),
      'next_allowed_at' => $nextAllowedAt
    ];
  }

  public function history($shopId, $limit = 10) {
    $this->ensureSchema();
    $this->db->query("SELECT id, status, selected_count, success_count, failed_count, unknown_count, started_at, completed_at, next_allowed_at, error_message FROM {$this->table} WHERE shop_id = :shop_id ORDER BY id DESC LIMIT " . (int)$limit);
    $this->db->bind('shop_id', (int)$shopId);
    $runs = $this->db->getAll();
    foreach ($runs as &$run) {
      $this->db->query("SELECT product_id, product_name, status, error_message, attempted_at FROM product_boost_items WHERE run_id = :run_id ORDER BY id ASC");
      $this->db->bind('run_id', (int)$run['id']);
      $run['items'] = $this->db->getAll();
    }
    return $runs;
  }
}
