<?php

class StockAlert extends BaseModel {
  protected $table = 'alerts';
  private $schemaReady = false;

  public function ensureSchema() {
    if ($this->schemaReady) return;
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
    $this->db->query('SHOW COLUMNS FROM alerts');
    $columns = array_column($this->db->getAll(), 'Field');
    foreach (['revision'=>'BIGINT UNSIGNED NOT NULL DEFAULT 1', 'changed_at'=>'DATETIME NULL', 'payload'=>'LONGTEXT NULL'] as $name=>$definition) {
      if (in_array($name,$columns,true)) continue;
      try { $this->db->query("ALTER TABLE alerts ADD COLUMN {$name} {$definition}"); $this->db->exe(); }
      catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0) !== 1060) throw $e; }
    }
    $this->schemaReady = true;
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
      $this->putAlert($shopId, 'low_stock', 'product', $productId, $severity, [
        'title'=>$stock === 0 ? 'Stok habis' : 'Stok kritis', 'message'=>$product['name'].' · stok '.$stock,
        'action_label'=>'Periksa produk', 'path'=>'/panel/products?shop_id='.$shopId.'&stock=critical&highlight='.rawurlencode($productId),
        'icon'=>'inventory_2', 'stage'=>0, 'source_at'=>gmdate('Y-m-d H:i:s'), 'next_action'=>$nextAction
      ]);
    }
    foreach ($existing as $fingerprint => $row) {
      if (isset($seen[$fingerprint]) || !empty($row['resolved_at'])) continue;
      $this->db->query("UPDATE alerts SET resolved_at = UTC_TIMESTAMP() WHERE id = :id");
      $this->db->bind('id', (int)$row['id']);
      $this->db->exe();
    }
    return count($products);
  }

  public function putAlert(int $shopId, string $type, string $entityType, string $entityId, string $severity, array $payload): void {
    $this->ensureSchema();
    $escalates = "(resolved_at IS NOT NULL OR (severity <> 'urgent' AND VALUES(severity) = 'urgent') OR COALESCE(JSON_EXTRACT(payload,'$.stage'),0) < COALESCE(JSON_EXTRACT(VALUES(payload),'$.stage'),0))";
    $this->db->query("INSERT INTO alerts (fingerprint,shop_id,type,entity_type,entity_id,severity,payload,next_action,first_seen_at,last_seen_at,changed_at)
      VALUES (:fingerprint,:shop,:type,:entity_type,:entity_id,:severity,:payload,:action,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())
      ON DUPLICATE KEY UPDATE revision=revision+IF({$escalates},1,0), changed_at=IF({$escalates},UTC_TIMESTAMP(),changed_at),
      acknowledged_at=IF({$escalates},NULL,acknowledged_at), silenced_until=IF({$escalates},NULL,silenced_until),
      occurrence_count=occurrence_count+IF(resolved_at IS NOT NULL,1,0), resolved_at=NULL,
      severity=VALUES(severity),payload=VALUES(payload),next_action=VALUES(next_action),last_seen_at=UTC_TIMESTAMP()");
    foreach (['fingerprint'=>hash('sha256',$type.':'.$shopId.':'.$entityId),'shop'=>$shopId,'type'=>$type,'entity_type'=>$entityType,'entity_id'=>$entityId,'severity'=>$severity,'payload'=>json_encode($payload,JSON_UNESCAPED_UNICODE),'action'=>$payload['next_action'] ?? $payload['action_label']] as $key=>$value) $this->db->bind($key,$value);
    $this->db->exe();
  }

  public function resolveAlert(int $shopId, string $type, string $entityId): void {
    $this->db->query('UPDATE alerts SET resolved_at=UTC_TIMESTAMP() WHERE fingerprint=:fingerprint AND resolved_at IS NULL');
    $this->db->bind('fingerprint',hash('sha256',$type.':'.$shopId.':'.$entityId)); $this->db->exe();
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
    $where[] = '(a.silenced_until IS NULL OR a.silenced_until <= UTC_TIMESTAMP())';
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
