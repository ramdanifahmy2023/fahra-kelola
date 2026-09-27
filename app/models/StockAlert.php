<?php

class StockAlert extends BaseModel {
  protected $table = 'alerts';
  private static $schemaReady = false;

  public function ensureSchema() {
    if (self::$schemaReady) return;
    $this->db->query("CREATE TABLE IF NOT EXISTS alerts (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      fingerprint CHAR(64) NOT NULL,
      shop_id INT NOT NULL,
      severity VARCHAR(20) NOT NULL,
      type VARCHAR(80) NOT NULL,
      entity_type VARCHAR(80) NULL,
      entity_id VARCHAR(190) NULL,
      first_seen_at DATETIME NOT NULL,
      last_seen_at DATETIME NOT NULL,
      occurrence_count INT UNSIGNED NOT NULL DEFAULT 1,
      acknowledged_at DATETIME NULL,
      resolved_at DATETIME NULL,
      silenced_until DATETIME NULL,
      next_action TEXT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id), UNIQUE KEY uq_alerts_fingerprint (fingerprint),
      KEY ix_alerts_shop_state (shop_id, resolved_at, severity),
      KEY ix_alerts_entity (entity_type, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $this->db->exe();
    self::$schemaReady = true;
  }

  public function reconcileShop($shopId) {
    $this->ensureSchema();
    $shopId = (int)$shopId;
    $this->db->query("SELECT id, name, total_stock FROM products WHERE shop_id = :shop_id AND status = 1 AND deleted_at IS NULL AND total_stock < 15 ORDER BY total_stock ASC, name ASC");
    $this->db->bind('shop_id', $shopId);
    $products = $this->db->getAll();
    $this->db->query("SELECT id, fingerprint, acknowledged_at, resolved_at FROM alerts WHERE shop_id = :shop_id AND type = 'low_stock' AND entity_type = 'product'");
    $this->db->bind('shop_id', $shopId);
    $existing = [];
    foreach ($this->db->getAll() as $row) $existing[$row['fingerprint']] = $row;
    $seen = [];
    foreach ($products as $product) {
      $productId = (string)$product['id'];
      $fingerprint = hash('sha256', 'low_stock:' . $shopId . ':' . $productId);
      $seen[$fingerprint] = true;
      $stock = (int)$product['total_stock'];
      $severity = $stock === 0 ? 'urgent' : 'warning';
      $nextAction = $stock === 0 ? 'Segera restock atau nonaktifkan produk.' : 'Pantau stok dan siapkan restock.';
      if (isset($existing[$fingerprint])) {
        $row = $existing[$fingerprint];
        $acknowledged = !empty($row['resolved_at']) ? null : $row['acknowledged_at'];
        $this->db->query("UPDATE alerts SET severity = :severity, last_seen_at = NOW(), acknowledged_at = :acknowledged_at, resolved_at = NULL, next_action = :next_action WHERE id = :id");
        $this->db->bind('severity', $severity);
        $this->db->bind('acknowledged_at', $acknowledged);
        $this->db->bind('next_action', $nextAction);
        $this->db->bind('id', (int)$row['id']);
        $this->db->exe();
      } else {
        $this->db->query("INSERT INTO alerts (fingerprint, shop_id, severity, type, entity_type, entity_id, first_seen_at, last_seen_at, next_action) VALUES (:fingerprint, :shop_id, :severity, 'low_stock', 'product', :entity_id, NOW(), NOW(), :next_action)");
        $this->db->bind('fingerprint', $fingerprint);
        $this->db->bind('shop_id', $shopId);
        $this->db->bind('severity', $severity);
        $this->db->bind('entity_id', $productId);
        $this->db->bind('next_action', $nextAction);
        $this->db->exe();
      }
    }
    foreach ($existing as $fingerprint => $row) {
      if (isset($seen[$fingerprint]) || !empty($row['resolved_at'])) continue;
      $this->db->query("UPDATE alerts SET resolved_at = NOW() WHERE id = :id");
      $this->db->bind('id', (int)$row['id']);
      $this->db->exe();
    }
    return count($products);
  }

  public function summary() {
    $this->ensureSchema();
    $this->db->query("SELECT COUNT(*) AS total, COALESCE(SUM(severity = 'urgent'), 0) AS urgent, COALESCE(SUM(severity = 'warning'), 0) AS warning FROM alerts WHERE resolved_at IS NULL");
    $row = $this->db->single() ?: [];
    return ['total' => (int)($row['total'] ?? 0), 'urgent' => (int)($row['urgent'] ?? 0), 'warning' => (int)($row['warning'] ?? 0)];
  }

  public function unreadCount() {
    $this->ensureSchema();
    $this->db->query("SELECT COUNT(*) AS total FROM alerts WHERE resolved_at IS NULL AND acknowledged_at IS NULL AND (silenced_until IS NULL OR silenced_until <= NOW())");
    return (int)($this->db->single()['total'] ?? 0);
  }

  public function listActive($shopId = 0, $unreadOnly = false, $limit = 50) {
    $this->ensureSchema();
    $limit = max(1, min(100, (int)$limit));
    $where = ["a.resolved_at IS NULL"];
    if ((int)$shopId > 0) $where[] = 'a.shop_id = :shop_id';
    if ($unreadOnly) $where[] = 'a.acknowledged_at IS NULL';
    $this->db->query("SELECT a.id, a.shop_id, s.name AS shop_name, a.severity, a.type, a.entity_id, p.name AS product_name, p.total_stock, a.first_seen_at, a.last_seen_at, a.acknowledged_at, a.next_action FROM alerts a LEFT JOIN shops s ON s.id = a.shop_id LEFT JOIN products p ON p.id = a.entity_id AND p.shop_id = a.shop_id WHERE " . implode(' AND ', $where) . " ORDER BY CASE WHEN a.severity = 'urgent' THEN 0 ELSE 1 END, a.last_seen_at DESC LIMIT {$limit}");
    if ((int)$shopId > 0) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }

  public function acknowledge($id) {
    $this->ensureSchema();
    $this->db->query("UPDATE alerts SET acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE id = :id AND resolved_at IS NULL");
    $this->db->bind('id', (int)$id);
    return $this->db->exe();
  }

  public function acknowledgeAll() {
    $this->ensureSchema();
    $this->db->query("UPDATE alerts SET acknowledged_at = COALESCE(acknowledged_at, NOW()) WHERE resolved_at IS NULL");
    return $this->db->exe();
  }
}
