-- Generated schema only. No application data or credentials.
CREATE DATABASE IF NOT EXISTS `shopdash_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `shopdash_db`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `ad_performance_snapshots`;
CREATE TABLE `ad_performance_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `campaign_type` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ok',
  `impressions` bigint(20) unsigned NOT NULL DEFAULT 0,
  `clicks` bigint(20) unsigned NOT NULL DEFAULT 0,
  `ctr` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `orders` bigint(20) unsigned NOT NULL DEFAULT 0,
  `items_sold` bigint(20) unsigned NOT NULL DEFAULT 0,
  `sales` decimal(20,2) NOT NULL DEFAULT 0.00,
  `ad_cost` decimal(20,2) NOT NULL DEFAULT 0.00,
  `roas` decimal(12,4) DEFAULT NULL,
  `broad_sales` decimal(20,2) NOT NULL DEFAULT 0.00,
  `broad_orders` bigint(20) unsigned NOT NULL DEFAULT 0,
  `broad_items_sold` bigint(20) unsigned NOT NULL DEFAULT 0,
  `broad_roas` decimal(12,4) DEFAULT NULL,
  `raw_payload` longtext DEFAULT NULL,
  `fetched_at` datetime NOT NULL,
  `error_message` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ad_performance_window` (`shop_id`,`period_start`,`period_end`,`campaign_type`),
  KEY `ad_performance_shop_period` (`shop_id`,`period_start`),
  KEY `ad_performance_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ad_shop_snapshots`;
CREATE TABLE `ad_shop_snapshots` (
  `shop_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unknown',
  `payload` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`),
  KEY `ad_shop_snapshots_status` (`status`),
  KEY `ad_shop_snapshots_synced` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `alerts`;
CREATE TABLE `alerts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fingerprint` char(64) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `severity` varchar(20) NOT NULL,
  `type` varchar(80) NOT NULL,
  `entity_type` varchar(80) DEFAULT NULL,
  `entity_id` varchar(190) DEFAULT NULL,
  `first_seen_at` datetime NOT NULL,
  `last_seen_at` datetime NOT NULL,
  `occurrence_count` int(10) unsigned NOT NULL DEFAULT 1,
  `acknowledged_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `silenced_until` datetime DEFAULT NULL,
  `next_action` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alerts_fingerprint` (`fingerprint`),
  KEY `ix_alerts_shop_state` (`shop_id`,`resolved_at`,`severity`),
  KEY `ix_alerts_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `balance_snapshots`;
CREATE TABLE `balance_snapshots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_account_id` bigint(20) unsigned NOT NULL,
  `available_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `pending_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `withdrawable_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `as_of_at` datetime NOT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_balance_snapshots_account_time` (`wallet_account_id`,`as_of_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `channels`;
CREATE TABLE `channels` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_channels_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `chat_conversations`;
CREATE TABLE `chat_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `remote_conversation_id` varchar(80) NOT NULL,
  `buyer_id` bigint(20) DEFAULT NULL,
  `buyer_name` varchar(255) DEFAULT NULL,
  `buyer_avatar` varchar(500) DEFAULT NULL,
  `buyer_shop_id` bigint(20) DEFAULT NULL,
  `status` varchar(32) DEFAULT NULL,
  `is_blocked` tinyint(1) NOT NULL DEFAULT 0,
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `latest_message_id` varchar(80) DEFAULT NULL,
  `latest_message_type` varchar(32) DEFAULT NULL,
  `latest_message_source` varchar(64) DEFAULT NULL,
  `latest_message_text` text DEFAULT NULL,
  `latest_message_at` datetime DEFAULT NULL,
  `latest_message_region` varchar(8) DEFAULT NULL,
  `raw_payload` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_conversations_remote` (`shop_id`,`remote_conversation_id`),
  KEY `chat_conversations_list` (`shop_id`,`latest_message_at`),
  KEY `chat_conversations_unread` (`shop_id`,`unread_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_messages`;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `remote_conversation_id` varchar(80) NOT NULL,
  `remote_message_id` varchar(80) NOT NULL,
  `sender_id` bigint(20) DEFAULT NULL,
  `receiver_id` bigint(20) DEFAULT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `message_type` varchar(32) DEFAULT NULL,
  `direction` varchar(16) DEFAULT NULL,
  `content_text` text DEFAULT NULL,
  `content_json` longtext DEFAULT NULL,
  `remote_created_at` datetime DEFAULT NULL,
  `remote_status` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_messages_remote` (`shop_id`,`remote_message_id`),
  KEY `chat_messages_conversation` (`shop_id`,`remote_conversation_id`,`remote_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `chat_shop_snapshots`;
CREATE TABLE `chat_shop_snapshots` (
  `shop_id` int(11) NOT NULL,
  `remote_shop_id` bigint(20) DEFAULT NULL,
  `remote_user_id` bigint(20) DEFAULT NULL,
  `remote_shop_name` varchar(255) DEFAULT NULL,
  `remote_country` varchar(8) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `unread_count` int(11) NOT NULL DEFAULT 0,
  `conversation_count` int(11) NOT NULL DEFAULT 0,
  `last_message_id` varchar(80) DEFAULT NULL,
  `last_message_region` varchar(8) DEFAULT NULL,
  `next_timestamp_nano` varchar(80) DEFAULT NULL,
  `session_expires_at` datetime DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `payload` longtext DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`),
  KEY `chat_snapshots_status` (`status`),
  KEY `chat_snapshots_sync` (`last_sync_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customer_shops`;
CREATE TABLE `customer_shops` (
  `customer_id` bigint(20) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `first_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_seen_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`customer_id`,`shop_id`),
  KEY `ix_customer_shops_shop` (`shop_id`,`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `data_quality_audits`;
CREATE TABLE `data_quality_audits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` char(36) NOT NULL,
  `issue_type` varchar(80) NOT NULL,
  `table_name` varchar(80) NOT NULL,
  `row_pk` varchar(190) DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_data_quality_run` (`run_id`,`issue_type`),
  KEY `ix_data_quality_table` (`table_name`,`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `finance_ledger_entries`;
CREATE TABLE `finance_ledger_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `external_id` varchar(190) NOT NULL,
  `entry_type` varchar(50) NOT NULL,
  `direction` varchar(20) NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'IDR',
  `occurred_at` datetime NOT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) NOT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `definition` varchar(190) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `metadata_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_ledger_source` (`shop_id`,`channel_id`,`external_id`,`entry_type`),
  KEY `ix_finance_ledger_order` (`order_id`,`entry_type`),
  KEY `ix_finance_ledger_period` (`shop_id`,`currency`,`occurred_at`),
  KEY `ix_finance_ledger_run` (`sync_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inventory_balances`;
CREATE TABLE `inventory_balances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `model_id` bigint(20) DEFAULT NULL,
  `sku` varchar(190) DEFAULT NULL,
  `warehouse_id` varchar(100) DEFAULT NULL,
  `available_qty` int(11) NOT NULL DEFAULT 0,
  `reserved_qty` int(11) NOT NULL DEFAULT 0,
  `incoming_qty` int(11) NOT NULL DEFAULT 0,
  `in_transit_qty` int(11) NOT NULL DEFAULT 0,
  `damaged_qty` int(11) NOT NULL DEFAULT 0,
  `safety_stock` int(11) NOT NULL DEFAULT 0,
  `source_updated_at` datetime DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inventory_scope` (`shop_id`,`channel_id`,`model_id`,`warehouse_id`),
  KEY `ix_inventory_sku` (`shop_id`,`sku`),
  KEY `ix_inventory_available` (`shop_id`,`available_qty`,`safety_stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inventory_movements`;
CREATE TABLE `inventory_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_balance_id` bigint(20) unsigned NOT NULL,
  `movement_type` varchar(50) NOT NULL,
  `quantity_delta` int(11) NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` varchar(190) DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_inventory_movements_balance_time` (`inventory_balance_id`,`occurred_at`),
  KEY `ix_inventory_movements_reference` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `inventory_reservations`;
CREATE TABLE `inventory_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `inventory_balance_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `quantity` int(11) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_inventory_reservations_order` (`order_id`,`status`),
  KEY `ix_inventory_reservations_expiry` (`status`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `logistic_channels`;
CREATE TABLE `logistic_channels` (
  `code` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `migration_history`;
CREATE TABLE `migration_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(190) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration_name` (`migration_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `order_incomes`;
CREATE TABLE `order_incomes` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  `total_price` bigint(20) DEFAULT NULL,
  `total_payment` bigint(20) DEFAULT NULL,
  `total_payment_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`total_payment_detail`)),
  `total_income` bigint(20) DEFAULT NULL,
  `hpp` bigint(20) DEFAULT NULL,
  `total_income_detail` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`total_income_detail`)),
  `released_time` datetime DEFAULT NULL,
  `income_checked_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  UNIQUE KEY `uq_order_incomes_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `ix_order_incomes_shop_release` (`shop_id`,`released_time`,`income_checked_at`),
  CONSTRAINT `order_incomes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `order_item_change_history`;
CREATE TABLE `order_item_change_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` int(11) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `previous_source_hash` char(64) DEFAULT NULL,
  `source_hash` char(64) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `variation_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `price` bigint(20) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `changed_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_order_item_history_item_time` (`order_item_id`,`changed_at`),
  KEY `ix_order_item_history_order_time` (`order_id`,`changed_at`),
  KEY `ix_order_item_history_run` (`sync_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `model_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `variation_name` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `price` bigint(20) DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `ix_order_items_order_product` (`order_id`,`product_id`,`model_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `order_return_refs`;
CREATE TABLE `order_return_refs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `external_return_id` varchar(190) NOT NULL,
  `request_due_at` datetime DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) NOT NULL,
  `last_seen_run_id` bigint(20) unsigned DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `observed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_return_source` (`channel_id`,`shop_id`,`external_return_id`),
  KEY `ix_order_return_order` (`order_id`,`shop_id`),
  KEY `ix_order_return_shop_time` (`shop_id`,`source_updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  `shop_id` int(11) NOT NULL,
  `order_sn` varchar(255) DEFAULT NULL,
  `advance_booking_sn` varchar(50) DEFAULT NULL,
  `order_type` varchar(10) DEFAULT NULL,
  `buyer_username` varchar(255) DEFAULT NULL,
  `total_price` bigint(20) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_state` varchar(30) DEFAULT NULL,
  `fulfillment_state` varchar(40) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `status_normalized` varchar(50) DEFAULT NULL,
  `status_type` varchar(255) DEFAULT NULL,
  `status_description` varchar(255) DEFAULT NULL,
  `shipping_cargo` varchar(255) DEFAULT NULL,
  `tracking_number` varchar(255) DEFAULT NULL,
  `ship_by_date` int(11) DEFAULT NULL,
  `ship_by_at` datetime DEFAULT NULL,
  `shipping_name` varchar(255) DEFAULT NULL,
  `shipping_phone` varchar(255) DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `last_seen_run_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `sync_status` varchar(24) DEFAULT NULL,
  `sync_attempts` int(11) NOT NULL DEFAULT 0,
  `sync_next_retry_at` datetime DEFAULT NULL,
  `sync_last_error` text DEFAULT NULL,
  `detail_synced_at` datetime DEFAULT NULL,
  `package_synced_at` datetime DEFAULT NULL,
  `income_synced_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_orders_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `shop_id` (`shop_id`),
  KEY `ix_orders_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `ix_orders_updated_scope` (`shop_id`,`updated_at`,`id`),
  KEY `ix_orders_shop_created` (`shop_id`,`created_at`,`id`),
  KEY `ix_orders_shop_updated` (`shop_id`,`updated_at`,`id`),
  KEY `ix_orders_shop_status_sla` (`shop_id`,`status_normalized`,`ship_by_at`),
  KEY `ix_orders_shop_payment_state` (`shop_id`,`payment_state`,`created_at`),
  KEY `ix_orders_shop_fulfillment_state` (`shop_id`,`fulfillment_state`,`ship_by_at`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `payment_methods`;
CREATE TABLE `payment_methods` (
  `code` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_boost_items`;
CREATE TABLE `product_boost_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` bigint(20) unsigned NOT NULL,
  `shop_id` int(11) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `response_payload` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `boost_items_run_product` (`run_id`,`product_id`),
  KEY `boost_items_shop_attempted` (`shop_id`,`attempted_at`),
  KEY `boost_items_product` (`shop_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_boost_runs`;
CREATE TABLE `product_boost_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'manual',
  `status` varchar(16) NOT NULL DEFAULT 'running',
  `selected_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `success_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `failed_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `unknown_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `next_allowed_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `boost_runs_shop_started` (`shop_id`,`started_at`),
  KEY `boost_runs_next_allowed` (`shop_id`,`next_allowed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `product_change_history`;
CREATE TABLE `product_change_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `model_id` bigint(20) DEFAULT NULL,
  `change_type` varchar(50) NOT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `before_json` longtext DEFAULT NULL,
  `after_json` longtext DEFAULT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `changed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_product_history_scope` (`shop_id`,`channel_id`,`product_id`,`model_id`,`changed_at`),
  KEY `ix_product_history_run` (`sync_run_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_listings`;
CREATE TABLE `product_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `model_id` bigint(20) DEFAULT NULL,
  `external_id` varchar(190) NOT NULL,
  `sku` varchar(190) DEFAULT NULL,
  `listing_status` varchar(30) NOT NULL DEFAULT 'active',
  `source_hash` char(64) DEFAULT NULL,
  `last_seen_run_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_listing_source` (`shop_id`,`channel_id`,`external_id`),
  KEY `ix_product_listing_model` (`shop_id`,`model_id`,`sku`),
  KEY `ix_product_listing_status` (`shop_id`,`listing_status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `product_models`;
CREATE TABLE `product_models` (
  `id` bigint(20) NOT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  `product_id` bigint(20) NOT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `sold_count` int(11) DEFAULT 0,
  `origin_price` bigint(20) DEFAULT NULL,
  `promotion_price` bigint(20) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `last_seen_run_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_models_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `product_id` (`product_id`),
  KEY `ix_product_models_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `ix_product_models_shop_product` (`shop_id`,`product_id`,`id`),
  CONSTRAINT `product_models_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) NOT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `external_id` varchar(190) DEFAULT NULL,
  `shop_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1,
  `cover_image` varchar(255) DEFAULT NULL,
  `parent_sku` varchar(100) DEFAULT NULL,
  `price_min` bigint(20) DEFAULT NULL,
  `price_max` bigint(20) DEFAULT NULL,
  `selling_price_min` bigint(20) DEFAULT NULL,
  `selling_price_max` bigint(20) DEFAULT NULL,
  `has_discount` tinyint(1) DEFAULT 0,
  `total_stock` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `liked_count` int(11) DEFAULT 0,
  `sold_count` int(11) DEFAULT 0,
  `promotion_data` longtext DEFAULT NULL,
  `create_time` int(11) DEFAULT NULL,
  `modify_time` int(11) DEFAULT NULL,
  `raw_data` longtext DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `last_seen_run_id` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `fk_shop_id` (`shop_id`),
  KEY `ix_products_source_scope` (`channel_id`,`shop_id`,`external_id`),
  KEY `ix_products_modify_scope` (`shop_id`,`modify_time`,`id`),
  KEY `ix_products_shop_status` (`shop_id`,`status`,`modify_time`,`id`),
  CONSTRAINT `fk_shop_id` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `promotion_shop_snapshots`;
CREATE TABLE `promotion_shop_snapshots` (
  `shop_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unknown',
  `payload` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`),
  KEY `promotion_snapshots_status` (`status`),
  KEY `promotion_snapshots_synced` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `reconciliation_findings`;
CREATE TABLE `reconciliation_findings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` char(36) NOT NULL,
  `issue_type` varchar(100) NOT NULL,
  `severity` varchar(20) NOT NULL DEFAULT 'warning',
  `table_name` varchar(80) NOT NULL,
  `row_pk` varchar(190) DEFAULT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `details_json` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_reconciliation_findings_run` (`run_id`,`issue_type`),
  KEY `ix_reconciliation_findings_scope` (`shop_id`,`channel_id`,`issue_type`),
  CONSTRAINT `fk_reconciliation_findings_run` FOREIGN KEY (`run_id`) REFERENCES `reconciliation_runs` (`run_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `reconciliation_runs`;
CREATE TABLE `reconciliation_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `run_id` char(36) NOT NULL,
  `shop_id` int(11) DEFAULT NULL,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `mode` varchar(30) NOT NULL DEFAULT 'read_only',
  `status` varchar(30) NOT NULL DEFAULT 'running',
  `checks_total` int(10) unsigned NOT NULL DEFAULT 0,
  `findings_total` int(10) unsigned NOT NULL DEFAULT 0,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reconciliation_runs_run_id` (`run_id`),
  KEY `ix_reconciliation_runs_scope` (`shop_id`,`channel_id`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `shipment_events`;
CREATE TABLE `shipment_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shipment_id` bigint(20) unsigned NOT NULL,
  `event_code` varchar(80) DEFAULT NULL,
  `status_normalized` varchar(50) DEFAULT NULL,
  `event_at` datetime NOT NULL,
  `location` varchar(190) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `raw_reference` varchar(190) DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipment_events_hash` (`shipment_id`,`source_hash`),
  KEY `ix_shipment_events_time` (`shipment_id`,`event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) NOT NULL,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `external_id` varchar(190) NOT NULL,
  `carrier_code` varchar(80) DEFAULT NULL,
  `tracking_number` varchar(190) DEFAULT NULL,
  `status_raw` varchar(100) DEFAULT NULL,
  `status_normalized` varchar(50) DEFAULT NULL,
  `ship_by_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `failed_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `last_event_at` datetime DEFAULT NULL,
  `source_updated_at` datetime DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `last_sync_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shipments_source` (`channel_id`,`shop_id`,`external_id`),
  KEY `ix_shipments_order_status` (`order_id`,`status_normalized`,`ship_by_at`),
  KEY `ix_shipments_shop_tracking` (`shop_id`,`tracking_number`),
  KEY `ix_shipments_shop_status_sla` (`shop_id`,`status_normalized`,`ship_by_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `shop_performance_daily`;
CREATE TABLE `shop_performance_daily` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `metric_date` date NOT NULL,
  `source` varchar(40) NOT NULL DEFAULT 'homepage',
  `shop_pv` bigint(20) DEFAULT NULL,
  `shop_uv` bigint(20) DEFAULT NULL,
  `product_clicks` bigint(20) DEFAULT NULL,
  `hybrid_uv` bigint(20) DEFAULT NULL,
  `paid_gmv` bigint(20) DEFAULT NULL,
  `place_gmv` bigint(20) DEFAULT NULL,
  `confirmed_gmv` bigint(20) DEFAULT NULL,
  `paid_orders` bigint(20) DEFAULT NULL,
  `place_orders` bigint(20) DEFAULT NULL,
  `confirmed_orders` bigint(20) DEFAULT NULL,
  `paid_sales_per_order` decimal(18,4) DEFAULT NULL,
  `place_sales_per_order` decimal(18,4) DEFAULT NULL,
  `confirmed_sales_per_order` decimal(18,4) DEFAULT NULL,
  `shop_uv_to_paid_buyers_rate` decimal(18,8) DEFAULT NULL,
  `shop_uv_to_placed_buyers_rate` decimal(18,8) DEFAULT NULL,
  `shop_uv_to_confirmed_buyers_rate` decimal(18,8) DEFAULT NULL,
  `product_clicks_to_placed_orders_rate` decimal(18,8) DEFAULT NULL,
  `product_clicks_to_paid_orders_rate` decimal(18,8) DEFAULT NULL,
  `product_clicks_to_confirmed_orders_rate` decimal(18,8) DEFAULT NULL,
  `raw_payload` longtext DEFAULT NULL,
  `synced_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shop_performance_daily_unique` (`shop_id`,`metric_date`,`source`),
  KEY `shop_performance_daily_shop_date` (`shop_id`,`metric_date`),
  KEY `shop_performance_daily_synced` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `shops`;
CREATE TABLE `shops` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_id` smallint(5) unsigned DEFAULT NULL,
  `shop_id` bigint(20) DEFAULT NULL,
  `account_id` int(11) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `shop_logo` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `cookie` text DEFAULT NULL,
  `address` text DEFAULT NULL,
  `sync_status` varchar(50) DEFAULT NULL,
  `last_successful_sync_at` datetime DEFAULT NULL,
  `last_sync_attempt_at` datetime DEFAULT NULL,
  `balances` bigint(20) DEFAULT 0,
  `total_products` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ads_credit` bigint(20) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `ix_shops_account_channel` (`account_id`,`channel_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_checkpoints`;
CREATE TABLE `sync_checkpoints` (
  `shop_id` int(11) NOT NULL,
  `sync_type` varchar(50) NOT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'diff',
  `cursor` text DEFAULT NULL,
  `page_number` int(11) NOT NULL DEFAULT 0,
  `seen_products` longtext DEFAULT NULL,
  `seen_models` longtext DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'running',
  `last_error` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`,`sync_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `sync_entity_snapshots`;
CREATE TABLE `sync_entity_snapshots` (
  `sync_run_id` bigint(20) unsigned NOT NULL,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `external_id` varchar(190) NOT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `seen_at` datetime NOT NULL,
  PRIMARY KEY (`sync_run_id`,`entity_type`,`external_id`),
  KEY `ix_sync_snapshots_scope` (`shop_id`,`channel_id`,`entity_type`,`external_id`),
  CONSTRAINT `fk_sync_snapshots_run` FOREIGN KEY (`sync_run_id`) REFERENCES `sync_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_errors`;
CREATE TABLE `sync_errors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_run_id` bigint(20) unsigned NOT NULL,
  `shop_id` int(11) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` varchar(190) DEFAULT NULL,
  `error_code` varchar(80) NOT NULL,
  `retryable` tinyint(1) NOT NULL DEFAULT 0,
  `retry_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ix_sync_errors_run` (`sync_run_id`,`created_at`),
  KEY `ix_sync_errors_shop` (`shop_id`,`error_code`,`created_at`),
  CONSTRAINT `fk_sync_errors_run` FOREIGN KEY (`sync_run_id`) REFERENCES `sync_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_job_orders`;
CREATE TABLE `sync_job_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_id` bigint(20) unsigned NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'queued',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `next_retry_at` datetime DEFAULT NULL,
  `lease_until` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_job_orders_unique` (`job_id`,`order_id`),
  KEY `sync_job_orders_claim` (`job_id`,`status`,`next_retry_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_jobs`;
CREATE TABLE `sync_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `sync_type` varchar(50) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'queued',
  `priority` smallint(6) NOT NULL DEFAULT 100,
  `trigger_type` varchar(30) NOT NULL DEFAULT 'manual',
  `idempotency_key` varchar(190) NOT NULL,
  `lock_token` char(36) DEFAULT NULL,
  `lease_expires_at` datetime DEFAULT NULL,
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `next_retry_at` datetime DEFAULT NULL,
  `checkpoint_cursor` text DEFAULT NULL,
  `checkpoint_page` int(10) unsigned NOT NULL DEFAULT 0,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `last_error_code` varchar(80) DEFAULT NULL,
  `last_error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `mode` varchar(16) NOT NULL DEFAULT 'diff',
  `page_sentinel` text DEFAULT NULL,
  `page_number` int(11) NOT NULL DEFAULT 1,
  `total_indexed` int(11) NOT NULL DEFAULT 0,
  `total_detail` int(11) NOT NULL DEFAULT 0,
  `processed_detail` int(11) NOT NULL DEFAULT 0,
  `failed_detail` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `lease_until` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync_jobs_idempotency` (`shop_id`,`sync_type`,`idempotency_key`),
  KEY `ix_sync_jobs_claim` (`status`,`priority`,`next_retry_at`,`id`),
  KEY `ix_sync_jobs_shop` (`shop_id`,`sync_type`,`status`),
  KEY `fk_sync_jobs_channel` (`channel_id`),
  CONSTRAINT `fk_sync_jobs_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_pages`;
CREATE TABLE `sync_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_run_id` bigint(20) unsigned NOT NULL,
  `page_number` int(10) unsigned NOT NULL,
  `cursor` text DEFAULT NULL,
  `source_count` int(10) unsigned NOT NULL DEFAULT 0,
  `fetched_count` int(10) unsigned NOT NULL DEFAULT 0,
  `checksum` char(64) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'running',
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `job_id` bigint(20) unsigned DEFAULT NULL,
  `sentinel` text DEFAULT NULL,
  `next_sentinel` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `orders_indexed` int(11) NOT NULL DEFAULT 0,
  `next_retry_at` datetime DEFAULT NULL,
  `lease_until` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sync_pages_run_page` (`sync_run_id`,`page_number`),
  CONSTRAINT `fk_sync_pages_run` FOREIGN KEY (`sync_run_id`) REFERENCES `sync_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_rate_buckets`;
CREATE TABLE `sync_rate_buckets` (
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `window_started_at` datetime NOT NULL,
  `request_count` int(10) unsigned NOT NULL DEFAULT 0,
  `window_limit` int(10) unsigned NOT NULL DEFAULT 30,
  `cooldown_until` datetime DEFAULT NULL,
  `consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `circuit_state` varchar(20) NOT NULL DEFAULT 'closed',
  `last_operation` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`,`channel_id`),
  KEY `ix_sync_rate_buckets_cooldown` (`circuit_state`,`cooldown_until`,`window_started_at`),
  KEY `fk_sync_rate_buckets_channel` (`channel_id`),
  CONSTRAINT `fk_sync_rate_buckets_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_rate_limits`;
CREATE TABLE `sync_rate_limits` (
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `sync_type` varchar(50) NOT NULL,
  `window_started_at` datetime NOT NULL,
  `request_count` int(10) unsigned NOT NULL DEFAULT 0,
  `window_limit` int(10) unsigned NOT NULL DEFAULT 30,
  `cooldown_until` datetime DEFAULT NULL,
  `consecutive_failures` int(10) unsigned NOT NULL DEFAULT 0,
  `circuit_state` varchar(20) NOT NULL DEFAULT 'closed',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`,`channel_id`,`sync_type`),
  KEY `ix_sync_rate_cooldown` (`circuit_state`,`cooldown_until`,`window_started_at`),
  KEY `fk_sync_rate_channel` (`channel_id`),
  CONSTRAINT `fk_sync_rate_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_run_entity_totals`;
CREATE TABLE `sync_run_entity_totals` (
  `sync_run_id` bigint(20) unsigned NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `expected_count` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`sync_run_id`,`entity_type`),
  CONSTRAINT `fk_sync_run_entity_totals_run` FOREIGN KEY (`sync_run_id`) REFERENCES `sync_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_runs`;
CREATE TABLE `sync_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `sync_job_id` bigint(20) unsigned NOT NULL,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `sync_type` varchar(50) NOT NULL,
  `source_window_start` datetime DEFAULT NULL,
  `source_window_end` datetime DEFAULT NULL,
  `source_snapshot_version` varchar(190) DEFAULT NULL,
  `fetched_count` int(10) unsigned NOT NULL DEFAULT 0,
  `inserted_count` int(10) unsigned NOT NULL DEFAULT 0,
  `updated_count` int(10) unsigned NOT NULL DEFAULT 0,
  `deleted_count` int(10) unsigned NOT NULL DEFAULT 0,
  `skipped_count` int(10) unsigned NOT NULL DEFAULT 0,
  `duplicate_count` int(10) unsigned NOT NULL DEFAULT 0,
  `error_count` int(10) unsigned NOT NULL DEFAULT 0,
  `status` varchar(30) NOT NULL DEFAULT 'running',
  `is_final_page` tinyint(1) NOT NULL DEFAULT 0,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `job_id` bigint(20) unsigned DEFAULT NULL,
  `phase` varchar(24) DEFAULT NULL,
  `fetched_pages` int(11) NOT NULL DEFAULT 0,
  `indexed_orders` int(11) NOT NULL DEFAULT 0,
  `detail_success` int(11) NOT NULL DEFAULT 0,
  `detail_failed` int(11) NOT NULL DEFAULT 0,
  `api_requests` int(11) NOT NULL DEFAULT 0,
  `heartbeat_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_sync_runs_job` (`sync_job_id`,`started_at`),
  KEY `ix_sync_runs_shop_type` (`shop_id`,`sync_type`,`started_at`),
  KEY `fk_sync_runs_channel` (`channel_id`),
  CONSTRAINT `fk_sync_runs_channel` FOREIGN KEY (`channel_id`) REFERENCES `channels` (`id`),
  CONSTRAINT `fk_sync_runs_job` FOREIGN KEY (`sync_job_id`) REFERENCES `sync_jobs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `sync_schedules`;
CREATE TABLE `sync_schedules` (
  `shop_id` int(11) NOT NULL,
  `sync_type` varchar(50) NOT NULL,
  `interval_seconds` int(11) NOT NULL DEFAULT 900,
  `full_interval_seconds` int(11) NOT NULL DEFAULT 0,
  `mode` varchar(16) NOT NULL DEFAULT 'diff',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `next_run_at` datetime DEFAULT NULL,
  `last_enqueued_at` datetime DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `last_full_at` datetime DEFAULT NULL,
  `last_error` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`shop_id`,`sync_type`),
  KEY `sync_schedules_due` (`enabled`,`next_run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `wallet_accounts`;
CREATE TABLE `wallet_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `shop_id` int(11) NOT NULL,
  `channel_id` smallint(5) unsigned NOT NULL,
  `account_type` varchar(50) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'IDR',
  `external_account_id` varchar(190) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wallet_accounts_scope` (`shop_id`,`channel_id`,`account_type`,`currency`),
  KEY `ix_wallet_accounts_external` (`channel_id`,`external_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `wallet_transactions`;
CREATE TABLE `wallet_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `wallet_account_id` bigint(20) unsigned NOT NULL,
  `external_transaction_id` varchar(190) NOT NULL,
  `transaction_type` varchar(50) NOT NULL,
  `direction` varchar(20) NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `currency` char(3) NOT NULL DEFAULT 'IDR',
  `occurred_at` datetime NOT NULL,
  `settlement_id` varchar(190) DEFAULT NULL,
  `source_hash` char(64) DEFAULT NULL,
  `sync_run_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_wallet_transactions_external` (`wallet_account_id`,`external_transaction_id`),
  KEY `ix_wallet_transactions_time` (`wallet_account_id`,`occurred_at`),
  KEY `ix_wallet_transactions_settlement` (`settlement_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
