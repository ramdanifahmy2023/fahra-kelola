CREATE TABLE IF NOT EXISTS notification_receipts (
  alert_id BIGINT UNSIGNED NOT NULL,
  user_id INT NOT NULL,
  revision BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  PRIMARY KEY (alert_id,user_id),
  KEY notification_receipts_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_checks (
  id TINYINT UNSIGNED NOT NULL,
  checked_at DATETIME NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_snoozes (
 alert_id BIGINT UNSIGNED NOT NULL,
 user_id INT NOT NULL,
 revision BIGINT UNSIGNED NOT NULL,
 snoozed_until DATETIME NOT NULL,
 PRIMARY KEY (alert_id,user_id),
 KEY notification_snoozes_user (user_id,snoozed_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
