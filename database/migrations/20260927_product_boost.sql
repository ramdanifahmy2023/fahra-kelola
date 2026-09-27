-- Manual Naikkan Produk: local quota, cooldown, and per-shop history.
-- Timestamps are written in UTC by the application/database session.
CREATE TABLE IF NOT EXISTS product_boost_runs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_boost_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
