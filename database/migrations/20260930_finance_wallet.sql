CREATE TABLE IF NOT EXISTS finance_wallet (
  shop_id INT NOT NULL,
  source_shop_id BIGINT NOT NULL,
  amount BIGINT NULL,
  withdrawal_restricted TINYINT NULL,
  notice VARCHAR(1000) NULL,
  synced_at DATETIME NULL,
  last_attempt_at DATETIME NULL,
  requested_at DATETIME NULL,
  failed TINYINT NOT NULL DEFAULT 0,
  error_message VARCHAR(255) NULL,
  PRIMARY KEY (shop_id,source_shop_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
