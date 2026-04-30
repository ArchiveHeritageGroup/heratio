-- ============================================================================
-- ahg-request-publish — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgRequestToPublishPlugin/database/install.sql
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
-- ahgRequestToPublishPlugin - Database Schema
-- ============================================================
-- Version: 1.0.0
-- Author: The Archive and Heritage Group
-- ============================================================

-- Request to Publish table (links to object table)
CREATE TABLE IF NOT EXISTS `request_to_publish` (
  `id` INT NOT NULL,
  `parent_id` VARCHAR(50) DEFAULT NULL,
  `rtp_type_id` INT DEFAULT NULL,
  `lft` INT NOT NULL DEFAULT 0,
  `rgt` INT NOT NULL DEFAULT 1,
  `source_culture` VARCHAR(14) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`),
  INDEX `idx_rtp_type` (`rtp_type_id`),
  INDEX `idx_parent` (`parent_id`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Request to Publish i18n table (translatable fields)
CREATE TABLE IF NOT EXISTS `request_to_publish_i18n` (
  `id` INT NOT NULL,
  `culture` VARCHAR(14) NOT NULL DEFAULT 'en',
  `unique_identifier` VARCHAR(1024) DEFAULT NULL,
  `rtp_name` VARCHAR(50) DEFAULT NULL,
  `rtp_surname` VARCHAR(50) DEFAULT NULL,
  `rtp_phone` VARCHAR(50) DEFAULT NULL,
  `rtp_email` VARCHAR(50) DEFAULT NULL,
  `rtp_institution` VARCHAR(200) DEFAULT NULL,
  `rtp_motivation` TEXT,
  `rtp_planned_use` TEXT,
  `rtp_need_image_by` DATETIME DEFAULT NULL,
  `rtp_admin_notes` TEXT,
  `object_id` VARCHAR(50) DEFAULT NULL,
  `status_id` INT NOT NULL DEFAULT 220,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`, `culture`),
  INDEX `idx_status` (`status_id`),
  INDEX `idx_object` (`object_id`(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
