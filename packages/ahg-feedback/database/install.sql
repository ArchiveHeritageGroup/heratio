-- ============================================================================
-- ahg-feedback — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgFeedbackPlugin/database/install.sql
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

-- ============================================================
-- ahgFeedbackPlugin - Database Schema
-- ============================================================
-- Version: 1.0.0
-- Author: The Archive and Heritage Group
-- ============================================================

-- Feedback table (links to object table)
CREATE TABLE IF NOT EXISTS `feedback` (
  `id` INT NOT NULL,
  `feed_name` VARCHAR(50) DEFAULT NULL,
  `feed_surname` VARCHAR(50) DEFAULT NULL,
  `feed_phone` VARCHAR(50) DEFAULT NULL,
  `feed_email` VARCHAR(50) DEFAULT NULL,
  `feed_relationship` TEXT,
  `parent_id` VARCHAR(50) DEFAULT NULL,
  `feed_type_id` INT DEFAULT NULL,
  `lft` INT NOT NULL DEFAULT 0,
  `rgt` INT NOT NULL DEFAULT 1,
  `source_culture` VARCHAR(14) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  INDEX `idx_feed_type` (`feed_type_id`),
  INDEX `idx_parent` (`parent_id`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feedback i18n table (translatable fields)
CREATE TABLE IF NOT EXISTS `feedback_i18n` (
  `id` INT NOT NULL,
  `culture` VARCHAR(14) NOT NULL DEFAULT 'en',
  `name` VARCHAR(1024) DEFAULT NULL,
  `unique_identifier` VARCHAR(1024) DEFAULT NULL,
  `remarks` TEXT,
  `object_id` TEXT,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status_id` INT NOT NULL DEFAULT 1030,
  `status` VARCHAR(50) DEFAULT 'pending' COMMENT 'pending, completed',
  PRIMARY KEY (`id`, `culture`),
  INDEX `idx_status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
