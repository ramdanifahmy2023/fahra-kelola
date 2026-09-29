CREATE TABLE IF NOT EXISTS ad_browser_reports (
  shop_id INT NOT NULL,
  source_shop_id BIGINT NOT NULL,
  channel VARCHAR(16) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  mapping_version INT NOT NULL,
  captured_at DATETIME NOT NULL,
  payload LONGTEXT NOT NULL,
  imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (shop_id, source_shop_id, channel, start_date, end_date, mapping_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
