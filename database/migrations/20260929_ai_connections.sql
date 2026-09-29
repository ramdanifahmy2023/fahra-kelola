CREATE TABLE IF NOT EXISTS ai_connections (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  base_url VARCHAR(2048) NOT NULL,
  default_model VARCHAR(255) NOT NULL,
  key_ciphertext TEXT NULL,
  key_nonce VARCHAR(64) NULL,
  key_version VARCHAR(40) NULL,
  version INT UNSIGNED NOT NULL DEFAULT 1,
  created_by INT NOT NULL,
  updated_by INT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  catalog_result TEXT NULL,
  test_result TEXT NULL,
  probe_token VARCHAR(64) NULL,
  probe_until DATETIME NULL,
  cooldown_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_connection_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  connection_id INT NOT NULL,
  actor_id INT NOT NULL,
  action VARCHAR(24) NOT NULL,
  version INT UNSIGNED NOT NULL,
  changed_fields VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ai_connection_events_connection (connection_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
