-- Additive Boost automation state; apply with bin/boost-worker.php --migrate.
CREATE TABLE IF NOT EXISTS boost_profiles (
  shop_id INT NOT NULL PRIMARY KEY,
  product_ids TEXT NOT NULL,
  version INT UNSIGNED NOT NULL DEFAULT 0,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  next_check_at DATETIME NULL,
  last_checked_at DATETIME NULL,
  last_message VARCHAR(255) NULL,
  failure_count INT NOT NULL DEFAULT 0,
  updated_by INT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY boost_due (enabled, next_check_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boost_run_meta (
  run_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  shop_id INT NOT NULL,
  request_key VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  profile_version INT NOT NULL DEFAULT 0,
  actor_id INT NULL,
  owner_token CHAR(64) NOT NULL,
  lease_until DATETIME NOT NULL,
  UNIQUE KEY boost_request (shop_id, request_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boost_item_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT NOT NULL,
  state VARCHAR(24) NOT NULL,
  actor_id INT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY boost_event_item (run_id, product_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS boost_worker_health (
  id TINYINT NOT NULL PRIMARY KEY,
  last_tick_at DATETIME NULL,
  sender_enabled TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
