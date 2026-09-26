CREATE TABLE IF NOT EXISTS promotion_shop_snapshots (
  shop_id INT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'unknown',
  payload LONGTEXT NULL,
  error_message TEXT NULL,
  synced_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (shop_id),
  KEY promotion_snapshots_status (status),
  KEY promotion_snapshots_synced (synced_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
