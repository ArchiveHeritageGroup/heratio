-- ============================================================================
-- ahg-reports — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgReportBuilderPlugin/database/install.sql
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
-- ahgReportBuilderPlugin - Database Schema & Seed Data
-- Enterprise Report Builder v2.0
-- Rich text (Quill.js), Word/PDF/XLSX/CSV export, sections,
-- templates, workflow, SQL queries, sharing, scheduling
-- Issue 148: Report Builder Enhancements
-- ============================================================
-- 12 tables: custom_report, report_archive, report_attachment,
--   report_comment, report_definition, report_link, report_query,
--   report_schedule, report_section, report_share, report_template,
--   report_version
-- ============================================================

-- ============================================================
-- TABLE 1: custom_report
-- User-created reports with layout, filters, charts, workflow
-- ============================================================
CREATE TABLE IF NOT EXISTS `custom_report` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `user_id` INT DEFAULT NULL,
    `is_shared` TINYINT(1) DEFAULT 0,
    `is_public` TINYINT(1) DEFAULT 0,
    `layout` JSON NOT NULL,
    `data_source` VARCHAR(100) NOT NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `sort_order` INT DEFAULT 100,
    `columns` JSON NOT NULL,
    `filters` JSON DEFAULT NULL,
    `charts` JSON DEFAULT NULL,
    `sort_config` JSON DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    `status` VARCHAR(59) DEFAULT 'draft' COMMENT 'draft, in_review, approved, published, archived',
    `template_id` BIGINT UNSIGNED DEFAULT NULL,
    `data_mode` VARCHAR(26) DEFAULT 'live' COMMENT 'live, snapshot',
    `snapshot_data` JSON DEFAULT NULL,
    `snapshot_at` DATETIME DEFAULT NULL,
    `cover_config` JSON DEFAULT NULL,
    `version` INT DEFAULT 1,
    `workflow_id` BIGINT UNSIGNED DEFAULT NULL,
    INDEX `idx_custom_report_user` (`user_id`),
    INDEX `idx_custom_report_shared` (`is_shared`),
    INDEX `idx_custom_report_public` (`is_public`),
    INDEX `idx_custom_report_status` (`status`),
    INDEX `idx_custom_report_template` (`template_id`),
    INDEX `idx_custom_report_category` (`category`),
    INDEX `idx_custom_report_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 2: report_schedule
-- Scheduled report generation (recurring or trigger-based)
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_schedule` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `custom_report_id` BIGINT UNSIGNED NOT NULL,
    `frequency` VARCHAR(45) NOT NULL COMMENT 'daily, weekly, monthly, quarterly',
    `day_of_week` TINYINT DEFAULT NULL,
    `day_of_month` TINYINT DEFAULT NULL,
    `time_of_day` TIME DEFAULT '08:00:00',
    `output_format` VARCHAR(26) DEFAULT 'pdf' COMMENT 'pdf, xlsx, csv',
    `email_recipients` TEXT DEFAULT NULL,
    `last_run` DATETIME DEFAULT NULL,
    `next_run` DATETIME DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    `schedule_type` VARCHAR(30) DEFAULT 'recurring' COMMENT 'recurring, trigger',
    `trigger_config` JSON DEFAULT NULL,
    FOREIGN KEY (`custom_report_id`) REFERENCES `custom_report`(`id`) ON DELETE CASCADE,
    INDEX `idx_report_schedule_report` (`custom_report_id`),
    INDEX `idx_report_schedule_next_run` (`next_run`),
    INDEX `idx_report_schedule_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 3: report_archive
-- Generated report files (PDF/XLSX/CSV) with download tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_archive` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `custom_report_id` BIGINT UNSIGNED DEFAULT NULL,
    `schedule_id` BIGINT UNSIGNED DEFAULT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_format` VARCHAR(10) NOT NULL,
    `file_size` INT UNSIGNED DEFAULT NULL,
    `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `generated_by` INT DEFAULT NULL,
    `parameters` JSON DEFAULT NULL,
    `download_token` VARCHAR(64) DEFAULT NULL,
    `download_count` INT DEFAULT 0,
    FOREIGN KEY (`custom_report_id`) REFERENCES `custom_report`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`schedule_id`) REFERENCES `report_schedule`(`id`) ON DELETE SET NULL,
    INDEX `idx_report_archive_report` (`custom_report_id`),
    INDEX `idx_report_archive_schedule` (`schedule_id`),
    INDEX `idx_report_archive_generated` (`generated_at`),
    INDEX `idx_report_archive_download_token` (`download_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 4: report_attachment
-- Media and document attachments for report sections
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_attachment` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED DEFAULT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(1024) NOT NULL,
    `file_type` VARCHAR(100) DEFAULT NULL,
    `file_size` BIGINT UNSIGNED DEFAULT 0,
    `thumbnail_path` VARCHAR(1024) DEFAULT NULL,
    `digital_object_id` INT DEFAULT NULL,
    `caption` TEXT DEFAULT NULL,
    `position` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report_attachment_report` (`report_id`),
    INDEX `idx_report_attachment_section` (`section_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 5: report_comment
-- Reviewer annotations and comments per report/section
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_comment` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED DEFAULT NULL,
    `user_id` INT NOT NULL,
    `content` TEXT NOT NULL,
    `is_resolved` TINYINT(1) DEFAULT 0,
    `resolved_by` INT DEFAULT NULL,
    `resolved_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_comment_report` (`report_id`),
    INDEX `idx_report_comment_section` (`section_id`),
    INDEX `idx_report_comment_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 6: report_definition
-- Standard report definitions (system-level report catalog)
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_definition` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(101) NOT NULL COMMENT 'collection, acquisition, access, preservation, researcher, compliance, statistics, custom',
    `sector` SET('archive','library','museum','dam','researcher') DEFAULT NULL,
    `report_class` VARCHAR(255) DEFAULT NULL,
    `parameters` JSON DEFAULT NULL,
    `output_formats` SET('html','pdf','csv','xlsx','json') DEFAULT 'html,csv',
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_definition_category` (`category`),
    INDEX `idx_report_definition_active` (`is_active`),
    INDEX `idx_report_definition_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 7: report_link
-- External URLs and internal cross-references in reports
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_link` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `section_id` BIGINT UNSIGNED DEFAULT NULL,
    `link_type` VARCHAR(86) NOT NULL COMMENT 'external, information_object, actor, repository, accession, digital_object',
    `url` VARCHAR(2048) DEFAULT NULL,
    `title` VARCHAR(500) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `target_id` INT DEFAULT NULL,
    `target_slug` VARCHAR(255) DEFAULT NULL,
    `link_category` VARCHAR(100) DEFAULT 'reference',
    `og_image` VARCHAR(2048) DEFAULT NULL,
    `position` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report_link_report` (`report_id`),
    INDEX `idx_report_link_section` (`section_id`),
    INDEX `idx_report_link_type` (`link_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 8: report_query
-- Saved SQL queries for raw SQL mode and visual query builder
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_query` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED DEFAULT NULL,
    `section_id` BIGINT UNSIGNED DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `query_text` TEXT NOT NULL,
    `query_type` VARCHAR(27) DEFAULT 'visual' COMMENT 'visual, raw_sql',
    `visual_config` JSON DEFAULT NULL,
    `parameters` JSON DEFAULT NULL,
    `row_limit` INT DEFAULT 1000,
    `timeout_seconds` INT DEFAULT 30,
    `created_by` INT NOT NULL,
    `is_shared` TINYINT(1) DEFAULT 0,
    `last_executed_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_query_report` (`report_id`),
    INDEX `idx_report_query_section` (`section_id`),
    INDEX `idx_report_query_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 9: report_section
-- Drag-drop ordered content blocks within a report
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_section` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `section_type` VARCHAR(82) NOT NULL COMMENT 'narrative, table, chart, summary_card, image_gallery, links, sql_query',
    `title` VARCHAR(255) DEFAULT NULL,
    `content` LONGTEXT DEFAULT NULL,
    `position` INT DEFAULT 0,
    `config` JSON DEFAULT NULL,
    `clearance_level` INT DEFAULT 0,
    `is_visible` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_section_report` (`report_id`),
    INDEX `idx_report_section_position` (`report_id`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 10: report_share
-- Public sharing with expiry tokens and access tracking
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_share` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `share_token` VARCHAR(64) NOT NULL UNIQUE,
    `shared_by` INT NOT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `access_count` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `email_recipients` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report_share_report` (`report_id`),
    INDEX `idx_report_share_token` (`share_token`),
    INDEX `idx_report_share_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 11: report_template
-- Reusable report structures (system, institution, or user scope)
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_template` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `category` VARCHAR(100) DEFAULT 'custom',
    `scope` VARCHAR(37) DEFAULT 'user' COMMENT 'system, institution, user',
    `structure` JSON NOT NULL,
    `created_by` INT DEFAULT NULL,
    `repository_id` INT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_template_category` (`category`),
    INDEX `idx_report_template_scope` (`scope`),
    INDEX `idx_report_template_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 12: report_version
-- Version history snapshots for reports
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_version` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `report_id` BIGINT UNSIGNED NOT NULL,
    `version_number` INT NOT NULL,
    `snapshot` JSON NOT NULL,
    `change_summary` VARCHAR(500) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_report_version_report` (`report_id`),
    INDEX `idx_report_version_number` (`report_id`, `version_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA: report_definition (32 standard report definitions)
-- ============================================================

-- Category: collection (5 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('collection_overview', 'Collection Overview', 'Summary of holdings by level, repository, and date range', 'collection', 'archive,library,museum,dam', 'reportCollectionOverview', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "level_of_description": "Level"}', 'html,pdf,csv', 1, 10);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('collection_growth', 'Collection Growth', 'Track collection growth over time by accessions and new descriptions', 'collection', 'archive,library,museum,dam', 'reportCollectionGrowth', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "period": "Grouping period (month/quarter/year)"}', 'html,pdf,csv,xlsx', 1, 20);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('collection_completeness', 'Collection Completeness', 'Analyse metadata completeness across descriptions by required fields', 'collection', 'archive,library,museum,dam', 'reportCollectionCompleteness', '{"repository_id": "Repository", "level_of_description": "Level", "standard": "Descriptive standard"}', 'html,pdf,csv', 1, 30);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('digital_objects', 'Digital Objects Report', 'Inventory of digital objects by format, size, and MIME type', 'collection', 'archive,library,museum,dam', 'reportDigitalObjects', '{"repository_id": "Repository", "mime_type": "MIME type filter", "date_from": "Start date", "date_to": "End date"}', 'html,pdf,csv,xlsx', 1, 40);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('collection_by_creator', 'Collection by Creator', 'Holdings grouped by creator or contributor', 'collection', 'archive,library,museum,dam', 'reportCollectionByCreator', '{"repository_id": "Repository", "actor_type": "Creator type"}', 'html,pdf,csv', 1, 50);

-- Category: acquisition (4 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('accessions_register', 'Accessions Register', 'Complete register of all accessions with dates, donors, and descriptions', 'acquisition', 'archive,library,museum,dam', 'reportAccessionsRegister', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "acquisition_type": "Acquisition type"}', 'html,pdf,csv,xlsx', 1, 60);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('accessions_by_donor', 'Accessions by Donor', 'Accessions grouped by donor with totals and date ranges', 'acquisition', 'archive,library,museum,dam', 'reportAccessionsByDonor', '{"date_from": "Start date", "date_to": "End date", "donor_id": "Donor"}', 'html,pdf,csv', 1, 70);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('deaccessions', 'Deaccessions Report', 'Register of deaccessioned materials with reasons and authorisation', 'acquisition', 'archive,museum', 'reportDeaccessions', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository"}', 'html,pdf,csv', 1, 80);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('acquisitions_value', 'Acquisitions Value Report', 'Financial summary of acquisitions including valuations and insurance', 'acquisition', 'archive,library,museum,dam', 'reportAcquisitionsValue', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "currency": "Currency"}', 'html,pdf,xlsx', 1, 85);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('donor_report', 'Donor Report', 'Donor directory with contact details, agreements, and donation history', 'acquisition', 'archive,library,museum,dam', 'reportDonor', '{"repository_id": "Repository", "status": "Agreement status"}', 'html,pdf,csv', 1, 90);

-- Category: access (4 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('access_statistics', 'Access Statistics', 'Record access and page view statistics over time', 'access', 'archive,library,museum,dam', 'reportAccessStatistics', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository"}', 'html,pdf,csv', 1, 100);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('popular_records', 'Popular Records', 'Most viewed and accessed records ranked by frequency', 'access', 'archive,library,museum,dam', 'reportPopularRecords', '{"date_from": "Start date", "date_to": "End date", "limit": "Number of results", "repository_id": "Repository"}', 'html,pdf,csv', 1, 110);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('downloads_report', 'Downloads Report', 'Digital object download statistics by format, user, and period', 'access', 'archive,library,museum,dam', 'reportDownloads', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository"}', 'html,pdf,csv,xlsx', 1, 120);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('search_analytics', 'Search Analytics', 'Search query analysis including top terms, zero-result queries, and trends', 'access', 'archive,library,museum,dam', 'reportSearchAnalytics', '{"date_from": "Start date", "date_to": "End date"}', 'html,pdf,csv', 1, 130);

-- Category: researcher (4 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('researcher_registrations', 'Researcher Registrations', 'New and active researcher registrations with institution and topic details', 'researcher', 'archive,library,researcher', 'reportResearcherRegistrations', '{"date_from": "Start date", "date_to": "End date", "status": "Registration status"}', 'html,pdf,csv', 1, 140);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('researcher_activity', 'Researcher Activity', 'Researcher visits, material requests, and reproduction orders', 'researcher', 'archive,library,researcher', 'reportResearcherActivity', '{"date_from": "Start date", "date_to": "End date", "researcher_id": "Researcher"}', 'html,pdf,csv,xlsx', 1, 150);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('reading_room_usage', 'Reading Room Usage', 'Reading room booking and attendance statistics', 'researcher', 'archive,library,researcher', 'reportReadingRoomUsage', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository"}', 'html,pdf,csv', 1, 160);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('material_requests', 'Material Requests', 'Material request log with status, turnaround times, and fulfilment rates', 'researcher', 'archive,library,researcher', 'reportMaterialRequests', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "status": "Request status"}', 'html,pdf,csv', 1, 170);

-- Category: preservation (4 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('condition_overview', 'Condition Overview', 'Summary of collection condition assessments by rating and material type', 'preservation', 'archive,library,museum,dam', 'reportConditionOverview', '{"repository_id": "Repository", "condition_rating": "Condition rating", "material_type": "Material type"}', 'html,pdf,csv', 1, 180);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('conservation_actions', 'Conservation Actions', 'Conservation and restoration actions performed with costs and outcomes', 'preservation', 'archive,library,museum', 'reportConservationActions', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "action_type": "Action type"}', 'html,pdf,csv,xlsx', 1, 190);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('preservation_priorities', 'Preservation Priorities', 'At-risk items prioritised by condition, significance, and use frequency', 'preservation', 'archive,library,museum,dam', 'reportPreservationPriorities', '{"repository_id": "Repository", "risk_level": "Risk level"}', 'html,pdf,csv', 1, 200);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('format_inventory', 'Format Inventory', 'Inventory of file formats with PRONOM identifiers and preservation risk', 'preservation', 'archive,library,museum,dam', 'reportFormatInventory', '{"repository_id": "Repository"}', 'html,pdf,csv,xlsx', 1, 210);

-- Category: compliance (5 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('audit_trail', 'Audit Trail Report', 'System audit trail with user actions, timestamps, and affected records', 'compliance', 'archive,library,museum,dam', 'reportAuditTrail', '{"date_from": "Start date", "date_to": "End date", "user_id": "User", "action_type": "Action type"}', 'html,pdf,csv,xlsx', 1, 220);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('rights_expiry', 'Rights Expiry Report', 'Upcoming rights expirations, embargo lifts, and licence renewals', 'compliance', 'archive,library,museum,dam', 'reportRightsExpiry', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "rights_type": "Rights type"}', 'html,pdf,csv', 1, 230);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('paia_requests', 'PAIA Requests Report', 'PAIA/POPI access requests with response times and outcomes', 'compliance', 'archive,library,museum,dam', 'reportPaiaRequests', '{"date_from": "Start date", "date_to": "End date", "status": "Request status", "jurisdiction": "Jurisdiction"}', 'html,pdf,csv', 1, 240);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('retention_schedule', 'Retention Schedule Report', 'Records due for retention review, transfer, or disposal', 'compliance', 'archive,library', 'reportRetentionSchedule', '{"date_from": "Start date", "date_to": "End date", "repository_id": "Repository", "disposition": "Disposition action"}', 'html,pdf,csv', 1, 250);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('security_clearance', 'Security Clearance Report', 'Security classification distribution and clearance assignments', 'compliance', 'archive,library,museum,dam', 'reportSecurityClearance', '{"repository_id": "Repository", "clearance_level": "Clearance level"}', 'html,pdf,csv', 1, 260);

-- Category: statistics (6 reports)
INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('user_activity', 'User Activity Report', 'User login frequency, session duration, and action breakdown', 'statistics', 'archive,library,museum,dam', 'reportUserActivity', '{"date_from": "Start date", "date_to": "End date", "user_id": "User", "role": "User role"}', 'html,pdf,csv,xlsx', 1, 270);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('system_updates', 'System Updates Report', 'Plugin installations, updates, configuration changes, and system events', 'statistics', 'archive,library,museum,dam', 'reportSystemUpdates', '{"date_from": "Start date", "date_to": "End date"}', 'html,pdf,csv', 1, 280);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('repository_stats', 'Repository Statistics', 'Per-repository statistics including holdings, users, and activity', 'statistics', 'archive,library,museum,dam', 'reportRepositoryStats', '{"repository_id": "Repository"}', 'html,pdf,csv,xlsx', 1, 290);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('taxonomy_usage', 'Taxonomy Usage Report', 'Term and taxonomy usage frequency across descriptions', 'statistics', 'archive,library,museum,dam', 'reportTaxonomyUsage', '{"taxonomy_id": "Taxonomy", "min_usage": "Minimum usage count"}', 'html,pdf,csv', 1, 300);

INSERT IGNORE INTO `report_definition` (`code`, `name`, `description`, `category`, `sector`, `report_class`, `parameters`, `output_formats`, `is_active`, `sort_order`) VALUES
('storage_locations', 'Storage Locations Report', 'Physical storage locations with occupancy, capacity, and linked records', 'statistics', 'archive,library,museum', 'reportStorageLocations', '{"repository_id": "Repository", "building": "Building", "floor": "Floor"}', 'html,pdf,csv,xlsx', 1, 310);

-- ============================================================
-- SEED DATA: report_template (4 system templates)
-- ============================================================

INSERT IGNORE INTO `report_template` (`name`, `description`, `category`, `scope`, `structure`, `is_active`) VALUES
('NARSSA Annual Report', 'Standard annual report template aligned with NARSSA reporting requirements for South African archives', 'narssa', 'system', '{}', 1);

INSERT IGNORE INTO `report_template` (`name`, `description`, `category`, `scope`, `structure`, `is_active`) VALUES
('GRAP 103 Heritage Asset Report', 'Heritage asset valuation and disclosure report template per GRAP 103 / IPSAS 45 standards', 'grap103', 'system', '{}', 1);

INSERT IGNORE INTO `report_template` (`name`, `description`, `category`, `scope`, `structure`, `is_active`) VALUES
('Accession Summary Report', 'Standard accession summary template with donor details, acquisition method, and material description', 'accession', 'system', '{}', 1);

INSERT IGNORE INTO `report_template` (`name`, `description`, `category`, `scope`, `structure`, `is_active`) VALUES
('Condition Assessment Report', 'Condition assessment template with rating scales, photographs, conservation recommendations', 'condition', 'system', '{}', 1);

SET FOREIGN_KEY_CHECKS = 1;
