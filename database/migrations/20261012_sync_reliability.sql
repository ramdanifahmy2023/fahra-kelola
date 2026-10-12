-- Additive reliability changes for the durable sync queue.  This migration is
-- safe to run against an existing installation and does not rebuild tables.

ALTER TABLE sync_jobs
  ADD COLUMN IF NOT EXISTS lease_owner CHAR(64) NULL AFTER lease_until,
  ADD COLUMN IF NOT EXISTS max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8 AFTER attempts,
  ADD COLUMN IF NOT EXISTS permanent_at DATETIME NULL AFTER completed_at;

ALTER TABLE sync_job_orders
  ADD COLUMN IF NOT EXISTS lease_owner CHAR(64) NULL AFTER lease_until,
  ADD COLUMN IF NOT EXISTS max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 8 AFTER attempts,
  ADD COLUMN IF NOT EXISTS permanent_at DATETIME NULL AFTER completed_at;

ALTER TABLE sync_pages
  ADD COLUMN IF NOT EXISTS `cursor` TEXT NULL AFTER page_number;

ALTER TABLE sync_schedules
  ADD COLUMN IF NOT EXISTS scheduler_owner CHAR(64) NULL AFTER next_run_at,
  ADD COLUMN IF NOT EXISTS scheduler_lease_until DATETIME NULL AFTER scheduler_owner;

CREATE TABLE IF NOT EXISTS sync_checkpoints (
  shop_id INT NOT NULL,
  sync_type VARCHAR(50) NOT NULL,
  mode VARCHAR(16) NOT NULL DEFAULT 'diff',
  `cursor` TEXT NULL,
  page_number INT NOT NULL DEFAULT 0,
  seen_products LONGTEXT NULL,
  seen_models LONGTEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'running',
  last_error TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (shop_id, sync_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
