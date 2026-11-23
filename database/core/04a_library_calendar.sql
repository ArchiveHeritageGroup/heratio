-- =============================================================================
-- 04a_library_calendar.sql - the circulation calendar axis (#1475 item 1)
-- =============================================================================
-- Mirrors .../ahg-library/database/migrations/2026_11_22_100000_create_library_calendar_tables.php
-- because a fresh install and the CI test database load database/core/0*.sql
-- and never run package migrations. Every statement is idempotent.
--
-- branch_id = 0 means "all branches", the same sentinel library_loan_rule uses.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `library_opening_hours` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL DEFAULT '0' COMMENT 'repository.id, or 0 = all branches',
  `weekday` tinyint unsigned NOT NULL COMMENT 'ISO-8601: 1 = Monday, 7 = Sunday',
  `is_open` tinyint(1) NOT NULL DEFAULT '1',
  `opens_at` time DEFAULT NULL,
  `closes_at` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_library_hours_branch_day` (`branch_id`,`weekday`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `library_closure` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL DEFAULT '0' COMMENT 'repository.id, or 0 = all branches',
  `closure_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ahg_dropdown library_closure_type',
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `recurs_annually` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_library_closure_branch` (`branch_id`,`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`,`taxonomy_label`,`taxonomy_section`,`code`,`label`,`sort_order`,`is_default`,`is_active`) VALUES
('library_closure_type','Library Closure Type','library','public_holiday','Public holiday',10,1,1),
('library_closure_type','Library Closure Type','library','scheduled','Scheduled closure',20,0,1),
('library_closure_type','Library Closure Type','library','stocktake','Stocktake',30,0,1),
('library_closure_type','Library Closure Type','library','emergency','Emergency closure',40,0,1);
