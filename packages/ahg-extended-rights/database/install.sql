-- ============================================================================
-- ahg-extended-rights — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/database/install.sql
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
-- ahgExtendedRightsPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `extended_rights`
--



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


--
-- Table structure for table `extended_rights_batch_log`
--



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


--
-- Table structure for table `extended_rights_i18n`
--



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


--
-- Table structure for table `extended_rights_tk_label`
--



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


--
-- Table structure for table `embargo`
--



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


--
-- Table structure for table `embargo_audit`
--



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


--
-- Table structure for table `embargo_exception`
--



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


--
-- Table structure for table `embargo_i18n`
--



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












-- Creative Commons License tables
-- NOTE: These tables are defined in ahgRightsPlugin/data/install.sql
-- Do not duplicate here - ensure ahgRightsPlugin is installed first

-- ============================================================
-- Retention schedule + disposal workflow (2026-05-17 sync from
-- ahgExtendedRightsPlugin migration). Records-management framework
-- support: File-Plan-driven retention with multi-stage disposal
-- workflow (records officer -> legal -> executive). Suitable for
-- any national archival framework (NARSSA, NARA, PRO Act, ISO 15489).
-- Operators replace seed schedules with their organisation File Plan.
-- ============================================================

CREATE TABLE IF NOT EXISTS `retention_schedule` (
    `id`                         INT NOT NULL AUTO_INCREMENT,
    `code`                       VARCHAR(50)  NOT NULL COMMENT 'Operator-friendly identifier from the organisation File Plan',
    `title`                      VARCHAR(255) NOT NULL,
    `description`                TEXT NULL,
    `active_period_years`        INT NOT NULL DEFAULT 5  COMMENT 'Years the record is operationally active',
    `dormant_period_years`       INT NOT NULL DEFAULT 0  COMMENT 'Years held after active period before disposal trigger',
    `trigger_event`              VARCHAR(50)  NOT NULL DEFAULT 'creation_date' COMMENT 'creation_date, file_closure, fiscal_year_end, contract_end, employment_end',
    `disposal_action`            VARCHAR(20)  NOT NULL DEFAULT 'review' COMMENT 'destroy, transfer_narssa, transfer_other, review, permanent',
    `legal_basis`                VARCHAR(255) NULL COMMENT 'Statutory authority for the schedule',
    `requires_legal_signoff`     TINYINT(1) NOT NULL DEFAULT 0,
    `requires_executive_signoff` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active`                  TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_code` (`code`),
    KEY `idx_disposal_action` (`disposal_action`),
    KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `retention_assignment` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id`    INT NOT NULL,
    `retention_schedule_id`    INT NOT NULL,
    `trigger_event_date`       DATE NOT NULL COMMENT 'When the retention clock starts',
    `calculated_disposal_due`  DATE NOT NULL COMMENT 'trigger_event_date + active + dormant',
    `assigned_by`              INT NULL,
    `notes`                    TEXT NULL,
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_io` (`information_object_id`),
    KEY `idx_schedule` (`retention_schedule_id`),
    KEY `idx_due` (`calculated_disposal_due`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `disposal_action` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id`    INT NOT NULL,
    `retention_assignment_id`  BIGINT UNSIGNED NULL,
    `action_type`              VARCHAR(20) NOT NULL COMMENT 'destroy, transfer_narssa, transfer_other, review, defer',
    `status`                   VARCHAR(30) NOT NULL DEFAULT 'proposed' COMMENT 'proposed, officer_signed, legal_signed, executive_signed, approved, executed, rejected, deferred',
    `proposed_by`              INT NULL,
    `proposed_at`              DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `officer_signed_by`        INT NULL,
    `officer_signed_at`        DATETIME NULL,
    `legal_signed_by`          INT NULL,
    `legal_signed_at`          DATETIME NULL,
    `executive_signed_by`      INT NULL,
    `executive_signed_at`      DATETIME NULL,
    `executed_by`              INT NULL,
    `executed_at`              DATETIME NULL,
    `rejected_by`              INT NULL,
    `rejected_at`              DATETIME NULL,
    `rejection_reason`         TEXT NULL,
    `transfer_destination`     VARCHAR(255) NULL COMMENT 'Archive identifier for transfer_* actions',
    `transfer_manifest_path`   VARCHAR(500) NULL COMMENT 'Path to generated transfer .tar.gz when applicable',
    `notes`                    TEXT NULL,
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_io` (`information_object_id`),
    KEY `idx_assignment` (`retention_assignment_id`),
    KEY `idx_status` (`status`),
    KEY `idx_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic seed schedules (operators replace with their File Plan).
-- Codes are intentionally generic (no jurisdiction prefix) so the
-- defaults work in any market. Legal-basis values reference example
-- frameworks; rewrite for the deployment's regulatory environment.
INSERT IGNORE INTO `retention_schedule`
  (`code`, `title`, `description`, `active_period_years`, `dormant_period_years`, `trigger_event`, `disposal_action`, `legal_basis`, `requires_legal_signoff`, `requires_executive_signoff`, `is_active`)
VALUES
  ('COMM-001', 'Press releases (general)',         'Routine media releases',                   2,  3, 'creation_date',   'destroy',         'See operator File Plan',   0, 0, 1),
  ('COMM-002', 'Cabinet/Board briefings',          'High-impact briefings, permanent transfer', 5, 25, 'creation_date',   'transfer_narssa', 'See operator File Plan',   1, 1, 1),
  ('CORP-001', 'Annual reports',                   'Annual reports - permanent retention',     5,  0, 'creation_date',   'permanent',       'See operator File Plan',   0, 1, 1),
  ('CORP-002', 'Procurement records',              'Procurement audit trail',                  5,  7, 'fiscal_year_end', 'destroy',         'See operator File Plan',   1, 0, 1),
  ('HR-001',   'Employee personnel files',         'Employee records',                         7, 30, 'employment_end',  'destroy',         'See operator File Plan',   1, 0, 1),
  ('LEG-001',  'Legal opinions / counsel records', 'Legal advice retained long-term',          5, 20, 'creation_date',   'review',          'See operator File Plan',   1, 1, 1);

SET FOREIGN_KEY_CHECKS = 1;
