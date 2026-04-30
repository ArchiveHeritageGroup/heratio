-- ============================================================================
-- ahg-integrity — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgIntegrityPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ahgIntegrityPlugin - Database Schema
-- Enterprise-grade automated integrity assurance

-- ============================================================
-- Table: integrity_schedule
-- Scoped verification schedules with concurrency controls
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_schedule` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `scope_type` VARCHAR(20) NOT NULL DEFAULT 'global' COMMENT 'global, repository, hierarchy',
    `repository_id` INT NULL,
    `information_object_id` INT NULL,
    `algorithm` VARCHAR(10) NOT NULL DEFAULT 'sha256' COMMENT 'sha256, sha512',
    `frequency` VARCHAR(20) NOT NULL DEFAULT 'weekly' COMMENT 'daily, weekly, monthly, ad_hoc',
    `cron_expression` VARCHAR(100) NULL COMMENT 'Optional cron expression override',
    `batch_size` INT UNSIGNED NOT NULL DEFAULT 200,
    `io_throttle_ms` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Microsleep between objects (ms)',
    `max_memory_mb` INT UNSIGNED NOT NULL DEFAULT 512,
    `max_runtime_minutes` INT UNSIGNED NOT NULL DEFAULT 120,
    `max_concurrent_runs` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `last_run_at` DATETIME NULL,
    `next_run_at` DATETIME NULL,
    `total_runs` INT UNSIGNED NOT NULL DEFAULT 0,
    `notify_on_failure` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_on_mismatch` TINYINT(1) NOT NULL DEFAULT 1,
    `notify_email` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_is_schedule_enabled` (`is_enabled`),
    INDEX `idx_is_schedule_next_run` (`next_run_at`),
    INDEX `idx_is_schedule_scope` (`scope_type`, `repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: integrity_run
-- Execution records for scheduled or manual verification runs
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_run` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `schedule_id` BIGINT UNSIGNED NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'running' COMMENT 'running, completed, partial, failed, timeout, cancelled',
    `algorithm` VARCHAR(10) NOT NULL DEFAULT 'sha256' COMMENT 'sha256, sha512',
    `objects_scanned` INT UNSIGNED NOT NULL DEFAULT 0,
    `objects_passed` INT UNSIGNED NOT NULL DEFAULT 0,
    `objects_failed` INT UNSIGNED NOT NULL DEFAULT 0,
    `objects_missing` INT UNSIGNED NOT NULL DEFAULT 0,
    `objects_error` INT UNSIGNED NOT NULL DEFAULT 0,
    `objects_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
    `bytes_scanned` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `triggered_by` VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'scheduler, manual, cli, api',
    `triggered_by_user` VARCHAR(255) NULL,
    `lock_token` VARCHAR(64) NULL,
    `error_message` TEXT NULL,
    `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ir_schedule` (`schedule_id`),
    INDEX `idx_ir_status` (`status`),
    INDEX `idx_ir_started` (`started_at`),
    CONSTRAINT `fk_ir_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `integrity_schedule`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: integrity_ledger
-- Append-only verification ledger. NEVER UPDATE or DELETE rows.
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_ledger` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `run_id` BIGINT UNSIGNED NULL,
    `digital_object_id` INT NOT NULL COMMENT 'No FK — survives object deletion',
    `information_object_id` INT NULL COMMENT 'Denormalized for scoped queries',
    `repository_id` INT NULL COMMENT 'Denormalized for scoped queries',
    `file_path` VARCHAR(1024) NULL,
    `file_size` BIGINT UNSIGNED NULL,
    `file_exists` TINYINT(1) NOT NULL DEFAULT 0,
    `file_readable` TINYINT(1) NOT NULL DEFAULT 0,
    `algorithm` VARCHAR(10) NOT NULL,
    `expected_hash` VARCHAR(128) NULL,
    `computed_hash` VARCHAR(128) NULL,
    `hash_match` TINYINT(1) NULL,
    `outcome` VARCHAR(30) NOT NULL COMMENT 'pass, mismatch, missing, unreadable, permission_error, path_drift, no_baseline, error',
    `error_detail` TEXT NULL,
    `duration_ms` INT UNSIGNED NULL,
    `verified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_il_run` (`run_id`),
    INDEX `idx_il_digital_object` (`digital_object_id`),
    INDEX `idx_il_outcome` (`outcome`),
    INDEX `idx_il_verified` (`verified_at`),
    INDEX `idx_il_repository` (`repository_id`),
    INDEX `idx_il_info_object` (`information_object_id`),
    CONSTRAINT `fk_il_run` FOREIGN KEY (`run_id`) REFERENCES `integrity_run`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: integrity_dead_letter
-- Persistent failure queue for objects that fail repeatedly
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_dead_letter` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `digital_object_id` INT NOT NULL,
    `failure_type` VARCHAR(30) NOT NULL COMMENT 'mismatch, missing, unreadable, permission_error, path_drift, error',
    `status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'open, acknowledged, investigating, resolved, ignored',
    `consecutive_failures` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_failure_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_failure_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_error_detail` TEXT NULL,
    `last_run_id` BIGINT UNSIGNED NULL,
    `retry_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_retries` INT UNSIGNED NOT NULL DEFAULT 3,
    `next_retry_at` DATETIME NULL,
    `acknowledged_by` VARCHAR(255) NULL,
    `acknowledged_at` DATETIME NULL,
    `resolution_notes` TEXT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_idl_object_failure` (`digital_object_id`, `failure_type`),
    INDEX `idx_idl_status` (`status`),
    INDEX `idx_idl_next_retry` (`next_retry_at`),
    CONSTRAINT `fk_idl_run` FOREIGN KEY (`last_run_id`) REFERENCES `integrity_run`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Issue #188: Add actor/hostname/previous_hash tracking to ledger
-- (Programmatic migration via runMigration() checks INFORMATION_SCHEMA before ALTER)
-- ============================================================
-- ALTER TABLE `integrity_ledger` ADD COLUMN `actor` VARCHAR(255) NULL AFTER `duration_ms`;
-- ALTER TABLE `integrity_ledger` ADD COLUMN `hostname` VARCHAR(255) NULL AFTER `actor`;
-- ALTER TABLE `integrity_ledger` ADD COLUMN `previous_hash` VARCHAR(128) NULL AFTER `hostname`;
-- NOTE: The above ALTERs are applied programmatically by IntegrityService::runMigration()
--       to avoid errors on re-run. They are commented here for documentation only.

-- ============================================================
-- Table: integrity_retention_policy (Issue #189)
-- Retention period definitions and scope rules
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_retention_policy` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `retention_period_days` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = indefinite retention',
    `trigger_type` VARCHAR(20) NOT NULL DEFAULT 'ingest_date' COMMENT 'ingest_date, last_modified, closure_date, last_access',
    `scope_type` VARCHAR(20) NOT NULL DEFAULT 'global' COMMENT 'global, repository, hierarchy',
    `repository_id` INT NULL,
    `information_object_id` INT NULL,
    `object_format` VARCHAR(100) NULL COMMENT 'MIME type filter e.g. image/tiff, application/pdf',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_irp_enabled` (`is_enabled`),
    INDEX `idx_irp_scope` (`scope_type`, `repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Issue #189: object_format column migration (programmatic via runMigration())
-- ALTER TABLE `integrity_retention_policy` ADD COLUMN `object_format` VARCHAR(100) NULL AFTER `information_object_id`;

-- ============================================================
-- Table: integrity_legal_hold (Issue #189)
-- Legal holds that block disposition of records
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_legal_hold` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `information_object_id` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `placed_by` VARCHAR(255) NOT NULL,
    `placed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `released_by` VARCHAR(255) NULL,
    `released_at` DATETIME NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, released',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ilh_io` (`information_object_id`),
    INDEX `idx_ilh_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: integrity_disposition_queue (Issue #189)
-- Disposition review queue for records past retention period
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_disposition_queue` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `policy_id` BIGINT UNSIGNED NOT NULL,
    `information_object_id` INT NOT NULL,
    `digital_object_id` INT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'eligible' COMMENT 'eligible, pending_review, approved, rejected, held, disposed',
    `eligible_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reviewed_by` VARCHAR(255) NULL,
    `reviewed_at` DATETIME NULL,
    `review_notes` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_idq_policy` (`policy_id`),
    INDEX `idx_idq_io` (`information_object_id`),
    INDEX `idx_idq_status` (`status`),
    CONSTRAINT `fk_idq_policy` FOREIGN KEY (`policy_id`) REFERENCES `integrity_retention_policy`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Table: integrity_alert_config (Issue #190)
-- Threshold-based alerting configuration
-- ============================================================
CREATE TABLE IF NOT EXISTS `integrity_alert_config` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `alert_type` VARCHAR(30) NOT NULL COMMENT 'pass_rate_below, failure_count_above, dead_letter_count_above, backlog_above, run_failure',
    `threshold_value` DECIMAL(12,2) NULL,
    `comparison` VARCHAR(5) NOT NULL DEFAULT 'gt' COMMENT 'lt, lte, gt, gte, eq',
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `email` VARCHAR(255) NULL,
    `webhook_url` VARCHAR(1024) NULL,
    `webhook_secret` VARCHAR(255) NULL,
    `last_triggered_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_iac_enabled` (`is_enabled`),
    INDEX `idx_iac_type` (`alert_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed data: 2 default schedules
-- ============================================================
INSERT IGNORE INTO `integrity_schedule` (`id`, `name`, `description`, `scope_type`, `algorithm`, `frequency`, `batch_size`, `io_throttle_ms`, `max_memory_mb`, `max_runtime_minutes`, `max_concurrent_runs`, `is_enabled`, `next_run_at`, `notify_on_failure`, `notify_on_mismatch`)
VALUES
(1, 'Daily Sample Check', 'Verifies a sample of 200 digital objects daily to detect early signs of data corruption', 'global', 'sha256', 'daily', 200, 10, 512, 30, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1, 1),
(2, 'Weekly Full Scan', 'Comprehensive weekly verification of all master digital objects across all repositories', 'global', 'sha256', 'weekly', 0, 5, 1024, 480, 1, 0, NULL, 1, 1);

-- =====================================================
-- Admin menu entry: Admin > Integrity
-- Inserts as last child of the Admin menu node (name='admin')
-- Uses MPTT: shift rgt values to make room, then insert
-- =====================================================
SET @admin_rgt = (SELECT rgt FROM menu WHERE name = 'admin' LIMIT 1);

-- Only insert if not already present
SET @exists = (SELECT COUNT(*) FROM menu WHERE name = 'integrity');

-- Make room in the nested set: shift nodes to the right
UPDATE menu SET rgt = rgt + 2 WHERE rgt >= @admin_rgt AND @exists = 0;
UPDATE menu SET lft = lft + 2 WHERE lft > @admin_rgt AND @exists = 0;

-- Insert the menu node as last child of Admin
INSERT IGNORE INTO menu (parent_id, name, path, lft, rgt, created_at, updated_at, source_culture, serial_number)
SELECT id, 'integrity', 'integrity/index', @admin_rgt, @admin_rgt + 1, NOW(), NOW(), 'en', 0
FROM menu WHERE name = 'admin' AND @exists = 0
LIMIT 1;

-- Insert the i18n label
INSERT IGNORE INTO menu_i18n (id, culture, label, description)
SELECT m.id, 'en', 'Integrity', 'Integrity assurance: fixity verification, retention policies, legal holds, alerting'
FROM menu m WHERE m.name = 'integrity' AND NOT EXISTS (
    SELECT 1 FROM menu_i18n mi WHERE mi.id = m.id AND mi.culture = 'en'
);

-- =====================================================
-- Settings defaults for integrity assurance
-- =====================================================
INSERT IGNORE INTO ahg_settings (setting_key, setting_value, setting_group, created_at, updated_at)
VALUES
('integrity_enabled', 'true', 'integrity', NOW(), NOW()),
('integrity_default_algorithm', 'sha256', 'integrity', NOW(), NOW()),
('integrity_default_batch_size', '200', 'integrity', NOW(), NOW()),
('integrity_default_max_runtime', '120', 'integrity', NOW(), NOW()),
('integrity_default_max_memory', '512', 'integrity', NOW(), NOW()),
('integrity_dead_letter_threshold', '3', 'integrity', NOW(), NOW()),
('integrity_io_throttle_ms', '10', 'integrity', NOW(), NOW()),
('integrity_auto_baseline', 'true', 'integrity', NOW(), NOW()),
('integrity_notify_on_failure', 'true', 'integrity', NOW(), NOW()),
('integrity_notify_on_mismatch', 'true', 'integrity', NOW(), NOW()),
('integrity_alert_email', '', 'integrity', NOW(), NOW()),
('integrity_webhook_url', '', 'integrity', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
