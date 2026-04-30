-- ============================================================================
-- ahg-favorites — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgFavoritesPlugin/database/install.sql
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
-- ahgFavoritesPlugin - Database Schema
-- ============================================================
-- Version: 2.0.0
-- Author: The Archive and Heritage Group
-- ============================================================

-- Favorites table (user bookmarks for archival descriptions)
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(50) DEFAULT NULL,
  `archival_description_id` VARCHAR(50) DEFAULT NULL,
  `archival_description` VARCHAR(1024) DEFAULT NULL,
  `slug` VARCHAR(1024) DEFAULT NULL,
  `notes` TEXT,
  `object_type` VARCHAR(50) DEFAULT 'information_object',
  `reference_code` VARCHAR(255) DEFAULT NULL,
  `folder_id` INT DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `last_viewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_description` (`archival_description_id`),
  INDEX `idx_folder` (`folder_id`),
  UNIQUE KEY `unique_user_item` (`user_id`, `archival_description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favorites folder table (organise bookmarks into folders)
CREATE TABLE IF NOT EXISTS `favorites_folder` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(7) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT NULL,
  `visibility` VARCHAR(35) DEFAULT 'private' COMMENT 'private, shared, public',
  `sort_order` INT DEFAULT 0,
  `parent_id` INT DEFAULT NULL,
  `share_token` VARCHAR(64) DEFAULT NULL,
  `share_expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_parent` (`parent_id`),
  INDEX `idx_share_token` (`share_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Favorites share table (access tracking for shared folders)
CREATE TABLE IF NOT EXISTS `favorites_share` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `folder_id` INT NOT NULL,
  `shared_with_user_id` INT DEFAULT NULL,
  `shared_via` VARCHAR(31) DEFAULT 'link' COMMENT 'link, email, direct',
  `token` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `accessed_at` DATETIME DEFAULT NULL,
  `access_count` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_folder` (`folder_id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_shared_with` (`shared_with_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
