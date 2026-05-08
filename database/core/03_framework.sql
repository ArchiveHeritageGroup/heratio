-- ============================================================================
-- Heratio standalone — framework schema (atom-framework + 10 schema migrations)
-- ============================================================================
-- Captured from /usr/share/nginx/archive/atom-framework/database/install.sql
-- (AHG framework, 295 tables) + database/migrations/*.sql (10 schema migrations)
-- at 2026-04-30. Heratio-owned standalone install schema. Idempotent — safe to re-run.
--
-- All DROPs removed; every CREATE TABLE is IF NOT EXISTS so overlay
-- deployments (where the framework already created these tables) re-run as
-- no-ops. ALTER TABLE ADD COLUMN/INDEX statements use stored-procedure
-- table+column existence guards (originally upstream + extended here for
-- standalone-install ordering — fresh DBs don't yet have plugin tables).
-- MODIFY COLUMN is naturally idempotent. INSERT statements use INSERT IGNORE
-- for re-run safety. COMMENT clauses on columns are moved to end-of-definition
-- (MySQL 8 strict syntax requirement).
--
-- 2 of the 12 upstream migrations are SEED-ONLY (data, not schema) and are
-- excluded from this file; they belong in database/seeds/ per the install
-- plan §6:
--   - 2026_01_07_sector_level_defaults.sql       (level_of_description rows)
--   - 2026_03_08_dropdown_seed_from_enums.sql    (3,800+ ahg_dropdown rows)
--
-- Phase 1 #2 of the standalone install plan (docs/standalone-install-plan.md).
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ============================================================================
-- atom-framework/database/install.sql (5,986 lines, 295 tables)
-- ============================================================================

-- =============================================================================
-- AtoM Framework + AHG Plugins - Complete Schema
-- Generated: 2025-12-29 14:22:32
-- Custom tables: 295
-- =============================================================================


-- Table: access_audit_log
CREATE TABLE IF NOT EXISTS `access_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT '1',
  `access_level` varchar(50) DEFAULT 'full',
  `denial_reasons` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_granted` (`granted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_justification_template
CREATE TABLE IF NOT EXISTS `access_justification_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `paia_section` varchar(50) DEFAULT NULL,
  `template_text` text NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request
CREATE TABLE IF NOT EXISTS `access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_type` VARCHAR(64) DEFAULT 'clearance' COMMENT 'clearance, object, repository, authority, researcher',
  `scope_type` VARCHAR(61) DEFAULT 'single' COMMENT 'single, with_children, collection, repository_all',
  `user_id` int unsigned NOT NULL,
  `requested_classification_id` int unsigned NOT NULL,
  `current_classification_id` int unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `justification` text,
  `urgency` VARCHAR(39) DEFAULT 'normal' COMMENT 'low, normal, high, critical',
  `status` VARCHAR(57) DEFAULT 'pending' COMMENT 'pending, approved, denied, cancelled, expired',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_classification` (`requested_classification_id`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  KEY `current_classification_id` (`current_classification_id`),
  CONSTRAINT `access_request_ibfk_1` FOREIGN KEY (`requested_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `access_request_ibfk_2` FOREIGN KEY (`current_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_approver
CREATE TABLE IF NOT EXISTS `access_request_approver` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `min_classification_level` int unsigned DEFAULT '0',
  `max_classification_level` int unsigned DEFAULT '5',
  `email_notifications` tinyint(1) DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_justification
CREATE TABLE IF NOT EXISTS `access_request_justification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `justification_text` text NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_log
CREATE TABLE IF NOT EXISTS `access_request_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `action` VARCHAR(77) NOT NULL COMMENT 'created, updated, approved, denied, cancelled, expired, escalated',
  `actor_id` int unsigned DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_actor_id` (`actor_id`),
  CONSTRAINT `access_request_log_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: access_request_scope
CREATE TABLE IF NOT EXISTS `access_request_scope` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `object_type` VARCHAR(49) NOT NULL COMMENT 'information_object, repository, actor',
  `object_id` int unsigned NOT NULL,
  `include_descendants` tinyint(1) DEFAULT '0',
  `object_title` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_object` (`object_type`,`object_id`),
  CONSTRAINT `access_request_scope_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: actor_face_index
CREATE TABLE IF NOT EXISTS `actor_face_index` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int NOT NULL,
  `face_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path to cropped face image',
  `source_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original image path',
  `bounding_box` json DEFAULT NULL COMMENT '{"x":0,"y":0,"width":100,"height":100}',
  `face_encoding` blob COMMENT 'Face embedding vector for similarity matching',
  `encoding_version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Version of encoding algorithm',
  `confidence` float DEFAULT '1',
  `detection_backend` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'local/aws/azure/google',
  `attributes` json DEFAULT NULL COMMENT 'Age, gender, emotions, etc.',
  `landmarks` json DEFAULT NULL COMMENT 'Facial landmarks',
  `is_primary` tinyint(1) DEFAULT '0' COMMENT 'Primary face for this actor',
  `is_active` tinyint(1) DEFAULT '1',
  `is_verified` tinyint(1) DEFAULT '0' COMMENT 'Human verified match',
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verified_by` (`verified_by`),
  KEY `created_by` (`created_by`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_verified` (`is_verified`),
  KEY `idx_backend` (`detection_backend`),
  CONSTRAINT `actor_face_index_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `actor` (`id`) ON DELETE CASCADE,
  CONSTRAINT `actor_face_index_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `actor_face_index_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: agreement_rights_vocabulary
CREATE TABLE IF NOT EXISTS `agreement_rights_vocabulary` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'usage, restriction, condition, license',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_rights_category` (`category`),
  KEY `idx_rights_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: ahg_settings
CREATE TABLE IF NOT EXISTS `ahg_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` VARCHAR(49) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, integer, boolean, json, float',
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_sensitive` tinyint(1) DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_setting_group` (`setting_group`),
  KEY `idx_setting_key` (`setting_key`),
  CONSTRAINT `ahg_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=894 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_contacts
CREATE TABLE IF NOT EXISTS `ahg_vendor_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_vendor` (`vendor_id`),
  KEY `idx_contact_primary` (`is_primary`),
  CONSTRAINT `ahg_vendor_contacts_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_metrics
CREATE TABLE IF NOT EXISTS `ahg_vendor_metrics` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `year` int NOT NULL,
  `month` int DEFAULT NULL,
  `total_transactions` int DEFAULT '0',
  `completed_transactions` int DEFAULT '0',
  `on_time_returns` int DEFAULT '0',
  `late_returns` int DEFAULT '0',
  `total_items_handled` int DEFAULT '0',
  `total_value_handled` decimal(15,2) DEFAULT '0.00',
  `total_cost` decimal(15,2) DEFAULT '0.00',
  `avg_turnaround_days` decimal(5,1) DEFAULT NULL,
  `avg_quality_score` decimal(3,2) DEFAULT NULL,
  `calculated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_period` (`vendor_id`,`year`,`month`),
  KEY `idx_vm_vendor` (`vendor_id`),
  KEY `idx_vm_year` (`year`),
  CONSTRAINT `ahg_vendor_metrics_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_service_types
CREATE TABLE IF NOT EXISTS `ahg_vendor_service_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `requires_insurance` tinyint(1) DEFAULT '0',
  `requires_valuation` tinyint(1) DEFAULT '0',
  `typical_duration_days` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_service_slug` (`slug`),
  KEY `idx_service_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_services
CREATE TABLE IF NOT EXISTS `ahg_vendor_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `vendor_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `fixed_rate` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_preferred` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vendor_service` (`vendor_id`,`service_type_id`),
  KEY `idx_vs_vendor` (`vendor_id`),
  KEY `idx_vs_service` (`service_type_id`),
  CONSTRAINT `ahg_vendor_services_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ahg_vendor_services_ibfk_2` FOREIGN KEY (`service_type_id`) REFERENCES `ahg_vendor_service_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_attachments
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `attachment_type` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'quote, invoice, condition_report, photo, receipt, certificate, other',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ta_transaction` (`transaction_id`),
  KEY `idx_ta_type` (`attachment_type`),
  CONSTRAINT `ahg_vendor_transaction_attachments_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_history
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `status_from` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_th_transaction` (`transaction_id`),
  KEY `idx_th_date` (`created_at`),
  CONSTRAINT `ahg_vendor_transaction_history_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transaction_items
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `item_title` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_before` text COLLATE utf8mb4_unicode_ci,
  `condition_before_rating` VARCHAR(49) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical',
  `condition_after` text COLLATE utf8mb4_unicode_ci,
  `condition_after_rating` VARCHAR(49) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical',
  `declared_value` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `service_description` text COLLATE utf8mb4_unicode_ci,
  `service_completed` tinyint(1) DEFAULT '0',
  `service_notes` text COLLATE utf8mb4_unicode_ci,
  `item_cost` decimal(10,2) DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ti_transaction` (`transaction_id`),
  KEY `idx_ti_object` (`information_object_id`),
  KEY `idx_ti_completed` (`service_completed`),
  CONSTRAINT `ahg_vendor_transaction_items_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `ahg_vendor_transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendor_transactions
CREATE TABLE IF NOT EXISTS `ahg_vendor_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_id` int NOT NULL,
  `service_type_id` int NOT NULL,
  `status` VARCHAR(137) COLLATE utf8mb4_unicode_ci DEFAULT 'pending_approval' COMMENT 'pending_approval, approved, dispatched, received_by_vendor, in_progress, completed, ready_for_collection, returned, cancelled',
  `request_date` date NOT NULL,
  `approval_date` date DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `requested_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `dispatched_by` int DEFAULT NULL,
  `received_by` int DEFAULT NULL,
  `estimated_cost` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `quote_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `payment_status` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT 'not_invoiced' COMMENT 'not_invoiced, invoiced, paid, disputed',
  `payment_date` date DEFAULT NULL,
  `total_insured_value` decimal(15,2) DEFAULT NULL,
  `insurance_arranged` tinyint(1) DEFAULT '0',
  `insurance_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `courier_company` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispatch_notes` text COLLATE utf8mb4_unicode_ci,
  `vendor_notes` text COLLATE utf8mb4_unicode_ci,
  `return_notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `has_quotes` tinyint(1) DEFAULT '0',
  `has_invoices` tinyint(1) DEFAULT '0',
  `has_condition_reports` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_number` (`transaction_number`),
  KEY `idx_trans_number` (`transaction_number`),
  KEY `idx_trans_vendor` (`vendor_id`),
  KEY `idx_trans_service` (`service_type_id`),
  KEY `idx_trans_status` (`status`),
  KEY `idx_trans_dispatch` (`dispatch_date`),
  KEY `idx_trans_expected` (`expected_return_date`),
  KEY `idx_trans_payment` (`payment_status`),
  CONSTRAINT `ahg_vendor_transactions_ibfk_1` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`),
  CONSTRAINT `ahg_vendor_transactions_ibfk_2` FOREIGN KEY (`service_type_id`) REFERENCES `ahg_vendor_service_types` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ahg_vendors
CREATE TABLE IF NOT EXISTS `ahg_vendors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `vendor_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor_type` VARCHAR(56) COLLATE utf8mb4_unicode_ci DEFAULT 'company' COMMENT 'company, individual, institution, government',
  `registration_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vat_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `street_address` text COLLATE utf8mb4_unicode_ci,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'South Africa',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_alt` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_branch_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank_account_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_insurance` tinyint(1) DEFAULT '0',
  `insurance_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_policy_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_expiry_date` date DEFAULT NULL,
  `insurance_coverage_amount` decimal(15,2) DEFAULT NULL,
  `quality_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `reliability_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `price_rating` tinyint DEFAULT NULL COMMENT '1-5 stars',
  `status` VARCHAR(57) COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, inactive, suspended, pending_approval',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_preferred` tinyint(1) DEFAULT '0',
  `is_bbbee_compliant` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `vendor_code` (`vendor_code`),
  KEY `idx_vendor_name` (`name`),
  KEY `idx_vendor_slug` (`slug`),
  KEY `idx_vendor_code` (`vendor_code`),
  KEY `idx_vendor_status` (`status`),
  KEY `idx_vendor_type` (`vendor_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension
CREATE TABLE IF NOT EXISTS `atom_extension` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'GPL-3.0',
  `status` VARCHAR(57) COLLATE utf8mb4_unicode_ci DEFAULT 'installed' COMMENT 'installed, enabled, disabled, pending_removal',
  `protection_level` VARCHAR(42) COLLATE utf8mb4_unicode_ci DEFAULT 'extension' COMMENT 'core, system, theme, extension',
  `theme_support` json DEFAULT NULL,
  `requires_framework` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_atom` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_php` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dependencies` json DEFAULT NULL,
  `optional_dependencies` json DEFAULT NULL,
  `tables_created` json DEFAULT NULL,
  `shared_tables` json DEFAULT NULL,
  `helpers` json DEFAULT NULL,
  `install_task` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uninstall_task` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installed_at` datetime DEFAULT NULL,
  `enabled_at` datetime DEFAULT NULL,
  `disabled_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_machine_name` (`machine_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_admin
CREATE TABLE IF NOT EXISTS `atom_extension_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `admin_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `section` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `route_params` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `badge_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_key` (`admin_key`),
  KEY `fk_admin_extension` (`extension_id`),
  CONSTRAINT `fk_admin_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_audit
CREATE TABLE IF NOT EXISTS `atom_extension_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int DEFAULT NULL,
  `extension_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `action` VARCHAR(157) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'discovered, installed, enabled, disabled, uninstalled, upgraded, downgraded, backup_created, backup_restored, data_deleted, config_changed, error',
  `performed_by` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_extension_name` (`extension_name`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_menu
CREATE TABLE IF NOT EXISTS `atom_extension_menu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `menu_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `menu_location` VARCHAR(45) COLLATE utf8mb4_unicode_ci DEFAULT 'main' COMMENT 'main, admin, user, footer, mobile',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_i18n` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `route_params` json DEFAULT NULL,
  `badge_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_cache_ttl` int DEFAULT '60',
  `visibility_callback` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `context` json DEFAULT NULL,
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `is_separator` tinyint(1) DEFAULT '0',
  `css_class` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_key` (`menu_key`),
  KEY `fk_menu_extension` (`extension_id`),
  CONSTRAINT `fk_menu_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_pending_deletion
CREATE TABLE IF NOT EXISTS `atom_extension_pending_deletion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_count` int DEFAULT '0',
  `backup_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `backup_size` bigint DEFAULT NULL,
  `delete_after` datetime NOT NULL,
  `status` VARCHAR(69) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, processing, deleted, restored, cancelled, failed',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_extension_name` (`extension_name`),
  KEY `idx_status` (`status`),
  KEY `idx_delete_after` (`delete_after`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_setting
CREATE TABLE IF NOT EXISTS `atom_extension_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int DEFAULT NULL,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` VARCHAR(49) COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, integer, boolean, json, array',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_extension_setting` (`extension_id`,`setting_key`),
  CONSTRAINT `fk_setting_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_extension_widget
CREATE TABLE IF NOT EXISTS `atom_extension_widget` (
  `id` int NOT NULL AUTO_INCREMENT,
  `extension_id` int NOT NULL,
  `widget_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `widget_type` VARCHAR(55) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'stat_card, chart, list, table, html, custom',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `data_callback` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dashboard` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'central',
  `section` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_ttl` int DEFAULT '300',
  `sort_order` int DEFAULT '100',
  `is_enabled` tinyint(1) DEFAULT '1',
  `config` json DEFAULT NULL,
  `permissions` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_widget_key` (`widget_key`),
  KEY `fk_widget_extension` (`extension_id`),
  CONSTRAINT `fk_widget_extension` FOREIGN KEY (`extension_id`) REFERENCES `atom_extension` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_framework_migrations
CREATE TABLE IF NOT EXISTS `atom_framework_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: atom_isbn_cache
CREATE TABLE IF NOT EXISTS `atom_isbn_cache` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `isbn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isbn_10` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn_13` varchar(13) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json NOT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'worldcat',
  `oclc_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_isbn` (`isbn`),
  KEY `idx_isbn_10` (`isbn_10`),
  KEY `idx_isbn_13` (`isbn_13`),
  KEY `idx_oclc` (`oclc_number`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_isbn_lookup_audit
CREATE TABLE IF NOT EXISTS `atom_isbn_lookup_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `isbn` varchar(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `information_object_id` int DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `fields_populated` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `lookup_time_ms` int unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_isbn` (`isbn`),
  KEY `idx_user` (`user_id`),
  KEY `idx_io` (`information_object_id`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_isbn_audit_io` FOREIGN KEY (`information_object_id`) REFERENCES `information_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_isbn_audit_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_isbn_provider
CREATE TABLE IF NOT EXISTS `atom_isbn_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_endpoint` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key_setting` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reference to atom_setting key',
  `priority` int NOT NULL DEFAULT '100',
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `rate_limit_per_minute` int unsigned DEFAULT NULL,
  `response_format` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'json' COMMENT 'json, xml, marcxml',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_enabled_priority` (`enabled`,`priority`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_block
-- KNOWN ISSUE: page_id is `int NOT NULL` here but atom_landing_page.id is
-- `int unsigned` on overlay deployments — upstream FK type mismatch. Fresh
-- standalone install creates this table before atom_landing_page so MySQL
-- accepts it; overlay re-run on a DB that already has atom_landing_page
-- with int unsigned will fail. Fix scope: change atom_landing_page.id
-- in 00_core_schema.sql to `int signed` OR change all atom_landing_*.id
-- columns to `int unsigned`. Deferred — needs cross-table coordination.
CREATE TABLE IF NOT EXISTS `atom_landing_block` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `block_type_id` int NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `config` json DEFAULT NULL,
  `css_classes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `container_type` VARCHAR(42) COLLATE utf8mb4_unicode_ci DEFAULT 'container' COMMENT 'fluid, container, container-lg',
  `background_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text_color` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `padding_top` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '3',
  `padding_bottom` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '3',
  `position` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_block_id` int DEFAULT NULL,
  `column_slot` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `block_type_id` (`block_type_id`),
  KEY `idx_page_position` (`page_id`,`position`),
  KEY `idx_parent` (`parent_block_id`),
  CONSTRAINT `atom_landing_block_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `atom_landing_page` (`id`) ON DELETE CASCADE,
  CONSTRAINT `atom_landing_block_ibfk_2` FOREIGN KEY (`block_type_id`) REFERENCES `atom_landing_block_type` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `atom_landing_block_ibfk_3` FOREIGN KEY (`parent_block_id`) REFERENCES `atom_landing_block` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_block_type
CREATE TABLE IF NOT EXISTS `atom_landing_block_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `machine_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-square',
  `template` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_config` json DEFAULT NULL,
  `config_schema` json DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `machine_name` (`machine_name`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page
CREATE TABLE IF NOT EXISTS `atom_landing_page` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_default` (`is_default`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page_audit
CREATE TABLE IF NOT EXISTS `atom_landing_page_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int DEFAULT NULL,
  `block_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` json DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_page` (`page_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_landing_page_version
CREATE TABLE IF NOT EXISTS `atom_landing_page_version` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_id` int NOT NULL,
  `version_number` int NOT NULL,
  `blocks_snapshot` json NOT NULL,
  `status` VARCHAR(38) COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT 'draft, published, archived',
  `published_at` datetime DEFAULT NULL,
  `published_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_page_version` (`page_id`,`version_number`),
  KEY `idx_status` (`status`),
  CONSTRAINT `atom_landing_page_version_ibfk_1` FOREIGN KEY (`page_id`) REFERENCES `atom_landing_page` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_migrations
CREATE TABLE IF NOT EXISTS `atom_migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_plugin
CREATE TABLE IF NOT EXISTS `atom_plugin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `class_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'ahg',
  `is_enabled` tinyint(1) DEFAULT '0',
  `is_core` tinyint(1) DEFAULT '0',
  `is_locked` tinyint(1) DEFAULT '0',
  `status` VARCHAR(57) DEFAULT 'enabled' COMMENT 'installed, enabled, disabled, pending_removal',
  `load_order` int DEFAULT '100',
  `plugin_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `record_check_query` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SQL query to check if plugin has associated records',
  `enabled_at` timestamp NULL DEFAULT NULL,
  `disabled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_is_enabled` (`is_enabled`),
  KEY `idx_category` (`category`),
  KEY `idx_load_order` (`load_order`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_plugin_audit
CREATE TABLE IF NOT EXISTS `atom_plugin_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(255) NOT NULL,
  `action` varchar(50) NOT NULL,
  `previous_state` varchar(50) DEFAULT NULL,
  `new_state` varchar(50) DEFAULT NULL,
  `reason` text,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_plugin` (`plugin_name`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: atom_plugin_dependency
CREATE TABLE IF NOT EXISTS `atom_plugin_dependency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` bigint unsigned NOT NULL,
  `requires_plugin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `min_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_optional` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plugin_dependency` (`plugin_id`,`requires_plugin`),
  KEY `idx_requires_plugin` (`requires_plugin`),
  CONSTRAINT `atom_plugin_dependency_ibfk_1` FOREIGN KEY (`plugin_id`) REFERENCES `atom_plugin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: atom_plugin_hook
CREATE TABLE IF NOT EXISTS `atom_plugin_hook` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` bigint unsigned NOT NULL,
  `event_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listener_class` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `listener_method` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `plugin_id` (`plugin_id`),
  KEY `idx_event_name` (`event_name`),
  KEY `idx_event_active` (`event_name`,`is_active`),
  CONSTRAINT `atom_plugin_hook_ibfk_1` FOREIGN KEY (`plugin_id`) REFERENCES `atom_plugin` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: backup_history
CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `backup_id` varchar(100) NOT NULL,
  `backup_path` varchar(500) NOT NULL,
  `backup_type` VARCHAR(46) DEFAULT 'full' COMMENT 'full, database, files, incremental',
  `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, failed',
  `size_bytes` bigint DEFAULT '0',
  `components` json DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `backup_id` (`backup_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: backup_schedule
CREATE TABLE IF NOT EXISTS `backup_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `frequency` VARCHAR(42) DEFAULT 'daily' COMMENT 'hourly, daily, weekly, monthly',
  `time` time DEFAULT '02:00:00',
  `day_of_week` tinyint DEFAULT NULL,
  `day_of_month` tinyint DEFAULT NULL,
  `include_database` tinyint(1) DEFAULT '1',
  `include_uploads` tinyint(1) DEFAULT '1',
  `include_plugins` tinyint(1) DEFAULT '1',
  `include_framework` tinyint(1) DEFAULT '1',
  `retention_days` int DEFAULT '30',
  `is_active` tinyint(1) DEFAULT '1',
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: backup_setting
CREATE TABLE IF NOT EXISTS `backup_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(42) DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: cart
CREATE TABLE IF NOT EXISTS `cart` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `archival_description_id` varchar(50) DEFAULT NULL,
  `archival_description` varchar(1024) DEFAULT NULL,
  `slug` varchar(1024) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=900678 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_assessment_schedule
CREATE TABLE IF NOT EXISTS `condition_assessment_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `frequency_months` int DEFAULT '12',
  `last_assessment_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_due` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_conservation_link
CREATE TABLE IF NOT EXISTS `condition_conservation_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `condition_event_id` int unsigned NOT NULL,
  `treatment_id` int unsigned NOT NULL,
  `link_type` varchar(50) DEFAULT 'treatment',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`condition_event_id`),
  KEY `idx_treatment` (`treatment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_damage
CREATE TABLE IF NOT EXISTS `condition_damage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `damage_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'overall',
  `severity` VARCHAR(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'minor' COMMENT 'minor, moderate, severe',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dimensions` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `treatment_required` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_report` (`condition_report_id`),
  KEY `idx_cd_type` (`damage_type`),
  KEY `idx_cd_severity` (`severity`),
  CONSTRAINT `condition_damage_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_event
CREATE TABLE IF NOT EXISTS `condition_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'assessment',
  `event_date` date NOT NULL,
  `assessor` varchar(255) DEFAULT NULL,
  `condition_status` varchar(50) DEFAULT NULL,
  `damage_types` json DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `notes` text,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`event_date`),
  KEY `idx_status` (`condition_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: condition_image
CREATE TABLE IF NOT EXISTS `condition_image` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_type` VARCHAR(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'general, detail, damage, before, after, raking, uv',
  `annotations` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ci_report` (`condition_report_id`),
  CONSTRAINT `condition_image_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_report
CREATE TABLE IF NOT EXISTS `condition_report` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `assessor_user_id` int unsigned DEFAULT NULL,
  `assessment_date` date NOT NULL,
  `context` VARCHAR(133) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'routine' COMMENT 'acquisition, loan_out, loan_in, loan_return, exhibition, storage, conservation, routine, incident, insurance, deaccession',
  `overall_rating` VARCHAR(53) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good' COMMENT 'excellent, good, fair, poor, unacceptable',
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `priority` VARCHAR(37) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'low, normal, high, urgent',
  `next_check_date` date DEFAULT NULL,
  `environmental_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `storage_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cr_object` (`information_object_id`),
  KEY `idx_cr_date` (`assessment_date`),
  KEY `idx_cr_rating` (`overall_rating`),
  KEY `idx_cr_next_check` (`next_check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_vocabulary
CREATE TABLE IF NOT EXISTS `condition_vocabulary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` VARCHAR(79) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'damage_type, severity, condition, priority, material, location_zone',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'For UI display',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'FontAwesome icon class',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_code` (`vocabulary_type`,`code`),
  KEY `idx_type_active` (`vocabulary_type`,`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: condition_vocabulary_term
CREATE TABLE IF NOT EXISTS `condition_vocabulary_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` varchar(50) NOT NULL,
  `term_code` varchar(50) NOT NULL,
  `term_label` varchar(255) NOT NULL,
  `term_description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vocab_term` (`vocabulary_type`,`term_code`),
  KEY `idx_type` (`vocabulary_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: contact_information_extended
CREATE TABLE IF NOT EXISTS `contact_information_extended` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contact_information_id` int NOT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mr, Mrs, Dr, Prof, etc.',
  `role` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Job title/position',
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Department/Division',
  `cell` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Mobile/Cell phone',
  `id_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID/Passport number',
  `alternative_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secondary email',
  `alternative_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Secondary phone',
  `preferred_contact_method` VARCHAR(41) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'email, phone, cell, fax, mail',
  `language_preference` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Preferred communication language',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT 'Additional notes',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_id` (`contact_information_id`),
  CONSTRAINT `fk_contact_info_ext` FOREIGN KEY (`contact_information_id`) REFERENCES `contact_information` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: creative_commons_license
CREATE TABLE IF NOT EXISTS `creative_commons_license` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '4.0',
  `allows_adaptation` tinyint(1) DEFAULT '1',
  `allows_commercial` tinyint(1) DEFAULT '1',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `requires_sharealike` tinyint(1) DEFAULT '0',
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_uri` (`uri`),
  UNIQUE KEY `uq_cc_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: creative_commons_license_i18n
CREATE TABLE IF NOT EXISTS `creative_commons_license_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creative_commons_license_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_i18n` (`creative_commons_license_id`,`culture`),
  KEY `idx_cc_i18n_parent` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: custom_watermark
CREATE TABLE IF NOT EXISTS `custom_watermark` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned DEFAULT NULL COMMENT 'NULL = global watermark',
  `name` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `position` varchar(50) DEFAULT 'center',
  `opacity` decimal(3,2) DEFAULT '0.40',
  `created_by` int unsigned DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: dam_iptc_metadata
CREATE TABLE IF NOT EXISTS `dam_iptc_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `creator` varchar(255) DEFAULT NULL,
  `creator_job_title` varchar(255) DEFAULT NULL,
  `creator_address` text,
  `creator_city` varchar(255) DEFAULT NULL,
  `creator_state` varchar(255) DEFAULT NULL,
  `creator_postal_code` varchar(50) DEFAULT NULL,
  `creator_country` varchar(255) DEFAULT NULL,
  `creator_phone` varchar(100) DEFAULT NULL,
  `creator_email` varchar(255) DEFAULT NULL,
  `creator_website` varchar(500) DEFAULT NULL,
  `headline` varchar(500) DEFAULT NULL,
  `caption` text,
  `keywords` text,
  `iptc_subject_code` varchar(255) DEFAULT NULL,
  `intellectual_genre` varchar(255) DEFAULT NULL,
  `iptc_scene` varchar(255) DEFAULT NULL,
  `date_created` date DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `state_province` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `sublocation` varchar(500) DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `job_id` varchar(255) DEFAULT NULL,
  `instructions` text,
  `credit_line` varchar(500) DEFAULT NULL,
  `source` varchar(500) DEFAULT NULL,
  `copyright_notice` text,
  `rights_usage_terms` text,
  `license_type` VARCHAR(91) DEFAULT NULL COMMENT 'rights_managed, royalty_free, creative_commons, public_domain, editorial, other',
  `license_url` varchar(500) DEFAULT NULL,
  `license_expiry` date DEFAULT NULL,
  `model_release_status` VARCHAR(52) DEFAULT 'none' COMMENT 'none, not_applicable, unlimited, limited',
  `model_release_id` varchar(255) DEFAULT NULL,
  `property_release_status` VARCHAR(52) DEFAULT 'none' COMMENT 'none, not_applicable, unlimited, limited',
  `property_release_id` varchar(255) DEFAULT NULL,
  `artwork_title` varchar(500) DEFAULT NULL,
  `artwork_creator` varchar(255) DEFAULT NULL,
  `artwork_date` varchar(100) DEFAULT NULL,
  `artwork_source` varchar(500) DEFAULT NULL,
  `artwork_copyright` text,
  `persons_shown` text,
  `camera_make` varchar(100) DEFAULT NULL,
  `camera_model` varchar(100) DEFAULT NULL,
  `lens` varchar(255) DEFAULT NULL,
  `focal_length` varchar(50) DEFAULT NULL,
  `aperture` varchar(20) DEFAULT NULL,
  `shutter_speed` varchar(50) DEFAULT NULL,
  `iso_speed` int DEFAULT NULL,
  `flash_used` tinyint(1) DEFAULT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `gps_altitude` decimal(10,2) DEFAULT NULL,
  `image_width` int DEFAULT NULL,
  `image_height` int DEFAULT NULL,
  `resolution_x` int DEFAULT NULL,
  `resolution_y` int DEFAULT NULL,
  `resolution_unit` varchar(20) DEFAULT NULL,
  `color_space` varchar(50) DEFAULT NULL,
  `bit_depth` int DEFAULT NULL,
  `orientation` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_creator` (`creator`),
  KEY `idx_keywords` (`keywords`(255)),
  KEY `idx_date_created` (`date_created`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: digital_object_faces
CREATE TABLE IF NOT EXISTS `digital_object_faces` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `face_index` int DEFAULT '0' COMMENT 'Face number in image (0-based)',
  `face_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounding_box` json DEFAULT NULL,
  `confidence` float DEFAULT '0',
  `matched_actor_id` int DEFAULT NULL,
  `match_similarity` float DEFAULT NULL,
  `match_verified` tinyint(1) DEFAULT '0',
  `alternative_matches` json DEFAULT NULL,
  `attributes` json DEFAULT NULL,
  `is_identified` tinyint(1) DEFAULT '0',
  `identification_source` VARCHAR(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'auto, manual, verified',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `identified_by` int DEFAULT NULL,
  `identified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `identified_by` (`identified_by`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_matched_actor` (`matched_actor_id`),
  KEY `idx_identified` (`is_identified`),
  KEY `idx_confidence` (`confidence`),
  CONSTRAINT `digital_object_faces_ibfk_1` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `digital_object_faces_ibfk_2` FOREIGN KEY (`matched_actor_id`) REFERENCES `actor` (`id`) ON DELETE SET NULL,
  CONSTRAINT `digital_object_faces_ibfk_3` FOREIGN KEY (`identified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: digital_object_metadata
CREATE TABLE IF NOT EXISTS `digital_object_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `file_type` VARCHAR(51) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'image, pdf, office, video, audio, other',
  `raw_metadata` json DEFAULT NULL COMMENT 'Complete raw metadata as extracted',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creator` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `keywords` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `copyright` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_created` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_width` int DEFAULT NULL,
  `image_height` int DEFAULT NULL,
  `camera_make` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_latitude` decimal(10,8) DEFAULT NULL,
  `gps_longitude` decimal(11,8) DEFAULT NULL,
  `gps_altitude` decimal(10,2) DEFAULT NULL,
  `page_count` int DEFAULT NULL,
  `word_count` int DEFAULT NULL,
  `author` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `application` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL COMMENT 'Duration in seconds',
  `duration_formatted` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolution` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frame_rate` decimal(6,2) DEFAULT NULL,
  `bitrate` int DEFAULT NULL,
  `sample_rate` int DEFAULT NULL,
  `channels` int DEFAULT NULL,
  `artist` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `track_number` int DEFAULT NULL,
  `genre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extraction_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `extraction_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extraction_errors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_file_type` (`file_type`),
  KEY `idx_creator` (`creator`),
  KEY `idx_date_created` (`date_created`),
  KEY `idx_gps` (`gps_latitude`,`gps_longitude`),
  CONSTRAINT `digital_object_metadata_ibfk_1` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: display_collection_type
CREATE TABLE IF NOT EXISTS `display_collection_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `default_profile_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_collection_type_i18n
CREATE TABLE IF NOT EXISTS `display_collection_type_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dcti_type` FOREIGN KEY (`id`) REFERENCES `display_collection_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_field
CREATE TABLE IF NOT EXISTS `display_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `field_group` VARCHAR(68) DEFAULT 'description' COMMENT 'identity, description, context, access, technical, admin',
  `data_type` VARCHAR(101) DEFAULT 'text' COMMENT 'text, textarea, date, daterange, number, select, multiselect, relation, file, actor, term',
  `source_table` varchar(100) DEFAULT NULL,
  `source_column` varchar(100) DEFAULT NULL,
  `source_i18n` tinyint(1) DEFAULT '0',
  `property_type_id` int DEFAULT NULL,
  `taxonomy_id` int DEFAULT NULL,
  `relation_type_id` int DEFAULT NULL,
  `event_type_id` int DEFAULT NULL,
  `isad_element` varchar(50) DEFAULT NULL,
  `spectrum_unit` varchar(50) DEFAULT NULL,
  `dc_element` varchar(50) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_field_i18n
CREATE TABLE IF NOT EXISTS `display_field_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `help_text` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dfi_field` FOREIGN KEY (`id`) REFERENCES `display_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_level
CREATE TABLE IF NOT EXISTS `display_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_code` varchar(30) DEFAULT NULL,
  `domain` varchar(20) DEFAULT 'universal',
  `valid_parent_codes` json DEFAULT NULL,
  `valid_child_codes` json DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `atom_term_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_level_i18n
CREATE TABLE IF NOT EXISTS `display_level_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dli_level` FOREIGN KEY (`id`) REFERENCES `display_level` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_mode_global
CREATE TABLE IF NOT EXISTS `display_mode_global` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `available_modes` json DEFAULT NULL COMMENT 'JSON array of enabled modes for this module',
  `allow_user_override` tinyint(1) DEFAULT '1' COMMENT 'Allow users to change from default',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module` (`module`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: display_object_config
CREATE TABLE IF NOT EXISTS `display_object_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `object_type` varchar(30) DEFAULT 'archive',
  `primary_profile_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`object_type`),
  CONSTRAINT `fk_doc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_object_profile
CREATE TABLE IF NOT EXISTS `display_object_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `profile_id` int NOT NULL,
  `context` varchar(30) DEFAULT 'default',
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`object_id`,`profile_id`,`context`),
  KEY `idx_object` (`object_id`),
  KEY `fk_dop_profile` (`profile_id`),
  CONSTRAINT `fk_dop_profile` FOREIGN KEY (`profile_id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_profile
CREATE TABLE IF NOT EXISTS `display_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `domain` varchar(20) DEFAULT NULL,
  `layout_mode` VARCHAR(74) DEFAULT 'detail' COMMENT 'detail, hierarchy, grid, gallery, list, card, masonry, catalog',
  `thumbnail_size` VARCHAR(50) DEFAULT 'medium' COMMENT 'none, small, medium, large, hero, full',
  `thumbnail_position` VARCHAR(48) DEFAULT 'left' COMMENT 'left, right, top, background, inline',
  `identity_fields` json DEFAULT NULL,
  `description_fields` json DEFAULT NULL,
  `context_fields` json DEFAULT NULL,
  `access_fields` json DEFAULT NULL,
  `technical_fields` json DEFAULT NULL,
  `hidden_fields` json DEFAULT NULL,
  `field_labels` json DEFAULT NULL,
  `available_actions` json DEFAULT NULL,
  `css_class` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: display_profile_i18n
CREATE TABLE IF NOT EXISTS `display_profile_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dpi_profile` FOREIGN KEY (`id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Table: email_setting
CREATE TABLE IF NOT EXISTS `email_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(60) DEFAULT 'text' COMMENT 'text, email, number, boolean, textarea, password',
  `setting_group` varchar(50) DEFAULT 'general',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_key` (`setting_key`),
  KEY `idx_group` (`setting_group`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: embargo
CREATE TABLE IF NOT EXISTS `embargo` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `embargo_type` VARCHAR(55) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'full, metadata_only, digital_object, custom',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `is_perpetual` tinyint(1) DEFAULT '0',
  `status` VARCHAR(44) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'active, expired, lifted, pending',
  `created_by` int DEFAULT NULL,
  `lifted_by` int DEFAULT NULL,
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lift_reason` text COLLATE utf8mb4_unicode_ci,
  `notify_on_expiry` tinyint(1) DEFAULT '1',
  `notify_days_before` int DEFAULT '30',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_embargo_object` (`object_id`),
  KEY `idx_embargo_status` (`object_id`,`status`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_object_active` (`object_id`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_audit
CREATE TABLE IF NOT EXISTS `embargo_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `action` VARCHAR(83) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created, modified, lifted, extended, exception_added, exception_removed',
  `user_id` int DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emb_audit_embargo` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_exception
CREATE TABLE IF NOT EXISTS `embargo_exception` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `exception_type` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'user, group, ip_range, repository',
  `exception_id` int DEFAULT NULL,
  `ip_range_start` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_range_end` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `granted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emb_exc_embargo` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: embargo_i18n
CREATE TABLE IF NOT EXISTS `embargo_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `embargo_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `public_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_embargo_i18n` (`embargo_id`,`culture`),
  KEY `idx_embargo_i18n_parent` (`embargo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights
CREATE TABLE IF NOT EXISTS `extended_rights` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `rights_statement_id` bigint unsigned DEFAULT NULL,
  `creative_commons_license_id` bigint unsigned DEFAULT NULL,
  `rights_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `rights_holder` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rights_holder_uri` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ext_rights_object` (`object_id`),
  KEY `idx_ext_rights_rs` (`rights_statement_id`),
  KEY `idx_ext_rights_cc` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_batch_log
CREATE TABLE IF NOT EXISTS `extended_rights_batch_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_count` int NOT NULL DEFAULT '0',
  `object_ids` json DEFAULT NULL,
  `data` json DEFAULT NULL,
  `results` json DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_performed_at` (`performed_at`),
  KEY `idx_performed_by` (`performed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_i18n
CREATE TABLE IF NOT EXISTS `extended_rights_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `extended_rights_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `rights_note` text COLLATE utf8mb4_unicode_ci,
  `usage_conditions` text COLLATE utf8mb4_unicode_ci,
  `copyright_notice` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_rights_i18n` (`extended_rights_id`,`culture`),
  KEY `idx_ext_rights_i18n_parent` (`extended_rights_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: extended_rights_tk_label
CREATE TABLE IF NOT EXISTS `extended_rights_tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `extended_rights_id` bigint unsigned NOT NULL,
  `tk_label_id` bigint unsigned NOT NULL,
  `community_id` int DEFAULT NULL,
  `community_note` text COLLATE utf8mb4_unicode_ci,
  `assigned_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ext_rights_tk` (`extended_rights_id`,`tk_label_id`),
  KEY `idx_ext_rights_tk_label` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: favorites
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `archival_description_id` varchar(50) DEFAULT NULL,
  `archival_description` varchar(1024) DEFAULT NULL,
  `slug` varchar(1024) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=900676 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: feedback
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int NOT NULL,
  `feed_name` varchar(50) DEFAULT NULL,
  `feed_surname` varchar(50) DEFAULT NULL,
  `feed_phone` varchar(50) DEFAULT NULL,
  `feed_email` varchar(50) DEFAULT NULL,
  `feed_relationship` text,
  `parent_id` varchar(50) DEFAULT NULL,
  `feed_type_id` int DEFAULT NULL,
  `lft` int NOT NULL,
  `rgt` int NOT NULL,
  `source_culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `feedback_FK_1` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: feedback_i18n
CREATE TABLE IF NOT EXISTS `feedback_i18n` (
  `name` varchar(1024) DEFAULT NULL,
  `unique_identifier` varchar(1024) DEFAULT NULL,
  `remarks` text,
  `id` int NOT NULL,
  `object_id` text,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `status_id` int NOT NULL,
  `culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `feedback_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `feedback` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist
CREATE TABLE IF NOT EXISTS `gallery_artist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `display_name` varchar(255) NOT NULL,
  `sort_name` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `death_date` date DEFAULT NULL,
  `death_place` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `artist_type` VARCHAR(53) DEFAULT 'individual' COMMENT 'individual, collective, studio, anonymous',
  `medium_specialty` text,
  `movement_style` text,
  `active_period` varchar(100) DEFAULT NULL,
  `represented` tinyint(1) DEFAULT '0',
  `representation_start` date DEFAULT NULL,
  `representation_end` date DEFAULT NULL,
  `representation_terms` text,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `exclusivity` tinyint(1) DEFAULT '0',
  `biography` text,
  `artist_statement` text,
  `cv` text,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `studio_address` text,
  `instagram` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_name` (`display_name`),
  KEY `idx_represented` (`represented`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist_bibliography
CREATE TABLE IF NOT EXISTS `gallery_artist_bibliography` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `entry_type` VARCHAR(84) DEFAULT 'article' COMMENT 'book, catalog, article, review, interview, thesis, website, video, other',
  `title` varchar(500) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `publication` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `volume` varchar(50) DEFAULT NULL,
  `issue` varchar(50) DEFAULT NULL,
  `pages` varchar(50) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_type` (`entry_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_artist_exhibition_history
CREATE TABLE IF NOT EXISTS `gallery_artist_exhibition_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `exhibition_type` VARCHAR(51) DEFAULT 'group' COMMENT 'solo, group, duo, retrospective, survey',
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `curator` varchar(255) DEFAULT NULL,
  `catalog_published` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition
CREATE TABLE IF NOT EXISTS `gallery_exhibition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `description` text,
  `curator` varchar(255) DEFAULT NULL,
  `exhibition_type` VARCHAR(60) DEFAULT 'temporary' COMMENT 'permanent, temporary, traveling, virtual, pop-up',
  `status` VARCHAR(77) DEFAULT 'planning' COMMENT 'planning, confirmed, installing, open, closing, closed, cancelled',
  `venue_id` int DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `opening_event_date` datetime DEFAULT NULL,
  `closing_event_date` datetime DEFAULT NULL,
  `target_audience` text,
  `themes` text,
  `budget` decimal(12,2) DEFAULT NULL,
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `visitor_count` int DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  KEY `idx_venue` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition_checklist
CREATE TABLE IF NOT EXISTS `gallery_exhibition_checklist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exhibition_id` int NOT NULL,
  `task_name` varchar(255) NOT NULL,
  `description` text,
  `category` VARCHAR(82) DEFAULT 'planning' COMMENT 'planning, design, marketing, installation, opening, operation, closing',
  `assigned_to` varchar(255) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int DEFAULT NULL,
  `priority` VARCHAR(39) DEFAULT 'medium' COMMENT 'low, medium, high, critical',
  `status` VARCHAR(54) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled',
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exhibition` (`exhibition_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_exhibition_object
CREATE TABLE IF NOT EXISTS `gallery_exhibition_object` (
  `id` int NOT NULL AUTO_INCREMENT,
  `exhibition_id` int NOT NULL,
  `object_id` int NOT NULL,
  `space_id` int DEFAULT NULL,
  `display_order` int DEFAULT '0',
  `section` varchar(255) DEFAULT NULL,
  `display_notes` text,
  `label_text` text,
  `installation_requirements` text,
  `installed_at` datetime DEFAULT NULL,
  `installed_by` int DEFAULT NULL,
  `removed_at` datetime DEFAULT NULL,
  `condition_on_install` text,
  `condition_on_remove` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_exhibit` (`exhibition_id`,`object_id`),
  KEY `idx_exhibition` (`exhibition_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_facility_report
CREATE TABLE IF NOT EXISTS `gallery_facility_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `report_type` VARCHAR(30) NOT NULL COMMENT 'incoming, outgoing',
  `institution_name` varchar(255) DEFAULT NULL,
  `building_age` int DEFAULT NULL,
  `construction_type` varchar(100) DEFAULT NULL,
  `fire_detection` tinyint(1) DEFAULT '0',
  `fire_suppression` tinyint(1) DEFAULT '0',
  `security_24hr` tinyint(1) DEFAULT '0',
  `security_guards` tinyint(1) DEFAULT '0',
  `cctv` tinyint(1) DEFAULT '0',
  `intrusion_detection` tinyint(1) DEFAULT '0',
  `climate_controlled` tinyint(1) DEFAULT '0',
  `temperature_range` varchar(50) DEFAULT NULL,
  `humidity_range` varchar(50) DEFAULT NULL,
  `light_levels` varchar(100) DEFAULT NULL,
  `uv_filtering` tinyint(1) DEFAULT '0',
  `trained_handlers` tinyint(1) DEFAULT '0',
  `loading_dock` tinyint(1) DEFAULT '0',
  `freight_elevator` tinyint(1) DEFAULT '0',
  `storage_available` tinyint(1) DEFAULT '0',
  `insurance_coverage` varchar(255) DEFAULT NULL,
  `completed_by` varchar(255) DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `approved` tinyint(1) DEFAULT '0',
  `approved_by` int DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_insurance_policy
CREATE TABLE IF NOT EXISTS `gallery_insurance_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_number` varchar(100) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `policy_type` VARCHAR(77) DEFAULT 'all_risk' COMMENT 'all_risk, named_perils, transit, exhibition, permanent_collection',
  `coverage_amount` decimal(14,2) DEFAULT NULL,
  `deductible` decimal(12,2) DEFAULT NULL,
  `premium` decimal(12,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_loan
CREATE TABLE IF NOT EXISTS `gallery_loan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) NOT NULL,
  `loan_type` VARCHAR(30) NOT NULL COMMENT 'incoming, outgoing',
  `status` VARCHAR(123) DEFAULT 'inquiry' COMMENT 'inquiry, requested, approved, agreed, in_transit_out, on_loan, in_transit_return, returned, cancelled, declined',
  `purpose` varchar(255) DEFAULT NULL,
  `exhibition_id` int DEFAULT NULL,
  `institution_name` varchar(255) NOT NULL,
  `institution_address` text,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_fee` decimal(12,2) DEFAULT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `insurance_provider` varchar(255) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `special_conditions` text,
  `agreement_signed` tinyint(1) DEFAULT '0',
  `agreement_date` date DEFAULT NULL,
  `facility_report_received` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_number` (`loan_number`),
  KEY `idx_type` (`loan_type`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`loan_start_date`,`loan_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_loan_object
CREATE TABLE IF NOT EXISTS `gallery_loan_object` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `object_id` int NOT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `condition_out` text,
  `condition_out_date` date DEFAULT NULL,
  `condition_out_by` int DEFAULT NULL,
  `condition_return` text,
  `condition_return_date` date DEFAULT NULL,
  `condition_return_by` int DEFAULT NULL,
  `packing_instructions` text,
  `display_requirements` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_space
CREATE TABLE IF NOT EXISTS `gallery_space` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venue_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `area_sqm` decimal(10,2) DEFAULT NULL,
  `wall_length_m` decimal(10,2) DEFAULT NULL,
  `height_m` decimal(10,2) DEFAULT NULL,
  `lighting_type` varchar(100) DEFAULT NULL,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `max_weight_kg` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venue` (`venue_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_valuation
CREATE TABLE IF NOT EXISTS `gallery_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_type` VARCHAR(79) DEFAULT 'insurance' COMMENT 'insurance, market, replacement, auction_estimate, probate, donation',
  `value_amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'ZAR',
  `valuation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `appraiser_name` varchar(255) DEFAULT NULL,
  `appraiser_credentials` varchar(255) DEFAULT NULL,
  `appraiser_organization` varchar(255) DEFAULT NULL,
  `methodology` text,
  `comparables` text,
  `notes` text,
  `document_path` varchar(500) DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`valuation_type`),
  KEY `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: gallery_venue
CREATE TABLE IF NOT EXISTS `gallery_venue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `address` text,
  `total_area_sqm` decimal(10,2) DEFAULT NULL,
  `max_capacity` int DEFAULT NULL,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `security_level` varchar(50) DEFAULT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: getty_vocabulary_link
CREATE TABLE IF NOT EXISTS `getty_vocabulary_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `term_id` int unsigned NOT NULL,
  `vocabulary` VARCHAR(26) NOT NULL COMMENT 'aat, tgn, ulan',
  `getty_uri` varchar(255) NOT NULL,
  `getty_id` varchar(50) NOT NULL,
  `getty_pref_label` varchar(500) DEFAULT NULL,
  `getty_scope_note` text,
  `status` VARCHAR(51) NOT NULL DEFAULT 'pending' COMMENT 'confirmed, suggested, rejected, pending',
  `confidence` decimal(3,2) NOT NULL DEFAULT '0.00',
  `confirmed_by_user_id` int unsigned DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_term_getty` (`term_id`,`getty_uri`),
  KEY `idx_vocabulary` (`vocabulary`),
  KEY `idx_status` (`status`),
  KEY `idx_getty_id` (`getty_id`),
  KEY `idx_vocab_status` (`vocabulary`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Table: iiif_3d_manifest
CREATE TABLE IF NOT EXISTS `iiif_3d_manifest` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `manifest_json` longtext,
  `manifest_hash` varchar(64) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_id` (`model_id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `iiif_3d_manifest_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_annotation
CREATE TABLE IF NOT EXISTS `iiif_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `canvas_id` int DEFAULT NULL,
  `target_canvas` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_selector` json DEFAULT NULL,
  `motivation` VARCHAR(94) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'commenting' COMMENT 'commenting, tagging, describing, linking, transcribing, identifying, supplementing',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_canvas` (`target_canvas`(255)),
  KEY `idx_motivation` (`motivation`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_annotation_body
CREATE TABLE IF NOT EXISTS `iiif_annotation_body` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annotation_id` int NOT NULL,
  `body_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'TextualBody',
  `body_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text/plain',
  `body_language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `body_purpose` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_annotation` (`annotation_id`),
  CONSTRAINT `iiif_annotation_body_ibfk_1` FOREIGN KEY (`annotation_id`) REFERENCES `iiif_annotation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_collection
CREATE TABLE IF NOT EXISTS `iiif_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `attribution` varchar(500) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `viewing_hint` VARCHAR(59) DEFAULT 'individuals' COMMENT 'individuals, paged, continuous, multi-part, top',
  `nav_date` date DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_public` (`is_public`),
  CONSTRAINT `iiif_collection_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `iiif_collection` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_collection_i18n
CREATE TABLE IF NOT EXISTS `iiif_collection_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection_culture` (`collection_id`,`culture`),
  CONSTRAINT `iiif_collection_i18n_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_collection_item
CREATE TABLE IF NOT EXISTS `iiif_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `manifest_uri` varchar(1000) DEFAULT NULL,
  `item_type` VARCHAR(32) DEFAULT 'manifest' COMMENT 'manifest, collection',
  `label` varchar(500) DEFAULT NULL,
  `description` text,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`),
  CONSTRAINT `iiif_collection_item_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE,
  CONSTRAINT `iiif_collection_item_ibfk_2` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: iiif_ocr_block
CREATE TABLE IF NOT EXISTS `iiif_ocr_block` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocr_id` int NOT NULL,
  `page_number` int DEFAULT '1',
  `block_type` VARCHAR(41) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'word' COMMENT 'word, line, paragraph, region',
  `text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `x` int NOT NULL,
  `y` int NOT NULL,
  `width` int NOT NULL,
  `height` int NOT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `block_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ocr` (`ocr_id`),
  KEY `idx_page` (`page_number`),
  KEY `idx_type` (`block_type`),
  KEY `idx_text` (`text`(100)),
  CONSTRAINT `iiif_ocr_block_ibfk_1` FOREIGN KEY (`ocr_id`) REFERENCES `iiif_ocr_text` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_ocr_text
CREATE TABLE IF NOT EXISTS `iiif_ocr_text` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `format` VARCHAR(29) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'plain' COMMENT 'plain, alto, hocr',
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `confidence` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  FULLTEXT KEY `ft_text` (`full_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: iiif_viewer_settings
CREATE TABLE IF NOT EXISTS `iiif_viewer_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: information_object_physical_location
CREATE TABLE IF NOT EXISTS `information_object_physical_location` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `physical_object_id` int DEFAULT NULL COMMENT 'Link to physical_object container',
  `shelf` varchar(50) DEFAULT NULL,
  `row` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `box_number` varchar(50) DEFAULT NULL,
  `folder_number` varchar(50) DEFAULT NULL,
  `item_number` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `extent_value` decimal(10,2) DEFAULT NULL,
  `extent_unit` varchar(50) DEFAULT NULL COMMENT 'items, pages, cm, etc',
  `condition_status` VARCHAR(49) DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical',
  `condition_notes` text,
  `access_status` VARCHAR(59) DEFAULT 'available' COMMENT 'available, in_use, restricted, offsite, missing',
  `last_accessed_at` datetime DEFAULT NULL,
  `accessed_by` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_info_object` (`information_object_id`),
  KEY `idx_physical_object` (`physical_object_id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_access_status` (`access_status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: level_of_description_sector
CREATE TABLE IF NOT EXISTS `level_of_description_sector` (
  `id` int NOT NULL AUTO_INCREMENT,
  `term_id` int NOT NULL,
  `sector` varchar(50) NOT NULL,
  `display_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_term_sector` (`term_id`,`sector`),
  KEY `idx_sector` (`sector`),
  KEY `idx_term` (`term_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================
-- Default Level of Description Sector Mappings
-- Uses name lookup to handle different term IDs across installations
-- =====================================================

-- Archive sector (ISAD standard levels)
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Record group';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Fonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subfonds';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Collection';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Series';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Subseries';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 70, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'File';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 80, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Item';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'archive', 90, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Part';

-- Museum sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artifact';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Object';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'museum', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Specimen';

-- Library sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Book';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Monograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Periodical';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Journal';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 45, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Article';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Manuscript';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'library', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Document';

-- Gallery sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Artwork';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Photograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'gallery', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Installation';

-- DAM sector
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 10, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Photograph';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 20, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Audio';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 30, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Video';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 40, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Image';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 50, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Document';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 60, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = '3D Model';
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order, created_at)
SELECT t.id, 'dam', 70, NOW() FROM term t JOIN term_i18n ti ON t.id = ti.id AND ti.culture = 'en' WHERE t.taxonomy_id = 34 AND ti.name = 'Dataset';

-- Table: library_item
CREATE TABLE IF NOT EXISTS `library_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `material_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monograph' COMMENT 'monograph, serial, volume, issue, chapter, article, manuscript, map, pamphlet',
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsibility_statement` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `call_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classification_scheme` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'dewey, lcc, udc, bliss, colon, custom',
  `classification_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dewey_decimal` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cutter_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shelf_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copy_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume_designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issn` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lccn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `oclc_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `openlibrary_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `goodreads_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `librarything_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `openlibrary_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ebook_preview_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_url_original` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition_statement` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_place` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_date` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_date` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `printing` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pagination` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimensions` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physical_details` text COLLATE utf8mb4_unicode_ci,
  `language` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accompanying_material` text COLLATE utf8mb4_unicode_ci,
  `series_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `series_issn` varchar(9) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subseries_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `general_note` text COLLATE utf8mb4_unicode_ci,
  `bibliography_note` text COLLATE utf8mb4_unicode_ci,
  `contents_note` text COLLATE utf8mb4_unicode_ci,
  `summary` text COLLATE utf8mb4_unicode_ci,
  `target_audience` text COLLATE utf8mb4_unicode_ci,
  `system_requirements` text COLLATE utf8mb4_unicode_ci,
  `binding_note` text COLLATE utf8mb4_unicode_ci,
  `frequency` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `former_frequency` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `numbering_peculiarities` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_start_date` date DEFAULT NULL,
  `publication_end_date` date DEFAULT NULL,
  `publication_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'current, ceased, suspended',
  `total_copies` smallint unsigned NOT NULL DEFAULT '1',
  `available_copies` smallint unsigned NOT NULL DEFAULT '1',
  `circulation_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available' COMMENT 'available, on_loan, processing, lost, withdrawn, reference',
  `cataloging_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cataloging_rules` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'aacr2, rda, isbd',
  `encoding_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: library_item_creator
CREATE TABLE IF NOT EXISTS `library_item_creator` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `library_item_id` bigint unsigned NOT NULL,
  `name` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'author',
  `sort_order` int DEFAULT '0',
  `authority_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_library_item_id` (`library_item_id`),
  KEY `idx_name` (`name`(100)),
  CONSTRAINT `library_item_creator_ibfk_1` FOREIGN KEY (`library_item_id`) REFERENCES `library_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: library_item_subject
CREATE TABLE IF NOT EXISTS `library_item_subject` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `library_item_id` bigint unsigned NOT NULL,
  `heading` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'topic',
  `source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_library_item_id` (`library_item_id`),
  KEY `idx_heading` (`heading`(100)),
  CONSTRAINT `library_item_subject_ibfk_1` FOREIGN KEY (`library_item_id`) REFERENCES `library_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=329 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan
CREATE TABLE IF NOT EXISTS `loan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `loan_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'out, in',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `purpose` VARCHAR(97) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exhibition' COMMENT 'exhibition, research, conservation, photography, education, filming, long_term, other',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `partner_institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_contact_phone` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `partner_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `request_date` date NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `insurance_type` VARCHAR(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrower' COMMENT 'borrower, lender, shared, government, self',
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ZAR',
  `insurance_policy_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_provider` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `loan_fee` decimal(12,2) DEFAULT NULL,
  `loan_fee_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ZAR',
  `internal_approver_id` int unsigned DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_loan_number_unique` (`loan_number`),
  KEY `idx_loan_type` (`loan_type`),
  KEY `idx_loan_partner` (`partner_institution`),
  KEY `idx_loan_start` (`start_date`),
  KEY `idx_loan_end` (`end_date`),
  KEY `idx_loan_return` (`return_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_document
CREATE TABLE IF NOT EXISTS `loan_document` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `document_type` VARCHAR(125) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'agreement, facilities_report, condition_report, insurance_certificate, receipt, correspondence, photograph, other',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ld_loan` (`loan_id`),
  KEY `idx_ld_type` (`document_type`),
  CONSTRAINT `loan_document_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_extension
CREATE TABLE IF NOT EXISTS `loan_extension` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `previous_end_date` date NOT NULL,
  `new_end_date` date NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `approved_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_le_loan` (`loan_id`),
  CONSTRAINT `loan_extension_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: loan_object
CREATE TABLE IF NOT EXISTS `loan_object` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` bigint unsigned NOT NULL,
  `information_object_id` int unsigned NOT NULL,
  `object_title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `object_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `condition_report_id` bigint unsigned DEFAULT NULL,
  `special_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lo_loan` (`loan_id`),
  KEY `idx_lo_object` (`information_object_id`),
  CONSTRAINT `loan_object_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_chapters
CREATE TABLE IF NOT EXISTS `media_chapters` (
  `id` int NOT NULL AUTO_INCREMENT,
  `media_metadata_id` int NOT NULL,
  `chapter_index` int NOT NULL,
  `start_time` decimal(12,3) NOT NULL,
  `end_time` decimal(12,3) DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_metadata` (`media_metadata_id`),
  CONSTRAINT `media_chapters_ibfk_1` FOREIGN KEY (`media_metadata_id`) REFERENCES `media_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_derivatives
CREATE TABLE IF NOT EXISTS `media_derivatives` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `derivative_type` VARCHAR(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'thumbnail, poster, preview, waveform',
  `derivative_index` int DEFAULT '0',
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_type` (`derivative_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_metadata
CREATE TABLE IF NOT EXISTS `media_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `media_type` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'audio, video',
  `format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL,
  `bitrate` int DEFAULT NULL,
  `audio_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_sample_rate` int DEFAULT NULL,
  `audio_channels` int DEFAULT NULL,
  `audio_bits_per_sample` int DEFAULT NULL,
  `audio_channel_layout` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_codec` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_width` int DEFAULT NULL,
  `video_height` int DEFAULT NULL,
  `video_frame_rate` decimal(10,3) DEFAULT NULL,
  `video_pixel_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_aspect_ratio` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `artist` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `album` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `make` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `software` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gps_coordinates` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_metadata` json DEFAULT NULL,
  `consolidated_metadata` json DEFAULT NULL,
  `waveform_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `extracted_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_media_type` (`media_type`),
  KEY `idx_format` (`format`),
  FULLTEXT KEY `ft_tags` (`title`,`artist`,`album`,`genre`,`comment`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_processing_queue
CREATE TABLE IF NOT EXISTS `media_processing_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `task_type` VARCHAR(67) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'metadata_extraction, transcription, waveform, thumbnail',
  `task_options` json DEFAULT NULL,
  `status` VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
  `priority` int DEFAULT '0',
  `progress` int DEFAULT '0',
  `progress_message` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retry_count` int DEFAULT '0',
  `max_retries` int DEFAULT '3',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_task_type` (`task_type`),
  KEY `idx_priority` (`priority` DESC),
  KEY `idx_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_processor_settings
CREATE TABLE IF NOT EXISTS `media_processor_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `setting_type` VARCHAR(49) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, integer, float, boolean, json',
  `setting_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `description` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_snippets
CREATE TABLE IF NOT EXISTS `media_snippets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `title` varchar(255) NOT NULL,
  `start_time` decimal(10,3) NOT NULL,
  `end_time` decimal(10,3) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_do_id` (`digital_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: media_speakers
CREATE TABLE IF NOT EXISTS `media_speakers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transcription_id` int NOT NULL,
  `speaker_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `speaker_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_duration` decimal(12,3) DEFAULT NULL,
  `segment_count` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_transcription` (`transcription_id`),
  CONSTRAINT `media_speakers_ibfk_1` FOREIGN KEY (`transcription_id`) REFERENCES `media_transcription` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: media_transcription
CREATE TABLE IF NOT EXISTS `media_transcription` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `transcription_data` json DEFAULT NULL,
  `segment_count` int DEFAULT NULL,
  `duration` decimal(12,3) DEFAULT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `model_used` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vtt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `srt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `txt_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_language` (`language`),
  FULLTEXT KEY `ft_text` (`full_text`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: metadata_extraction_log
CREATE TABLE IF NOT EXISTS `metadata_extraction_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `operation` VARCHAR(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'extract, face_detect, face_match, index_face, bulk',
  `status` VARCHAR(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'success, partial, failed, skipped',
  `metadata_extracted` tinyint(1) DEFAULT '0',
  `faces_detected` int DEFAULT '0',
  `faces_matched` int DEFAULT '0',
  `error_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `processing_time_ms` int DEFAULT NULL,
  `triggered_by` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'upload, job, manual, api',
  `job_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_operation` (`operation`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: museum_metadata
CREATE TABLE IF NOT EXISTS `museum_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `work_type` varchar(50) DEFAULT NULL,
  `object_type` varchar(255) DEFAULT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `materials` text,
  `techniques` text,
  `measurements` varchar(255) DEFAULT NULL,
  `dimensions` varchar(255) DEFAULT NULL,
  `creation_date_earliest` date DEFAULT NULL,
  `creation_date_latest` date DEFAULT NULL,
  `inscription` text,
  `inscriptions` text,
  `condition_notes` text,
  `provenance` text,
  `style_period` varchar(255) DEFAULT NULL,
  `cultural_context` varchar(255) DEFAULT NULL,
  `current_location` text,
  `edition_description` text,
  `state_description` varchar(512) DEFAULT NULL,
  `state_identification` varchar(100) DEFAULT NULL,
  `facture_description` text,
  `technique_cco` varchar(512) DEFAULT NULL,
  `technique_qualifier` varchar(255) DEFAULT NULL,
  `orientation` varchar(100) DEFAULT NULL,
  `physical_appearance` text,
  `color` varchar(255) DEFAULT NULL,
  `shape` varchar(255) DEFAULT NULL,
  `condition_term` varchar(100) DEFAULT NULL,
  `condition_date` date DEFAULT NULL,
  `condition_description` text,
  `condition_agent` varchar(255) DEFAULT NULL,
  `treatment_type` varchar(255) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `treatment_agent` varchar(255) DEFAULT NULL,
  `treatment_description` text,
  `inscription_transcription` text,
  `inscription_type` varchar(100) DEFAULT NULL,
  `inscription_location` varchar(255) DEFAULT NULL,
  `inscription_language` varchar(100) DEFAULT NULL,
  `inscription_translation` text,
  `mark_type` varchar(100) DEFAULT NULL,
  `mark_description` text,
  `mark_location` varchar(255) DEFAULT NULL,
  `related_work_type` varchar(100) DEFAULT NULL,
  `related_work_relationship` varchar(255) DEFAULT NULL,
  `related_work_label` varchar(512) DEFAULT NULL,
  `related_work_id` varchar(255) DEFAULT NULL,
  `current_location_repository` varchar(512) DEFAULT NULL,
  `current_location_geography` varchar(512) DEFAULT NULL,
  `current_location_coordinates` varchar(100) DEFAULT NULL,
  `current_location_ref_number` varchar(255) DEFAULT NULL,
  `creation_place` varchar(512) DEFAULT NULL,
  `creation_place_type` varchar(100) DEFAULT NULL,
  `discovery_place` varchar(512) DEFAULT NULL,
  `discovery_place_type` varchar(100) DEFAULT NULL,
  `provenance_text` text,
  `ownership_history` text,
  `legal_status` varchar(255) DEFAULT NULL,
  `rights_type` varchar(100) DEFAULT NULL,
  `rights_holder` varchar(512) DEFAULT NULL,
  `rights_date` varchar(100) DEFAULT NULL,
  `rights_remarks` text,
  `cataloger_name` varchar(255) DEFAULT NULL,
  `cataloging_date` date DEFAULT NULL,
  `cataloging_institution` varchar(512) DEFAULT NULL,
  `cataloging_remarks` text,
  `record_type` varchar(100) DEFAULT NULL,
  `record_level` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creator_identity` varchar(512) DEFAULT NULL,
  `creator_role` varchar(255) DEFAULT NULL,
  `creator_extent` varchar(255) DEFAULT NULL,
  `creator_qualifier` varchar(255) DEFAULT NULL,
  `creator_attribution` varchar(255) DEFAULT NULL,
  `creation_date_display` varchar(255) DEFAULT NULL,
  `creation_date_qualifier` varchar(100) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `period` varchar(255) DEFAULT NULL,
  `cultural_group` varchar(255) DEFAULT NULL,
  `movement` varchar(255) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `dynasty` varchar(255) DEFAULT NULL,
  `subject_indexing_type` varchar(100) DEFAULT NULL,
  `subject_display` text,
  `subject_extent` varchar(255) DEFAULT NULL,
  `historical_context` text,
  `architectural_context` text,
  `archaeological_context` text,
  `object_class` varchar(255) DEFAULT NULL,
  `object_category` varchar(255) DEFAULT NULL,
  `object_sub_category` varchar(255) DEFAULT NULL,
  `edition_number` varchar(100) DEFAULT NULL,
  `edition_size` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object` (`object_id`),
  CONSTRAINT `museum_metadata_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_fixity_check
CREATE TABLE IF NOT EXISTS `oais_fixity_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `content_id` int DEFAULT NULL,
  `check_type` VARCHAR(31) NOT NULL COMMENT 'md5, sha256, sha512',
  `expected_value` varchar(128) NOT NULL,
  `actual_value` varchar(128) NOT NULL,
  `is_valid` tinyint(1) NOT NULL,
  `checked_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `checked_by` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_is_valid` (`is_valid`),
  CONSTRAINT `oais_fixity_check_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_information_package
CREATE TABLE IF NOT EXISTS `oais_information_package` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_type` VARCHAR(25) NOT NULL COMMENT 'SIP, AIP, DIP',
  `package_id` varchar(255) NOT NULL,
  `object_id` int DEFAULT NULL COMMENT 'Link to information_object',
  `parent_package_id` int DEFAULT NULL COMMENT 'For DIP->AIP relationship',
  `status` VARCHAR(70) DEFAULT 'pending' COMMENT 'pending, ingesting, stored, preserved, disseminated, error',
  `checksum_md5` varchar(32) DEFAULT NULL,
  `checksum_sha256` varchar(64) DEFAULT NULL,
  `checksum_sha512` varchar(128) DEFAULT NULL,
  `total_size` bigint DEFAULT '0',
  `file_count` int DEFAULT '0',
  `storage_location` varchar(500) DEFAULT NULL,
  `preservation_level` VARCHAR(34) DEFAULT 'bit' COMMENT 'bit, logical, semantic',
  `retention_period` int DEFAULT NULL COMMENT 'Years to retain',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ingested_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_id` (`package_id`),
  KEY `parent_package_id` (`parent_package_id`),
  KEY `idx_package_type` (`package_type`),
  KEY `idx_status` (`status`),
  KEY `idx_object_id` (`object_id`),
  CONSTRAINT `oais_information_package_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `oais_information_package_ibfk_2` FOREIGN KEY (`parent_package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_package_content
CREATE TABLE IF NOT EXISTS `oais_package_content` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint DEFAULT '0',
  `mime_type` varchar(100) DEFAULT NULL,
  `checksum_md5` varchar(32) DEFAULT NULL,
  `checksum_sha256` varchar(64) DEFAULT NULL,
  `pronom_puid` varchar(50) DEFAULT NULL COMMENT 'PRONOM format ID',
  `format_name` varchar(255) DEFAULT NULL,
  `format_version` varchar(50) DEFAULT NULL,
  `content_type` VARCHAR(50) DEFAULT 'content' COMMENT 'content, metadata, manifest, signature',
  `is_original` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_pronom` (`pronom_puid`),
  CONSTRAINT `oais_package_content_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oais_package_content_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_premis_event
CREATE TABLE IF NOT EXISTS `oais_premis_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `package_id` int NOT NULL,
  `content_id` int DEFAULT NULL,
  `event_identifier` varchar(255) NOT NULL,
  `event_type` VARCHAR(289) NOT NULL COMMENT 'capture, compression, creation, deaccession, decompression, decryption, deletion, digital_signature_validation, dissemination, encryption, fixity_check, format_identification, ingestion, message_digest_calculation, migration, normalization, replication, validation, virus_check',
  `event_date_time` datetime NOT NULL,
  `event_detail` text,
  `event_outcome` VARCHAR(37) NOT NULL COMMENT 'success, failure, warning',
  `event_outcome_detail` text,
  `linking_agent_identifier` varchar(255) DEFAULT NULL,
  `linking_agent_role` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `content_id` (`content_id`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_event_date` (`event_date_time`),
  CONSTRAINT `oais_premis_event_ibfk_1` FOREIGN KEY (`package_id`) REFERENCES `oais_information_package` (`id`) ON DELETE CASCADE,
  CONSTRAINT `oais_premis_event_ibfk_2` FOREIGN KEY (`content_id`) REFERENCES `oais_package_content` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_preservation_policy
CREATE TABLE IF NOT EXISTS `oais_preservation_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `source_format_puid` varchar(50) DEFAULT NULL,
  `target_format_puid` varchar(50) DEFAULT NULL,
  `action_type` VARCHAR(49) NOT NULL COMMENT 'migrate, normalize, emulate, preserve',
  `priority` int DEFAULT '5',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: oais_pronom_format
CREATE TABLE IF NOT EXISTS `oais_pronom_format` (
  `id` int NOT NULL AUTO_INCREMENT,
  `puid` varchar(50) NOT NULL COMMENT 'e.g., fmt/18 for PDF 1.4',
  `format_name` varchar(255) NOT NULL,
  `format_version` varchar(50) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `extensions` text COMMENT 'JSON array of extensions',
  `risk_level` VARCHAR(39) DEFAULT 'low' COMMENT 'low, medium, high, critical',
  `preservation_action_required` tinyint(1) DEFAULT '0',
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `puid` (`puid`),
  KEY `idx_puid` (`puid`),
  KEY `idx_risk` (`risk_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_audit_log
CREATE TABLE IF NOT EXISTS `object_3d_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` VARCHAR(88) NOT NULL COMMENT 'upload, update, delete, view, ar_view, download, hotspot_add, hotspot_delete',
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_hotspot
CREATE TABLE IF NOT EXISTS `object_3d_hotspot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `hotspot_type` VARCHAR(50) DEFAULT 'annotation' COMMENT 'annotation, info, link, damage, detail',
  `position_x` decimal(10,6) NOT NULL,
  `position_y` decimal(10,6) NOT NULL,
  `position_z` decimal(10,6) NOT NULL,
  `normal_x` decimal(10,6) DEFAULT '0.000000',
  `normal_y` decimal(10,6) DEFAULT '1.000000',
  `normal_z` decimal(10,6) DEFAULT '0.000000',
  `icon` varchar(50) DEFAULT 'info',
  `color` varchar(20) DEFAULT '#1a73e8',
  `link_url` varchar(500) DEFAULT NULL,
  `link_target` VARCHAR(25) DEFAULT '_blank' COMMENT '_self, _blank',
  `display_order` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_hotspot_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_hotspot_i18n
CREATE TABLE IF NOT EXISTS `object_3d_hotspot_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hotspot_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hotspot_culture` (`hotspot_id`,`culture`),
  CONSTRAINT `object_3d_hotspot_i18n_ibfk_1` FOREIGN KEY (`hotspot_id`) REFERENCES `object_3d_hotspot` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_model
CREATE TABLE IF NOT EXISTS `object_3d_model` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `format` VARCHAR(47) DEFAULT 'glb' COMMENT 'glb, gltf, obj, fbx, stl, ply, usdz',
  `vertex_count` int DEFAULT NULL,
  `face_count` int DEFAULT NULL,
  `texture_count` int DEFAULT NULL,
  `animation_count` int DEFAULT '0',
  `has_materials` tinyint(1) DEFAULT '1',
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) DEFAULT '0deg 75deg 105%',
  `min_camera_orbit` varchar(100) DEFAULT NULL,
  `max_camera_orbit` varchar(100) DEFAULT NULL,
  `field_of_view` varchar(20) DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `shadow_softness` decimal(3,2) DEFAULT '1.00',
  `environment_image` varchar(255) DEFAULT NULL,
  `skybox_image` varchar(255) DEFAULT NULL,
  `background_color` varchar(20) DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) DEFAULT 'auto',
  `ar_placement` VARCHAR(23) DEFAULT 'floor' COMMENT 'floor, wall',
  `poster_image` varchar(500) DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_format` (`format`),
  KEY `idx_is_public` (`is_public`),
  CONSTRAINT `object_3d_model_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_model_i18n
CREATE TABLE IF NOT EXISTS `object_3d_model_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `alt_text` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model_culture` (`model_id`,`culture`),
  CONSTRAINT `object_3d_model_i18n_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_3d_settings
CREATE TABLE IF NOT EXISTS `object_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0deg 75deg 105%',
  `field_of_view` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `background_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `ar_placement` VARCHAR(23) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'floor' COMMENT 'floor, wall',
  `poster_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_3d_texture
CREATE TABLE IF NOT EXISTS `object_3d_texture` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `texture_type` VARCHAR(75) DEFAULT 'diffuse' COMMENT 'diffuse, normal, roughness, metallic, ao, emissive, environment',
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_texture_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_access_grant
CREATE TABLE IF NOT EXISTS `object_access_grant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `request_id` int unsigned DEFAULT NULL,
  `object_type` VARCHAR(49) NOT NULL COMMENT 'information_object, repository, actor',
  `object_id` int unsigned NOT NULL,
  `include_descendants` tinyint(1) DEFAULT '0',
  `access_level` VARCHAR(32) DEFAULT 'view' COMMENT 'view, download, edit',
  `granted_by` int unsigned NOT NULL,
  `granted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` int unsigned DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_object` (`object_type`,`object_id`),
  KEY `idx_active` (`active`),
  KEY `idx_request` (`request_id`),
  CONSTRAINT `object_access_grant_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_classification_history
CREATE TABLE IF NOT EXISTS `object_classification_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `previous_classification_id` int unsigned DEFAULT NULL,
  `new_classification_id` int unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `changed_by` int DEFAULT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_compartment
CREATE TABLE IF NOT EXISTS `object_compartment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `compartment_id` int unsigned NOT NULL,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_date` date NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object_compartment` (`object_id`,`compartment_id`),
  KEY `idx_compartment` (`compartment_id`),
  CONSTRAINT `object_compartment_ibfk_1` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_creative_commons
CREATE TABLE IF NOT EXISTS `object_creative_commons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `creative_commons_license_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_cc` (`object_id`,`creative_commons_license_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_cc_id` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_declassification_schedule
CREATE TABLE IF NOT EXISTS `object_declassification_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `from_classification_id` int DEFAULT NULL,
  `to_classification_id` int DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_scheduled` (`scheduled_date`),
  KEY `idx_processed` (`processed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_provenance
CREATE TABLE IF NOT EXISTS `object_provenance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `donor_id` int DEFAULT NULL,
  `acquisition_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acquisition_date` date DEFAULT NULL,
  `provenance_notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_donor_id` (`donor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_rights_holder
CREATE TABLE IF NOT EXISTS `object_rights_holder` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `donor_id` int NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_donor_id` (`donor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: object_rights_statement
CREATE TABLE IF NOT EXISTS `object_rights_statement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `rights_statement_id` bigint unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_rs` (`object_id`,`rights_statement_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_rights_statement_id` (`rights_statement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_security_classification
CREATE TABLE IF NOT EXISTS `object_security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `classified_by` int DEFAULT NULL,
  `classified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `review_date` date DEFAULT NULL,
  `declassify_date` date DEFAULT NULL,
  `declassify_to_id` int unsigned DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inherit_to_children` tinyint(1) DEFAULT '1',
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_osc_object` (`object_id`),
  KEY `idx_osc_classification_review_declassify` (`classification_id`,`review_date`,`declassify_date`),
  KEY `idx_osc_assigned_by` (`assigned_by`),
  KEY `fk_osc_classified_by` (`classified_by`),
  CONSTRAINT `fk_osc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_osc_classified_by` FOREIGN KEY (`classified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_osc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_tk_label
CREATE TABLE IF NOT EXISTS `object_tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `tk_label_id` bigint unsigned NOT NULL,
  `community_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_contact` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `custom_text` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_obj_tk` (`object_id`,`tk_label_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_tk_label_id` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: object_watermark_setting
CREATE TABLE IF NOT EXISTS `object_watermark_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `watermark_enabled` tinyint(1) DEFAULT '1',
  `watermark_type_id` int unsigned DEFAULT NULL,
  `custom_watermark_id` int unsigned DEFAULT NULL,
  `position` varchar(50) DEFAULT 'center',
  `opacity` decimal(3,2) DEFAULT '0.40',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `watermark_type_id` (`watermark_type_id`),
  KEY `custom_watermark_id` (`custom_watermark_id`),
  CONSTRAINT `object_watermark_setting_ibfk_1` FOREIGN KEY (`watermark_type_id`) REFERENCES `watermark_type` (`id`) ON DELETE SET NULL,
  CONSTRAINT `object_watermark_setting_ibfk_2` FOREIGN KEY (`custom_watermark_id`) REFERENCES `custom_watermark` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: physical_object_extended
CREATE TABLE IF NOT EXISTS `physical_object_extended` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `physical_object_id` int NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `aisle` varchar(50) DEFAULT NULL,
  `bay` varchar(50) DEFAULT NULL,
  `rack` varchar(50) DEFAULT NULL,
  `shelf` varchar(50) DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `reference_code` varchar(100) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `depth` decimal(10,2) DEFAULT NULL,
  `total_capacity` int unsigned DEFAULT NULL COMMENT 'Total slots/spaces available',
  `used_capacity` int unsigned DEFAULT '0' COMMENT 'Currently occupied',
  `available_capacity` int unsigned GENERATED ALWAYS AS ((`total_capacity` - `used_capacity`)) STORED,
  `capacity_unit` varchar(50) DEFAULT NULL COMMENT 'boxes, files, metres, items etc',
  `total_linear_metres` decimal(10,2) DEFAULT NULL,
  `used_linear_metres` decimal(10,2) DEFAULT '0.00',
  `available_linear_metres` decimal(10,2) GENERATED ALWAYS AS ((`total_linear_metres` - `used_linear_metres`)) STORED,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `temperature_min` decimal(5,2) DEFAULT NULL,
  `temperature_max` decimal(5,2) DEFAULT NULL,
  `humidity_min` decimal(5,2) DEFAULT NULL,
  `humidity_max` decimal(5,2) DEFAULT NULL,
  `security_level` varchar(50) DEFAULT NULL,
  `access_restrictions` text,
  `status` VARCHAR(53) DEFAULT 'active' COMMENT 'active, full, maintenance, decommissioned',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_physical_object_id` (`physical_object_id`),
  KEY `idx_barcode` (`barcode`),
  KEY `idx_reference_code` (`reference_code`),
  KEY `idx_building` (`building`),
  KEY `idx_status` (`status`),
  KEY `idx_available_capacity` (`available_capacity`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Table: provenance_entry
CREATE TABLE IF NOT EXISTS `provenance_entry` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `sequence` smallint unsigned NOT NULL DEFAULT '1',
  `owner_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_type` VARCHAR(108) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'person, family, dealer, auction_house, museum, corporate, government, religious, artist, unknown',
  `owner_actor_id` int unsigned DEFAULT NULL,
  `owner_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `owner_location_tgn` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date_qualifier` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'circa, before, after, by',
  `end_date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `end_date_qualifier` VARCHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'circa, before, after, by',
  `transfer_type` VARCHAR(138) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'sale, auction, gift, bequest, inheritance, commission, exchange, seizure, restitution, transfer, loan, found, created, unknown',
  `transfer_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `sale_currency` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auction_house` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auction_lot` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certainty` VARCHAR(59) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown' COMMENT 'certain, probable, possible, uncertain, unknown',
  `sources` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_gap` tinyint(1) NOT NULL DEFAULT '0',
  `gap_explanation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pe_object` (`information_object_id`),
  KEY `idx_pe_object_seq` (`information_object_id`,`sequence`),
  KEY `idx_pe_owner` (`owner_name`),
  KEY `idx_pe_transfer` (`transfer_type`),
  KEY `idx_pe_certainty` (`certainty`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: report_definition
CREATE TABLE IF NOT EXISTS `report_definition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` VARCHAR(101) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'collection, acquisition, access, preservation, researcher, compliance, statistics, custom',
  `sector` set('archive','library','museum','dam','researcher') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'archive',
  `report_class` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PHP class name for report generator',
  `parameters` json DEFAULT NULL COMMENT 'Available filter parameters',
  `output_formats` set('html','pdf','csv','xlsx','json') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'html,csv',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_report_category` (`category`),
  KEY `idx_report_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: request_to_publish
CREATE TABLE IF NOT EXISTS `request_to_publish` (
  `id` int NOT NULL,
  `parent_id` varchar(50) DEFAULT NULL,
  `rtp_type_id` int DEFAULT NULL,
  `lft` int NOT NULL,
  `rgt` int NOT NULL,
  `source_culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `requesttopublish_FK_1` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: request_to_publish_i18n
CREATE TABLE IF NOT EXISTS `request_to_publish_i18n` (
  `unique_identifier` varchar(1024) DEFAULT NULL,
  `rtp_name` varchar(50) DEFAULT NULL,
  `rtp_surname` varchar(50) DEFAULT NULL,
  `rtp_phone` varchar(50) DEFAULT NULL,
  `rtp_email` varchar(50) DEFAULT NULL,
  `rtp_institution` varchar(200) DEFAULT NULL,
  `rtp_motivation` text,
  `rtp_planned_use` text,
  `rtp_need_image_by` datetime DEFAULT NULL,
  `status_id` int NOT NULL,
  `id` int NOT NULL,
  `object_id` varchar(50) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `culture` varchar(14) NOT NULL,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `requesttopublish_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `request_to_publish` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_annotation
CREATE TABLE IF NOT EXISTS `research_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `object_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `annotation_type` VARCHAR(57) DEFAULT 'note' COMMENT 'note, highlight, bookmark, tag, transcription',
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `target_selector` text,
  `tags` varchar(500) DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_booking
CREATE TABLE IF NOT EXISTS `research_booking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `reading_room_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text,
  `status` VARCHAR(61) DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled, completed, no_show',
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_citation_log
CREATE TABLE IF NOT EXISTS `research_citation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int DEFAULT NULL,
  `object_id` int NOT NULL,
  `citation_style` varchar(50) NOT NULL,
  `citation_text` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1781 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_collection
CREATE TABLE IF NOT EXISTS `research_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `is_public` tinyint(1) DEFAULT '0',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_share_token` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_collection_item
CREATE TABLE IF NOT EXISTS `research_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int NOT NULL,
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item` (`collection_id`,`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_material_request
CREATE TABLE IF NOT EXISTS `research_material_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `object_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `notes` text,
  `status` VARCHAR(74) DEFAULT 'requested' COMMENT 'requested, retrieved, delivered, in_use, returned, unavailable',
  `retrieved_by` int DEFAULT NULL,
  `retrieved_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `condition_notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_password_reset
CREATE TABLE IF NOT EXISTS `research_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_reading_room
CREATE TABLE IF NOT EXISTS `research_reading_room` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `amenities` text,
  `capacity` int DEFAULT '10',
  `location` varchar(255) DEFAULT NULL,
  `operating_hours` text,
  `rules` text,
  `advance_booking_days` int DEFAULT '14',
  `max_booking_hours` int DEFAULT '4',
  `cancellation_hours` int DEFAULT '24',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `opening_time` time DEFAULT '09:00:00',
  `closing_time` time DEFAULT '17:00:00',
  `days_open` varchar(50) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_researcher
CREATE TABLE IF NOT EXISTS `research_researcher` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `affiliation_type` VARCHAR(70) DEFAULT 'independent' COMMENT 'academic, government, private, independent, student, other',
  `institution` varchar(255) DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `research_interests` text,
  `current_project` text,
  `orcid_id` varchar(50) DEFAULT NULL,
  `id_type` VARCHAR(71) DEFAULT NULL COMMENT 'passport, national_id, drivers_license, student_card, other',
  `id_number` varchar(100) DEFAULT NULL,
  `id_verified` tinyint(1) DEFAULT '0',
  `id_verified_by` int DEFAULT NULL,
  `id_verified_at` datetime DEFAULT NULL,
  `status` VARCHAR(49) DEFAULT 'pending' COMMENT 'pending, approved, suspended, expired',
  `rejection_reason` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: research_saved_search
CREATE TABLE IF NOT EXISTS `research_saved_search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `search_query` text NOT NULL,
  `search_filters` text,
  `search_type` varchar(50) DEFAULT 'informationobject',
  `alert_enabled` tinyint(1) DEFAULT '0',
  `alert_frequency` VARCHAR(34) DEFAULT 'weekly' COMMENT 'daily, weekly, monthly',
  `last_alert_at` datetime DEFAULT NULL,
  `new_results_count` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: ric_orphan_tracking
CREATE TABLE IF NOT EXISTS `ric_orphan_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_id` int DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detection_method` VARCHAR(49) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'integrity_check, sync_failure, manual',
  `status` VARCHAR(59) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detected' COMMENT 'detected, reviewed, cleaned, retained, restored',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `triple_count` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_orphan_uri` (`ric_uri`(255)),
  KEY `idx_ric_orphan_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_config
CREATE TABLE IF NOT EXISTS `ric_sync_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_log
CREATE TABLE IF NOT EXISTS `ric_sync_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `operation` VARCHAR(74) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create, update, delete, move, resync, cleanup, integrity_check',
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(46) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'success, failure, partial, skipped',
  `triples_affected` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `execution_time_ms` int DEFAULT NULL,
  `triggered_by` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system' COMMENT 'user, system, cron, api, cli',
  `user_id` int DEFAULT NULL,
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_log_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_log_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_queue
CREATE TABLE IF NOT EXISTS `ric_sync_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `operation` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create, update, delete, move',
  `priority` tinyint NOT NULL DEFAULT '5',
  `status` VARCHAR(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued' COMMENT 'queued, processing, completed, failed, cancelled',
  `attempts` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '3',
  `old_parent_id` int DEFAULT NULL,
  `new_parent_id` int DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_queue_status` (`status`,`priority`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ric_sync_status
CREATE TABLE IF NOT EXISTS `ric_sync_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_status` VARCHAR(54) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'synced, pending, failed, deleted, orphaned',
  `last_synced_at` datetime DEFAULT NULL,
  `last_sync_attempt` datetime DEFAULT NULL,
  `sync_error` text COLLATE utf8mb4_unicode_ci,
  `retry_count` int NOT NULL DEFAULT '0',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atom_updated_at` datetime DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `hierarchy_path` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_sync_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_sync_uri` (`ric_uri`(255)),
  KEY `idx_ric_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_cc_license
CREATE TABLE IF NOT EXISTS `rights_cc_license` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4.0',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allows_commercial` tinyint(1) DEFAULT '1',
  `allows_derivatives` tinyint(1) DEFAULT '1',
  `requires_share_alike` tinyint(1) DEFAULT '0',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_cc_license_i18n
CREATE TABLE IF NOT EXISTS `rights_cc_license_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `human_readable` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_cc_license_i18n` FOREIGN KEY (`id`) REFERENCES `rights_cc_license` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_derivative_log
CREATE TABLE IF NOT EXISTS `rights_derivative_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `rule_id` int DEFAULT NULL,
  `derivative_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derivative_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` int DEFAULT NULL,
  `request_purpose` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_rule` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_derivative_rule
CREATE TABLE IF NOT EXISTS `rights_derivative_rule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL COMMENT 'NULL = applies to collection or global',
  `collection_id` int DEFAULT NULL COMMENT 'NULL = applies to object or global',
  `is_global` tinyint(1) DEFAULT '0',
  `rule_type` VARCHAR(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'watermark, redaction, resize, format_conversion, metadata_strip',
  `priority` int DEFAULT '0',
  `applies_to_roles` json DEFAULT NULL COMMENT 'Array of role IDs, NULL = all',
  `applies_to_clearance_levels` json DEFAULT NULL COMMENT 'Array of clearance level codes',
  `applies_to_purposes` json DEFAULT NULL COMMENT 'Array of purpose codes',
  `watermark_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_position` VARCHAR(72) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'bottom_right' COMMENT 'center, top_left, top_right, bottom_left, bottom_right, tile',
  `watermark_opacity` int DEFAULT '50' COMMENT '0-100',
  `redaction_areas` json DEFAULT NULL COMMENT 'Array of {x, y, width, height, page}',
  `redaction_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `max_width` int DEFAULT NULL,
  `max_height` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_rule_type` (`rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo
CREATE TABLE IF NOT EXISTS `rights_embargo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `embargo_type` VARCHAR(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT 'full, metadata_only, digital_only, partial',
  `reason` VARCHAR(105) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'donor_restriction, copyright, privacy, legal, commercial, research, cultural, security, other',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL = indefinite',
  `auto_release` tinyint(1) DEFAULT '1' COMMENT 'Auto-lift on end_date',
  `review_date` date DEFAULT NULL,
  `review_interval_months` int DEFAULT '12',
  `last_reviewed_at` datetime DEFAULT NULL,
  `last_reviewed_by` int DEFAULT NULL,
  `status` VARCHAR(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, pending, lifted, expired, extended',
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` int DEFAULT NULL,
  `lift_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notify_before_days` int DEFAULT '30',
  `notification_sent` tinyint(1) DEFAULT '0',
  `notify_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of emails',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_review_date` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo_i18n
CREATE TABLE IF NOT EXISTS `rights_embargo_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `reason_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_embargo_i18n` FOREIGN KEY (`id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_embargo_log
CREATE TABLE IF NOT EXISTS `rights_embargo_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `embargo_id` int NOT NULL,
  `action` VARCHAR(81) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created, extended, lifted, reviewed, notification_sent, auto_released',
  `old_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_end_date` date DEFAULT NULL,
  `new_end_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_embargo` (`embargo_id`),
  CONSTRAINT `fk_embargo_log` FOREIGN KEY (`embargo_id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_grant
CREATE TABLE IF NOT EXISTS `rights_grant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `act` VARCHAR(119) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'render, disseminate, replicate, migrate, modify, delete, print, use, publish, excerpt, annotate, move, sell',
  `restriction` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'allow' COMMENT 'allow, disallow, conditional',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `condition_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_act` (`act`),
  KEY `idx_restriction` (`restriction`),
  CONSTRAINT `fk_rights_grant_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_grant_i18n
CREATE TABLE IF NOT EXISTS `rights_grant_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_grant_i18n` FOREIGN KEY (`id`) REFERENCES `rights_grant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_object_tk_label
CREATE TABLE IF NOT EXISTS `rights_object_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `tk_label_id` int NOT NULL,
  `community_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `custom_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `verified` tinyint(1) DEFAULT '0',
  `verified_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_object_label` (`object_id`,`tk_label_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_label` (`tk_label_id`),
  CONSTRAINT `fk_object_tk_label` FOREIGN KEY (`tk_label_id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_search_step
CREATE TABLE IF NOT EXISTS `rights_orphan_search_step` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orphan_work_id` int NOT NULL,
  `source_type` VARCHAR(103) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'database, registry, publisher, author_society, archive, library, internet, newspaper, other',
  `source_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `search_date` date NOT NULL,
  `search_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `results_found` tinyint(1) DEFAULT '0',
  `results_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evidence_file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `screenshot_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orphan_work` (`orphan_work_id`),
  CONSTRAINT `fk_orphan_search_step` FOREIGN KEY (`orphan_work_id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_work
CREATE TABLE IF NOT EXISTS `rights_orphan_work` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `status` VARCHAR(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress' COMMENT 'in_progress, completed, rights_holder_found, abandoned',
  `work_type` VARCHAR(127) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'literary, dramatic, musical, artistic, film, sound_recording, broadcast, typographical, database, photograph, other',
  `search_started_date` date DEFAULT NULL,
  `search_completed_date` date DEFAULT NULL,
  `search_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA',
  `rights_holder_found` tinyint(1) DEFAULT '0',
  `rights_holder_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rights_holder_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_attempted` tinyint(1) DEFAULT '0',
  `contact_date` date DEFAULT NULL,
  `contact_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `intended_use` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `proposed_fee` decimal(10,2) DEFAULT NULL,
  `fee_held_in_escrow` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_orphan_work_i18n
CREATE TABLE IF NOT EXISTS `rights_orphan_work_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `search_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_orphan_work_i18n` FOREIGN KEY (`id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_record
CREATE TABLE IF NOT EXISTS `rights_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `basis` VARCHAR(61) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'copyright' COMMENT 'copyright, license, statute, donor, policy, other',
  `rights_statement_id` int DEFAULT NULL,
  `cc_license_id` int DEFAULT NULL,
  `copyright_status` VARCHAR(47) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unknown' COMMENT 'copyrighted, public_domain, unknown',
  `copyright_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_holder_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `copyright_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA' COMMENT 'ISO 3166-1 alpha-2',
  `copyright_determination_date` date DEFAULT NULL,
  `copyright_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statute_citation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_determination_date` date DEFAULT NULL,
  `statute_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `donor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `donor_agreement_date` date DEFAULT NULL,
  `donor_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `policy_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `documentation_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentation_role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL COMMENT 'FK to user.id',
  `updated_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_basis` (`basis`),
  KEY `idx_status` (`copyright_status`),
  KEY `fk_rights_statement` (`rights_statement_id`),
  KEY `fk_rights_cc_license` (`cc_license_id`),
  CONSTRAINT `fk_rights_cc_license` FOREIGN KEY (`cc_license_id`) REFERENCES `rights_cc_license` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_record_i18n
CREATE TABLE IF NOT EXISTS `rights_record_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `rights_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_record_i18n` FOREIGN KEY (`id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_statement
CREATE TABLE IF NOT EXISTS `rights_statement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'in-copyright, no-copyright, other',
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rights_statement_uri` (`uri`),
  UNIQUE KEY `uq_rights_statement_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_statement_i18n
CREATE TABLE IF NOT EXISTS `rights_statement_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rights_statement_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `scope_note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rs_i18n` (`rights_statement_id`,`culture`),
  KEY `idx_rs_i18n_parent` (`rights_statement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_territory
CREATE TABLE IF NOT EXISTS `rights_territory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `territory_type` VARCHAR(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'include' COMMENT 'include, exclude',
  `country_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ISO 3166-1 alpha-2 or region code',
  `is_gdpr_territory` tinyint(1) DEFAULT '0',
  `gdpr_legal_basis` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_country` (`country_code`),
  CONSTRAINT `fk_rights_territory_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_tk_label
CREATE TABLE IF NOT EXISTS `rights_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` VARCHAR(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tk' COMMENT 'tk, bc, attribution',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex color code',
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rights_tk_label_i18n
CREATE TABLE IF NOT EXISTS `rights_tk_label_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `usage_protocol` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_tk_label_i18n` FOREIGN KEY (`id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search
CREATE TABLE IF NOT EXISTS `saved_search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `search_params` json NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'informationobject',
  `search_url` text COLLATE utf8mb4_unicode_ci,
  `result_count` int DEFAULT NULL,
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `is_global` tinyint(1) DEFAULT '0',
  `display_order` int DEFAULT '100',
  `share_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notify_on_new` tinyint(1) NOT NULL DEFAULT '0',
  `notification_frequency` VARCHAR(34) COLLATE utf8mb4_unicode_ci DEFAULT 'weekly' COMMENT 'daily, weekly, monthly',
  `last_notification_at` datetime DEFAULT NULL,
  `last_result_count` int DEFAULT NULL,
  `usage_count` int NOT NULL DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `idx_saved_search_user` (`user_id`),
  KEY `idx_saved_search_entity` (`entity_type`),
  KEY `idx_saved_search_public` (`is_public`),
  KEY `idx_saved_search_notify` (`notify_on_new`,`notification_frequency`),
  KEY `idx_global` (`is_global`,`display_order`),
  CONSTRAINT `fk_saved_search_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_i18n
CREATE TABLE IF NOT EXISTS `saved_search_i18n` (
  `id` int NOT NULL,
  `culture` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_saved_search_i18n` FOREIGN KEY (`id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_log
CREATE TABLE IF NOT EXISTS `saved_search_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saved_search_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `executed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `result_count` int DEFAULT NULL,
  `execution_time_ms` int DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_search` (`saved_search_id`),
  KEY `idx_log_date` (`executed_at`),
  KEY `idx_log_user` (`user_id`),
  CONSTRAINT `fk_saved_search_log` FOREIGN KEY (`saved_search_id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: saved_search_tag
CREATE TABLE IF NOT EXISTS `saved_search_tag` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saved_search_id` int NOT NULL,
  `tag` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_search_tag` (`saved_search_id`,`tag`),
  KEY `idx_tag` (`tag`),
  CONSTRAINT `fk_saved_search_tag` FOREIGN KEY (`saved_search_id`) REFERENCES `saved_search` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_history
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `session_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `search_query` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_params` json DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `result_count` int DEFAULT '0',
  `execution_time` float DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_history_user` (`user_id`),
  KEY `idx_search_history_session` (`session_id`),
  KEY `idx_search_history_created` (`created_at`),
  KEY `idx_search_history_entity` (`entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_popular
CREATE TABLE IF NOT EXISTS `search_popular` (
  `id` int NOT NULL AUTO_INCREMENT,
  `search_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_query` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `search_params` json DEFAULT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `search_count` int DEFAULT '1',
  `last_searched` datetime DEFAULT CURRENT_TIMESTAMP,
  `avg_results` float DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_popular_hash` (`search_hash`),
  KEY `idx_search_popular_count` (`search_count` DESC),
  KEY `idx_search_popular_last` (`last_searched`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_settings
CREATE TABLE IF NOT EXISTS `search_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: search_template
CREATE TABLE IF NOT EXISTS `search_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'fa-search',
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'primary',
  `search_params` json NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'informationobject',
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `show_on_homepage` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_search_template_slug` (`slug`),
  KEY `idx_search_template_category` (`category`),
  KEY `idx_search_template_featured` (`is_featured`),
  KEY `idx_search_template_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_2fa_session
CREATE TABLE IF NOT EXISTS `security_2fa_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `verified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_user_session` (`user_id`,`session_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_access_condition_link
CREATE TABLE IF NOT EXISTS `security_access_condition_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `access_conditions` text,
  `reproduction_conditions` text,
  `narssa_ref` varchar(100) DEFAULT NULL,
  `retention_period` varchar(50) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_access_log
CREATE TABLE IF NOT EXISTS `security_access_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_granted` tinyint(1) NOT NULL,
  `denial_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sal_object` (`object_id`),
  KEY `idx_sal_user` (`user_id`),
  KEY `idx_sal_classification` (`classification_id`),
  CONSTRAINT `fk_sal_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sal_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_access_request
CREATE TABLE IF NOT EXISTS `security_access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned DEFAULT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `compartment_id` int unsigned DEFAULT NULL,
  `request_type` VARCHAR(81) NOT NULL COMMENT 'view, download, print, clearance_upgrade, compartment_access, renewal',
  `justification` text NOT NULL,
  `duration_hours` int DEFAULT NULL,
  `priority` VARCHAR(37) DEFAULT 'normal' COMMENT 'normal, urgent, immediate',
  `status` VARCHAR(57) DEFAULT 'pending' COMMENT 'pending, approved, denied, expired, cancelled',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text,
  `access_granted_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority_status` (`priority`,`status`,`created_at`),
  KEY `classification_id` (`classification_id`),
  KEY `compartment_id` (`compartment_id`),
  CONSTRAINT `security_access_request_ibfk_1` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_access_request_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_audit_log
CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `object_type` varchar(50) DEFAULT 'information_object',
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `action_category` varchar(50) DEFAULT 'access',
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_category` (`action_category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_classification
CREATE TABLE IF NOT EXISTS `security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_justification` tinyint(1) NOT NULL DEFAULT '0',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `requires_2fa` tinyint(1) NOT NULL DEFAULT '0',
  `max_session_hours` int DEFAULT NULL,
  `watermark_required` tinyint(1) NOT NULL DEFAULT '0',
  `watermark_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `print_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `copy_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security_classification_level` (`level`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: security_clearance_history
CREATE TABLE IF NOT EXISTS `security_clearance_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `previous_classification_id` int unsigned DEFAULT NULL,
  `new_classification_id` int unsigned DEFAULT NULL,
  `action` VARCHAR(95) NOT NULL COMMENT 'granted, upgraded, downgraded, revoked, renewed, expired, 2fa_enabled, 2fa_disabled',
  `changed_by` int unsigned NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `previous_classification_id` (`previous_classification_id`),
  KEY `new_classification_id` (`new_classification_id`),
  CONSTRAINT `security_clearance_history_ibfk_1` FOREIGN KEY (`previous_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_clearance_history_ibfk_2` FOREIGN KEY (`new_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_compartment
CREATE TABLE IF NOT EXISTS `security_compartment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `min_clearance_id` int unsigned NOT NULL,
  `requires_need_to_know` tinyint(1) DEFAULT '1',
  `requires_briefing` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`active`),
  KEY `min_clearance_id` (`min_clearance_id`),
  CONSTRAINT `security_compartment_ibfk_1` FOREIGN KEY (`min_clearance_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_compliance_log
CREATE TABLE IF NOT EXISTS `security_compliance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_declassification_schedule
CREATE TABLE IF NOT EXISTS `security_declassification_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `from_classification_id` int unsigned NOT NULL,
  `to_classification_id` int unsigned DEFAULT NULL,
  `trigger_type` VARCHAR(34) NOT NULL DEFAULT 'date' COMMENT 'date, event, retention',
  `trigger_event` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scheduled` (`scheduled_date`,`processed`),
  KEY `idx_object` (`object_id`),
  KEY `from_classification_id` (`from_classification_id`),
  KEY `to_classification_id` (`to_classification_id`),
  CONSTRAINT `security_declassification_schedule_ibfk_1` FOREIGN KEY (`from_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `security_declassification_schedule_ibfk_2` FOREIGN KEY (`to_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_retention_schedule
CREATE TABLE IF NOT EXISTS `security_retention_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `narssa_ref` varchar(100) NOT NULL,
  `record_type` varchar(255) NOT NULL,
  `retention_period` varchar(100) NOT NULL,
  `disposal_action` varchar(100) NOT NULL,
  `legal_reference` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_narssa` (`narssa_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: security_watermark_log
CREATE TABLE IF NOT EXISTS `security_watermark_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `watermark_type` VARCHAR(36) NOT NULL DEFAULT 'visible' COMMENT 'visible, invisible, both',
  `watermark_text` varchar(500) NOT NULL,
  `watermark_code` varchar(100) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `idx_object` (`object_id`),
  KEY `idx_code` (`watermark_code`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_acquisition
CREATE TABLE IF NOT EXISTS `spectrum_acquisition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `acquisition_number` varchar(50) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_method` varchar(50) DEFAULT NULL,
  `acquisition_source` varchar(255) DEFAULT NULL,
  `source_contact` text,
  `acquisition_reason` text,
  `acquisition_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `funding_source` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `price_currency` varchar(10) DEFAULT NULL,
  `group_purchase_price` decimal(15,2) DEFAULT NULL,
  `accession_date` date DEFAULT NULL,
  `accession_number` varchar(50) DEFAULT NULL,
  `title_transfer_date` date DEFAULT NULL,
  `ownership_history` text,
  `acquisition_note` text,
  `provenance_note` text,
  `legal_title` text,
  `conditions_of_acquisition` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_acquisition_number` (`acquisition_number`),
  KEY `idx_accession_number` (`accession_number`),
  KEY `idx_acquisition_date` (`acquisition_date`),
  KEY `idx_wf_acq` (`workflow_state`),
  CONSTRAINT `spectrum_acquisition_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_approval
CREATE TABLE IF NOT EXISTS `spectrum_approval` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `status` VARCHAR(39) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, approved, rejected',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  KEY `idx_approver` (`approver_id`),
  CONSTRAINT `spectrum_approval_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_audit_log
CREATE TABLE IF NOT EXISTS `spectrum_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `procedure_type` varchar(50) NOT NULL,
  `procedure_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `note` text,
  PRIMARY KEY (`id`),
  KEY `idx_audit_object` (`object_id`),
  KEY `idx_audit_procedure` (`procedure_type`,`procedure_id`),
  KEY `idx_audit_date` (`action_date`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_barcode
CREATE TABLE IF NOT EXISTS `spectrum_barcode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `barcode_type` varchar(20) NOT NULL,
  `barcode_content` varchar(500) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `print_count` int DEFAULT '0',
  `last_printed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_barcode_object` (`object_id`),
  KEY `idx_barcode_type` (`barcode_type`),
  CONSTRAINT `spectrum_barcode_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_check
CREATE TABLE IF NOT EXISTS `spectrum_condition_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `condition_reference` varchar(50) DEFAULT NULL,
  `check_date` datetime NOT NULL,
  `check_reason` varchar(100) DEFAULT NULL,
  `checked_by` varchar(255) NOT NULL,
  `overall_condition` varchar(50) DEFAULT NULL,
  `condition_note` text,
  `completeness_note` text,
  `hazard_note` text,
  `technical_assessment` text,
  `recommended_treatment` text,
  `treatment_priority` varchar(50) DEFAULT NULL,
  `next_check_date` date DEFAULT NULL,
  `environment_recommendation` text,
  `handling_recommendation` text,
  `display_recommendation` text,
  `storage_recommendation` text,
  `packing_recommendation` text,
  `image_reference` text,
  `photo_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `condition_check_reference` varchar(255) DEFAULT NULL,
  `completeness` varchar(50) DEFAULT NULL,
  `condition_description` text,
  `hazards_noted` text,
  `recommendations` text,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `condition_rating` varchar(50) DEFAULT NULL COMMENT 'Overall condition rating',
  `condition_notes` text COMMENT 'Detailed condition notes',
  `template_id` int DEFAULT NULL,
  `material_type` varchar(50) DEFAULT NULL,
  `template_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_condition_date` (`check_date`),
  KEY `idx_condition_reference` (`condition_reference`),
  KEY `idx_overall_condition` (`overall_condition`),
  KEY `idx_wf_cond` (`workflow_state`),
  KEY `idx_check_date` (`check_date`),
  CONSTRAINT `spectrum_condition_check_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_check_data
CREATE TABLE IF NOT EXISTS `spectrum_condition_check_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `template_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_check_field` (`condition_check_id`,`field_id`),
  KEY `template_id` (`template_id`),
  KEY `field_id` (`field_id`),
  KEY `idx_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_check_data_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_3` FOREIGN KEY (`field_id`) REFERENCES `spectrum_condition_template_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_photo
CREATE TABLE IF NOT EXISTS `spectrum_condition_photo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `photo_type` VARCHAR(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail' COMMENT 'before, after, detail, damage, overall, other',
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_on_object` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `photographer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_date` date DEFAULT NULL,
  `camera_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `annotations` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_condition_check` (`condition_check_id`),
  KEY `idx_photo_type` (`photo_type`),
  KEY `idx_photo_date` (`photo_date`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `spectrum_condition_photo_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_photo_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_condition_photo_comparison
CREATE TABLE IF NOT EXISTS `spectrum_condition_photo_comparison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `before_photo_id` int NOT NULL,
  `after_photo_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_condition_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_photo_comparison_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_photos
CREATE TABLE IF NOT EXISTS `spectrum_condition_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL COMMENT 'Reference to spectrum_condition_check',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored filename',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original uploaded filename',
  `category` VARCHAR(61) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'overall' COMMENT 'Photo category' COMMENT 'overall, detail, damage, before, after, reference',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Description or notes about the photo',
  `annotations` json DEFAULT NULL COMMENT 'JSON annotations for damage markers',
  `file_size` int DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type of the image',
  `width` int DEFAULT NULL COMMENT 'Image width in pixels',
  `height` int DEFAULT NULL COMMENT 'Image height in pixels',
  `captured_at` datetime DEFAULT NULL COMMENT 'When photo was taken (from EXIF)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who uploaded the photo',
  `updated_at` datetime DEFAULT NULL COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_check` (`condition_check_id`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_condition_template
CREATE TABLE IF NOT EXISTS `spectrum_condition_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_template_field
CREATE TABLE IF NOT EXISTS `spectrum_condition_template_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` VARCHAR(86) NOT NULL COMMENT 'text, textarea, select, multiselect, checkbox, radio, rating, date, number',
  `options` json DEFAULT NULL COMMENT 'For select/multiselect/radio - array of options',
  `default_value` varchar(255) DEFAULT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `help_text` text,
  `is_required` tinyint(1) DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `spectrum_condition_template_field_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `spectrum_condition_template_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_condition_template_section
CREATE TABLE IF NOT EXISTS `spectrum_condition_template_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_template` (`template_id`),
  CONSTRAINT `spectrum_condition_template_section_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_conservation
CREATE TABLE IF NOT EXISTS `spectrum_conservation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `conservation_reference` varchar(50) DEFAULT NULL,
  `treatment_date` date NOT NULL,
  `treatment_end_date` date DEFAULT NULL,
  `conservator_name` varchar(255) NOT NULL,
  `conservator_organization` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `treatment_proposal` text,
  `treatment_performed` text,
  `materials_used` text,
  `condition_after` text,
  `treatment_cost` decimal(15,2) DEFAULT NULL,
  `cost_currency` varchar(10) DEFAULT NULL,
  `next_treatment_date` date DEFAULT NULL,
  `treatment_note` text,
  `report_reference` varchar(100) DEFAULT NULL,
  `image_before` text,
  `image_after` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `recommendations` text,
  `conservation_note` text,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_conservation_reference` (`conservation_reference`),
  KEY `idx_treatment_date` (`treatment_date`),
  KEY `idx_wf_cons` (`workflow_state`),
  CONSTRAINT `spectrum_conservation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_conservation_treatment
CREATE TABLE IF NOT EXISTS `spectrum_conservation_treatment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `treatment_reference` varchar(100) DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `conservator` varchar(255) DEFAULT NULL,
  `description` text,
  `materials_used` text,
  `outcome` text,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`treatment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_deaccession
CREATE TABLE IF NOT EXISTS `spectrum_deaccession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `deaccession_number` varchar(50) NOT NULL,
  `deaccession_date` date NOT NULL,
  `proposal_date` date DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `authorized_by` varchar(255) DEFAULT NULL,
  `deaccession_reason` text,
  `disposal_method` varchar(50) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_recipient` varchar(255) DEFAULT NULL,
  `disposal_price` decimal(15,2) DEFAULT NULL,
  `disposal_currency` varchar(10) DEFAULT NULL,
  `new_owner` varchar(255) DEFAULT NULL,
  `new_owner_contact` text,
  `legal_requirements_met` tinyint(1) DEFAULT '0',
  `documentation_complete` tinyint(1) DEFAULT '0',
  `deaccession_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_deaccession_number` (`deaccession_number`),
  KEY `idx_deaccession_date` (`deaccession_date`),
  KEY `idx_wf_deacc` (`workflow_state`),
  CONSTRAINT `spectrum_deaccession_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_event
CREATE TABLE IF NOT EXISTS `spectrum_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_from` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `assigned_to_id` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_procedure` (`object_id`,`procedure_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_procedure` (`procedure_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status_to`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `spectrum_event_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: spectrum_loan_agreements
CREATE TABLE IF NOT EXISTS `spectrum_loan_agreements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL COMMENT 'Reference to spectrum_loan_in or spectrum_loan_out',
  `loan_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of loan' COMMENT 'in, out',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Generated PDF filename',
  `template` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'standard' COMMENT 'Template used',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who generated the agreement',
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`,`loan_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_loan_document
CREATE TABLE IF NOT EXISTS `spectrum_loan_document` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_type` varchar(20) NOT NULL,
  `loan_id` int NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `signed` tinyint(1) DEFAULT '0',
  `signed_at` datetime DEFAULT NULL,
  `signed_by` varchar(255) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_loan_document` (`loan_type`,`loan_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_loan_in
CREATE TABLE IF NOT EXISTS `spectrum_loan_in` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_in_number` varchar(50) NOT NULL,
  `lender_name` varchar(255) NOT NULL,
  `lender_contact` text,
  `lender_address` text,
  `loan_in_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `insurance_note` text,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `special_requirements` text,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `loan_in_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Lender address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_in_number` (`loan_in_number`),
  KEY `idx_loan_in_status` (`loan_status`),
  KEY `idx_loan_return_date` (`loan_return_date`),
  KEY `idx_wf_lin` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_in_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_loan_out
CREATE TABLE IF NOT EXISTS `spectrum_loan_out` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_out_number` varchar(50) NOT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `borrower_contact` text,
  `borrower_address` text,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_address` text,
  `loan_out_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `indemnity_reference` varchar(100) DEFAULT NULL,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `exhibition_title` varchar(255) DEFAULT NULL,
  `exhibition_dates` text,
  `special_requirements` text,
  `courier_required` tinyint(1) DEFAULT '0',
  `courier_name` varchar(255) DEFAULT NULL,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `insurance_policy` varchar(255) DEFAULT NULL,
  `loan_out_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Borrower address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_out_number` (`loan_out_number`),
  KEY `idx_loan_out_status` (`loan_status`),
  KEY `idx_borrower` (`borrower_name`),
  KEY `idx_wf_lout` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_out_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_location
CREATE TABLE IF NOT EXISTS `spectrum_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_building` varchar(255) DEFAULT NULL,
  `location_floor` varchar(50) DEFAULT NULL,
  `location_room` varchar(100) DEFAULT NULL,
  `location_unit` varchar(100) DEFAULT NULL,
  `location_shelf` varchar(100) DEFAULT NULL,
  `location_box` varchar(100) DEFAULT NULL,
  `location_note` text,
  `fitness_for_purpose` text,
  `security_note` text,
  `environment_note` text,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `location_coordinates` varchar(255) DEFAULT NULL,
  `security_level` varchar(50) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_location_current` (`object_id`,`is_current`),
  KEY `idx_location_name` (`location_name`),
  KEY `idx_wf_loc` (`workflow_state`),
  CONSTRAINT `spectrum_location_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_movement
CREATE TABLE IF NOT EXISTS `spectrum_movement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `movement_reference` varchar(50) DEFAULT NULL,
  `scanned_barcode` varchar(100) DEFAULT NULL,
  `scanned_at` datetime DEFAULT NULL,
  `scanned_by` int DEFAULT NULL,
  `movement_date` datetime NOT NULL,
  `movement_reason` varchar(100) DEFAULT NULL,
  `location_from` int DEFAULT NULL,
  `location_to` int DEFAULT NULL,
  `movement_method` varchar(100) DEFAULT NULL,
  `movement_contact` varchar(255) DEFAULT NULL,
  `handler_name` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `condition_after` text,
  `planned_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `movement_note` text,
  `removal_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `from_location_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `location_from` (`location_from`),
  KEY `location_to` (`location_to`),
  KEY `idx_movement_date` (`movement_date`),
  KEY `idx_movement_reference` (`movement_reference`),
  KEY `idx_wf_mov` (`workflow_state`),
  CONSTRAINT `spectrum_movement_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_movement_ibfk_2` FOREIGN KEY (`location_from`) REFERENCES `spectrum_location` (`id`),
  CONSTRAINT `spectrum_movement_ibfk_3` FOREIGN KEY (`location_to`) REFERENCES `spectrum_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_notification
CREATE TABLE IF NOT EXISTS `spectrum_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_unread` (`user_id`,`read_at`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `spectrum_notification_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: spectrum_object_entry
CREATE TABLE IF NOT EXISTS `spectrum_object_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `entry_number` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_method` varchar(50) DEFAULT NULL,
  `entry_reason` text,
  `depositor_name` varchar(255) DEFAULT NULL,
  `depositor_contact` text,
  `depositor_address` text,
  `current_owner` varchar(255) DEFAULT NULL,
  `owner_contact` text,
  `return_date` date DEFAULT NULL,
  `entry_note` text,
  `received_by` varchar(255) DEFAULT NULL,
  `packing_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'received',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_entry_number` (`entry_number`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_depositor` (`depositor_name`),
  KEY `idx_wf_entry` (`workflow_state`),
  CONSTRAINT `spectrum_object_entry_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_object_exit
CREATE TABLE IF NOT EXISTS `spectrum_object_exit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `exit_number` varchar(50) NOT NULL,
  `exit_date` date NOT NULL,
  `exit_reason` varchar(50) DEFAULT NULL,
  `exit_destination` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` text,
  `recipient_address` text,
  `authorization_name` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `packing_note` text,
  `dispatch_note` text,
  `courier_name` varchar(255) DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `exit_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_exit_number` (`exit_number`),
  KEY `idx_exit_date` (`exit_date`),
  KEY `idx_wf_exit` (`workflow_state`),
  CONSTRAINT `spectrum_object_exit_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_procedure_history
CREATE TABLE IF NOT EXISTS `spectrum_procedure_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_type` varchar(100) NOT NULL,
  `procedure_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_valuation
CREATE TABLE IF NOT EXISTS `spectrum_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_reference` varchar(50) DEFAULT NULL,
  `valuation_date` date NOT NULL,
  `valuation_type` varchar(50) DEFAULT NULL,
  `valuation_amount` decimal(15,2) NOT NULL,
  `valuation_currency` varchar(10) DEFAULT 'ZAR',
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_organization` varchar(255) DEFAULT NULL,
  `valuation_note` text,
  `renewal_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `renewal_cycle_months` int DEFAULT '36' COMMENT 'Months between valuations',
  `valuer` varchar(255) DEFAULT NULL COMMENT 'Name of appraiser/company',
  `currency` varchar(3) DEFAULT 'ZAR' COMMENT 'ISO currency code',
  PRIMARY KEY (`id`),
  KEY `idx_valuation_date` (`valuation_date`),
  KEY `idx_valuation_current` (`object_id`,`is_current`),
  KEY `idx_wf_val` (`workflow_state`),
  KEY `idx_renewal_date` (`renewal_date`),
  KEY `idx_wf_valuation` (`workflow_state`),
  CONSTRAINT `spectrum_valuation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_valuation_alert
CREATE TABLE IF NOT EXISTS `spectrum_valuation_alert` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_id` int DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `alert_date` date NOT NULL,
  `message` text,
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_alert_date` (`alert_date`),
  KEY `idx_alert_acknowledged` (`is_acknowledged`),
  CONSTRAINT `spectrum_valuation_alert_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_config
CREATE TABLE IF NOT EXISTS `spectrum_workflow_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `config_json` json NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `version` int DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_history
CREATE TABLE IF NOT EXISTS `spectrum_workflow_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `from_state` varchar(50) NOT NULL,
  `to_state` varchar(50) NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `user_id` int DEFAULT NULL,
  `note` text,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_record` (`procedure_type`,`record_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_notification
CREATE TABLE IF NOT EXISTS `spectrum_workflow_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `recipient_user_id` int DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`is_sent`,`created_at`),
  KEY `idx_recipient` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: spectrum_workflow_state
CREATE TABLE IF NOT EXISTS `spectrum_workflow_state` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `current_state` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_record` (`procedure_type`,`record_id`),
  KEY `idx_procedure_state` (`procedure_type`,`current_state`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: tiff_pdf_merge_file
CREATE TABLE IF NOT EXISTS `tiff_pdf_merge_file` (
  `id` int NOT NULL AUTO_INCREMENT,
  `merge_job_id` int NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT '0',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'image/tiff',
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `bit_depth` int DEFAULT NULL,
  `color_space` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `page_order` int DEFAULT '0',
  `status` VARCHAR(51) COLLATE utf8mb4_unicode_ci DEFAULT 'uploaded' COMMENT 'uploaded, processing, processed, failed',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `checksum_md5` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tpm_file_job` (`merge_job_id`),
  KEY `idx_tpm_file_order` (`merge_job_id`,`page_order`),
  CONSTRAINT `tiff_pdf_merge_file_ibfk_1` FOREIGN KEY (`merge_job_id`) REFERENCES `tiff_pdf_merge_job` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tiff_pdf_merge_job
CREATE TABLE IF NOT EXISTS `tiff_pdf_merge_job` (
  `id` int NOT NULL AUTO_INCREMENT,
  `information_object_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `job_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` VARCHAR(58) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, queued, processing, completed, failed',
  `total_files` int DEFAULT '0',
  `processed_files` int DEFAULT '0',
  `output_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_digital_object_id` int DEFAULT NULL,
  `pdf_standard` VARCHAR(42) COLLATE utf8mb4_unicode_ci DEFAULT 'pdfa-2b' COMMENT 'pdf, pdfa-1b, pdfa-2b, pdfa-3b',
  `compression_quality` int DEFAULT '85',
  `page_size` VARCHAR(39) COLLATE utf8mb4_unicode_ci DEFAULT 'auto' COMMENT 'auto, a4, letter, legal, a3',
  `orientation` VARCHAR(37) COLLATE utf8mb4_unicode_ci DEFAULT 'auto' COMMENT 'auto, portrait, landscape',
  `dpi` int DEFAULT '300',
  `preserve_originals` tinyint(1) DEFAULT '1',
  `attach_to_record` tinyint(1) DEFAULT '1',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tpm_job_status` (`status`),
  KEY `idx_tpm_job_user` (`user_id`),
  KEY `idx_tpm_job_info_object` (`information_object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tiff_pdf_settings
CREATE TABLE IF NOT EXISTS `tiff_pdf_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` VARCHAR(42) COLLATE utf8mb4_unicode_ci DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label
CREATE TABLE IF NOT EXISTS `tk_label` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` bigint unsigned NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_code` (`code`),
  UNIQUE KEY `uq_tk_uri` (`uri`),
  KEY `idx_tk_cat` (`tk_label_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_category
CREATE TABLE IF NOT EXISTS `tk_label_category` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_category_i18n
CREATE TABLE IF NOT EXISTS `tk_label_category_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_i18n` (`tk_label_category_id`,`culture`),
  KEY `idx_tk_cat_i18n_parent` (`tk_label_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_i18n
CREATE TABLE IF NOT EXISTS `tk_label_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `usage_guide` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_i18n` (`tk_label_id`,`culture`),
  KEY `idx_tk_i18n_parent` (`tk_label_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_compartment_access
CREATE TABLE IF NOT EXISTS `user_compartment_access` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `compartment_id` int unsigned NOT NULL,
  `granted_by` int unsigned NOT NULL,
  `granted_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `briefing_date` date DEFAULT NULL,
  `briefing_reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_compartment` (`user_id`,`compartment_id`),
  KEY `idx_compartment` (`compartment_id`),
  KEY `idx_expiry` (`expiry_date`,`active`),
  CONSTRAINT `user_compartment_access_ibfk_1` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: user_display_preference
CREATE TABLE IF NOT EXISTS `user_display_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module context: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list' COMMENT 'tree, grid, gallery, list, timeline',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_custom` tinyint(1) DEFAULT '1' COMMENT 'True if user explicitly set, false if inherited from global',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_udp_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_security_clearance
CREATE TABLE IF NOT EXISTS `user_security_clearance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usc_user` (`user_id`),
  KEY `idx_usc_classification_id` (`classification_id`),
  KEY `idx_usc_expires_at` (`expires_at`),
  KEY `idx_usc_granted_by` (`granted_by`),
  CONSTRAINT `fk_usc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: user_security_clearance_log
CREATE TABLE IF NOT EXISTS `user_security_clearance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `action` VARCHAR(46) NOT NULL COMMENT 'granted, revoked, updated, expired',
  `changed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: viewer_3d_settings
CREATE TABLE IF NOT EXISTS `viewer_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(42) DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: watermark_setting
CREATE TABLE IF NOT EXISTS `watermark_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: watermark_type
CREATE TABLE IF NOT EXISTS `watermark_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `image_file` varchar(255) NOT NULL,
  `position` varchar(50) DEFAULT 'repeat',
  `opacity` decimal(3,2) DEFAULT '0.30',
  `active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Table: workflow_history
CREATE TABLE IF NOT EXISTS `workflow_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_instance_id` bigint unsigned NOT NULL,
  `from_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `transition` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int unsigned NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wh_instance` (`workflow_instance_id`),
  KEY `idx_wh_created` (`created_at`),
  CONSTRAINT `workflow_history_workflow_instance_id_foreign` FOREIGN KEY (`workflow_instance_id`) REFERENCES `workflow_instance` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: workflow_instance
CREATE TABLE IF NOT EXISTS `workflow_instance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `workflow_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int unsigned NOT NULL,
  `current_state` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_complete` tinyint(1) NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_wi_workflow` (`workflow_id`),
  KEY `idx_wi_entity` (`entity_type`,`entity_id`),
  KEY `idx_wi_state` (`current_state`),
  KEY `idx_wi_complete` (`is_complete`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table: library_settings
CREATE TABLE IF NOT EXISTS `library_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(42) DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: email_setting defaults
INSERT IGNORE INTO `email_setting` (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`) VALUES
('smtp_enabled', '0', 'boolean', 'smtp', 'Enable email sending'),
('smtp_host', '', 'text', 'smtp', 'SMTP server hostname'),
('smtp_port', '587', 'number', 'smtp', 'SMTP server port'),
('smtp_encryption', 'tls', 'text', 'smtp', 'Encryption type (tls, ssl, or empty)'),
('smtp_username', '', 'text', 'smtp', 'SMTP username'),
('smtp_password', '', 'password', 'smtp', 'SMTP password'),
('smtp_from_email', '', 'email', 'smtp', 'From email address'),
('smtp_from_name', 'AtoM Archive', 'text', 'smtp', 'From name'),
('notify_new_researcher', '', 'email', 'notifications', 'Email to notify of new researcher registrations'),
('notify_new_booking', '', 'email', 'notifications', 'Email to notify of new booking requests'),
('notify_access_request', '', 'email', 'notifications', 'Email to notify of access requests'),
('template_welcome', 'Welcome to our archive. Your registration has been received.', 'textarea', 'templates', 'Welcome email template'),
('template_booking_confirm', 'Your booking has been confirmed.', 'textarea', 'templates', 'Booking confirmation template'),
('template_access_approved', 'Your access request has been approved.', 'textarea', 'templates', 'Access approved template');

-- Table: atom_isbn_provider
CREATE TABLE IF NOT EXISTS `atom_isbn_provider` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `api_endpoint` varchar(500) NOT NULL,
  `api_key_setting` varchar(100) DEFAULT NULL,
  `priority` int DEFAULT 10,
  `enabled` tinyint(1) DEFAULT 1,
  `rate_limit_per_minute` int DEFAULT 100,
  `response_format` varchar(20) DEFAULT 'json',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default ISBN providers
INSERT IGNORE INTO atom_isbn_provider (name, slug, api_endpoint, api_key_setting, priority, enabled, rate_limit_per_minute, response_format) VALUES
('Open Library', 'openlibrary', 'https://openlibrary.org/api/books', NULL, 10, 1, 100, 'json'),
('Google Books', 'googlebooks', 'https://www.googleapis.com/books/v1/volumes', NULL, 20, 1, 100, 'json'),
('WorldCat', 'worldcat', 'https://www.worldcat.org/webservices/catalog/content/isbn/', NULL, 30, 0, 10, 'marcxml');


-- Migration tracking table
CREATE TABLE IF NOT EXISTS atom_migration (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration VARCHAR(255) NOT NULL UNIQUE,
  batch INT NOT NULL DEFAULT 1,
  executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Library cover queue for async processing
CREATE TABLE IF NOT EXISTS atom_library_cover_queue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  information_object_id INT UNSIGNED NOT NULL,
  isbn VARCHAR(20) NOT NULL,
  status VARCHAR(50) COMMENT 'pending, processing, completed, failed' DEFAULT 'pending',
  attempts TINYINT DEFAULT 0,
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processed_at TIMESTAMP NULL,
  INDEX idx_status (status),
  INDEX idx_io_id (information_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Security Classification Object relationship
CREATE TABLE IF NOT EXISTS security_classification_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    classification_id BIGINT UNSIGNED NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sco_object (object_id),
    INDEX idx_sco_classification (classification_id),
    UNIQUE KEY unique_object_classification (object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- SEED DATA: Required AHG Plugins (DO NOT REMOVE)
-- =============================================================================

-- Required plugins into atom_plugin (for Symfony/AtoM loading)
INSERT INTO atom_plugin (name, class_name, is_enabled, is_core, is_locked, load_order, category, created_at, updated_at)
VALUES
('ahgCorePlugin', 'ahgCorePluginConfiguration', 1, 1, 1, 5, 'ahg', NOW(), NOW()),
('ahgThemeB5Plugin', 'ahgThemeB5PluginConfiguration', 0, 1, 1, 10, 'theme', NOW(), NOW()),
('ahgSecurityClearancePlugin', 'ahgSecurityClearancePluginConfiguration', 1, 1, 1, 20, 'ahg', NOW(), NOW()),
('ahgDisplayPlugin', 'ahgDisplayPluginConfiguration', 1, 1, 1, 30, 'ahg', NOW(), NOW())
ON DUPLICATE KEY UPDATE is_core = 1, is_locked = 1;

-- Required plugins into atom_extension (for extension manager)
INSERT INTO atom_extension (machine_name, display_name, version, description, status, protection_level, installed_at, enabled_at, created_at)
VALUES
('ahgCorePlugin', 'AHG Core', '1.0.0', 'Core framework components required by all AHG plugins', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgThemeB5Plugin', 'AHG Bootstrap 5 Theme', '1.0.0', 'AHG Bootstrap 5 theme with enhanced UI', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgSecurityClearancePlugin', 'Security Clearance', '1.0.0', 'Security classification system for records', 'enabled', 'system', NOW(), NOW(), NOW()),
('ahgDisplayPlugin', 'Display Mode Manager', '1.0.0', 'Display mode switching for GLAM sectors', 'enabled', 'system', NOW(), NOW(), NOW())
ON DUPLICATE KEY UPDATE protection_level = 'system';

-- NOTE: GLAM/DAM terms are created by individual plugins:
-- ahgMuseumPlugin, ahgLibraryPlugin, ahgGalleryPlugin, ahgDAMPlugin
-- Each plugin creates its own terms in its data/install.sql

-- =============================================
-- Watermark Types (default data)
-- =============================================
INSERT INTO `watermark_type` (`code`, `name`, `image_file`, `position`, `opacity`, `active`, `sort_order`) VALUES
('DRAFT', 'Draft', 'draft.png', 'center', 0.40, 1, 1),
('COPYRIGHT', 'Copyright', 'copyright.png', 'bottom right', 0.30, 1, 2),
('CONFIDENTIAL', 'Confidential', 'confidential.png', 'repeat', 0.40, 1, 3),
('SECRET', 'Secret', 'secret_copyright.png', 'repeat', 0.40, 1, 4),
('TOP_SECRET', 'Top Secret', 'top_secret_copyright.png', 'repeat', 0.50, 1, 5),
('NONE', 'No Watermark', '', 'none', 0.00, 1, 6),
('SAMPLE', 'Sample', 'sample.png', 'center', 0.50, 1, 7),
('PREVIEW', 'Preview Only', 'preview.png', 'center', 0.40, 1, 8),
('RESTRICTED', 'Restricted', 'restricted.png', 'repeat', 0.35, 1, 9)
ON DUPLICATE KEY UPDATE name=VALUES(name), image_file=VALUES(image_file), position=VALUES(position), opacity=VALUES(opacity);

-- ============================================================
-- TK Labels (Traditional Knowledge Labels)
-- ============================================================

-- Table: tk_label_category
CREATE TABLE IF NOT EXISTS `tk_label_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_category_i18n
CREATE TABLE IF NOT EXISTS `tk_label_category_i18n` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` int unsigned NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_cat_i18n` (`tk_label_category_id`, `culture`),
  CONSTRAINT `fk_tk_cat_i18n` FOREIGN KEY (`tk_label_category_id`) REFERENCES `tk_label_category` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label
CREATE TABLE IF NOT EXISTS `tk_label` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_category_id` int unsigned DEFAULT NULL,
  `code` varchar(20) NOT NULL,
  `uri` varchar(255) DEFAULT NULL,
  `icon_url` varchar(500) DEFAULT NULL,
  `icon_file` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_code` (`code`),
  KEY `idx_tk_cat` (`tk_label_category_id`),
  CONSTRAINT `fk_tk_label_cat` FOREIGN KEY (`tk_label_category_id`) REFERENCES `tk_label_category` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tk_label_i18n
CREATE TABLE IF NOT EXISTS `tk_label_i18n` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tk_label_id` int unsigned NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  `community_protocol` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tk_i18n` (`tk_label_id`, `culture`),
  CONSTRAINT `fk_tk_label_i18n` FOREIGN KEY (`tk_label_id`) REFERENCES `tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default TK Label Categories
INSERT IGNORE INTO `tk_label_category` (`id`, `code`, `color`, `sort_order`) VALUES
(1, 'attribution', '#0d6efd', 1),
(2, 'protocol', '#198754', 2),
(3, 'provenance', '#ffc107', 3);

INSERT IGNORE INTO `tk_label_category_i18n` (`tk_label_category_id`, `culture`, `name`, `description`) VALUES
(1, 'en', 'Attribution', 'Labels for proper attribution and credit'),
(2, 'en', 'Protocol', 'Labels for cultural protocols and restrictions'),
(3, 'en', 'Provenance', 'Labels for provenance and verification');

-- Default TK Labels
INSERT IGNORE INTO `tk_label` (`id`, `tk_label_category_id`, `code`, `uri`, `icon_url`, `is_active`, `sort_order`) VALUES
(1, 1, 'TK-A', 'https://localcontexts.org/label/tk-attribution/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Attribution.png', 1, 1),
(2, 1, 'TK-CL', 'https://localcontexts.org/label/tk-clan/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Clan.png', 1, 2),
(3, 1, 'TK-F', 'https://localcontexts.org/label/tk-family/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Family.png', 1, 3),
(4, 2, 'TK-MC', 'https://localcontexts.org/label/tk-multiple-communities/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Men_General.png', 1, 10),
(5, 2, 'TK-WG', 'https://localcontexts.org/label/tk-women-general/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Women_General.png', 1, 11),
(6, 2, 'TK-SS', 'https://localcontexts.org/label/tk-seasonal/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Seasonal.png', 1, 12),
(7, 2, 'TK-CV', 'https://localcontexts.org/label/tk-community-voice/', NULL, 1, 13),
(8, 2, 'TK-CS', 'https://localcontexts.org/label/tk-community-use-only/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Community_Use_Only.png', 1, 14),
(9, 2, 'TK-NC', 'https://localcontexts.org/label/tk-non-commercial/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_NonCommercial.png', 1, 15),
(10, 3, 'TK-V', 'https://localcontexts.org/label/tk-verified/', 'https://localcontexts.org/wp-content/uploads/2023/03/TK_Verified.png', 1, 20),
(11, 3, 'TK-CO', 'https://localcontexts.org/label/tk-open-to-commercialization/', NULL, 1, 21),
(12, 3, 'TK-OC', 'https://localcontexts.org/label/tk-outreach/', NULL, 1, 22);

INSERT IGNORE INTO `tk_label_i18n` (`tk_label_id`, `culture`, `name`, `description`) VALUES
(1, 'en', 'TK Attribution', 'Corrects historical mistakes in attribution.'),
(2, 'en', 'TK Clan', 'Material associated with a specific clan.'),
(3, 'en', 'TK Family', 'Material with family ownership.'),
(4, 'en', 'TK Multiple Communities', 'Shared across multiple communities.'),
(5, 'en', 'TK Women General', 'Gender restrictions apply - women.'),
(6, 'en', 'TK Seasonal', 'Seasonal or time-based restrictions.'),
(7, 'en', 'TK Community Voice', 'Community protocols apply.'),
(8, 'en', 'TK Community Use Only', 'For community use only.'),
(9, 'en', 'TK Non-Commercial', 'Not for commercial use.'),
(10, 'en', 'TK Verified', 'Verified by community.'),
(11, 'en', 'TK-CO', 'Open to commercialization with permission.'),
(12, 'en', 'TK Open to Commercialization', 'Approved for outreach activities.');

-- =============================================================================
-- Heritage Era Reference Data
-- Core historical eras for search query understanding
-- Additional eras can be loaded from PeriodO via: bin/load-eras
-- =============================================================================

CREATE TABLE IF NOT EXISTS `heritage_era` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `term` VARCHAR(100) NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `category` VARCHAR(50) DEFAULT 'general',
    `region` VARCHAR(50) DEFAULT NULL,
    `source` VARCHAR(50) DEFAULT 'system',
    `is_enabled` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_term` (`term`),
    KEY `idx_category` (`category`),
    KEY `idx_region` (`region`),
    KEY `idx_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core eras (installed by default, covers common GLAM queries)
INSERT INTO `heritage_era` (`term`, `label`, `start_date`, `end_date`, `category`, `region`, `source`) VALUES
-- World Wars
('ww1', 'World War I', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('wwi', 'World War I', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('world war 1', 'World War I', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('world war i', 'World War I', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('first world war', 'First World War', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('great war', 'Great War', '1914-01-01', '1918-12-31', 'war', 'global', 'system'),
('ww2', 'World War II', '1939-01-01', '1945-12-31', 'war', 'global', 'system'),
('wwii', 'World War II', '1939-01-01', '1945-12-31', 'war', 'global', 'system'),
('world war 2', 'World War II', '1939-01-01', '1945-12-31', 'war', 'global', 'system'),
('world war ii', 'World War II', '1939-01-01', '1945-12-31', 'war', 'global', 'system'),
('second world war', 'Second World War', '1939-01-01', '1945-12-31', 'war', 'global', 'system'),
('cold war', 'Cold War', '1947-01-01', '1991-12-31', 'war', 'global', 'system'),
('pre-war', 'Pre-War', '1900-01-01', '1913-12-31', 'period', 'global', 'system'),
('inter-war', 'Inter-War', '1918-01-01', '1939-12-31', 'period', 'global', 'system'),
('post-war', 'Post-War', '1945-01-01', '1960-12-31', 'period', 'global', 'system'),
-- British
('victorian', 'Victorian Era', '1837-01-01', '1901-12-31', 'period', 'britain', 'system'),
('edwardian', 'Edwardian Era', '1901-01-01', '1910-12-31', 'period', 'britain', 'system'),
('georgian', 'Georgian Era', '1714-01-01', '1837-12-31', 'period', 'britain', 'system'),
('tudor', 'Tudor Period', '1485-01-01', '1603-12-31', 'period', 'britain', 'system'),
('elizabethan', 'Elizabethan Era', '1558-01-01', '1603-12-31', 'period', 'britain', 'system'),
-- American
('civil war', 'American Civil War', '1861-01-01', '1865-12-31', 'war', 'america', 'system'),
('american civil war', 'American Civil War', '1861-01-01', '1865-12-31', 'war', 'america', 'system'),
('great depression', 'Great Depression', '1929-01-01', '1939-12-31', 'period', 'america', 'system'),
('roaring twenties', 'Roaring Twenties', '1920-01-01', '1929-12-31', 'period', 'america', 'system'),
('civil rights era', 'Civil Rights Era', '1954-01-01', '1968-12-31', 'period', 'america', 'system'),
-- European
('medieval', 'Medieval Period', '0500-01-01', '1500-12-31', 'period', 'europe', 'system'),
('middle ages', 'Middle Ages', '0500-01-01', '1500-12-31', 'period', 'europe', 'system'),
('renaissance', 'Renaissance', '1400-01-01', '1600-12-31', 'period', 'europe', 'system'),
('enlightenment', 'Age of Enlightenment', '1685-01-01', '1815-12-31', 'period', 'europe', 'system'),
('french revolution', 'French Revolution', '1789-01-01', '1799-12-31', 'period', 'europe', 'system'),
('holocaust', 'Holocaust', '1941-01-01', '1945-12-31', 'period', 'europe', 'system'),
-- South Africa
('apartheid', 'Apartheid Era', '1948-01-01', '1994-12-31', 'period', 'africa', 'system'),
('post-apartheid', 'Post-Apartheid', '1994-01-01', '2030-12-31', 'period', 'africa', 'system'),
('colonial', 'Colonial Era (SA)', '1652-01-01', '1910-12-31', 'period', 'africa', 'system'),
('boer war', 'Boer War', '1899-01-01', '1902-12-31', 'war', 'africa', 'system'),
('anglo-boer war', 'Anglo-Boer War', '1899-01-01', '1902-12-31', 'war', 'africa', 'system'),
('great trek', 'Great Trek', '1836-01-01', '1852-12-31', 'period', 'africa', 'system'),
('liberation struggle', 'Liberation Struggle', '1960-01-01', '1994-12-31', 'period', 'africa', 'system'),
-- Art movements
('baroque', 'Baroque', '1600-01-01', '1750-12-31', 'art', 'europe', 'system'),
('impressionism', 'Impressionism', '1860-01-01', '1890-12-31', 'art', 'europe', 'system'),
('art deco', 'Art Deco', '1920-01-01', '1940-12-31', 'art', 'global', 'system'),
('modernism', 'Modernism', '1900-01-01', '1970-12-31', 'art', 'global', 'system'),
-- General
('industrial revolution', 'Industrial Revolution', '1760-01-01', '1840-12-31', 'period', 'global', 'system'),
('digital age', 'Digital Age', '1990-01-01', '2030-12-31', 'period', 'global', 'system')
ON DUPLICATE KEY UPDATE label = VALUES(label);

-- =============================================================================
-- Heritage Contributions Module
-- =============================================================================

-- Table: heritage_contributor
CREATE TABLE IF NOT EXISTS `heritage_contributor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `bio` text,
  `trust_level` VARCHAR(45) DEFAULT 'new' COMMENT 'new, contributor, trusted, expert',
  `email_verified` tinyint(1) DEFAULT '0',
  `email_verify_token` varchar(100) DEFAULT NULL,
  `email_verify_expires` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(100) DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `total_contributions` int DEFAULT '0',
  `approved_contributions` int DEFAULT '0',
  `rejected_contributions` int DEFAULT '0',
  `points` int DEFAULT '0',
  `badges` json DEFAULT NULL,
  `preferences` json DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_contribution_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_trust_level` (`trust_level`),
  KEY `idx_points` (`points` DESC),
  KEY `idx_verified` (`email_verified`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_type
CREATE TABLE IF NOT EXISTS `heritage_contribution_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT 'bi-pencil',
  `color` varchar(20) DEFAULT 'primary',
  `requires_validation` tinyint(1) DEFAULT '1',
  `points_value` int DEFAULT '10',
  `min_trust_level` VARCHAR(45) DEFAULT 'new' COMMENT 'new, contributor, trusted, expert',
  `display_order` int DEFAULT '100',
  `is_active` tinyint(1) DEFAULT '1',
  `config_json` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution
CREATE TABLE IF NOT EXISTS `heritage_contribution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contributor_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `contribution_type_id` int NOT NULL,
  `content` json NOT NULL,
  `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, approved, rejected, superseded',
  `reviewed_by` int DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text,
  `points_awarded` int DEFAULT '0',
  `version_number` int DEFAULT '1',
  `is_featured` tinyint(1) DEFAULT '0',
  `view_count` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contributor` (`contributor_id`),
  KEY `idx_object` (`information_object_id`),
  KEY `idx_type` (`contribution_type_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  KEY `idx_created` (`created_at`),
  KEY `idx_featured` (`is_featured`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_version
CREATE TABLE IF NOT EXISTS `heritage_contribution_version` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contribution_id` int NOT NULL,
  `version_number` int NOT NULL,
  `content` json NOT NULL,
  `created_by` int NOT NULL,
  `change_summary` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contribution` (`contribution_id`),
  KEY `idx_version` (`contribution_id`,`version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_session
CREATE TABLE IF NOT EXISTS `heritage_contributor_session` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contributor_id` int NOT NULL,
  `token` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_contributor` (`contributor_id`),
  KEY `idx_token` (`token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge
CREATE TABLE IF NOT EXISTS `heritage_contributor_badge` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` varchar(50) DEFAULT 'bi-award',
  `color` varchar(20) DEFAULT 'primary',
  `criteria_type` VARCHAR(76) DEFAULT 'contribution_count' COMMENT 'contribution_count, approval_rate, points, type_specific, manual',
  `criteria_value` int DEFAULT '0',
  `criteria_config` json DEFAULT NULL,
  `points_bonus` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge_award
CREATE TABLE IF NOT EXISTS `heritage_contributor_badge_award` (
  `id` int NOT NULL AUTO_INCREMENT,
  `contributor_id` int NOT NULL,
  `badge_id` int NOT NULL,
  `awarded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contributor_badge` (`contributor_id`,`badge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: Contribution Types
INSERT IGNORE INTO `heritage_contribution_type` (`code`, `name`, `description`, `icon`, `color`, `requires_validation`, `points_value`, `display_order`) VALUES
('transcription', 'Transcription', 'Transcribe handwritten or typed documents into searchable text', 'bi-file-text', 'primary', 1, 25, 1),
('identification', 'Identification', 'Identify people, places, or objects in photographs and documents', 'bi-person-badge', 'success', 1, 15, 2),
('context', 'Historical Context', 'Add historical context, personal memories, or background information', 'bi-book', 'info', 1, 20, 3),
('correction', 'Correction', 'Suggest corrections to existing metadata or descriptions', 'bi-pencil-square', 'warning', 1, 10, 4),
('translation', 'Translation', 'Translate content into other languages', 'bi-translate', 'secondary', 1, 30, 5),
('tag', 'Tags/Keywords', 'Add relevant tags and keywords to improve discoverability', 'bi-tags', 'dark', 0, 5, 6);

-- Seed: Contributor Badges
INSERT IGNORE INTO `heritage_contributor_badge` (`code`, `name`, `description`, `icon`, `color`, `criteria_type`, `criteria_value`, `display_order`) VALUES
('first_contribution', 'First Steps', 'Made your first contribution', 'bi-star', 'warning', 'contribution_count', 1, 1),
('contributor_10', 'Active Contributor', 'Made 10 approved contributions', 'bi-star-fill', 'warning', 'contribution_count', 10, 2),
('contributor_50', 'Dedicated Contributor', 'Made 50 approved contributions', 'bi-trophy', 'warning', 'contribution_count', 50, 3),
('contributor_100', 'Heritage Champion', 'Made 100 approved contributions', 'bi-trophy-fill', 'primary', 'contribution_count', 100, 4),
('transcriber', 'Transcription Expert', 'Completed 25 transcriptions', 'bi-file-text-fill', 'primary', 'type_specific', 25, 10),
('identifier', 'Sharp Eye', 'Identified people in 25 photographs', 'bi-eye', 'success', 'type_specific', 25, 11),
('historian', 'Local Historian', 'Added context to 25 records', 'bi-book-fill', 'info', 'type_specific', 25, 12),
('perfectionist', 'High Quality', 'Maintained 95% approval rate on 20+ contributions', 'bi-check-circle-fill', 'success', 'approval_rate', 95, 20);


-- ============================================================================
-- Schema migrations (date order, seed-only ones excluded)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 2025_01_08_add_record_check.sql
-- ----------------------------------------------------------------------------
-- Migration: Add record check query column to atom_plugin
-- Date: 2025-01-08
-- Purpose: Store SQL queries to check if plugin has associated records

-- Check if column exists before adding
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'atom_plugin' 
    AND COLUMN_NAME = 'record_check_query'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE atom_plugin ADD COLUMN record_check_query TEXT NULL AFTER settings',
    'SELECT "Column record_check_query already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add record check queries for existing plugins
UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_library_item' 
WHERE name = 'ahgLibraryPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_audit_log' 
WHERE name = 'ahgAuditTrailPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_research_request' 
WHERE name = 'ahgResearchPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_backup' 
WHERE name = 'ahgBackupPlugin' AND record_check_query IS NULL;

UPDATE atom_plugin SET record_check_query = 'SELECT COUNT(*) FROM atom_security_clearance' 
WHERE name = 'ahgSecurityClearancePlugin' AND record_check_query IS NULL;

-- (SKIP — seed-only, belongs in database/seeds/: 2026_01_07_sector_level_defaults.sql)

-- ----------------------------------------------------------------------------
-- 2026_01_18_add_missing_indexes.sql
-- ----------------------------------------------------------------------------
-- Migration: Add missing database indexes for better query performance
-- Date: 2026-01-18
-- Purpose: Improve query performance on frequently filtered columns

-- Helper procedure to safely add indexes
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_index_if_not_exists(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_column VARCHAR(64)
)
proc: BEGIN
    DECLARE table_exists INT DEFAULT 0;
    DECLARE column_exists INT DEFAULT 0;
    DECLARE index_exists INT DEFAULT 0;

    -- Heratio standalone-install patch: skip silently when the target table
    -- or column does not exist yet (the relevant plugin install.sql will run
    -- later and create the index itself). Upstream procedure assumed all
    -- plugin tables were already in place — true on AtoM overlay, not on a
    -- fresh standalone install.
    SELECT COUNT(*) INTO table_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
    IF table_exists = 0 THEN
        LEAVE proc;
    END IF;

    SELECT COUNT(*) INTO column_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
    IF column_exists = 0 THEN
        LEAVE proc;
    END IF;

    SELECT COUNT(*) INTO index_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = p_table
    AND INDEX_NAME = p_index;

    IF index_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_column, '`)');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END proc //
DELIMITER ;

-- Add indexes for rights tables
CALL add_index_if_not_exists('rights_i18n', 'idx_culture', 'culture');
CALL add_index_if_not_exists('rights_grant_i18n', 'idx_culture', 'culture');

-- Add indexes for research tables
CALL add_index_if_not_exists('research_annotation', 'idx_annotation_type', 'annotation_type');
CALL add_index_if_not_exists('research_booking', 'idx_confirmed_by', 'confirmed_by');

-- Add indexes for privacy tables
CALL add_index_if_not_exists('privacy_dsar', 'idx_jurisdiction', 'jurisdiction');

-- Add indexes for spectrum tables (commonly queried)
CALL add_index_if_not_exists('spectrum_event', 'idx_event_type', 'event_type');
CALL add_index_if_not_exists('spectrum_event', 'idx_created_at', 'created_at');
CALL add_index_if_not_exists('spectrum_condition_check', 'idx_check_date', 'check_date');
CALL add_index_if_not_exists('spectrum_condition_check', 'idx_condition_status', 'condition_status');

-- Clean up procedure
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ----------------------------------------------------------------------------
-- 2026_01_25_create_heritage_discovery_tables.sql
-- ----------------------------------------------------------------------------
-- Migration: Create Heritage Discovery Engine Tables
-- Date: 2026-01-25
-- Description: Tables for intelligent search, learning, and ranking

-- ============================================================================
-- Table: heritage_discovery_click
-- Track user clicks on search results for learning
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_discovery_click (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    search_log_id BIGINT NOT NULL,
    item_id INT NOT NULL,
    item_type VARCHAR(50) DEFAULT 'information_object',
    position INT NOT NULL,
    time_to_click_ms INT DEFAULT NULL,
    dwell_time_seconds INT DEFAULT NULL,

    session_id VARCHAR(100) DEFAULT NULL,
    user_id INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_search_log (search_log_id),
    INDEX idx_item (item_id),
    INDEX idx_session (session_id),
    INDEX idx_created (created_at),

    CONSTRAINT fk_discovery_click_log
        FOREIGN KEY (search_log_id) REFERENCES heritage_discovery_log(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_learned_term
-- Learned synonyms and term relationships from user behavior
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_learned_term (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    term VARCHAR(255) NOT NULL,
    related_term VARCHAR(255) NOT NULL,
    relationship_type VARCHAR(57) COMMENT 'synonym, broader, narrower, related, spelling' DEFAULT 'related',
    confidence_score DECIMAL(5,4) DEFAULT 0.5,
    usage_count INT DEFAULT 1,

    source VARCHAR(52) COMMENT 'user_behavior, admin, taxonomy, external' DEFAULT 'user_behavior',
    is_verified TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_term_pair (institution_id, term, related_term),
    INDEX idx_term (term),
    INDEX idx_related (related_term),
    INDEX idx_confidence (confidence_score),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_search_suggestion
-- Autocomplete suggestions built from successful searches
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_search_suggestion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    suggestion_text VARCHAR(255) NOT NULL,
    suggestion_type VARCHAR(49) COMMENT 'query, title, subject, creator, place' DEFAULT 'query',

    search_count INT DEFAULT 1,
    click_count INT DEFAULT 0,
    success_rate DECIMAL(5,4) DEFAULT 0.5,
    avg_results INT DEFAULT 0,

    last_searched_at TIMESTAMP NULL,
    is_curated TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_suggestion (institution_id, suggestion_text, suggestion_type),
    INDEX idx_text (suggestion_text),
    INDEX idx_type (suggestion_type),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_success_rate (success_rate DESC),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_ranking_config
-- Configurable ranking weights per institution
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_ranking_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Relevance weights
    weight_title_match DECIMAL(4,3) DEFAULT 1.000,
    weight_content_match DECIMAL(4,3) DEFAULT 0.700,
    weight_identifier_match DECIMAL(4,3) DEFAULT 0.900,
    weight_subject_match DECIMAL(4,3) DEFAULT 0.800,
    weight_creator_match DECIMAL(4,3) DEFAULT 0.800,

    -- Quality weights
    weight_has_digital_object DECIMAL(4,3) DEFAULT 0.300,
    weight_description_length DECIMAL(4,3) DEFAULT 0.200,
    weight_has_dates DECIMAL(4,3) DEFAULT 0.150,
    weight_has_subjects DECIMAL(4,3) DEFAULT 0.150,

    -- Engagement weights
    weight_view_count DECIMAL(4,3) DEFAULT 0.100,
    weight_download_count DECIMAL(4,3) DEFAULT 0.150,
    weight_citation_count DECIMAL(4,3) DEFAULT 0.200,

    -- Boost/penalty
    boost_featured DECIMAL(4,3) DEFAULT 1.500,
    boost_recent DECIMAL(4,3) DEFAULT 1.100,
    penalty_incomplete DECIMAL(4,3) DEFAULT 0.800,

    -- Freshness decay
    freshness_decay_days INT DEFAULT 365,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_entity_cache
-- Cached extracted entities for faster filtering
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_entity_cache (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,

    entity_type VARCHAR(58) COMMENT 'person, organization, place, date, event, work' NOT NULL,
    entity_value VARCHAR(500) NOT NULL,
    normalized_value VARCHAR(500) DEFAULT NULL,
    confidence_score DECIMAL(5,4) DEFAULT 1.0,

    source_field VARCHAR(100) DEFAULT NULL,
    extraction_method VARCHAR(42) COMMENT 'taxonomy, ner, pattern, manual' DEFAULT 'taxonomy',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_value (entity_value(100)),
    INDEX idx_normalized (normalized_value(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Add columns to heritage_discovery_log for enhanced tracking
-- Using procedure to handle "column already exists" gracefully
-- ============================================================================
DROP PROCEDURE IF EXISTS add_discovery_log_columns;
DELIMITER //
CREATE PROCEDURE add_discovery_log_columns()
proc: BEGIN
    -- Heratio standalone-install patch: skip silently when the target table
    -- does not exist yet.
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "heritage_discovery_log") THEN
        LEAVE proc;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'detected_language') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN detected_language VARCHAR(10) DEFAULT 'en' AFTER query_text;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'query_intent') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN query_intent VARCHAR(50) DEFAULT NULL AFTER detected_language;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'parsed_entities') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN parsed_entities JSON DEFAULT NULL AFTER query_intent;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'expanded_terms') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN expanded_terms JSON DEFAULT NULL AFTER parsed_entities;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'click_count') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN click_count INT DEFAULT 0 AFTER result_count;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heritage_discovery_log' AND COLUMN_NAME = 'first_click_position') THEN
        ALTER TABLE heritage_discovery_log ADD COLUMN first_click_position INT DEFAULT NULL AFTER click_count;
    END IF;
END proc //
DELIMITER ;
CALL add_discovery_log_columns();
DROP PROCEDURE IF EXISTS add_discovery_log_columns;

-- ============================================================================
-- Seed default ranking config (global defaults)
-- ============================================================================
INSERT IGNORE INTO heritage_ranking_config (institution_id) VALUES (NULL);

-- ============================================================================
-- Seed some common learned terms (basic synonyms)
-- ============================================================================
INSERT IGNORE INTO heritage_learned_term (institution_id, term, related_term, relationship_type, confidence_score, source, is_verified) VALUES
-- Photo synonyms
(NULL, 'photo', 'photograph', 'synonym', 0.95, 'admin', 1),
(NULL, 'photos', 'photographs', 'synonym', 0.95, 'admin', 1),
(NULL, 'picture', 'photograph', 'synonym', 0.90, 'admin', 1),
(NULL, 'image', 'photograph', 'related', 0.85, 'admin', 1),
-- Document synonyms
(NULL, 'doc', 'document', 'synonym', 0.90, 'admin', 1),
(NULL, 'letter', 'correspondence', 'related', 0.85, 'admin', 1),
(NULL, 'memo', 'memorandum', 'synonym', 0.95, 'admin', 1),
-- Map synonyms
(NULL, 'map', 'cartographic material', 'related', 0.80, 'admin', 1),
(NULL, 'chart', 'map', 'related', 0.75, 'admin', 1),
-- Time period terms
(NULL, 'old', 'historic', 'related', 0.70, 'admin', 1),
(NULL, 'ancient', 'historic', 'related', 0.75, 'admin', 1),
(NULL, 'vintage', 'historic', 'related', 0.80, 'admin', 1),
(NULL, 'antique', 'historic', 'related', 0.75, 'admin', 1),
-- Common misspellings
(NULL, 'arcive', 'archive', 'spelling', 0.99, 'admin', 1),
(NULL, 'photgraph', 'photograph', 'spelling', 0.99, 'admin', 1),
(NULL, 'documnet', 'document', 'spelling', 0.99, 'admin', 1);

-- Verification
SELECT 'heritage_discovery_click' as tbl, COUNT(*) as cnt FROM heritage_discovery_click
UNION ALL SELECT 'heritage_learned_term', COUNT(*) FROM heritage_learned_term
UNION ALL SELECT 'heritage_search_suggestion', COUNT(*) FROM heritage_search_suggestion
UNION ALL SELECT 'heritage_ranking_config', COUNT(*) FROM heritage_ranking_config
UNION ALL SELECT 'heritage_entity_cache', COUNT(*) FROM heritage_entity_cache;

-- ----------------------------------------------------------------------------
-- 2026_01_25_create_heritage_enhanced_landing.sql
-- ----------------------------------------------------------------------------
-- Migration: Create Heritage Enhanced Landing Tables
-- Date: 2026-01-25
-- Description: Rijksstudio-inspired discovery interface with curated collections, timeline, and explore categories

-- =============================================================================
-- Table: heritage_featured_collection
-- Curated collections for showcase on landing page
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_featured_collection (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    curator_note TEXT DEFAULT NULL,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Link
    link_type VARCHAR(52) COMMENT 'collection, search, repository, external' DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    search_query JSON DEFAULT NULL,

    -- Stats (cached)
    item_count INT DEFAULT 0,
    image_count INT DEFAULT 0,

    -- Display
    display_size VARCHAR(42) COMMENT 'small, medium, large, featured' DEFAULT 'medium',
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_link_type (link_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_hero_slide
-- Full-bleed hero carousel slides
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_hero_slide (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    title VARCHAR(255) DEFAULT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Media
    image_path VARCHAR(500) NOT NULL,
    image_alt VARCHAR(255) DEFAULT NULL,
    video_url VARCHAR(500) DEFAULT NULL,
    media_type VARCHAR(24) COMMENT 'image, video' DEFAULT 'image',

    -- Visual effects
    overlay_type VARCHAR(33) COMMENT 'none, gradient, solid' DEFAULT 'gradient',
    overlay_color VARCHAR(7) DEFAULT '#000000',
    overlay_opacity DECIMAL(3,2) DEFAULT 0.50,
    text_position VARCHAR(58) COMMENT 'left, center, right, bottom-left, bottom-right' DEFAULT 'left',
    ken_burns TINYINT(1) DEFAULT 1,

    -- Call to action
    cta_text VARCHAR(100) DEFAULT NULL,
    cta_url VARCHAR(500) DEFAULT NULL,
    cta_style VARCHAR(46) COMMENT 'primary, secondary, outline, light' DEFAULT 'primary',

    -- Attribution
    source_item_id INT DEFAULT NULL,
    source_collection VARCHAR(255) DEFAULT NULL,
    photographer_credit VARCHAR(255) DEFAULT NULL,

    -- Display
    display_order INT DEFAULT 100,
    display_duration INT DEFAULT 8,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Scheduling
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_explore_category
-- Visual browse categories (like "Time", "Place", "People", "Theme")
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_explore_category (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    code VARCHAR(50) NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    tagline VARCHAR(255) DEFAULT NULL,

    -- Visual
    icon VARCHAR(50) DEFAULT 'bi-grid',
    cover_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT '#0d6efd',
    text_color VARCHAR(7) DEFAULT '#ffffff',

    -- Data source
    source_type VARCHAR(53) COMMENT 'taxonomy, authority, field, facet, custom' NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    taxonomy_id INT DEFAULT NULL,

    -- Display configuration
    display_style VARCHAR(47) COMMENT 'grid, list, timeline, map, carousel' DEFAULT 'grid',
    items_per_page INT DEFAULT 24,
    show_counts TINYINT(1) DEFAULT 1,
    show_thumbnails TINYINT(1) DEFAULT 1,

    -- Landing page display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    landing_items INT DEFAULT 6,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_code (institution_id, code),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Table: heritage_timeline_period
-- Time periods for timeline navigation
-- =============================================================================
CREATE TABLE IF NOT EXISTS heritage_timeline_period (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Content
    name VARCHAR(100) NOT NULL,
    short_name VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,

    -- Date range
    start_year INT NOT NULL,
    end_year INT DEFAULT NULL,
    circa TINYINT(1) DEFAULT 0,

    -- Visual
    cover_image VARCHAR(500) DEFAULT NULL,
    thumbnail_image VARCHAR(500) DEFAULT NULL,
    background_color VARCHAR(7) DEFAULT NULL,

    -- Search integration
    search_query JSON DEFAULT NULL,
    date_field VARCHAR(100) DEFAULT 'dates',

    -- Stats (cached)
    item_count INT DEFAULT 0,

    -- Display
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_years (start_year, end_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Default explore categories
INSERT IGNORE INTO heritage_explore_category (institution_id, code, name, description, tagline, icon, source_type, source_reference, display_style, display_order, show_on_landing) VALUES
(NULL, 'time', 'Time', 'Browse by historical period', 'Journey through time', 'bi-clock-history', 'field', 'dates', 'timeline', 1, 1),
(NULL, 'place', 'Place', 'Browse by location', 'Explore by geography', 'bi-geo-alt', 'authority', 'place', 'map', 2, 1),
(NULL, 'people', 'People', 'Browse by person or creator', 'Discover the people', 'bi-people', 'authority', 'actor', 'grid', 3, 1),
(NULL, 'theme', 'Theme', 'Browse by subject', 'Explore by topic', 'bi-tag', 'taxonomy', 'subject', 'grid', 4, 1),
(NULL, 'format', 'Format', 'Browse by format type', 'Filter by media', 'bi-collection', 'taxonomy', 'contentType', 'grid', 5, 1),
(NULL, 'trending', 'Trending', 'Popular items this week', 'What people are viewing', 'bi-graph-up', 'custom', 'trending', 'carousel', 6, 1);

-- Default timeline periods (South African focused with international context)
INSERT IGNORE INTO heritage_timeline_period (institution_id, name, short_name, start_year, end_year, description, display_order, show_on_landing) VALUES
(NULL, 'Pre-Colonial Era', 'Pre-1652', -10000, 1651, 'San and Khoi peoples, early Iron Age settlements, and African kingdoms before European contact', 1, 1),
(NULL, 'Dutch Colonial Period', '1652-1795', 1652, 1795, 'Dutch East India Company settlement at the Cape, expansion and conflicts', 2, 1),
(NULL, 'British Colonial Era', '1795-1910', 1795, 1910, 'British rule, the Great Trek, mineral discoveries, and Anglo-Boer Wars', 3, 1),
(NULL, 'Union of South Africa', '1910-1948', 1910, 1948, 'Formation of the Union, World Wars, and early segregation policies', 4, 1),
(NULL, 'Apartheid Era', '1948-1994', 1948, 1994, 'Formal apartheid, resistance movements, and the struggle for democracy', 5, 1),
(NULL, 'Democratic Era', '1994-Present', 1994, NULL, 'Post-apartheid South Africa, reconciliation, and nation building', 6, 1);

-- =============================================================================
-- VERIFICATION
-- =============================================================================
SELECT 'Enhanced Landing Tables Created' as status;
SELECT
    (SELECT COUNT(*) FROM heritage_featured_collection) as featured_collections,
    (SELECT COUNT(*) FROM heritage_hero_slide) as hero_slides,
    (SELECT COUNT(*) FROM heritage_explore_category) as explore_categories,
    (SELECT COUNT(*) FROM heritage_timeline_period) as timeline_periods;

-- ----------------------------------------------------------------------------
-- 2026_01_25_create_heritage_tables.sql
-- ----------------------------------------------------------------------------
-- Migration: Create Heritage Platform Tables
-- Date: 2026-01-25
-- Description: Foundation tables for Heritage discovery platform - landing page config, filters, stories, hero images

-- ============================================================================
-- Table: heritage_landing_config
-- Institution landing page configuration
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_landing_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    -- Hero section
    hero_tagline VARCHAR(500) DEFAULT 'Discover our collections',
    hero_subtext VARCHAR(500) DEFAULT NULL,
    hero_search_placeholder VARCHAR(255) DEFAULT 'What are you looking for?',
    suggested_searches JSON DEFAULT NULL,

    -- Hero media
    hero_media JSON DEFAULT NULL,
    hero_rotation_seconds INT DEFAULT 8,
    hero_effect VARCHAR(32) COMMENT 'kenburns, fade, none' DEFAULT 'kenburns',

    -- Sections enabled
    show_curated_stories TINYINT(1) DEFAULT 1,
    show_community_activity TINYINT(1) DEFAULT 1,
    show_filters TINYINT(1) DEFAULT 1,
    show_stats TINYINT(1) DEFAULT 1,
    show_recent_additions TINYINT(1) DEFAULT 1,

    -- Stats configuration
    stats_config JSON DEFAULT NULL,

    -- Styling
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_filter_type
-- Available filter types system-wide
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_filter_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    source_type VARCHAR(46) COMMENT 'taxonomy, authority, field, custom' NOT NULL,
    source_reference VARCHAR(255) DEFAULT NULL,
    is_system TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_institution_filter
-- Institution's filter configuration
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_institution_filter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    filter_type_id INT NOT NULL,

    is_enabled TINYINT(1) DEFAULT 1,
    display_name VARCHAR(100) DEFAULT NULL,
    display_icon VARCHAR(50) DEFAULT NULL,
    display_order INT DEFAULT 100,
    show_on_landing TINYINT(1) DEFAULT 1,
    show_in_search TINYINT(1) DEFAULT 1,
    max_items_landing INT DEFAULT 6,

    is_hierarchical TINYINT(1) DEFAULT 0,
    allow_multiple TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_filter_type (filter_type_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_inst_filter_type
        FOREIGN KEY (filter_type_id) REFERENCES heritage_filter_type(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_filter_value
-- Custom filter values for non-taxonomy filters
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_filter_value (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_filter_id INT NOT NULL,
    value_code VARCHAR(100) NOT NULL,
    display_label VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 100,
    parent_id INT DEFAULT NULL,
    filter_query JSON DEFAULT NULL,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution_filter (institution_filter_id),
    INDEX idx_parent (parent_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),

    CONSTRAINT fk_heritage_filter_value_inst
        FOREIGN KEY (institution_filter_id) REFERENCES heritage_institution_filter(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_heritage_filter_value_parent
        FOREIGN KEY (parent_id) REFERENCES heritage_filter_value(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_curated_story
-- Featured stories/collections on landing page
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_curated_story (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    title VARCHAR(255) NOT NULL,
    subtitle VARCHAR(500) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    cover_image VARCHAR(500) DEFAULT NULL,
    story_type VARCHAR(50) DEFAULT 'collection',

    link_type VARCHAR(46) COMMENT 'collection, search, external, page' DEFAULT 'search',
    link_reference VARCHAR(500) DEFAULT NULL,

    item_count INT DEFAULT NULL,

    is_featured TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_featured (is_featured),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_hero_image
-- Hero images for rotation
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_hero_image (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    image_path VARCHAR(500) NOT NULL,
    caption VARCHAR(500) DEFAULT NULL,
    collection_name VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,

    display_order INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_discovery_log
-- Search analytics and logging
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_discovery_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,

    query_text VARCHAR(500) DEFAULT NULL,
    filters_applied JSON DEFAULT NULL,
    result_count INT DEFAULT 0,

    user_id INT DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    search_duration_ms INT DEFAULT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at),
    INDEX idx_query (query_text(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Seed default filter types
-- ============================================================================
INSERT IGNORE INTO heritage_filter_type (code, name, icon, source_type, source_reference, is_system) VALUES
('content_type', 'Format', 'bi-file-earmark', 'taxonomy', 'contentType', 1),
('time_period', 'Time Period', 'bi-calendar', 'field', 'date', 1),
('place', 'Place', 'bi-geo-alt', 'authority', 'place', 1),
('subject', 'Subject', 'bi-tag', 'taxonomy', 'subject', 1),
('creator', 'Creator', 'bi-person', 'authority', 'actor', 1),
('collection', 'Collection', 'bi-collection', 'field', 'repository', 1),
('language', 'Language', 'bi-translate', 'taxonomy', 'language', 1),
('glam_sector', 'Type', 'bi-building', 'taxonomy', 'glamSector', 1);

-- ============================================================================
-- Seed default landing config (for single-institution deployments)
-- ============================================================================
INSERT IGNORE INTO heritage_landing_config (id, institution_id, hero_tagline, hero_subtext, hero_search_placeholder, suggested_searches, stats_config) VALUES
(1, NULL, 'Discover Our Heritage', 'Explore collections spanning centuries of history, culture, and human achievement', 'Search photographs, documents, artifacts...', '["photographs", "maps", "letters", "newspapers"]', '{"show_items": true, "show_collections": true, "show_contributors": false}');

-- ============================================================================
-- Seed default institution filters (enabled for single-institution)
-- ============================================================================
INSERT IGNORE INTO heritage_institution_filter (institution_id, filter_type_id, is_enabled, display_order, show_on_landing, show_in_search, max_items_landing)
SELECT NULL, id, 1,
    CASE code
        WHEN 'content_type' THEN 10
        WHEN 'time_period' THEN 20
        WHEN 'place' THEN 30
        WHEN 'subject' THEN 40
        WHEN 'creator' THEN 50
        WHEN 'collection' THEN 60
        WHEN 'language' THEN 70
        WHEN 'glam_sector' THEN 80
    END,
    CASE WHEN code IN ('content_type', 'time_period', 'place', 'subject', 'creator', 'collection') THEN 1 ELSE 0 END,
    1,
    6
FROM heritage_filter_type
WHERE is_system = 1;

-- Verification
SELECT 'heritage_landing_config' as tbl, COUNT(*) as cnt FROM heritage_landing_config
UNION ALL SELECT 'heritage_filter_type', COUNT(*) FROM heritage_filter_type
UNION ALL SELECT 'heritage_institution_filter', COUNT(*) FROM heritage_institution_filter
UNION ALL SELECT 'heritage_filter_value', COUNT(*) FROM heritage_filter_value
UNION ALL SELECT 'heritage_curated_story', COUNT(*) FROM heritage_curated_story
UNION ALL SELECT 'heritage_hero_image', COUNT(*) FROM heritage_hero_image
UNION ALL SELECT 'heritage_discovery_log', COUNT(*) FROM heritage_discovery_log;

-- ----------------------------------------------------------------------------
-- 2026_01_26_create_heritage_contributions.sql
-- ----------------------------------------------------------------------------
-- Migration: Create Heritage Contributions Tables
-- Date: 2026-01-26
-- Description: Public contributor accounts and contribution system

-- ============================================================================
-- CONTRIBUTOR ACCOUNTS
-- ============================================================================

-- Table: heritage_contributor
-- Public user accounts (separate from AtoM users)
CREATE TABLE IF NOT EXISTS heritage_contributor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    trust_level VARCHAR(45) COMMENT 'new, contributor, trusted, expert' DEFAULT 'new',
    email_verified TINYINT(1) DEFAULT 0,
    email_verify_token VARCHAR(100) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL,
    password_reset_token VARCHAR(100) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL,
    total_contributions INT DEFAULT 0,
    approved_contributions INT DEFAULT 0,
    rejected_contributions INT DEFAULT 0,
    points INT DEFAULT 0,
    badges JSON DEFAULT NULL,
    preferences JSON DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    last_contribution_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_email (email),
    INDEX idx_trust_level (trust_level),
    INDEX idx_points (points DESC),
    INDEX idx_verified (email_verified),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTION TYPES
-- ============================================================================

-- Table: heritage_contribution_type
-- Types of contributions users can make
CREATE TABLE IF NOT EXISTS heritage_contribution_type (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-pencil',
    color VARCHAR(20) DEFAULT 'primary',
    requires_validation TINYINT(1) DEFAULT 1,
    points_value INT DEFAULT 10,
    min_trust_level VARCHAR(45) COMMENT 'new, contributor, trusted, expert' DEFAULT 'new',
    display_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTIONS
-- ============================================================================

-- Table: heritage_contribution
-- Individual contributions from users
CREATE TABLE IF NOT EXISTS heritage_contribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    information_object_id INT NOT NULL,
    contribution_type_id INT NOT NULL,
    content JSON NOT NULL,
    status VARCHAR(51) COMMENT 'pending, approved, rejected, superseded' DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    review_notes TEXT DEFAULT NULL,
    points_awarded INT DEFAULT 0,
    version_number INT DEFAULT 1,
    is_featured TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_object (information_object_id),
    INDEX idx_type (contribution_type_id),
    INDEX idx_status (status),
    INDEX idx_reviewed_by (reviewed_by),
    INDEX idx_created (created_at),
    INDEX idx_featured (is_featured),

    CONSTRAINT fk_heritage_contribution_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_type
        FOREIGN KEY (contribution_type_id) REFERENCES heritage_contribution_type(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contribution_version
-- Version history for contribution edits
CREATE TABLE IF NOT EXISTS heritage_contribution_version (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contribution_id INT NOT NULL,
    version_number INT NOT NULL,
    content JSON NOT NULL,
    created_by INT NOT NULL,
    change_summary VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contribution (contribution_id),
    INDEX idx_version (contribution_id, version_number),

    CONSTRAINT fk_heritage_contribution_version_contribution
        FOREIGN KEY (contribution_id) REFERENCES heritage_contribution(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_contribution_version_creator
        FOREIGN KEY (created_by) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTOR SESSIONS
-- ============================================================================

-- Table: heritage_contributor_session
-- Session tokens for contributor authentication
CREATE TABLE IF NOT EXISTS heritage_contributor_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    token VARCHAR(100) NOT NULL UNIQUE,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_contributor (contributor_id),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),

    CONSTRAINT fk_heritage_contributor_session_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CONTRIBUTOR BADGES/ACHIEVEMENTS
-- ============================================================================

-- Table: heritage_contributor_badge
-- Badges that can be earned
CREATE TABLE IF NOT EXISTS heritage_contributor_badge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT 'bi-award',
    color VARCHAR(20) DEFAULT 'primary',
    criteria_type VARCHAR(76) COMMENT 'contribution_count, approval_rate, points, type_specific, manual' DEFAULT 'contribution_count',
    criteria_value INT DEFAULT 0,
    criteria_config JSON DEFAULT NULL,
    points_bonus INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_contributor_badge_award
-- Badges awarded to contributors
CREATE TABLE IF NOT EXISTS heritage_contributor_badge_award (
    id INT AUTO_INCREMENT PRIMARY KEY,
    contributor_id INT NOT NULL,
    badge_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_contributor_badge (contributor_id, badge_id),

    CONSTRAINT fk_heritage_badge_award_contributor
        FOREIGN KEY (contributor_id) REFERENCES heritage_contributor(id)
        ON DELETE CASCADE ON UPDATE CASCADE,

    CONSTRAINT fk_heritage_badge_award_badge
        FOREIGN KEY (badge_id) REFERENCES heritage_contributor_badge(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default contribution types
INSERT IGNORE INTO heritage_contribution_type (code, name, description, icon, color, requires_validation, points_value, display_order) VALUES
('transcription', 'Transcription', 'Transcribe handwritten or typed documents into searchable text', 'bi-file-text', 'primary', 1, 25, 1),
('identification', 'Identification', 'Identify people, places, or objects in photographs and documents', 'bi-person-badge', 'success', 1, 15, 2),
('context', 'Historical Context', 'Add historical context, personal memories, or background information', 'bi-book', 'info', 1, 20, 3),
('correction', 'Correction', 'Suggest corrections to existing metadata or descriptions', 'bi-pencil-square', 'warning', 1, 10, 4),
('translation', 'Translation', 'Translate content into other languages', 'bi-translate', 'secondary', 1, 30, 5),
('tag', 'Tags/Keywords', 'Add relevant tags and keywords to improve discoverability', 'bi-tags', 'dark', 0, 5, 6);

-- Default badges
INSERT IGNORE INTO heritage_contributor_badge (code, name, description, icon, color, criteria_type, criteria_value, display_order) VALUES
('first_contribution', 'First Steps', 'Made your first contribution', 'bi-star', 'warning', 'contribution_count', 1, 1),
('contributor_10', 'Active Contributor', 'Made 10 approved contributions', 'bi-star-fill', 'warning', 'contribution_count', 10, 2),
('contributor_50', 'Dedicated Contributor', 'Made 50 approved contributions', 'bi-trophy', 'warning', 'contribution_count', 50, 3),
('contributor_100', 'Heritage Champion', 'Made 100 approved contributions', 'bi-trophy-fill', 'primary', 'contribution_count', 100, 4),
('transcriber', 'Transcription Expert', 'Completed 25 transcriptions', 'bi-file-text-fill', 'primary', 'type_specific', 25, 10),
('identifier', 'Sharp Eye', 'Identified people in 25 photographs', 'bi-eye', 'success', 'type_specific', 25, 11),
('historian', 'Local Historian', 'Added context to 25 records', 'bi-book-fill', 'info', 'type_specific', 25, 12),
('perfectionist', 'High Quality', 'Maintained 95% approval rate on 20+ contributions', 'bi-check-circle-fill', 'success', 'approval_rate', 95, 20);

-- Verification
SELECT 'heritage_contributor' as tbl, COUNT(*) as cnt FROM heritage_contributor
UNION ALL SELECT 'heritage_contribution_type', COUNT(*) FROM heritage_contribution_type
UNION ALL SELECT 'heritage_contribution', COUNT(*) FROM heritage_contribution
UNION ALL SELECT 'heritage_contribution_version', COUNT(*) FROM heritage_contribution_version
UNION ALL SELECT 'heritage_contributor_session', COUNT(*) FROM heritage_contributor_session
UNION ALL SELECT 'heritage_contributor_badge', COUNT(*) FROM heritage_contributor_badge
UNION ALL SELECT 'heritage_contributor_badge_award', COUNT(*) FROM heritage_contributor_badge_award;

-- ----------------------------------------------------------------------------
-- 2026_01_26_create_heritage_sessions_6_9.sql
-- ----------------------------------------------------------------------------
-- Migration: Create Heritage Platform Tables for Sessions 6-9
-- Date: 2026-01-26
-- Description: Admin Configuration, Access Mediation, Custodian Interface, Analytics & Learning

-- ============================================================================
-- SESSION 8: ADMIN CONFIGURATION
-- ============================================================================

-- Table: heritage_feature_toggle
-- Feature flags per institution
CREATE TABLE IF NOT EXISTS heritage_feature_toggle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    feature_code VARCHAR(100) NOT NULL,
    feature_name VARCHAR(255) NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,
    config_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution_feature (institution_id, feature_code),
    INDEX idx_feature_code (feature_code),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_branding_config
-- Institution branding configuration
CREATE TABLE IF NOT EXISTS heritage_branding_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    logo_path VARCHAR(500) DEFAULT NULL,
    favicon_path VARCHAR(500) DEFAULT NULL,
    primary_color VARCHAR(7) DEFAULT '#0d6efd',
    secondary_color VARCHAR(7) DEFAULT NULL,
    accent_color VARCHAR(7) DEFAULT NULL,
    banner_text VARCHAR(500) DEFAULT NULL,
    footer_text TEXT DEFAULT NULL,
    custom_css TEXT DEFAULT NULL,
    social_links JSON DEFAULT NULL,
    contact_info JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_institution (institution_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 6: ACCESS MEDIATION
-- ============================================================================

-- Table: heritage_trust_level
-- User trust levels for access control
CREATE TABLE IF NOT EXISTS heritage_trust_level (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    level INT NOT NULL DEFAULT 0,
    can_view_restricted TINYINT(1) DEFAULT 0,
    can_download TINYINT(1) DEFAULT 0,
    can_bulk_download TINYINT(1) DEFAULT 0,
    is_system TINYINT(1) DEFAULT 0,
    description TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_level (level),
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_user_trust
-- User trust level assignments
CREATE TABLE IF NOT EXISTS heritage_user_trust (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trust_level_id INT NOT NULL,
    institution_id INT DEFAULT NULL,
    granted_by INT DEFAULT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    notes TEXT DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,

    UNIQUE KEY uk_user_institution (user_id, institution_id),
    INDEX idx_trust_level (trust_level_id),
    INDEX idx_expires (expires_at),
    INDEX idx_active (is_active),

    CONSTRAINT fk_heritage_user_trust_level
        FOREIGN KEY (trust_level_id) REFERENCES heritage_trust_level(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_purpose
-- Purposes for access requests
CREATE TABLE IF NOT EXISTS heritage_purpose (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    requires_approval TINYINT(1) DEFAULT 0,
    min_trust_level INT DEFAULT 0,
    is_enabled TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 100,

    INDEX idx_enabled (is_enabled),
    INDEX idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_embargo
-- Embargoes on objects
CREATE TABLE IF NOT EXISTS heritage_embargo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    embargo_type VARCHAR(47) COMMENT 'full, digital_only, metadata_hidden' DEFAULT 'full',
    reason TEXT DEFAULT NULL,
    legal_basis VARCHAR(255) DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    auto_release TINYINT(1) DEFAULT 1,
    notify_on_release TINYINT(1) DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_end_date (end_date),
    INDEX idx_type (embargo_type),
    INDEX idx_auto_release (auto_release, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_request
-- Access requests from users
CREATE TABLE IF NOT EXISTS heritage_access_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    object_id INT NOT NULL,
    purpose_id INT DEFAULT NULL,
    purpose_text VARCHAR(255) DEFAULT NULL,
    justification TEXT DEFAULT NULL,
    research_description TEXT DEFAULT NULL,
    institution_affiliation VARCHAR(255) DEFAULT NULL,
    status VARCHAR(57) COMMENT 'pending, approved, denied, expired, withdrawn' DEFAULT 'pending',
    decision_by INT DEFAULT NULL,
    decision_at TIMESTAMP NULL,
    decision_notes TEXT DEFAULT NULL,
    valid_from DATE DEFAULT NULL,
    valid_until DATE DEFAULT NULL,
    access_granted JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_user (user_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),

    CONSTRAINT fk_heritage_access_request_purpose
        FOREIGN KEY (purpose_id) REFERENCES heritage_purpose(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_access_rule
-- Access rules for objects/collections
CREATE TABLE IF NOT EXISTS heritage_access_rule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT DEFAULT NULL,
    collection_id INT DEFAULT NULL,
    repository_id INT DEFAULT NULL,
    rule_type VARCHAR(41) COMMENT 'allow, deny, require_approval' DEFAULT 'deny',
    applies_to VARCHAR(54) COMMENT 'all, anonymous, authenticated, trust_level' DEFAULT 'all',
    trust_level_id INT DEFAULT NULL,
    action VARCHAR(70) COMMENT 'view, view_metadata, download, download_master, print, all' DEFAULT 'view',
    priority INT DEFAULT 100,
    is_enabled TINYINT(1) DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_collection (collection_id),
    INDEX idx_repository (repository_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_popia_flag
-- POPIA/GDPR privacy flags
CREATE TABLE IF NOT EXISTS heritage_popia_flag (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    flag_type VARCHAR(116) COMMENT 'personal_info, sensitive, children, health, biometric, criminal, financial, political, religious, sexual' NOT NULL,
    severity VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'medium',
    description TEXT DEFAULT NULL,
    affected_fields JSON DEFAULT NULL,
    detected_by VARCHAR(37) COMMENT 'automatic, manual, review' DEFAULT 'manual',
    is_resolved TINYINT(1) DEFAULT 0,
    resolution_notes TEXT DEFAULT NULL,
    resolved_by INT DEFAULT NULL,
    resolved_at TIMESTAMP NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id),
    INDEX idx_flag_type (flag_type),
    INDEX idx_severity (severity),
    INDEX idx_resolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 7: CUSTODIAN INTERFACE
-- ============================================================================

-- Table: heritage_audit_log
-- Detailed change tracking
CREATE TABLE IF NOT EXISTS heritage_audit_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    username VARCHAR(255) DEFAULT NULL,
    object_id INT DEFAULT NULL,
    object_type VARCHAR(100) DEFAULT 'information_object',
    object_identifier VARCHAR(255) DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    action_category VARCHAR(79) COMMENT 'create, update, delete, view, export, import, batch, access, system' DEFAULT 'update',
    field_name VARCHAR(100) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    changes_json JSON DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    session_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_object (object_id, object_type),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_category (action_category),
    INDEX idx_created (created_at),
    INDEX idx_field (field_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_job
-- Batch job tracking
CREATE TABLE IF NOT EXISTS heritage_batch_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_type VARCHAR(100) NOT NULL,
    job_name VARCHAR(255) DEFAULT NULL,
    status VARCHAR(77) COMMENT 'pending, queued, processing, completed, failed, cancelled, paused' DEFAULT 'pending',
    user_id INT NOT NULL,
    total_items INT DEFAULT 0,
    processed_items INT DEFAULT 0,
    successful_items INT DEFAULT 0,
    failed_items INT DEFAULT 0,
    skipped_items INT DEFAULT 0,
    parameters JSON DEFAULT NULL,
    results JSON DEFAULT NULL,
    error_log JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    progress_message VARCHAR(500) DEFAULT NULL,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_type (job_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_batch_item
-- Individual items in a batch job
CREATE TABLE IF NOT EXISTS heritage_batch_item (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    object_id INT NOT NULL,
    status VARCHAR(57) COMMENT 'pending, processing, success, failed, skipped' DEFAULT 'pending',
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job (job_id),
    INDEX idx_object (object_id),
    INDEX idx_status (status),

    CONSTRAINT fk_heritage_batch_item_job
        FOREIGN KEY (job_id) REFERENCES heritage_batch_job(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SESSION 9: ANALYTICS & LEARNING
-- ============================================================================

-- Table: heritage_analytics_daily
-- Daily aggregate metrics
CREATE TABLE IF NOT EXISTS heritage_analytics_daily (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    metric_type VARCHAR(100) NOT NULL,
    metric_value DECIMAL(15,2) DEFAULT 0,
    previous_value DECIMAL(15,2) DEFAULT NULL,
    change_percent DECIMAL(10,2) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_date_metric (institution_id, date, metric_type),
    INDEX idx_date (date),
    INDEX idx_metric_type (metric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_search
-- Search pattern tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_search (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    date DATE NOT NULL,
    query_pattern VARCHAR(255) DEFAULT NULL,
    query_normalized VARCHAR(255) DEFAULT NULL,
    search_count INT DEFAULT 0,
    click_count INT DEFAULT 0,
    zero_result_count INT DEFAULT 0,
    avg_results DECIMAL(10,2) DEFAULT 0,
    avg_position_clicked DECIMAL(5,2) DEFAULT NULL,
    conversion_rate DECIMAL(5,4) DEFAULT 0,

    UNIQUE KEY uk_date_pattern (institution_id, date, query_pattern),
    INDEX idx_date (date),
    INDEX idx_search_count (search_count DESC),
    INDEX idx_zero_result (zero_result_count DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_content
-- Content performance tracking
CREATE TABLE IF NOT EXISTS heritage_analytics_content (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    view_count INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    search_appearances INT DEFAULT 0,
    download_count INT DEFAULT 0,
    citation_count INT DEFAULT 0,
    share_count INT DEFAULT 0,
    avg_dwell_time_seconds INT DEFAULT NULL,
    click_through_rate DECIMAL(5,4) DEFAULT 0,
    bounce_rate DECIMAL(5,4) DEFAULT NULL,
    metadata JSON DEFAULT NULL,

    UNIQUE KEY uk_object_period (object_id, period_start, period_end),
    INDEX idx_period (period_start, period_end),
    INDEX idx_views (view_count DESC),
    INDEX idx_ctr (click_through_rate DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_analytics_alert
-- Actionable alerts and insights
CREATE TABLE IF NOT EXISTS heritage_analytics_alert (
    id INT AUTO_INCREMENT PRIMARY KEY,
    institution_id INT DEFAULT NULL,
    alert_type VARCHAR(100) NOT NULL,
    category VARCHAR(65) COMMENT 'content, search, access, quality, system, opportunity' DEFAULT 'system',
    severity VARCHAR(44) COMMENT 'info, warning, critical, success' DEFAULT 'info',
    title VARCHAR(255) NOT NULL,
    message TEXT DEFAULT NULL,
    action_url VARCHAR(500) DEFAULT NULL,
    action_label VARCHAR(100) DEFAULT NULL,
    related_data JSON DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_dismissed TINYINT(1) DEFAULT 0,
    dismissed_by INT DEFAULT NULL,
    dismissed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_institution (institution_id),
    INDEX idx_type (alert_type),
    INDEX idx_category (category),
    INDEX idx_severity (severity),
    INDEX idx_dismissed (is_dismissed),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: heritage_content_quality
-- Content quality scores
CREATE TABLE IF NOT EXISTS heritage_content_quality (
    id INT AUTO_INCREMENT PRIMARY KEY,
    object_id INT NOT NULL UNIQUE,
    overall_score DECIMAL(5,2) DEFAULT 0,
    completeness_score DECIMAL(5,2) DEFAULT 0,
    accessibility_score DECIMAL(5,2) DEFAULT 0,
    engagement_score DECIMAL(5,2) DEFAULT 0,
    discoverability_score DECIMAL(5,2) DEFAULT 0,
    issues JSON DEFAULT NULL,
    suggestions JSON DEFAULT NULL,
    last_calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_overall (overall_score DESC),
    INDEX idx_completeness (completeness_score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Default trust levels
INSERT IGNORE INTO heritage_trust_level (code, name, level, can_view_restricted, can_download, can_bulk_download, is_system, description) VALUES
('anonymous', 'Anonymous', 0, 0, 0, 0, 1, 'Unauthenticated visitors'),
('registered', 'Registered User', 1, 0, 1, 0, 1, 'Basic registered account'),
('contributor', 'Contributor', 2, 0, 1, 0, 1, 'Users who contribute content'),
('trusted', 'Trusted User', 3, 1, 1, 0, 1, 'Verified trusted researchers'),
('moderator', 'Moderator', 4, 1, 1, 1, 1, 'Content moderators'),
('custodian', 'Custodian', 5, 1, 1, 1, 1, 'Full custodial access');

-- Default purposes
INSERT IGNORE INTO heritage_purpose (code, name, description, requires_approval, min_trust_level, display_order) VALUES
('personal', 'Personal/Family Research', 'Research into family history and genealogy', 0, 0, 1),
('academic', 'Academic Research', 'Scholarly research for educational institutions', 0, 0, 2),
('education', 'Educational Use', 'Use in teaching and educational materials', 0, 0, 3),
('commercial', 'Commercial Use', 'For-profit use requiring license agreement', 1, 1, 4),
('media', 'Media/Journalism', 'Publication in news or media outlets', 1, 1, 5),
('legal', 'Legal/Compliance', 'Legal proceedings or compliance requirements', 1, 1, 6),
('government', 'Government/Official', 'Official government use', 1, 1, 7),
('preservation', 'Preservation/Conservation', 'Digital preservation activities', 0, 2, 8);

-- Default feature toggles (global)
INSERT IGNORE INTO heritage_feature_toggle (institution_id, feature_code, feature_name, is_enabled, config_json) VALUES
(NULL, 'community_contributions', 'Community Contributions', 1, '{"require_moderation": true}'),
(NULL, 'user_registration', 'User Registration', 1, '{"require_email_verification": true}'),
(NULL, 'social_sharing', 'Social Sharing', 1, '{"platforms": ["facebook", "twitter", "linkedin", "email"]}'),
(NULL, 'downloads', 'Downloads', 1, '{"require_login": false, "track_downloads": true}'),
(NULL, 'citations', 'Citation Generation', 1, '{"formats": ["apa", "mla", "chicago", "harvard"]}'),
(NULL, 'analytics', 'Analytics Dashboard', 1, '{"admin_only": true}'),
(NULL, 'access_requests', 'Access Requests', 1, '{"email_notifications": true}'),
(NULL, 'embargoes', 'Embargo Management', 1, '{}'),
(NULL, 'batch_operations', 'Batch Operations', 1, '{"max_items": 1000}'),
(NULL, 'audit_trail', 'Audit Trail', 1, '{"retention_days": 365}');

-- Default branding (global)
INSERT IGNORE INTO heritage_branding_config (institution_id, primary_color, secondary_color, banner_text, footer_text) VALUES
(NULL, '#0d6efd', '#6c757d', NULL, 'Powered by AtoM Heritage Platform');

-- Verification
SELECT 'heritage_feature_toggle' as tbl, COUNT(*) as cnt FROM heritage_feature_toggle
UNION ALL SELECT 'heritage_branding_config', COUNT(*) FROM heritage_branding_config
UNION ALL SELECT 'heritage_trust_level', COUNT(*) FROM heritage_trust_level
UNION ALL SELECT 'heritage_user_trust', COUNT(*) FROM heritage_user_trust
UNION ALL SELECT 'heritage_purpose', COUNT(*) FROM heritage_purpose
UNION ALL SELECT 'heritage_embargo', COUNT(*) FROM heritage_embargo
UNION ALL SELECT 'heritage_access_request', COUNT(*) FROM heritage_access_request
UNION ALL SELECT 'heritage_access_rule', COUNT(*) FROM heritage_access_rule
UNION ALL SELECT 'heritage_popia_flag', COUNT(*) FROM heritage_popia_flag
UNION ALL SELECT 'heritage_audit_log', COUNT(*) FROM heritage_audit_log
UNION ALL SELECT 'heritage_batch_job', COUNT(*) FROM heritage_batch_job
UNION ALL SELECT 'heritage_batch_item', COUNT(*) FROM heritage_batch_item
UNION ALL SELECT 'heritage_analytics_daily', COUNT(*) FROM heritage_analytics_daily
UNION ALL SELECT 'heritage_analytics_search', COUNT(*) FROM heritage_analytics_search
UNION ALL SELECT 'heritage_analytics_content', COUNT(*) FROM heritage_analytics_content
UNION ALL SELECT 'heritage_analytics_alert', COUNT(*) FROM heritage_analytics_alert
UNION ALL SELECT 'heritage_content_quality', COUNT(*) FROM heritage_content_quality;

-- ----------------------------------------------------------------------------
-- 2026_03_01_create_queue_tables.sql
-- ----------------------------------------------------------------------------
-- =============================================================================
-- Queue Engine Tables for AtoM Heratio
-- Issue #167: Durable Workflow Engine for Background Jobs
-- =============================================================================

-- 1. ahg_queue_batch — Batch job groups
CREATE TABLE IF NOT EXISTS ahg_queue_batch (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    queue       VARCHAR(100) NOT NULL DEFAULT 'default',
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending, running, paused, completed, failed, cancelled',
    total_jobs      INT UNSIGNED NOT NULL DEFAULT 0,
    completed_jobs  INT UNSIGNED NOT NULL DEFAULT 0,
    failed_jobs     INT UNSIGNED NOT NULL DEFAULT 0,
    progress_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    max_concurrent  INT UNSIGNED NOT NULL DEFAULT 5,
    delay_between_ms INT UNSIGNED NOT NULL DEFAULT 0,
    max_retries     INT UNSIGNED NOT NULL DEFAULT 3,
    options         JSON NULL,
    on_complete_callback VARCHAR(255) NULL,
    user_id     INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at  DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batch_status (status),
    INDEX idx_batch_queue (queue),
    INDEX idx_batch_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. ahg_queue_job — Central job queue
CREATE TABLE IF NOT EXISTS ahg_queue_job (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue       VARCHAR(100) NOT NULL DEFAULT 'default',
    job_type    VARCHAR(255) NOT NULL COMMENT 'Handler identifier, e.g. ingest:commit',
    payload     JSON NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'pending' COMMENT 'pending, reserved, running, completed, failed, cancelled',
    priority    TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 9=lowest',
    batch_id    BIGINT UNSIGNED NULL,
    chain_id    BIGINT UNSIGNED NULL,
    chain_order INT NULL,
    attempt_count   INT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts    INT UNSIGNED NOT NULL DEFAULT 3,
    delay_seconds   INT UNSIGNED NOT NULL DEFAULT 0,
    backoff_strategy VARCHAR(20) NOT NULL DEFAULT 'exponential' COMMENT 'none, linear, exponential',
    available_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reserved_at     DATETIME NULL,
    started_at      DATETIME NULL,
    completed_at    DATETIME NULL,
    processing_time_ms INT UNSIGNED NULL,
    result_data     JSON NULL,
    error_message   TEXT NULL,
    error_code      VARCHAR(100) NULL,
    error_trace     TEXT NULL,
    worker_id       VARCHAR(100) NULL,
    user_id         INT NULL,
    progress_current INT UNSIGNED NOT NULL DEFAULT 0,
    progress_total   INT UNSIGNED NOT NULL DEFAULT 0,
    progress_message VARCHAR(500) NULL,
    rate_limit_group VARCHAR(100) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_job_dispatch (queue, status, priority, available_at),
    INDEX idx_job_batch (batch_id),
    INDEX idx_job_chain (chain_id, chain_order),
    INDEX idx_job_worker (worker_id),
    INDEX idx_job_status (status),
    INDEX idx_job_user (user_id),
    INDEX idx_job_type (job_type),
    CONSTRAINT fk_job_batch FOREIGN KEY (batch_id) REFERENCES ahg_queue_batch(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. ahg_queue_failed — Archived failed jobs
CREATE TABLE IF NOT EXISTS ahg_queue_failed (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue       VARCHAR(100) NOT NULL,
    job_type    VARCHAR(255) NOT NULL,
    payload     JSON NULL,
    error_message TEXT NULL,
    error_trace TEXT NULL,
    original_job_id BIGINT UNSIGNED NULL,
    batch_id    BIGINT UNSIGNED NULL,
    user_id     INT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_failed_queue (queue),
    INDEX idx_failed_job_type (job_type),
    INDEX idx_failed_original (original_job_id),
    INDEX idx_failed_at (failed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. ahg_queue_log — Audit trail
CREATE TABLE IF NOT EXISTS ahg_queue_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id      BIGINT UNSIGNED NULL,
    batch_id    BIGINT UNSIGNED NULL,
    event_type  VARCHAR(50) NOT NULL COMMENT 'dispatched, reserved, started, completed, failed, retried, cancelled',
    message     VARCHAR(500) NULL,
    details     JSON NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_job (job_id),
    INDEX idx_log_batch (batch_id),
    INDEX idx_log_event (event_type),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. ahg_queue_rate_limit — Per-group rate limiter
CREATE TABLE IF NOT EXISTS ahg_queue_rate_limit (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_name  VARCHAR(100) NOT NULL,
    max_per_minute INT UNSIGNED NOT NULL DEFAULT 60,
    window_start DATETIME NULL,
    request_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX idx_rate_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2026_03_08_dropdown_column_map.sql
-- ----------------------------------------------------------------------------
-- ============================================================================
-- Dropdown Column Mapping Table
-- Date: 2026-03-08
-- Links database columns to ahg_dropdown taxonomies
-- Used by DropdownService for validation and label resolution
-- ============================================================================

CREATE TABLE IF NOT EXISTS ahg_dropdown_column_map (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    column_name VARCHAR(100) NOT NULL,
    taxonomy VARCHAR(100) NOT NULL COMMENT 'FK to ahg_dropdown.taxonomy',
    is_strict TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=only dropdown values allowed, 0=freetext also allowed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_table_column (table_name, column_name),
    KEY idx_taxonomy (taxonomy)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auto-populate from columns that have value-list COMMENTs (formerly ENUM).
-- Heratio standalone-install patch: wrapped in a stored procedure that
-- verifies ahg_dropdown exists before attempting the INSERT...SELECT
-- (ahg_dropdown is created by the ahg-settings plugin install.sql, which
-- on a fresh standalone install runs after this framework schema).
DROP PROCEDURE IF EXISTS heratio_seed_dropdown_column_map;
DELIMITER //
CREATE PROCEDURE heratio_seed_dropdown_column_map()
proc: BEGIN
    IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ahg_dropdown') THEN
        LEAVE proc;
    END IF;
    INSERT IGNORE INTO ahg_dropdown_column_map (table_name, column_name, taxonomy)
    SELECT c.TABLE_NAME, c.COLUMN_NAME, c.COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS c
    JOIN INFORMATION_SCHEMA.TABLES t ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
    WHERE c.TABLE_SCHEMA = DATABASE()
    AND c.COLUMN_COMMENT REGEXP '^[a-z0-9_]+(, [a-z0-9_]+)+'
    AND c.DATA_TYPE = 'varchar'
    AND EXISTS (SELECT 1 FROM ahg_dropdown d WHERE d.taxonomy = c.COLUMN_NAME LIMIT 1)
    ORDER BY c.TABLE_NAME, c.COLUMN_NAME;
END proc //
DELIMITER ;
CALL heratio_seed_dropdown_column_map();
DROP PROCEDURE IF EXISTS heratio_seed_dropdown_column_map;

-- (SKIP — seed-only, belongs in database/seeds/: 2026_03_08_dropdown_seed_from_enums.sql)

-- ----------------------------------------------------------------------------
-- 2026_03_08_enum_to_varchar.sql
-- ----------------------------------------------------------------------------
-- ============================================================================
-- ENUM → VARCHAR Migration
-- Date: 2026-03-08
-- Purpose: Convert all ENUM columns to VARCHAR with COMMENT listing valid values
-- Scope: All AHG plugin base tables in archive database (459 columns)
-- Rule: "Never use ENUM columns" — use VARCHAR(N) with COMMENT instead
-- Note: Views (grap_heritage_asset, ric_queue_status, ric_recent_operations,
--        ric_sync_summary) are excluded — they inherit column types from base tables
-- ----------------------------------------------------------------------------
-- Heratio standalone-install patch: wrapped in a stored procedure with
-- CONTINUE HANDLERs so missing tables/columns (e.g. plugin tables not yet
-- loaded on a fresh install) are silently skipped. Re-run after plugin
-- install.sql files have run to apply the ENUM→VARCHAR conversions.
-- ============================================================================

DROP PROCEDURE IF EXISTS heratio_enum_to_varchar_migration;
DELIMITER //
CREATE PROCEDURE heratio_enum_to_varchar_migration()
BEGIN
    DECLARE CONTINUE HANDLER FOR 1146 BEGIN END;  -- table doesn't exist
    DECLARE CONTINUE HANDLER FOR 1054 BEGIN END;  -- unknown column
    DECLARE CONTINUE HANDLER FOR 1072 BEGIN END;  -- key column doesn't exist

ALTER TABLE `heritage_access_request` MODIFY COLUMN `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, approved, denied, expired, withdrawn';
ALTER TABLE `heritage_access_rule` MODIFY COLUMN `action` VARCHAR(63) DEFAULT 'view' COMMENT 'view, view_metadata, download, download_master, print, all';
ALTER TABLE `heritage_access_rule` MODIFY COLUMN `applies_to` VARCHAR(49) DEFAULT 'all' COMMENT 'all, anonymous, authenticated, trust_level';
ALTER TABLE `heritage_access_rule` MODIFY COLUMN `rule_type` VARCHAR(37) DEFAULT 'deny' COMMENT 'allow, deny, require_approval';
ALTER TABLE `heritage_analytics_alert` MODIFY COLUMN `category` VARCHAR(58) DEFAULT 'system' COMMENT 'content, search, access, quality, system, opportunity';
ALTER TABLE `heritage_analytics_alert` MODIFY COLUMN `severity` VARCHAR(39) DEFAULT 'info' COMMENT 'info, warning, critical, success';
ALTER TABLE `heritage_asset` MODIFY COLUMN `acquisition_method` VARCHAR(65) DEFAULT NULL COMMENT 'purchase, donation, bequest, transfer, found, exchange, other';
ALTER TABLE `heritage_asset` MODIFY COLUMN `condition_rating` VARCHAR(43) DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical';
ALTER TABLE `heritage_asset` MODIFY COLUMN `depreciation_policy` VARCHAR(76) DEFAULT 'not_depreciated' COMMENT 'not_depreciated, straight_line, reducing_balance, units_of_production';
ALTER TABLE `heritage_asset` MODIFY COLUMN `derecognition_reason` VARCHAR(60) DEFAULT NULL COMMENT 'disposal, destruction, loss, transfer, write_off, other';
ALTER TABLE `heritage_asset` MODIFY COLUMN `heritage_significance` VARCHAR(37) DEFAULT NULL COMMENT 'exceptional, high, medium, low';
ALTER TABLE `heritage_asset` MODIFY COLUMN `measurement_basis` VARCHAR(49) DEFAULT 'cost' COMMENT 'cost, fair_value, nominal, not_practicable';
ALTER TABLE `heritage_asset` MODIFY COLUMN `recognition_status` VARCHAR(56) DEFAULT 'pending' COMMENT 'recognised, not_recognised, pending, derecognised';
ALTER TABLE `heritage_asset` MODIFY COLUMN `revaluation_frequency` VARCHAR(64) DEFAULT 'as_needed' COMMENT 'annual, triennial, quinquennial, as_needed, not_applicable';
ALTER TABLE `heritage_asset` MODIFY COLUMN `valuation_method` VARCHAR(51) DEFAULT NULL COMMENT 'market, cost, income, expert, insurance, other';
ALTER TABLE `heritage_audit_log` MODIFY COLUMN `action_category` VARCHAR(69) DEFAULT 'update' COMMENT 'create, update, delete, view, export, import, batch, access, system';
ALTER TABLE `heritage_batch_item` MODIFY COLUMN `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, processing, success, failed, skipped';
ALTER TABLE `heritage_batch_job` MODIFY COLUMN `status` VARCHAR(69) DEFAULT 'pending' COMMENT 'pending, queued, processing, completed, failed, cancelled, paused';
ALTER TABLE `heritage_compliance_rule` MODIFY COLUMN `category` VARCHAR(44) NOT NULL COMMENT 'recognition, measurement, disclosure';
ALTER TABLE `heritage_compliance_rule` MODIFY COLUMN `check_type` VARCHAR(54) DEFAULT 'required_field' COMMENT 'required_field, value_check, date_check, custom';
ALTER TABLE `heritage_compliance_rule` MODIFY COLUMN `severity` VARCHAR(28) DEFAULT 'error' COMMENT 'error, warning, info';
ALTER TABLE `heritage_contribution` MODIFY COLUMN `status` VARCHAR(46) DEFAULT 'pending' COMMENT 'pending, approved, rejected, superseded';
ALTER TABLE `heritage_contribution_type` MODIFY COLUMN `min_trust_level` VARCHAR(40) DEFAULT 'new' COMMENT 'new, contributor, trusted, expert';
ALTER TABLE `heritage_contributor` MODIFY COLUMN `trust_level` VARCHAR(40) DEFAULT 'new' COMMENT 'new, contributor, trusted, expert';
ALTER TABLE `heritage_contributor_badge` MODIFY COLUMN `criteria_type` VARCHAR(70) DEFAULT 'contribution_count' COMMENT 'contribution_count, approval_rate, points, type_specific, manual';
ALTER TABLE `heritage_curated_story` MODIFY COLUMN `link_type` VARCHAR(41) DEFAULT 'search' COMMENT 'collection, search, external, page';
ALTER TABLE `heritage_embargo` MODIFY COLUMN `embargo_type` VARCHAR(43) DEFAULT 'full' COMMENT 'full, digital_only, metadata_hidden';
ALTER TABLE `heritage_explore_category` MODIFY COLUMN `display_style` VARCHAR(41) DEFAULT 'grid' COMMENT 'grid, list, timeline, map, carousel';
ALTER TABLE `heritage_explore_category` MODIFY COLUMN `source_type` VARCHAR(47) NOT NULL COMMENT 'taxonomy, authority, field, facet, custom';
ALTER TABLE `heritage_featured_collection` MODIFY COLUMN `source_type` VARCHAR(23) NOT NULL DEFAULT 'archival' COMMENT 'iiif, archival';
ALTER TABLE `heritage_filter_type` MODIFY COLUMN `source_type` VARCHAR(41) NOT NULL COMMENT 'taxonomy, authority, field, custom';
ALTER TABLE `heritage_graph_build_log` MODIFY COLUMN `build_type` VARCHAR(37) NOT NULL DEFAULT 'incremental' COMMENT 'full, incremental, edges_only';
ALTER TABLE `heritage_graph_build_log` MODIFY COLUMN `status` VARCHAR(34) NOT NULL DEFAULT 'running' COMMENT 'running, completed, failed';
ALTER TABLE `heritage_hero_slide` MODIFY COLUMN `cta_style` VARCHAR(41) DEFAULT 'primary' COMMENT 'primary, secondary, outline, light';
ALTER TABLE `heritage_hero_slide` MODIFY COLUMN `media_type` VARCHAR(21) DEFAULT 'image' COMMENT 'image, video';
ALTER TABLE `heritage_hero_slide` MODIFY COLUMN `overlay_type` VARCHAR(29) DEFAULT 'gradient' COMMENT 'none, gradient, solid';
ALTER TABLE `heritage_hero_slide` MODIFY COLUMN `text_position` VARCHAR(52) DEFAULT 'left' COMMENT 'left, center, right, bottom-left, bottom-right';
ALTER TABLE `heritage_journal_entry` MODIFY COLUMN `journal_type` VARCHAR(111) NOT NULL COMMENT 'recognition, revaluation, depreciation, impairment, impairment_reversal, derecognition, adjustment, transfer';
ALTER TABLE `heritage_landing_config` MODIFY COLUMN `hero_effect` VARCHAR(28) DEFAULT 'kenburns' COMMENT 'kenburns, fade, none';
ALTER TABLE `heritage_learned_term` MODIFY COLUMN `relationship_type` VARCHAR(51) DEFAULT 'related' COMMENT 'synonym, broader, narrower, related, spelling';
ALTER TABLE `heritage_learned_term` MODIFY COLUMN `source` VARCHAR(47) DEFAULT 'user_behavior' COMMENT 'user_behavior, admin, taxonomy, external';
ALTER TABLE `heritage_movement_register` MODIFY COLUMN `condition_on_departure` VARCHAR(34) DEFAULT NULL COMMENT 'excellent, good, fair, poor';
ALTER TABLE `heritage_movement_register` MODIFY COLUMN `condition_on_return` VARCHAR(34) DEFAULT NULL COMMENT 'excellent, good, fair, poor';
ALTER TABLE `heritage_movement_register` MODIFY COLUMN `movement_type` VARCHAR(84) NOT NULL COMMENT 'loan_out, loan_return, transfer, exhibition, conservation, storage_change, other';
ALTER TABLE `heritage_popia_flag` MODIFY COLUMN `detected_by` VARCHAR(33) DEFAULT 'manual' COMMENT 'automatic, manual, review';
ALTER TABLE `heritage_popia_flag` MODIFY COLUMN `flag_type` VARCHAR(105) NOT NULL COMMENT 'personal_info, sensitive, children, health, biometric, criminal, financial, political, religious, sexual';
ALTER TABLE `heritage_popia_flag` MODIFY COLUMN `severity` VARCHAR(34) DEFAULT 'medium' COMMENT 'low, medium, high, critical';
ALTER TABLE `heritage_search_suggestion` MODIFY COLUMN `suggestion_type` VARCHAR(43) DEFAULT 'query' COMMENT 'query, title, subject, creator, place';
ALTER TABLE `heritage_tenant` MODIFY COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'trial' COMMENT 'active, suspended, trial';
ALTER TABLE `heritage_tenant_user` MODIFY COLUMN `role` VARCHAR(52) NOT NULL DEFAULT 'viewer' COMMENT 'owner, super_user, editor, contributor, viewer';
ALTER TABLE `heritage_valuation_history` MODIFY COLUMN `valuation_method` VARCHAR(51) DEFAULT NULL COMMENT 'market, cost, income, expert, insurance, other';
ALTER TABLE `icip_access_restriction` MODIFY COLUMN `restriction_type` VARCHAR(198) NOT NULL COMMENT 'community_permission_required, gender_restricted_male, gender_restricted_female, initiated_only, seasonal, mourning_period, repatriation_pending, under_consultation, elder_approval_required, custom';
ALTER TABLE `icip_community` MODIFY COLUMN `state_territory` VARCHAR(47) NOT NULL COMMENT 'NSW, VIC, QLD, WA, SA, TAS, NT, ACT, External';
ALTER TABLE `icip_consent` MODIFY COLUMN `consent_status` VARCHAR(135) NOT NULL DEFAULT 'unknown' COMMENT 'not_required, pending_consultation, consultation_in_progress, conditional_consent, full_consent, restricted_consent, denied, unknown';
ALTER TABLE `icip_consultation` MODIFY COLUMN `consultation_method` VARCHAR(50) NOT NULL COMMENT 'in_person, phone, video, email, letter, other';
ALTER TABLE `icip_consultation` MODIFY COLUMN `consultation_type` VARCHAR(132) NOT NULL COMMENT 'initial_contact, consent_request, access_request, repatriation, digitisation, exhibition, publication, research, general, follow_up';
ALTER TABLE `icip_consultation` MODIFY COLUMN `status` VARCHAR(58) NOT NULL DEFAULT 'completed' COMMENT 'scheduled, completed, cancelled, follow_up_required';
ALTER TABLE `icip_cultural_notice_type` MODIFY COLUMN `severity` VARCHAR(31) DEFAULT 'warning' COMMENT 'info, warning, critical';
ALTER TABLE `icip_tk_label` MODIFY COLUMN `applied_by` VARCHAR(31) NOT NULL DEFAULT 'institution' COMMENT 'community, institution';
ALTER TABLE `icip_tk_label_type` MODIFY COLUMN `category` VARCHAR(20) NOT NULL COMMENT 'TK, BC';
ALTER TABLE `iiif_annotation` MODIFY COLUMN `motivation` VARCHAR(86) DEFAULT 'commenting' COMMENT 'commenting, tagging, describing, linking, transcribing, identifying, supplementing';
ALTER TABLE `iiif_auth_access_log` MODIFY COLUMN `action` VARCHAR(67) NOT NULL COMMENT 'view, download, token_request, token_grant, token_deny, logout';
ALTER TABLE `iiif_auth_service` MODIFY COLUMN `profile` VARCHAR(43) NOT NULL DEFAULT 'login' COMMENT 'login, clickthrough, kiosk, external';
ALTER TABLE `iiif_collection` MODIFY COLUMN `viewing_hint` VARCHAR(53) DEFAULT 'individuals' COMMENT 'individuals, paged, continuous, multi-part, top';
ALTER TABLE `iiif_collection_item` MODIFY COLUMN `item_type` VARCHAR(29) DEFAULT 'manifest' COMMENT 'manifest, collection';
ALTER TABLE `iiif_ocr_block` MODIFY COLUMN `block_type` VARCHAR(36) DEFAULT 'word' COMMENT 'word, line, paragraph, region';
ALTER TABLE `iiif_ocr_text` MODIFY COLUMN `format` VARCHAR(25) DEFAULT 'plain' COMMENT 'plain, alto, hocr';
ALTER TABLE `information_object_physical_location` MODIFY COLUMN `access_status` VARCHAR(53) DEFAULT 'available' COMMENT 'available, in_use, restricted, offsite, missing';
ALTER TABLE `information_object_physical_location` MODIFY COLUMN `condition_status` VARCHAR(43) DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical';
ALTER TABLE `ingest_file` MODIFY COLUMN `file_type` VARCHAR(31) NOT NULL COMMENT 'csv, zip, ead, directory';
ALTER TABLE `ingest_job` MODIFY COLUMN `status` VARCHAR(51) DEFAULT 'queued' COMMENT 'queued, running, completed, failed, cancelled';
ALTER TABLE `ingest_session` MODIFY COLUMN `parent_placement` VARCHAR(46) DEFAULT 'top_level' COMMENT 'existing, new, top_level, csv_hierarchy';
ALTER TABLE `ingest_session` MODIFY COLUMN `sector` VARCHAR(44) NOT NULL DEFAULT 'archive' COMMENT 'archive, museum, library, gallery, dam';
ALTER TABLE `ingest_session` MODIFY COLUMN `standard` VARCHAR(40) NOT NULL DEFAULT 'isadg' COMMENT 'isadg, dc, spectrum, cco, rad, dacs';
ALTER TABLE `ingest_session` MODIFY COLUMN `status` VARCHAR(81) DEFAULT 'configure' COMMENT 'configure, upload, map, validate, preview, commit, completed, failed, cancelled';
ALTER TABLE `ingest_validation` MODIFY COLUMN `severity` VARCHAR(28) DEFAULT 'error' COMMENT 'error, warning, info';
ALTER TABLE `ipsas_asset_category` MODIFY COLUMN `asset_type` VARCHAR(36) DEFAULT 'heritage' COMMENT 'heritage, operational, mixed';
ALTER TABLE `ipsas_asset_category` MODIFY COLUMN `depreciation_policy` VARCHAR(45) DEFAULT 'none' COMMENT 'none, straight_line, reducing_balance';
ALTER TABLE `ipsas_depreciation` MODIFY COLUMN `calculation_method` VARCHAR(40) NOT NULL COMMENT 'straight_line, reducing_balance';
ALTER TABLE `ipsas_disposal` MODIFY COLUMN `disposal_method` VARCHAR(67) NOT NULL COMMENT 'sale, donation, destruction, loss, theft, transfer, deaccession';
ALTER TABLE `ipsas_financial_year_summary` MODIFY COLUMN `status` VARCHAR(29) DEFAULT 'open' COMMENT 'open, closed, audited';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `acquisition_method` VARCHAR(67) DEFAULT 'unknown' COMMENT 'purchase, donation, bequest, transfer, found, exchange, unknown';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `condition_rating` VARCHAR(43) DEFAULT 'good' COMMENT 'excellent, good, fair, poor, critical';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `depreciation_policy` VARCHAR(45) DEFAULT 'none' COMMENT 'none, straight_line, reducing_balance';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `risk_level` VARCHAR(34) DEFAULT 'low' COMMENT 'low, medium, high, critical';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `status` VARCHAR(78) DEFAULT 'active' COMMENT 'active, on_loan, in_storage, under_conservation, disposed, lost, destroyed';
ALTER TABLE `ipsas_heritage_asset` MODIFY COLUMN `valuation_basis` VARCHAR(59) DEFAULT 'nominal' COMMENT 'historical_cost, fair_value, nominal, not_recognized';
ALTER TABLE `ipsas_insurance` MODIFY COLUMN `policy_type` VARCHAR(59) NOT NULL COMMENT 'all_risks, named_perils, blanket, transit, exhibition';
ALTER TABLE `ipsas_insurance` MODIFY COLUMN `status` VARCHAR(50) DEFAULT 'active' COMMENT 'active, expired, cancelled, pending_renewal';
ALTER TABLE `ipsas_valuation` MODIFY COLUMN `valuation_basis` VARCHAR(61) NOT NULL COMMENT 'historical_cost, fair_value, nominal, replacement_cost';
ALTER TABLE `ipsas_valuation` MODIFY COLUMN `valuation_type` VARCHAR(58) NOT NULL COMMENT 'initial, revaluation, impairment, reversal, disposal';
ALTER TABLE `ipsas_valuation` MODIFY COLUMN `valuer_type` VARCHAR(38) DEFAULT 'internal' COMMENT 'internal, external, government';
ALTER TABLE `library_settings` MODIFY COLUMN `setting_type` VARCHAR(37) DEFAULT 'string' COMMENT 'string, integer, boolean, json';
ALTER TABLE `library_subject_authority` MODIFY COLUMN `heading_type` VARCHAR(61) DEFAULT 'topical' COMMENT 'topical, personal, corporate, geographic, genre, meeting';
ALTER TABLE `loan` MODIFY COLUMN `insurance_type` VARCHAR(48) NOT NULL DEFAULT 'borrower' COMMENT 'borrower, lender, shared, government, self';
ALTER TABLE `loan` MODIFY COLUMN `loan_type` VARCHAR(20) NOT NULL COMMENT 'out, in';
ALTER TABLE `loan` MODIFY COLUMN `purpose` VARCHAR(88) NOT NULL DEFAULT 'exhibition' COMMENT 'exhibition, research, conservation, photography, education, filming, long_term, other';
ALTER TABLE `loan_document` MODIFY COLUMN `document_type` VARCHAR(116) NOT NULL COMMENT 'agreement, facilities_report, condition_report, insurance_certificate, receipt, correspondence, photograph, other';
ALTER TABLE `marketplace_auction` MODIFY COLUMN `auction_type` VARCHAR(34) DEFAULT 'english' COMMENT 'english, sealed_bid, dutch';
ALTER TABLE `marketplace_auction` MODIFY COLUMN `status` VARCHAR(41) DEFAULT 'upcoming' COMMENT 'upcoming, active, ended, cancelled';
ALTER TABLE `marketplace_category` MODIFY COLUMN `sector` VARCHAR(44) NOT NULL COMMENT 'gallery, museum, archive, library, dam';
ALTER TABLE `marketplace_collection` MODIFY COLUMN `collection_type` VARCHAR(57) DEFAULT 'curated' COMMENT 'curated, exhibition, seasonal, featured, genre, sale';
ALTER TABLE `marketplace_enquiry` MODIFY COLUMN `status` VARCHAR(33) DEFAULT 'new' COMMENT 'new, read, replied, closed';
ALTER TABLE `marketplace_listing` MODIFY COLUMN `condition_rating` VARCHAR(39) DEFAULT NULL COMMENT 'mint, excellent, good, fair, poor';
ALTER TABLE `marketplace_listing` MODIFY COLUMN `listing_type` VARCHAR(40) NOT NULL COMMENT 'fixed_price, auction, offer_only';
ALTER TABLE `marketplace_listing` MODIFY COLUMN `sector` VARCHAR(44) NOT NULL COMMENT 'gallery, museum, archive, library, dam';
ALTER TABLE `marketplace_listing` MODIFY COLUMN `status` VARCHAR(79) DEFAULT 'draft' COMMENT 'draft, pending_review, active, reserved, sold, expired, withdrawn, suspended';
ALTER TABLE `marketplace_offer` MODIFY COLUMN `status` VARCHAR(63) DEFAULT 'pending' COMMENT 'pending, accepted, rejected, countered, withdrawn, expired';
ALTER TABLE `marketplace_payout` MODIFY COLUMN `method` VARCHAR(60) NOT NULL COMMENT 'bank_transfer, paypal, payfast, manual, stripe_connect';
ALTER TABLE `marketplace_payout` MODIFY COLUMN `status` VARCHAR(55) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, cancelled';
ALTER TABLE `marketplace_review` MODIFY COLUMN `review_type` VARCHAR(41) NOT NULL COMMENT 'buyer_to_seller, seller_to_buyer';
ALTER TABLE `marketplace_seller` MODIFY COLUMN `payout_method` VARCHAR(45) DEFAULT 'bank_transfer' COMMENT 'bank_transfer, paypal, payfast, manual';
ALTER TABLE `marketplace_seller` MODIFY COLUMN `seller_type` VARCHAR(53) NOT NULL COMMENT 'artist, gallery, institution, collector, estate';
ALTER TABLE `marketplace_seller` MODIFY COLUMN `trust_level` VARCHAR(36) DEFAULT 'new' COMMENT 'new, active, trusted, premium';
ALTER TABLE `marketplace_seller` MODIFY COLUMN `verification_status` VARCHAR(47) DEFAULT 'unverified' COMMENT 'unverified, pending, verified, suspended';
ALTER TABLE `marketplace_settings` MODIFY COLUMN `setting_type` VARCHAR(43) DEFAULT 'text' COMMENT 'text, number, boolean, json, currency';
ALTER TABLE `marketplace_transaction` MODIFY COLUMN `payment_status` VARCHAR(47) DEFAULT 'pending' COMMENT 'pending, paid, failed, refunded, disputed';
ALTER TABLE `marketplace_transaction` MODIFY COLUMN `shipping_status` VARCHAR(65) DEFAULT 'pending' COMMENT 'pending, preparing, shipped, in_transit, delivered, returned';
ALTER TABLE `marketplace_transaction` MODIFY COLUMN `source` VARCHAR(35) NOT NULL COMMENT 'fixed_price, auction, offer';
ALTER TABLE `marketplace_transaction` MODIFY COLUMN `status` VARCHAR(87) DEFAULT 'pending_payment' COMMENT 'pending_payment, paid, shipping, delivered, completed, cancelled, disputed, refunded';
ALTER TABLE `media_derivatives` MODIFY COLUMN `derivative_type` VARCHAR(43) NOT NULL COMMENT 'thumbnail, poster, preview, waveform';
ALTER TABLE `media_metadata` MODIFY COLUMN `media_type` VARCHAR(21) NOT NULL COMMENT 'audio, video';
ALTER TABLE `media_processing_queue` MODIFY COLUMN `status` VARCHAR(45) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed';
ALTER TABLE `media_processing_queue` MODIFY COLUMN `task_type` VARCHAR(62) NOT NULL COMMENT 'metadata_extraction, transcription, waveform, thumbnail';
ALTER TABLE `media_processor_settings` MODIFY COLUMN `setting_type` VARCHAR(43) DEFAULT 'string' COMMENT 'string, integer, float, boolean, json';
ALTER TABLE `metadata_export_log` MODIFY COLUMN `status` VARCHAR(32) DEFAULT 'success' COMMENT 'success, failed, partial';
ALTER TABLE `metadata_extraction_log` MODIFY COLUMN `operation` VARCHAR(56) NOT NULL COMMENT 'extract, face_detect, face_match, index_face, bulk';
ALTER TABLE `metadata_extraction_log` MODIFY COLUMN `status` VARCHAR(40) NOT NULL COMMENT 'success, partial, failed, skipped';
ALTER TABLE `metadata_extraction_log` MODIFY COLUMN `triggered_by` VARCHAR(31) DEFAULT NULL COMMENT 'upload, job, manual, api';
ALTER TABLE `naz_closure_period` MODIFY COLUMN `closure_type` VARCHAR(50) NOT NULL DEFAULT 'standard' COMMENT 'standard, extended, indefinite, ministerial';
ALTER TABLE `naz_closure_period` MODIFY COLUMN `status` VARCHAR(42) DEFAULT 'active' COMMENT 'active, expired, extended, released';
ALTER TABLE `naz_protected_record` MODIFY COLUMN `protection_type` VARCHAR(52) NOT NULL COMMENT 'cabinet, security, personal, legal, commercial';
ALTER TABLE `naz_protected_record` MODIFY COLUMN `status` VARCHAR(31) DEFAULT 'active' COMMENT 'active, expired, lifted';
ALTER TABLE `naz_records_schedule` MODIFY COLUMN `access_restriction` VARCHAR(45) DEFAULT 'open' COMMENT 'open, restricted, confidential, secret';
ALTER TABLE `naz_records_schedule` MODIFY COLUMN `classification` VARCHAR(46) DEFAULT 'useful' COMMENT 'vital, important, useful, non-essential';
ALTER TABLE `naz_records_schedule` MODIFY COLUMN `disposal_action` VARCHAR(43) NOT NULL COMMENT 'destroy, transfer, review, permanent';
ALTER TABLE `naz_records_schedule` MODIFY COLUMN `status` VARCHAR(52) DEFAULT 'draft' COMMENT 'draft, pending, approved, superseded, archived';
ALTER TABLE `naz_research_permit` MODIFY COLUMN `permit_type` VARCHAR(36) NOT NULL DEFAULT 'general' COMMENT 'general, restricted, special';
ALTER TABLE `naz_research_permit` MODIFY COLUMN `status` VARCHAR(58) DEFAULT 'pending' COMMENT 'pending, approved, rejected, active, expired, revoked';
ALTER TABLE `naz_researcher` MODIFY COLUMN `researcher_type` VARCHAR(37) NOT NULL DEFAULT 'local' COMMENT 'local, foreign, institutional';
ALTER TABLE `naz_researcher` MODIFY COLUMN `status` VARCHAR(47) DEFAULT 'active' COMMENT 'active, inactive, suspended, blacklisted';
ALTER TABLE `naz_transfer` MODIFY COLUMN `status` VARCHAR(79) DEFAULT 'proposed' COMMENT 'proposed, scheduled, in_transit, received, accessioned, rejected, cancelled';
ALTER TABLE `naz_transfer` MODIFY COLUMN `transfer_type` VARCHAR(45) DEFAULT 'scheduled' COMMENT 'scheduled, voluntary, rescue, donation';
ALTER TABLE `naz_transfer_item` MODIFY COLUMN `access_restriction` VARCHAR(38) DEFAULT 'open' COMMENT 'open, restricted, confidential';
ALTER TABLE `nmmz_antiquity` MODIFY COLUMN `acquisition_method` VARCHAR(68) DEFAULT 'unknown' COMMENT 'excavation, donation, purchase, confiscation, transfer, unknown';
ALTER TABLE `nmmz_antiquity` MODIFY COLUMN `condition_rating` VARCHAR(46) DEFAULT 'good' COMMENT 'excellent, good, fair, poor, fragmentary';
ALTER TABLE `nmmz_antiquity` MODIFY COLUMN `ownership_type` VARCHAR(38) DEFAULT 'state' COMMENT 'state, museum, private, unknown';
ALTER TABLE `nmmz_antiquity` MODIFY COLUMN `status` VARCHAR(61) DEFAULT 'in_collection' COMMENT 'in_collection, on_loan, missing, repatriated, destroyed';
ALTER TABLE `nmmz_archaeological_site` MODIFY COLUMN `protection_status` VARCHAR(49) DEFAULT 'unprotected' COMMENT 'protected, unprotected, at_risk, destroyed';
ALTER TABLE `nmmz_archaeological_site` MODIFY COLUMN `research_potential` VARCHAR(35) DEFAULT 'medium' COMMENT 'high, medium, low, exhausted';
ALTER TABLE `nmmz_archaeological_site` MODIFY COLUMN `status` VARCHAR(47) DEFAULT 'active' COMMENT 'active, destroyed, submerged, built_over';
ALTER TABLE `nmmz_export_permit` MODIFY COLUMN `applicant_type` VARCHAR(50) NOT NULL COMMENT 'individual, institution, dealer, researcher';
ALTER TABLE `nmmz_export_permit` MODIFY COLUMN `export_purpose` VARCHAR(63) NOT NULL COMMENT 'exhibition, research, conservation, sale, personal, return';
ALTER TABLE `nmmz_export_permit` MODIFY COLUMN `status` VARCHAR(65) DEFAULT 'pending' COMMENT 'pending, approved, rejected, issued, used, expired, cancelled';
ALTER TABLE `nmmz_heritage_impact_assessment` MODIFY COLUMN `impact_level` VARCHAR(39) DEFAULT 'moderate' COMMENT 'none, low, moderate, high, severe';
ALTER TABLE `nmmz_heritage_impact_assessment` MODIFY COLUMN `recommendation` VARCHAR(62) DEFAULT 'further_study' COMMENT 'approve, approve_with_conditions, reject, further_study';
ALTER TABLE `nmmz_heritage_impact_assessment` MODIFY COLUMN `status` VARCHAR(58) DEFAULT 'submitted' COMMENT 'submitted, under_review, approved, rejected, expired';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `condition_rating` VARCHAR(53) DEFAULT 'good' COMMENT 'excellent, good, fair, poor, critical, destroyed';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `conservation_priority` VARCHAR(25) DEFAULT 'medium' COMMENT 'high, medium, low';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `legal_status` VARCHAR(48) DEFAULT 'proposed' COMMENT 'gazetted, provisional, proposed, delisted';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `ownership_type` VARCHAR(38) DEFAULT 'state' COMMENT 'state, private, communal, mixed';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `protection_level` VARCHAR(35) DEFAULT 'national' COMMENT 'national, provincial, local';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `status` VARCHAR(43) DEFAULT 'active' COMMENT 'active, at_risk, destroyed, delisted';
ALTER TABLE `nmmz_monument` MODIFY COLUMN `world_heritage_status` VARCHAR(34) DEFAULT 'none' COMMENT 'inscribed, tentative, none';
ALTER TABLE `nmmz_monument_category` MODIFY COLUMN `protection_level` VARCHAR(35) DEFAULT 'national' COMMENT 'national, provincial, local';
ALTER TABLE `nmmz_monument_inspection` MODIFY COLUMN `condition_rating` VARCHAR(53) NOT NULL COMMENT 'excellent, good, fair, poor, critical, destroyed';
ALTER TABLE `nmmz_monument_inspection` MODIFY COLUMN `previous_rating` VARCHAR(53) DEFAULT NULL COMMENT 'excellent, good, fair, poor, critical, destroyed';
ALTER TABLE `numbering_scheme` MODIFY COLUMN `sector` VARCHAR(48) NOT NULL COMMENT 'archive, library, museum, gallery, dam, all';
ALTER TABLE `numbering_scheme` MODIFY COLUMN `sequence_reset` VARCHAR(30) DEFAULT 'never' COMMENT 'never, yearly, monthly';
ALTER TABLE `oais_fixity_check` MODIFY COLUMN `check_type` VARCHAR(27) NOT NULL COMMENT 'md5, sha256, sha512';
ALTER TABLE `oais_information_package` MODIFY COLUMN `package_type` VARCHAR(21) NOT NULL COMMENT 'SIP, AIP, DIP';
ALTER TABLE `oais_information_package` MODIFY COLUMN `preservation_level` VARCHAR(30) DEFAULT 'bit' COMMENT 'bit, logical, semantic';
ALTER TABLE `oais_information_package` MODIFY COLUMN `status` VARCHAR(63) DEFAULT 'pending' COMMENT 'pending, ingesting, stored, preserved, disseminated, error';
ALTER TABLE `oais_package_content` MODIFY COLUMN `content_type` VARCHAR(45) DEFAULT 'content' COMMENT 'content, metadata, manifest, signature';
ALTER TABLE `oais_premis_event` MODIFY COLUMN `event_outcome` VARCHAR(33) NOT NULL COMMENT 'success, failure, warning';
ALTER TABLE `oais_premis_event` MODIFY COLUMN `event_type` VARCHAR(269) NOT NULL COMMENT 'capture, compression, creation, deaccession, decompression, decryption, deletion, digital_signature_validation, dissemination, encryption, fixity_check, format_identification, ingestion, message_digest_calculation, migration, normalization, replication, validation, virus_check';
ALTER TABLE `oais_preservation_policy` MODIFY COLUMN `action_type` VARCHAR(44) NOT NULL COMMENT 'migrate, normalize, emulate, preserve';
ALTER TABLE `oais_pronom_format` MODIFY COLUMN `risk_level` VARCHAR(34) DEFAULT 'low' COMMENT 'low, medium, high, critical';
ALTER TABLE `object_3d_audit_log` MODIFY COLUMN `action` VARCHAR(79) NOT NULL COMMENT 'upload, update, delete, view, ar_view, download, hotspot_add, hotspot_delete';
ALTER TABLE `object_3d_hotspot` MODIFY COLUMN `hotspot_type` VARCHAR(44) DEFAULT 'annotation' COMMENT 'annotation, info, link, damage, detail';
ALTER TABLE `object_3d_hotspot` MODIFY COLUMN `link_target` VARCHAR(22) DEFAULT '_blank' COMMENT '_self, _blank';
ALTER TABLE `object_3d_model` MODIFY COLUMN `ar_placement` VARCHAR(20) DEFAULT 'floor' COMMENT 'floor, wall';
ALTER TABLE `object_3d_model` MODIFY COLUMN `format` VARCHAR(39) DEFAULT 'glb' COMMENT 'glb, gltf, obj, fbx, stl, ply, usdz';
ALTER TABLE `object_3d_settings` MODIFY COLUMN `ar_placement` VARCHAR(20) DEFAULT 'floor' COMMENT 'floor, wall';
ALTER TABLE `object_3d_texture` MODIFY COLUMN `texture_type` VARCHAR(67) DEFAULT 'diffuse' COMMENT 'diffuse, normal, roughness, metallic, ao, emissive, environment';
ALTER TABLE `object_access_grant` MODIFY COLUMN `access_level` VARCHAR(28) DEFAULT 'view' COMMENT 'view, download, edit';
ALTER TABLE `object_access_grant` MODIFY COLUMN `object_type` VARCHAR(45) NOT NULL COMMENT 'information_object, repository, actor';
ALTER TABLE `physical_object_extended` MODIFY COLUMN `status` VARCHAR(48) DEFAULT 'active' COMMENT 'active, full, maintenance, decommissioned';
ALTER TABLE `preservation_backup_verification` MODIFY COLUMN `backup_type` VARCHAR(41) NOT NULL COMMENT 'database, files, full, incremental';
ALTER TABLE `preservation_backup_verification` MODIFY COLUMN `status` VARCHAR(47) NOT NULL COMMENT 'valid, invalid, missing, error, corrupted';
ALTER TABLE `preservation_checksum` MODIFY COLUMN `algorithm` VARCHAR(32) NOT NULL DEFAULT 'sha256' COMMENT 'md5, sha1, sha256, sha512';
ALTER TABLE `preservation_checksum` MODIFY COLUMN `verification_status` VARCHAR(37) DEFAULT 'pending' COMMENT 'pending, valid, invalid, error';
ALTER TABLE `preservation_event` MODIFY COLUMN `event_outcome` VARCHAR(41) DEFAULT 'unknown' COMMENT 'success, failure, warning, unknown';
ALTER TABLE `preservation_event` MODIFY COLUMN `event_type` VARCHAR(207) NOT NULL COMMENT 'creation, capture, ingestion, validation, fixity_check, virus_check, format_identification, normalization, migration, replication, deletion, deaccession, modification, metadata_modification, access, dissemination';
ALTER TABLE `preservation_event` MODIFY COLUMN `linking_agent_type` VARCHAR(43) DEFAULT 'system' COMMENT 'user, system, software, organization';
ALTER TABLE `preservation_fixity_check` MODIFY COLUMN `algorithm` VARCHAR(32) NOT NULL COMMENT 'md5, sha1, sha256, sha512';
ALTER TABLE `preservation_fixity_check` MODIFY COLUMN `status` VARCHAR(33) NOT NULL COMMENT 'pass, fail, error, missing';
ALTER TABLE `preservation_format` MODIFY COLUMN `preservation_action` VARCHAR(40) DEFAULT 'monitor' COMMENT 'none, monitor, migrate, normalize';
ALTER TABLE `preservation_format` MODIFY COLUMN `risk_level` VARCHAR(34) DEFAULT 'medium' COMMENT 'low, medium, high, critical';
ALTER TABLE `preservation_format_conversion` MODIFY COLUMN `status` VARCHAR(45) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed';
ALTER TABLE `preservation_format_obsolescence` MODIFY COLUMN `current_risk_level` VARCHAR(34) NOT NULL COMMENT 'low, medium, high, critical';
ALTER TABLE `preservation_format_obsolescence` MODIFY COLUMN `migration_urgency` VARCHAR(39) DEFAULT 'none' COMMENT 'none, low, medium, high, critical';
ALTER TABLE `preservation_migration_pathway` MODIFY COLUMN `quality_impact` VARCHAR(47) DEFAULT 'minimal' COMMENT 'lossless, minimal, moderate, significant';
ALTER TABLE `preservation_migration_plan` MODIFY COLUMN `scope_type` VARCHAR(42) DEFAULT 'all' COMMENT 'all, repository, collection, custom';
ALTER TABLE `preservation_migration_plan` MODIFY COLUMN `status` VARCHAR(63) DEFAULT 'draft' COMMENT 'draft, approved, in_progress, completed, cancelled, failed';
ALTER TABLE `preservation_migration_plan_object` MODIFY COLUMN `status` VARCHAR(60) DEFAULT 'pending' COMMENT 'pending, queued, processing, completed, failed, skipped';
ALTER TABLE `preservation_object_format` MODIFY COLUMN `confidence` VARCHAR(33) DEFAULT 'medium' COMMENT 'low, medium, high, certain';
ALTER TABLE `preservation_package` MODIFY COLUMN `package_format` VARCHAR(33) NOT NULL DEFAULT 'bagit' COMMENT 'bagit, zip, tar, directory';
ALTER TABLE `preservation_package` MODIFY COLUMN `package_type` VARCHAR(21) NOT NULL COMMENT 'sip, aip, dip';
ALTER TABLE `preservation_package` MODIFY COLUMN `status` VARCHAR(58) NOT NULL DEFAULT 'draft' COMMENT 'draft, building, complete, validated, exported, error';
ALTER TABLE `preservation_package_event` MODIFY COLUMN `event_outcome` VARCHAR(33) DEFAULT 'success' COMMENT 'success, failure, warning';
ALTER TABLE `preservation_package_event` MODIFY COLUMN `event_type` VARCHAR(89) NOT NULL COMMENT 'creation, modification, building, validation, export, import, transfer, deletion, error';
ALTER TABLE `preservation_package_object` MODIFY COLUMN `object_role` VARCHAR(43) DEFAULT 'payload' COMMENT 'payload, metadata, manifest, tagfile';
ALTER TABLE `preservation_policy` MODIFY COLUMN `policy_type` VARCHAR(45) NOT NULL COMMENT 'fixity, format, retention, replication';
ALTER TABLE `preservation_replication_log` MODIFY COLUMN `operation` VARCHAR(29) NOT NULL COMMENT 'sync, verify, restore';
ALTER TABLE `preservation_replication_log` MODIFY COLUMN `status` VARCHAR(42) NOT NULL COMMENT 'started, completed, failed, partial';
ALTER TABLE `preservation_replication_target` MODIFY COLUMN `last_sync_status` VARCHAR(32) DEFAULT NULL COMMENT 'success, partial, failed';
ALTER TABLE `preservation_replication_target` MODIFY COLUMN `target_type` VARCHAR(39) NOT NULL COMMENT 'local, sftp, s3, azure, gcs, rsync';
ALTER TABLE `preservation_virus_scan` MODIFY COLUMN `status` VARCHAR(38) NOT NULL COMMENT 'clean, infected, error, skipped';
ALTER TABLE `preservation_workflow_run` MODIFY COLUMN `status` VARCHAR(52) NOT NULL DEFAULT 'running' COMMENT 'running, completed, failed, timeout, cancelled';
ALTER TABLE `preservation_workflow_run` MODIFY COLUMN `triggered_by` VARCHAR(30) DEFAULT 'scheduler' COMMENT 'scheduler, manual, api';
ALTER TABLE `preservation_workflow_schedule` MODIFY COLUMN `last_run_status` VARCHAR(40) DEFAULT NULL COMMENT 'success, partial, failed, timeout';
ALTER TABLE `preservation_workflow_schedule` MODIFY COLUMN `schedule_type` VARCHAR(30) NOT NULL DEFAULT 'cron' COMMENT 'cron, interval, manual';
ALTER TABLE `preservation_workflow_schedule` MODIFY COLUMN `workflow_type` VARCHAR(105) NOT NULL COMMENT 'format_identification, fixity_check, virus_scan, format_conversion, backup_verification, replication';
ALTER TABLE `privacy_breach` MODIFY COLUMN `breach_type` VARCHAR(48) NOT NULL COMMENT 'confidentiality, integrity, availability';
ALTER TABLE `privacy_breach` MODIFY COLUMN `risk_to_rights` VARCHAR(39) DEFAULT NULL COMMENT 'unlikely, possible, likely, high';
ALTER TABLE `privacy_breach` MODIFY COLUMN `severity` VARCHAR(34) NOT NULL DEFAULT 'medium' COMMENT 'low, medium, high, critical';
ALTER TABLE `privacy_breach` MODIFY COLUMN `status` VARCHAR(58) NOT NULL DEFAULT 'detected' COMMENT 'detected, investigating, contained, resolved, closed';
ALTER TABLE `privacy_breach_notification` MODIFY COLUMN `method` VARCHAR(45) NOT NULL COMMENT 'email, letter, portal, phone, in_person';
ALTER TABLE `privacy_breach_notification` MODIFY COLUMN `notification_type` VARCHAR(53) NOT NULL COMMENT 'regulator, data_subject, internal, third_party';
ALTER TABLE `privacy_complaint` MODIFY COLUMN `status` VARCHAR(58) DEFAULT 'received' COMMENT 'received, investigating, resolved, escalated, closed';
ALTER TABLE `privacy_compliance_rule` MODIFY COLUMN `severity` VARCHAR(28) DEFAULT 'error' COMMENT 'error, warning, info';
ALTER TABLE `privacy_consent` MODIFY COLUMN `consent_type` VARCHAR(86) NOT NULL COMMENT 'processing, marketing, profiling, third_party, cookies, research, special_category';
ALTER TABLE `privacy_consent_log` MODIFY COLUMN `action` VARCHAR(43) NOT NULL COMMENT 'granted, withdrawn, expired, renewed';
ALTER TABLE `privacy_data_inventory` MODIFY COLUMN `data_type` VARCHAR(88) NOT NULL COMMENT 'personal, special_category, children, criminal, financial, health, biometric, genetic';
ALTER TABLE `privacy_data_inventory` MODIFY COLUMN `storage_format` VARCHAR(31) NOT NULL DEFAULT 'electronic' COMMENT 'electronic, paper, both';
ALTER TABLE `privacy_dsar` MODIFY COLUMN `outcome` VARCHAR(58) DEFAULT NULL COMMENT 'granted, partially_granted, refused, not_applicable';
ALTER TABLE `privacy_dsar` MODIFY COLUMN `priority` VARCHAR(32) NOT NULL DEFAULT 'normal' COMMENT 'low, normal, high, urgent';
ALTER TABLE `privacy_dsar` MODIFY COLUMN `request_type` VARCHAR(89) NOT NULL COMMENT 'access, rectification, erasure, portability, restriction, objection, withdraw_consent';
ALTER TABLE `privacy_dsar` MODIFY COLUMN `status` VARCHAR(81) NOT NULL DEFAULT 'received' COMMENT 'received, verified, in_progress, pending_info, completed, rejected, withdrawn';
ALTER TABLE `privacy_paia_request` MODIFY COLUMN `access_form` VARCHAR(27) NOT NULL DEFAULT 'copy' COMMENT 'inspect, copy, both';
ALTER TABLE `privacy_paia_request` MODIFY COLUMN `paia_section` VARCHAR(64) NOT NULL COMMENT 'section_18, section_22, section_23, section_50, section_77';
ALTER TABLE `privacy_paia_request` MODIFY COLUMN `status` VARCHAR(84) NOT NULL DEFAULT 'received' COMMENT 'received, processing, granted, partially_granted, refused, transferred, appealed';
ALTER TABLE `privacy_redaction_cache` MODIFY COLUMN `file_type` VARCHAR(20) NOT NULL DEFAULT 'pdf' COMMENT 'pdf, image';
ALTER TABLE `privacy_retention_schedule` MODIFY COLUMN `disposal_action` VARCHAR(42) NOT NULL DEFAULT 'destroy' COMMENT 'destroy, archive, anonymize, review';
ALTER TABLE `privacy_visual_redaction` MODIFY COLUMN `region_type` VARCHAR(36) NOT NULL DEFAULT 'rectangle' COMMENT 'rectangle, polygon, freehand';
ALTER TABLE `privacy_visual_redaction` MODIFY COLUMN `source` VARCHAR(43) NOT NULL DEFAULT 'manual' COMMENT 'manual, auto_ner, auto_pii, imported';
ALTER TABLE `privacy_visual_redaction` MODIFY COLUMN `status` VARCHAR(43) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, applied, rejected';
ALTER TABLE `provenance_agent` MODIFY COLUMN `agent_type` VARCHAR(44) DEFAULT 'person' COMMENT 'person, organization, family, unknown';
ALTER TABLE `provenance_document` MODIFY COLUMN `document_type` VARCHAR(299) NOT NULL DEFAULT 'other' COMMENT 'deed_of_gift, bill_of_sale, invoice, receipt, auction_catalog, exhibition_catalog, inventory, insurance_record, photograph, correspondence, certificate, customs_document, export_license, import_permit, appraisal, condition_report, newspaper_clipping, publication, oral_history, affidavit, legal_document, other';
ALTER TABLE `provenance_entry` MODIFY COLUMN `certainty` VARCHAR(53) NOT NULL DEFAULT 'unknown' COMMENT 'certain, probable, possible, uncertain, unknown';
ALTER TABLE `provenance_entry` MODIFY COLUMN `end_date_qualifier` VARCHAR(31) DEFAULT NULL COMMENT 'circa, before, after, by';
ALTER TABLE `provenance_entry` MODIFY COLUMN `owner_type` VARCHAR(97) NOT NULL DEFAULT 'unknown' COMMENT 'person, family, dealer, auction_house, museum, corporate, government, religious, artist, unknown';
ALTER TABLE `provenance_entry` MODIFY COLUMN `start_date_qualifier` VARCHAR(31) DEFAULT NULL COMMENT 'circa, before, after, by';
ALTER TABLE `provenance_entry` MODIFY COLUMN `transfer_type` VARCHAR(123) NOT NULL DEFAULT 'unknown' COMMENT 'sale, auction, gift, bequest, inheritance, commission, exchange, seizure, restitution, transfer, loan, found, created, unknown';
ALTER TABLE `provenance_event` MODIFY COLUMN `certainty` VARCHAR(45) DEFAULT 'uncertain' COMMENT 'certain, probable, possible, uncertain';
ALTER TABLE `provenance_event` MODIFY COLUMN `date_certainty` VARCHAR(45) DEFAULT 'unknown' COMMENT 'exact, approximate, estimated, unknown';
ALTER TABLE `provenance_event` MODIFY COLUMN `event_type` VARCHAR(331) NOT NULL DEFAULT 'unknown' COMMENT 'creation, commission, sale, purchase, auction, gift, donation, bequest, inheritance, descent, loan_out, loan_return, deposit, withdrawal, transfer, exchange, theft, recovery, confiscation, restitution, repatriation, discovery, excavation, import, export, authentication, appraisal, conservation, restoration, accessioning, deaccessioning, unknown, other';
ALTER TABLE `provenance_event` MODIFY COLUMN `evidence_type` VARCHAR(55) DEFAULT 'none' COMMENT 'documentary, physical, oral, circumstantial, none';
ALTER TABLE `provenance_record` MODIFY COLUMN `acquisition_type` VARCHAR(91) DEFAULT 'unknown' COMMENT 'donation, purchase, bequest, transfer, loan, deposit, exchange, field_collection, unknown';
ALTER TABLE `provenance_record` MODIFY COLUMN `certainty_level` VARCHAR(53) DEFAULT 'unknown' COMMENT 'certain, probable, possible, uncertain, unknown';
ALTER TABLE `provenance_record` MODIFY COLUMN `cultural_property_status` VARCHAR(51) DEFAULT 'none' COMMENT 'none, claimed, disputed, repatriated, cleared';
ALTER TABLE `provenance_record` MODIFY COLUMN `current_status` VARCHAR(50) DEFAULT 'owned' COMMENT 'owned, on_loan, deposited, unknown, disputed';
ALTER TABLE `provenance_record` MODIFY COLUMN `custody_type` VARCHAR(42) DEFAULT 'permanent' COMMENT 'permanent, temporary, loan, deposit';
ALTER TABLE `provenance_record` MODIFY COLUMN `research_status` VARCHAR(55) DEFAULT 'not_started' COMMENT 'not_started, in_progress, complete, inconclusive';
ALTER TABLE `report_definition` MODIFY COLUMN `category` VARCHAR(92) NOT NULL COMMENT 'collection, acquisition, access, preservation, researcher, compliance, statistics, custom';
ALTER TABLE `report_link` MODIFY COLUMN `link_type` VARCHAR(79) NOT NULL COMMENT 'external, information_object, actor, repository, accession, digital_object';
ALTER TABLE `report_query` MODIFY COLUMN `query_type` VARCHAR(24) DEFAULT 'visual' COMMENT 'visual, raw_sql';
ALTER TABLE `report_schedule` MODIFY COLUMN `frequency` VARCHAR(40) NOT NULL COMMENT 'daily, weekly, monthly, quarterly';
ALTER TABLE `report_schedule` MODIFY COLUMN `output_format` VARCHAR(22) DEFAULT 'pdf' COMMENT 'pdf, xlsx, csv';
ALTER TABLE `report_schedule` MODIFY COLUMN `schedule_type` VARCHAR(27) DEFAULT 'recurring' COMMENT 'recurring, trigger';
ALTER TABLE `report_section` MODIFY COLUMN `section_type` VARCHAR(74) NOT NULL COMMENT 'narrative, table, chart, summary_card, image_gallery, links, sql_query';
ALTER TABLE `report_template` MODIFY COLUMN `scope` VARCHAR(33) DEFAULT 'user' COMMENT 'system, institution, user';
ALTER TABLE `reproduction_request` MODIFY COLUMN `status` VARCHAR(55) DEFAULT 'pending' COMMENT 'pending, approved, rejected, completed, cancelled';
ALTER TABLE `research_access_decision` MODIFY COLUMN `decision` VARCHAR(26) NOT NULL COMMENT 'permitted, denied';
ALTER TABLE `research_activity` MODIFY COLUMN `activity_type` VARCHAR(86) NOT NULL COMMENT 'class, tour, exhibit, loan, conservation, photography, filming, event, meeting, other';
ALTER TABLE `research_activity` MODIFY COLUMN `status` VARCHAR(71) DEFAULT 'requested' COMMENT 'requested, tentative, confirmed, in_progress, completed, cancelled';
ALTER TABLE `research_activity_log` MODIFY COLUMN `activity_type` VARCHAR(348) NOT NULL COMMENT 'view, search, download, cite, annotate, collect, book, request, export, share, login, logout, snapshot_created, snapshot_compared, assertion_created, assertion_verified, assertion_disputed, extraction_queued, extraction_completed, validation_accepted, validation_rejected, hypothesis_created, hypothesis_updated, policy_evaluated, doi_minted, create, clipboard_add';
ALTER TABLE `research_activity_material` MODIFY COLUMN `status` VARCHAR(71) DEFAULT 'requested' COMMENT 'requested, approved, rejected, retrieved, in_use, returned, damaged';
ALTER TABLE `research_activity_participant` MODIFY COLUMN `registration_status` VARCHAR(63) DEFAULT 'pending' COMMENT 'pending, confirmed, waitlist, cancelled, attended, no_show';
ALTER TABLE `research_activity_participant` MODIFY COLUMN `role` VARCHAR(78) DEFAULT 'visitor' COMMENT 'organizer, instructor, presenter, student, visitor, assistant, staff, other';
ALTER TABLE `research_annotation` MODIFY COLUMN `annotation_type` VARCHAR(51) DEFAULT 'note' COMMENT 'note, highlight, bookmark, tag, transcription';
ALTER TABLE `research_annotation` MODIFY COLUMN `content_format` VARCHAR(20) DEFAULT 'text' COMMENT 'text, html';
ALTER TABLE `research_annotation` MODIFY COLUMN `visibility` VARCHAR(31) DEFAULT 'private' COMMENT 'private, shared, public';
ALTER TABLE `research_annotation_target` MODIFY COLUMN `selector_type` VARCHAR(97) DEFAULT NULL COMMENT 'TextQuoteSelector, FragmentSelector, SvgSelector, PointSelector, RangeSelector, TimeSelector';
ALTER TABLE `research_annotation_v2` MODIFY COLUMN `motivation` VARCHAR(84) NOT NULL DEFAULT 'commenting' COMMENT 'commenting, describing, classifying, linking, questioning, tagging, highlighting';
ALTER TABLE `research_annotation_v2` MODIFY COLUMN `status` VARCHAR(33) DEFAULT 'active' COMMENT 'active, archived, deleted';
ALTER TABLE `research_annotation_v2` MODIFY COLUMN `visibility` VARCHAR(31) DEFAULT 'private' COMMENT 'private, shared, public';
ALTER TABLE `research_assertion` MODIFY COLUMN `assertion_type` VARCHAR(67) NOT NULL COMMENT 'biographical, chronological, spatial, relational, attributive';
ALTER TABLE `research_assertion` MODIFY COLUMN `status` VARCHAR(46) DEFAULT 'proposed' COMMENT 'proposed, verified, disputed, retracted';
ALTER TABLE `research_assertion_evidence` MODIFY COLUMN `relationship` VARCHAR(26) NOT NULL COMMENT 'supports, refutes';
ALTER TABLE `research_bibliography_entry` MODIFY COLUMN `entry_type` VARCHAR(60) DEFAULT 'archival' COMMENT 'archival, book, article, chapter, thesis, website, other';
ALTER TABLE `research_booking` MODIFY COLUMN `status` VARCHAR(55) DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled, completed, no_show';
ALTER TABLE `research_comment` MODIFY COLUMN `entity_type` VARCHAR(67) NOT NULL COMMENT 'report, report_section, annotation, journal_entry, collection';
ALTER TABLE `research_entity_resolution` MODIFY COLUMN `status` VARCHAR(36) DEFAULT 'proposed' COMMENT 'proposed, accepted, rejected';
ALTER TABLE `research_equipment` MODIFY COLUMN `condition_status` VARCHAR(57) DEFAULT 'good' COMMENT 'excellent, good, fair, needs_repair, out_of_service';
ALTER TABLE `research_equipment` MODIFY COLUMN `equipment_type` VARCHAR(127) NOT NULL COMMENT 'microfilm_reader, microfiche_reader, scanner, computer, magnifier, book_cradle, light_box, camera_stand, gloves, weights, other';
ALTER TABLE `research_equipment_booking` MODIFY COLUMN `condition_on_return` VARCHAR(37) DEFAULT NULL COMMENT 'excellent, good, fair, damaged';
ALTER TABLE `research_equipment_booking` MODIFY COLUMN `status` VARCHAR(52) DEFAULT 'reserved' COMMENT 'reserved, in_use, returned, cancelled, no_show';
ALTER TABLE `research_external_collaborator` MODIFY COLUMN `role` VARCHAR(28) DEFAULT 'viewer' COMMENT 'viewer, contributor';
ALTER TABLE `research_extraction_job` MODIFY COLUMN `extraction_type` VARCHAR(79) NOT NULL COMMENT 'ocr, ner, summarize, translate, spellcheck, face_detection, form_extraction';
ALTER TABLE `research_extraction_job` MODIFY COLUMN `status` VARCHAR(41) DEFAULT 'queued' COMMENT 'queued, running, completed, failed';
ALTER TABLE `research_extraction_result` MODIFY COLUMN `result_type` VARCHAR(66) NOT NULL COMMENT 'entity, summary, translation, transcription, form_field, face';
ALTER TABLE `research_hypothesis` MODIFY COLUMN `status` VARCHAR(44) DEFAULT 'proposed' COMMENT 'proposed, testing, supported, refuted';
ALTER TABLE `research_hypothesis_evidence` MODIFY COLUMN `relationship` VARCHAR(34) NOT NULL COMMENT 'supports, refutes, neutral';
ALTER TABLE `research_institutional_share` MODIFY COLUMN `share_type` VARCHAR(30) DEFAULT 'view' COMMENT 'view, contribute, full';
ALTER TABLE `research_institutional_share` MODIFY COLUMN `status` VARCHAR(40) DEFAULT 'pending' COMMENT 'pending, active, revoked, expired';
ALTER TABLE `research_journal_entry` MODIFY COLUMN `content_format` VARCHAR(20) DEFAULT 'html' COMMENT 'text, html';
ALTER TABLE `research_journal_entry` MODIFY COLUMN `entry_type` VARCHAR(108) DEFAULT 'manual' COMMENT 'manual, auto_booking, auto_material, auto_annotation, auto_search, auto_collection, reflection, milestone';
ALTER TABLE `research_material_request` MODIFY COLUMN `priority` VARCHAR(26) DEFAULT 'normal' COMMENT 'normal, high, rush';
ALTER TABLE `research_material_request` MODIFY COLUMN `request_type` VARCHAR(54) DEFAULT 'reading_room' COMMENT 'reading_room, reproduction, loan, remote_access';
ALTER TABLE `research_material_request` MODIFY COLUMN `status` VARCHAR(67) DEFAULT 'requested' COMMENT 'requested, retrieved, delivered, in_use, returned, unavailable';
ALTER TABLE `research_notification` MODIFY COLUMN `type` VARCHAR(70) NOT NULL COMMENT 'alert, invitation, comment, reply, system, reminder, collaboration';
ALTER TABLE `research_notification_preference` MODIFY COLUMN `digest_frequency` VARCHAR(37) DEFAULT 'immediate' COMMENT 'immediate, daily, weekly, none';
ALTER TABLE `research_peer_review` MODIFY COLUMN `status` VARCHAR(48) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, declined';
ALTER TABLE `research_print_template` MODIFY COLUMN `orientation` VARCHAR(28) DEFAULT 'portrait' COMMENT 'portrait, landscape';
ALTER TABLE `research_print_template` MODIFY COLUMN `page_size` VARCHAR(55) DEFAULT 'a4' COMMENT 'a4, a5, letter, label_4x6, label_2x4, badge, custom';
ALTER TABLE `research_print_template` MODIFY COLUMN `template_type` VARCHAR(65) NOT NULL COMMENT 'call_slip, paging_slip, receipt, badge, label, report, letter';
ALTER TABLE `research_project` MODIFY COLUMN `project_type` VARCHAR(103) DEFAULT 'personal' COMMENT 'thesis, dissertation, publication, exhibition, documentary, genealogy, institutional, personal, other';
ALTER TABLE `research_project` MODIFY COLUMN `status` VARCHAR(52) DEFAULT 'planning' COMMENT 'planning, active, on_hold, completed, archived';
ALTER TABLE `research_project` MODIFY COLUMN `visibility` VARCHAR(38) DEFAULT 'private' COMMENT 'private, collaborators, public';
ALTER TABLE `research_project_collaborator` MODIFY COLUMN `role` VARCHAR(41) DEFAULT 'contributor' COMMENT 'owner, editor, contributor, viewer';
ALTER TABLE `research_project_collaborator` MODIFY COLUMN `status` VARCHAR(43) DEFAULT 'pending' COMMENT 'pending, accepted, declined, removed';
ALTER TABLE `research_project_milestone` MODIFY COLUMN `status` VARCHAR(49) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled';
ALTER TABLE `research_project_resource` MODIFY COLUMN `link_type` VARCHAR(73) DEFAULT NULL COMMENT 'academic, archive, database, government, website, social_media, other';
ALTER TABLE `research_project_resource` MODIFY COLUMN `resource_type` VARCHAR(92) NOT NULL COMMENT 'collection, saved_search, annotation, bibliography, object, external_link, document, note';
ALTER TABLE `research_quality_metric` MODIFY COLUMN `metric_type` VARCHAR(78) NOT NULL COMMENT 'ocr_confidence, image_quality, digitisation_completeness, fixity_status';
ALTER TABLE `research_reading_room_seat` MODIFY COLUMN `seat_type` VARCHAR(69) DEFAULT 'standard' COMMENT 'standard, accessible, computer, microfilm, oversize, quiet, group';
ALTER TABLE `research_report` MODIFY COLUMN `status` VARCHAR(53) DEFAULT 'draft' COMMENT 'draft, in_progress, review, completed, archived';
ALTER TABLE `research_report` MODIFY COLUMN `template_type` VARCHAR(85) DEFAULT 'custom' COMMENT 'research_summary, genealogical, historical, source_analysis, finding_aid, custom';
ALTER TABLE `research_report_section` MODIFY COLUMN `content_format` VARCHAR(20) DEFAULT 'html' COMMENT 'text, html';
ALTER TABLE `research_report_section` MODIFY COLUMN `section_type` VARCHAR(98) DEFAULT 'text' COMMENT 'title_page, toc, heading, text, bibliography, collection_list, annotation_list, timeline, custom';
ALTER TABLE `research_reproduction_item` MODIFY COLUMN `color_mode` VARCHAR(28) DEFAULT 'grayscale' COMMENT 'color, grayscale, bw';
ALTER TABLE `research_reproduction_item` MODIFY COLUMN `reproduction_type` VARCHAR(120) DEFAULT 'scan' COMMENT 'photocopy, scan, photograph, digital_copy, digital_scan, transcription, certification, certified_copy, microfilm, other';
ALTER TABLE `research_reproduction_item` MODIFY COLUMN `status` VARCHAR(49) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled';
ALTER TABLE `research_reproduction_request` MODIFY COLUMN `delivery_method` VARCHAR(69) DEFAULT 'email' COMMENT 'email, download, post, collect, digital, pickup, courier, physical';
ALTER TABLE `research_reproduction_request` MODIFY COLUMN `status` VARCHAR(87) DEFAULT 'draft' COMMENT 'draft, submitted, processing, awaiting_payment, in_production, completed, cancelled';
ALTER TABLE `research_request_queue` MODIFY COLUMN `queue_type` VARCHAR(57) DEFAULT 'retrieval' COMMENT 'retrieval, paging, return, curatorial, reproduction';
ALTER TABLE `research_request_queue` MODIFY COLUMN `sort_direction` VARCHAR(20) DEFAULT 'ASC' COMMENT 'ASC, DESC';
ALTER TABLE `research_request_status_history` MODIFY COLUMN `request_type` VARCHAR(31) NOT NULL DEFAULT 'material' COMMENT 'material, reproduction';
ALTER TABLE `research_researcher` MODIFY COLUMN `affiliation_type` VARCHAR(63) DEFAULT 'independent' COMMENT 'academic, government, private, independent, student, other';
ALTER TABLE `research_researcher` MODIFY COLUMN `id_type` VARCHAR(65) DEFAULT NULL COMMENT 'passport, national_id, drivers_license, student_card, other';
ALTER TABLE `research_researcher` MODIFY COLUMN `status` VARCHAR(53) DEFAULT 'pending' COMMENT 'pending, approved, suspended, expired, rejected';
ALTER TABLE `research_rights_policy` MODIFY COLUMN `action_type` VARCHAR(57) NOT NULL COMMENT 'use, reproduce, distribute, modify, archive, display';
ALTER TABLE `research_rights_policy` MODIFY COLUMN `policy_type` VARCHAR(43) NOT NULL COMMENT 'permission, prohibition, obligation';
ALTER TABLE `research_room` MODIFY COLUMN `status` VARCHAR(31) DEFAULT 'draft' COMMENT 'draft, active, archived';
ALTER TABLE `research_room_manifest` MODIFY COLUMN `derivative_type` VARCHAR(31) DEFAULT 'full' COMMENT 'full, subset, annotated';
ALTER TABLE `research_room_participant` MODIFY COLUMN `role` VARCHAR(29) DEFAULT 'viewer' COMMENT 'owner, editor, viewer';
ALTER TABLE `research_saved_search` MODIFY COLUMN `alert_frequency` VARCHAR(30) DEFAULT 'weekly' COMMENT 'daily, weekly, monthly';
ALTER TABLE `research_seat_assignment` MODIFY COLUMN `status` VARCHAR(44) DEFAULT 'assigned' COMMENT 'assigned, occupied, released, no_show';
ALTER TABLE `research_snapshot` MODIFY COLUMN `status` VARCHAR(32) DEFAULT 'active' COMMENT 'active, frozen, archived';
ALTER TABLE `research_source_assessment` MODIFY COLUMN `completeness` VARCHAR(58) DEFAULT 'complete' COMMENT 'complete, partial, fragment, missing_pages, redacted';
ALTER TABLE `research_source_assessment` MODIFY COLUMN `source_form` VARCHAR(62) DEFAULT 'original' COMMENT 'born_digital, scan, original, transcription, translation';
ALTER TABLE `research_source_assessment` MODIFY COLUMN `source_type` VARCHAR(36) NOT NULL COMMENT 'primary, secondary, tertiary';
ALTER TABLE `research_timeline_event` MODIFY COLUMN `date_type` VARCHAR(46) DEFAULT 'event' COMMENT 'event, creation, accession, publication';
ALTER TABLE `research_validation_queue` MODIFY COLUMN `status` VARCHAR(44) DEFAULT 'pending' COMMENT 'pending, accepted, rejected, modified';
ALTER TABLE `research_verification` MODIFY COLUMN `status` VARCHAR(43) DEFAULT 'pending' COMMENT 'pending, verified, rejected, expired';
ALTER TABLE `research_verification` MODIFY COLUMN `verification_type` VARCHAR(113) NOT NULL COMMENT 'id_document, institutional_letter, institutional_email, orcid, staff_approval, professional_membership, other';
ALTER TABLE `research_walk_in_visitor` MODIFY COLUMN `id_type` VARCHAR(65) DEFAULT NULL COMMENT 'passport, national_id, drivers_license, student_card, other';
ALTER TABLE `research_workspace` MODIFY COLUMN `visibility` VARCHAR(32) DEFAULT 'private' COMMENT 'private, members, public';
ALTER TABLE `research_workspace_member` MODIFY COLUMN `role` VARCHAR(54) DEFAULT 'viewer' COMMENT 'owner, admin, editor, viewer, member, contributor';
ALTER TABLE `research_workspace_member` MODIFY COLUMN `status` VARCHAR(43) DEFAULT 'pending' COMMENT 'pending, accepted, declined, removed';
ALTER TABLE `research_workspace_resource` MODIFY COLUMN `resource_type` VARCHAR(68) NOT NULL COMMENT 'collection, project, bibliography, saved_search, document, link';
ALTER TABLE `researcher_submission` MODIFY COLUMN `source_type` VARCHAR(24) NOT NULL DEFAULT 'online' COMMENT 'online, offline';
ALTER TABLE `researcher_submission` MODIFY COLUMN `status` VARCHAR(75) NOT NULL DEFAULT 'draft' COMMENT 'draft, submitted, under_review, approved, published, returned, rejected';
ALTER TABLE `researcher_submission_item` MODIFY COLUMN `item_type` VARCHAR(45) NOT NULL DEFAULT 'description' COMMENT 'description, note, repository, creator';
ALTER TABLE `researcher_submission_review` MODIFY COLUMN `action` VARCHAR(47) NOT NULL COMMENT 'comment, return, approve, reject, publish';
ALTER TABLE `ric_orphan_tracking` MODIFY COLUMN `detection_method` VARCHAR(45) NOT NULL COMMENT 'integrity_check, sync_failure, manual';
ALTER TABLE `ric_orphan_tracking` MODIFY COLUMN `status` VARCHAR(53) NOT NULL DEFAULT 'detected' COMMENT 'detected, reviewed, cleaned, retained, restored';
ALTER TABLE `ric_sync_log` MODIFY COLUMN `operation` VARCHAR(66) NOT NULL COMMENT 'create, update, delete, move, resync, cleanup, integrity_check';
ALTER TABLE `ric_sync_log` MODIFY COLUMN `status` VARCHAR(41) NOT NULL COMMENT 'success, failure, partial, skipped';
ALTER TABLE `ric_sync_log` MODIFY COLUMN `triggered_by` VARCHAR(34) NOT NULL DEFAULT 'system' COMMENT 'user, system, cron, api, cli';
ALTER TABLE `ric_sync_queue` MODIFY COLUMN `operation` VARCHAR(35) NOT NULL COMMENT 'create, update, delete, move';
ALTER TABLE `ric_sync_queue` MODIFY COLUMN `status` VARCHAR(54) NOT NULL DEFAULT 'queued' COMMENT 'queued, processing, completed, failed, cancelled';
ALTER TABLE `ric_sync_status` MODIFY COLUMN `sync_status` VARCHAR(48) NOT NULL DEFAULT 'pending' COMMENT 'synced, pending, failed, deleted, orphaned';
ALTER TABLE `rights_derivative_rule` MODIFY COLUMN `rule_type` VARCHAR(69) NOT NULL COMMENT 'watermark, redaction, resize, format_conversion, metadata_strip';
ALTER TABLE `rights_derivative_rule` MODIFY COLUMN `watermark_position` VARCHAR(65) DEFAULT 'bottom_right' COMMENT 'center, top_left, top_right, bottom_left, bottom_right, tile';
ALTER TABLE `rights_embargo` MODIFY COLUMN `embargo_type` VARCHAR(49) NOT NULL DEFAULT 'full' COMMENT 'full, metadata_only, digital_only, partial';
ALTER TABLE `rights_embargo` MODIFY COLUMN `reason` VARCHAR(95) NOT NULL COMMENT 'donor_restriction, copyright, privacy, legal, commercial, research, cultural, security, other';
ALTER TABLE `rights_embargo` MODIFY COLUMN `status` VARCHAR(48) DEFAULT 'active' COMMENT 'active, pending, lifted, expired, extended';
ALTER TABLE `rights_embargo_log` MODIFY COLUMN `action` VARCHAR(74) NOT NULL COMMENT 'created, extended, lifted, reviewed, notification_sent, auto_released';
ALTER TABLE `rights_grant` MODIFY COLUMN `act` VARCHAR(105) NOT NULL COMMENT 'render, disseminate, replicate, migrate, modify, delete, print, use, publish, excerpt, annotate, move, sell';
ALTER TABLE `rights_grant` MODIFY COLUMN `restriction` VARCHAR(36) NOT NULL DEFAULT 'allow' COMMENT 'allow, disallow, conditional';
ALTER TABLE `rights_orphan_search_step` MODIFY COLUMN `source_type` VARCHAR(93) NOT NULL COMMENT 'database, registry, publisher, author_society, archive, library, internet, newspaper, other';
ALTER TABLE `rights_orphan_work` MODIFY COLUMN `status` VARCHAR(61) DEFAULT 'in_progress' COMMENT 'in_progress, completed, rights_holder_found, abandoned';
ALTER TABLE `rights_orphan_work` MODIFY COLUMN `work_type` VARCHAR(115) NOT NULL COMMENT 'literary, dramatic, musical, artistic, film, sound_recording, broadcast, typographical, database, photograph, other';
ALTER TABLE `rights_record` MODIFY COLUMN `basis` VARCHAR(54) NOT NULL DEFAULT 'copyright' COMMENT 'copyright, license, statute, donor, policy, other';
ALTER TABLE `rights_record` MODIFY COLUMN `copyright_status` VARCHAR(43) DEFAULT 'unknown' COMMENT 'copyrighted, public_domain, unknown';
ALTER TABLE `rights_statement` MODIFY COLUMN `category` VARCHAR(41) NOT NULL COMMENT 'in-copyright, no-copyright, other';
ALTER TABLE `rights_territory` MODIFY COLUMN `territory_type` VARCHAR(25) NOT NULL DEFAULT 'include' COMMENT 'include, exclude';
ALTER TABLE `rights_tk_label` MODIFY COLUMN `category` VARCHAR(27) NOT NULL DEFAULT 'tk' COMMENT 'tk, bc, attribution';
ALTER TABLE `saved_search` MODIFY COLUMN `notification_frequency` VARCHAR(30) DEFAULT 'weekly' COMMENT 'daily, weekly, monthly';
ALTER TABLE `security_access_request` MODIFY COLUMN `priority` VARCHAR(33) DEFAULT 'normal' COMMENT 'normal, urgent, immediate';
ALTER TABLE `security_access_request` MODIFY COLUMN `request_type` VARCHAR(74) NOT NULL COMMENT 'view, download, print, clearance_upgrade, compartment_access, renewal';
ALTER TABLE `security_access_request` MODIFY COLUMN `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, approved, denied, expired, cancelled';
ALTER TABLE `security_clearance_history` MODIFY COLUMN `action` VARCHAR(86) NOT NULL COMMENT 'granted, upgraded, downgraded, revoked, renewed, expired, 2fa_enabled, 2fa_disabled';
ALTER TABLE `security_declassification_schedule` MODIFY COLUMN `trigger_type` VARCHAR(30) NOT NULL DEFAULT 'date' COMMENT 'date, event, retention';
ALTER TABLE `security_watermark_log` MODIFY COLUMN `watermark_type` VARCHAR(32) NOT NULL DEFAULT 'visible' COMMENT 'visible, invisible, both';
ALTER TABLE `semantic_synonym` MODIFY COLUMN `relation_type` VARCHAR(50) DEFAULT 'synonym' COMMENT 'synonym, broader, narrower, related, use_for';
ALTER TABLE `service_monitor_log` MODIFY COLUMN `event_type` VARCHAR(37) NOT NULL DEFAULT 'info' COMMENT 'down, warning, recovered, info';
ALTER TABLE `spectrum_approval` MODIFY COLUMN `status` VARCHAR(35) DEFAULT 'pending' COMMENT 'pending, approved, rejected';
ALTER TABLE `spectrum_condition_photo` MODIFY COLUMN `photo_type` VARCHAR(50) NOT NULL DEFAULT 'detail' COMMENT 'before, after, detail, damage, overall, other';
ALTER TABLE `spectrum_condition_photos` MODIFY COLUMN `category` VARCHAR(54) DEFAULT 'overall' COMMENT 'overall, detail, damage, before, after, reference';
ALTER TABLE `spectrum_condition_template_field` MODIFY COLUMN `field_type` VARCHAR(76) NOT NULL COMMENT 'text, textarea, select, multiselect, checkbox, radio, rating, date, number';
ALTER TABLE `spectrum_loan_agreements` MODIFY COLUMN `loan_type` VARCHAR(20) NOT NULL COMMENT 'in, out';
ALTER TABLE `tiff_pdf_merge_file` MODIFY COLUMN `status` VARCHAR(46) DEFAULT 'uploaded' COMMENT 'uploaded, processing, processed, failed';
ALTER TABLE `tiff_pdf_merge_job` MODIFY COLUMN `orientation` VARCHAR(33) DEFAULT 'auto' COMMENT 'auto, portrait, landscape';
ALTER TABLE `tiff_pdf_merge_job` MODIFY COLUMN `page_size` VARCHAR(33) DEFAULT 'auto' COMMENT 'auto, a4, letter, legal, a3';
ALTER TABLE `tiff_pdf_merge_job` MODIFY COLUMN `pdf_standard` VARCHAR(37) DEFAULT 'pdfa-2b' COMMENT 'pdf, pdfa-1b, pdfa-2b, pdfa-3b';
ALTER TABLE `tiff_pdf_merge_job` MODIFY COLUMN `status` VARCHAR(52) DEFAULT 'pending' COMMENT 'pending, queued, processing, completed, failed';
ALTER TABLE `tiff_pdf_settings` MODIFY COLUMN `setting_type` VARCHAR(37) DEFAULT 'string' COMMENT 'string, integer, boolean, json';
ALTER TABLE `triposr_jobs` MODIFY COLUMN `output_format` VARCHAR(20) DEFAULT 'glb' COMMENT 'glb, obj';
ALTER TABLE `triposr_jobs` MODIFY COLUMN `processing_mode` VARCHAR(22) DEFAULT 'local' COMMENT 'local, remote';
ALTER TABLE `triposr_jobs` MODIFY COLUMN `status` VARCHAR(45) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed';
ALTER TABLE `user_browse_settings` MODIFY COLUMN `default_sort_direction` VARCHAR(20) DEFAULT 'desc' COMMENT 'asc, desc';
ALTER TABLE `user_display_preference` MODIFY COLUMN `card_size` VARCHAR(28) DEFAULT 'medium' COMMENT 'small, medium, large';
ALTER TABLE `user_display_preference` MODIFY COLUMN `sort_direction` VARCHAR(20) DEFAULT 'desc' COMMENT 'asc, desc';
ALTER TABLE `user_security_clearance_log` MODIFY COLUMN `action` VARCHAR(41) NOT NULL COMMENT 'granted, revoked, updated, expired';
ALTER TABLE `viewer_3d_settings` MODIFY COLUMN `setting_type` VARCHAR(37) DEFAULT 'string' COMMENT 'string, integer, boolean, json';

END //
DELIMITER ;
CALL heratio_enum_to_varchar_migration();
DROP PROCEDURE IF EXISTS heratio_enum_to_varchar_migration;

-- Verification (intentionally outside the procedure — run on real DB only):
-- SELECT COUNT(*) AS remaining_enums FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'enum';

-- ============================================================================
-- End of framework schema
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
