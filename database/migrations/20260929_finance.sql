CREATE TABLE IF NOT EXISTS finance_imports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  source_shop_id BIGINT NOT NULL,
  category TINYINT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  state VARCHAR(16) NOT NULL DEFAULT 'queued',
  cursor_json TEXT NULL,
  seen_cursors LONGTEXT NULL,
  page_count INT NOT NULL DEFAULT 0,
  overview_pending BIGINT NULL,
  error_message VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  KEY finance_import_queue (shop_id,state,id),
  KEY finance_import_range (shop_id,category,start_date,end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_income_rows (
  import_id BIGINT UNSIGNED NOT NULL,
  shop_id INT NOT NULL,
  external_order_id BIGINT NOT NULL,
  order_sn VARCHAR(100) NOT NULL,
  product_name VARCHAR(500) NOT NULL DEFAULT '',
  item_count INT NULL,
  income_amount BIGINT NOT NULL,
  adjustment_amount BIGINT NULL,
  net_amount BIGINT NULL,
  released_at DATETIME NULL,
  released_date DATE NULL,
  estimated_at DATETIME NULL,
  adjustment_at DATETIME NULL,
  status_code INT NULL,
  status_key VARCHAR(120) NOT NULL DEFAULT '',
  PRIMARY KEY (import_id,external_order_id),
  KEY finance_rows_date (shop_id,released_date,import_id),
  KEY finance_rows_order (shop_id,external_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_current (
  shop_id INT NOT NULL PRIMARY KEY,
  import_id BIGINT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_overview_totals (
  import_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  week_start DATE NOT NULL,
  month_start DATE NOT NULL,
  as_of_date DATE NOT NULL,
  week_amount BIGINT NULL,
  month_amount BIGINT NULL,
  all_amount BIGINT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_days (
  shop_id INT NOT NULL,
  income_date DATE NOT NULL,
  import_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (shop_id,income_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_cost_heads (
  shop_id INT NOT NULL,
  product_id BIGINT NOT NULL,
  model_id BIGINT NOT NULL DEFAULT 0,
  sku VARCHAR(190) NOT NULL DEFAULT '',
  product_name VARCHAR(500) NOT NULL,
  variation_name VARCHAR(255) NOT NULL DEFAULT '',
  version INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (shop_id,product_id,model_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_cost_versions (
  shop_id INT NOT NULL,
  product_id BIGINT NOT NULL,
  model_id BIGINT NOT NULL DEFAULT 0,
  valid_from DATE NOT NULL,
  unit_cost BIGINT NOT NULL,
  actor_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (shop_id,product_id,model_id,valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_cost_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  product_id BIGINT NOT NULL,
  model_id BIGINT NOT NULL DEFAULT 0,
  valid_from DATE NOT NULL,
  previous_cost BIGINT NULL,
  unit_cost BIGINT NOT NULL,
  version INT NOT NULL,
  actor_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  KEY finance_cost_audit (shop_id,product_id,model_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
