CREATE TABLE IF NOT EXISTS ad_balance_topups (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shop_id INT NOT NULL,
  source_shop_id BIGINT NOT NULL,
  order_id BIGINT NOT NULL,
  order_sn VARCHAR(190) DEFAULT NULL,
  order_status VARCHAR(32) NOT NULL DEFAULT 'completed',
  actual_price DECIMAL(20,2) NOT NULL,
  original_price DECIMAL(20,2) DEFAULT NULL,
  discount_price DECIMAL(20,2) DEFAULT NULL,
  tax_amount DECIMAL(20,2) DEFAULT NULL,
  tax_rate DECIMAL(10,4) DEFAULT NULL,
  payment_channel VARCHAR(100) DEFAULT NULL,
  payment_type VARCHAR(100) DEFAULT NULL,
  occurred_at_utc DATETIME NOT NULL,
  source_hash CHAR(64) NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_balance_topups_source (shop_id, order_id),
  KEY ix_ad_balance_topups_period (shop_id, occurred_at_utc),
  KEY ix_ad_balance_topups_source_shop (shop_id, source_shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_topup_sync_state (
  shop_id INT NOT NULL,
  source_shop_id BIGINT NOT NULL,
  next_page_number INT NOT NULL DEFAULT 1,
  backfill_complete TINYINT(1) NOT NULL DEFAULT 0,
  synced_at DATETIME DEFAULT NULL,
  last_error TEXT DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (shop_id),
  KEY ix_ad_topup_sync_state_source (source_shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
