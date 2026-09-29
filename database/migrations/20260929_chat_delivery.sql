CREATE TABLE IF NOT EXISTS chat_sync_progress (
  shop_id INT NOT NULL PRIMARY KEY,
  older_cursor VARCHAR(80) NOT NULL DEFAULT '',
  older_region VARCHAR(8) NOT NULL DEFAULT 'ID',
  backfill_done TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_thread_sync (
  shop_id INT NOT NULL,
  conversation_id VARCHAR(80) NOT NULL,
  requested_at DATETIME NULL,
  synced_at DATETIME NULL,
  retry_at DATETIME NULL,
  error_message TEXT NULL,
  history_count INT NOT NULL DEFAULT 0,
  PRIMARY KEY (shop_id, conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_outbox (
  request_id CHAR(36) NOT NULL PRIMARY KEY,
  shop_id INT NOT NULL,
  conversation_id VARCHAR(80) NOT NULL,
  content_hash CHAR(64) NOT NULL,
  content_uid CHAR(36) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'sending',
  remote_message_id VARCHAR(80) NULL,
  error_message TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY chat_outbox_thread (shop_id, conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
