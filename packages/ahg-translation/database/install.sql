-- ============================================================================
-- ahg-translation — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgTranslationPlugin/database/install.sql
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

-- ahgTranslationPlugin tables
CREATE TABLE IF NOT EXISTS `ahg_translation_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(128) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ahg_translation_draft` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` BIGINT UNSIGNED NOT NULL,
  `entity_type` VARCHAR(64) NOT NULL DEFAULT 'information_object',
  `field_name` VARCHAR(64) NOT NULL,
  `source_culture` VARCHAR(8) NOT NULL,
  `target_culture` VARCHAR(8) NOT NULL DEFAULT 'en',
  `source_hash` CHAR(64) NOT NULL,
  `source_text` LONGTEXT NOT NULL,
  `translated_text` LONGTEXT NOT NULL,
  `status` VARCHAR(36) NOT NULL DEFAULT 'draft' COMMENT 'draft, applied, rejected',
  `created_by_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `applied_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object_field` (`object_id`, `field_name`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `uk_draft_dedupe` (`object_id`, `field_name`, `source_culture`, `target_culture`, `source_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ahg_translation_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `object_id` BIGINT UNSIGNED NULL,
  `field_name` VARCHAR(64) NULL,
  `source_culture` VARCHAR(8) NULL,
  `target_culture` VARCHAR(8) NULL,
  `endpoint` VARCHAR(255) NULL,
  `http_status` INT NULL,
  `ok` TINYINT(1) NOT NULL DEFAULT 0,
  `error` TEXT NULL,
  `elapsed_ms` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_ok` (`ok`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default settings (safe insert)
INSERT IGNORE INTO `ahg_translation_settings` (`setting_key`, `setting_value`)
  SELECT 'mt.endpoint', 'http://127.0.0.1:5100/translate'
  WHERE NOT EXISTS (SELECT 1 FROM `ahg_translation_settings` WHERE `setting_key`='mt.endpoint');

INSERT IGNORE INTO `ahg_translation_settings` (`setting_key`, `setting_value`)
  SELECT 'mt.timeout_seconds', '30'
  WHERE NOT EXISTS (SELECT 1 FROM `ahg_translation_settings` WHERE `setting_key`='mt.timeout_seconds');

INSERT IGNORE INTO `ahg_translation_settings` (`setting_key`, `setting_value`)
  SELECT 'mt.target_culture', 'en'
  WHERE NOT EXISTS (SELECT 1 FROM `ahg_translation_settings` WHERE `setting_key`='mt.target_culture');

-- ui_string_change — pending/approved/rejected workflow for /admin/translation/strings
-- (issue #54 second-pass — admin auto-approves, editor goes to queue, admin
-- can opt-in to second-review). Also serves as the audit log: every change
-- (immediate-approve OR queued-then-approved) writes a row.
CREATE TABLE IF NOT EXISTS `ui_string_change` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `locale` VARCHAR(16) NOT NULL,
  `key_text` TEXT NOT NULL,
  `old_value` LONGTEXT,
  `new_value` LONGTEXT,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `submitted_by_user_id` INT NOT NULL,
  `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by_user_id` INT,
  `reviewed_at` DATETIME,
  `review_note` TEXT,
  PRIMARY KEY (`id`),
  KEY `idx_status_submitted` (`status`, `submitted_at`),
  KEY `idx_locale_status` (`locale`, `status`),
  KEY `idx_submitter` (`submitted_by_user_id`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
