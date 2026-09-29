CREATE TABLE IF NOT EXISTS chat_incoming_monitors (
  shop_id INT NOT NULL PRIMARY KEY,
  started_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_incoming_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  shop_id INT NOT NULL,
  remote_message_id VARCHAR(80) NOT NULL,
  conversation_id VARCHAR(80) NOT NULL,
  message_at DATETIME(6) NOT NULL,
  detected_at DATETIME(6) NOT NULL,
  UNIQUE KEY chat_incoming_message (shop_id, remote_message_id),
  KEY chat_incoming_conversation (shop_id, conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
