-- ============================================================================
-- ahg-scan — Install SQL
--
-- Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
-- Licensed under the GNU Affero General Public License v3.
--
-- Idempotent: uses CREATE TABLE IF NOT EXISTS and adds columns only when missing.
-- Run: mysql -u root heratio < packages/ahg-scan/database/install.sql
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. Streaming-mode columns on ingest_session (P1)
-- ---------------------------------------------------------------------------

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_session' AND COLUMN_NAME = 'session_kind');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_session ADD COLUMN session_kind VARCHAR(32) NOT NULL DEFAULT ''wizard'' AFTER entity_type, ADD KEY ix_session_kind (session_kind)',
    'SELECT ''ingest_session.session_kind exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_session' AND COLUMN_NAME = 'auto_commit');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_session ADD COLUMN auto_commit TINYINT(1) NOT NULL DEFAULT 0 AFTER session_kind',
    'SELECT ''ingest_session.auto_commit exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_session' AND COLUMN_NAME = 'source_ref');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_session ADD COLUMN source_ref VARCHAR(255) NULL AFTER auto_commit',
    'SELECT ''ingest_session.source_ref exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. Per-file state columns on ingest_file (P1)
-- ---------------------------------------------------------------------------

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'status');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT ''pending'' AFTER extracted_path, ADD KEY ix_ingest_file_status (status)',
    'SELECT ''ingest_file.status exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'stage');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN stage VARCHAR(32) NULL AFTER status',
    'SELECT ''ingest_file.stage exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'source_hash');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN source_hash CHAR(64) NULL AFTER stage, ADD KEY ix_ingest_file_hash (source_hash)',
    'SELECT ''ingest_file.source_hash exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'error_message');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN error_message TEXT NULL AFTER source_hash',
    'SELECT ''ingest_file.error_message exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'attempts');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER error_message',
    'SELECT ''ingest_file.attempts exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'resolved_io_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN resolved_io_id INT NULL AFTER attempts, ADD KEY ix_ingest_file_io (resolved_io_id)',
    'SELECT ''ingest_file.resolved_io_id exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'resolved_do_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN resolved_do_id INT NULL AFTER resolved_io_id',
    'SELECT ''ingest_file.resolved_do_id exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ingest_file' AND COLUMN_NAME = 'completed_at');
SET @sql := IF(@col = 0,
    'ALTER TABLE ingest_file ADD COLUMN completed_at DATETIME NULL AFTER resolved_do_id',
    'SELECT ''ingest_file.completed_at exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 3. scan_folder: watched-folder configuration
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `scan_folder` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `label` VARCHAR(255) NOT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `layout` VARCHAR(32) NOT NULL DEFAULT 'path',
  `ingest_session_id` INT NOT NULL,
  `disposition_success` VARCHAR(32) NOT NULL DEFAULT 'move',
  `disposition_failure` VARCHAR(32) NOT NULL DEFAULT 'quarantine',
  `min_quiet_seconds` INT NOT NULL DEFAULT 10,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_scanned_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scan_folder_code` (`code`),
  KEY `ix_scan_folder_enabled` (`enabled`),
  KEY `ix_scan_folder_session` (`ingest_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
