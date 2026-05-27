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

-- Favorites table (user bookmarks for archival descriptions + custom entities)
--
-- object_type values come from ahg_dropdown taxonomy 'favorites_object_type':
--   information_object, actor, repository, accession, donor, rights_holder,
--   term, function, physical_object, research_journal, research_collection,
--   research_project, custom.
--
-- The `url` column is populated for non-information_object favorites that
-- live outside the /{slug} catch-all (e.g. /research/projects/123).
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` VARCHAR(50) DEFAULT NULL,
  `archival_description_id` VARCHAR(50) DEFAULT NULL,
  `archival_description` VARCHAR(1024) DEFAULT NULL,
  `slug` VARCHAR(1024) DEFAULT NULL,
  `url` VARCHAR(1024) DEFAULT NULL,
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
  INDEX `idx_object_type` (`object_type`),
  UNIQUE KEY `unique_user_item` (`user_id`, `archival_description_id`, `object_type`)
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

-- ============================================================
-- Dropdown seeds — favourites taxonomies
-- ============================================================
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('favorites_object_type', 'Favorites Object Type', 'information_object', 'Archival Description', '#0d6efd', 'fa-file-alt', 10, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'actor', 'Actor', '#6610f2', 'fa-user', 20, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'repository', 'Repository', '#6f42c1', 'fa-building', 30, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'accession', 'Accession', '#d63384', 'fa-box', 40, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'donor', 'Donor', '#dc3545', 'fa-hand-holding-heart', 50, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'rights_holder', 'Rights Holder', '#fd7e14', 'fa-balance-scale', 60, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'term', 'Term', '#ffc107', 'fa-tag', 70, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'function', 'Function', '#198754', 'fa-cog', 80, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'physical_object', 'Physical Object', '#20c997', 'fa-cube', 90, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'research_journal', 'Research Journal', '#0dcaf0', 'fa-book', 100, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'research_collection', 'Research Collection', '#6c757d', 'fa-folder', 110, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'research_project', 'Research Project', '#343a40', 'fa-project-diagram', 120, 1, 'favorites'),
('favorites_object_type', 'Favorites Object Type', 'custom', 'Custom Link', '#6c757d', 'fa-link', 130, 1, 'favorites');

INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('favorites_visibility', 'Folder Visibility', 'private', 'Private', '#6c757d', 'fa-lock', 10, 1, 'favorites'),
('favorites_visibility', 'Folder Visibility', 'shared', 'Shared (Token Link)', '#0dcaf0', 'fa-share-alt', 20, 1, 'favorites'),
('favorites_visibility', 'Folder Visibility', 'public', 'Public', '#198754', 'fa-globe', 30, 1, 'favorites');

INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `code`, `label`, `color`, `icon`, `sort_order`, `is_active`, `taxonomy_section`) VALUES
('favorites_export_format', 'Favorites Export Format', 'csv', 'CSV (Spreadsheet)', '#198754', 'fa-file-csv', 10, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'json', 'JSON', '#ffc107', 'fa-file-code', 20, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'pdf', 'PDF (Printable)', '#dc3545', 'fa-file-pdf', 30, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'bibtex', 'BibTeX (Reference Manager)', '#6610f2', 'fa-quote-right', 40, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'ris', 'RIS (Reference Manager)', '#0d6efd', 'fa-quote-left', 50, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'print', 'Print View (HTML)', '#6c757d', 'fa-print', 60, 1, 'favorites'),
('favorites_export_format', 'Favorites Export Format', 'ead', 'EAD XML', '#fd7e14', 'fa-file-code', 70, 1, 'favorites');
