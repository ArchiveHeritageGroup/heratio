-- ============================================================================
-- ahg-rights — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgRightsPlugin/database/install.sql
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
-- ahgRightsPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `rights`
--



CREATE TABLE IF NOT EXISTS `rights` (
  `id` int NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `basis_id` int DEFAULT NULL,
  `rights_holder_id` int DEFAULT NULL,
  `copyright_status_id` int DEFAULT NULL,
  `copyright_status_date` date DEFAULT NULL,
  `copyright_jurisdiction` varchar(1024) DEFAULT NULL,
  `statute_determination_date` date DEFAULT NULL,
  `statute_citation_id` int DEFAULT NULL,
  `source_culture` varchar(16) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `rights_FI_2` (`basis_id`),
  KEY `rights_FI_3` (`rights_holder_id`),
  KEY `rights_FI_4` (`copyright_status_id`),
  KEY `rights_FI_5` (`statute_citation_id`),
  CONSTRAINT `rights_FK_1` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rights_FK_2` FOREIGN KEY (`basis_id`) REFERENCES `term` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rights_FK_3` FOREIGN KEY (`rights_holder_id`) REFERENCES `actor` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rights_FK_4` FOREIGN KEY (`copyright_status_id`) REFERENCES `term` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rights_FK_5` FOREIGN KEY (`statute_citation_id`) REFERENCES `term` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `rights_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_i18n` (
  `rights_note` text,
  `copyright_note` text,
  `identifier_value` text,
  `identifier_type` text,
  `identifier_role` text,
  `license_terms` text,
  `license_note` text,
  `statute_jurisdiction` text,
  `statute_note` text,
  `id` int NOT NULL,
  `culture` varchar(16) NOT NULL,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `rights_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `rights` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `rights_cc_license`
--



CREATE TABLE IF NOT EXISTS `rights_cc_license` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '4.0',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `allows_commercial` tinyint(1) DEFAULT '1',
  `allows_derivatives` tinyint(1) DEFAULT '1',
  `requires_share_alike` tinyint(1) DEFAULT '0',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `badge_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_cc_license_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_cc_license_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `human_readable` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_cc_license_i18n` FOREIGN KEY (`id`) REFERENCES `rights_cc_license` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Seed data for `rights_cc_license` (Creative Commons licenses)
--

INSERT IGNORE INTO `rights_cc_license` (`id`, `code`, `version`, `uri`, `allows_commercial`, `allows_derivatives`, `requires_share_alike`, `requires_attribution`, `icon`, `badge_url`, `sort_order`, `is_active`) VALUES
(1, 'CC0-1.0', '1.0', 'https://creativecommons.org/publicdomain/zero/1.0/', 1, 1, 0, 0, 'cc-zero.png', 'https://licensebuttons.net/l/zero/1.0/88x31.png', 1, 1),
(2, 'CC-BY-4.0', '4.0', 'https://creativecommons.org/licenses/by/4.0/', 1, 1, 0, 1, 'cc-by.png', 'https://licensebuttons.net/l/by/4.0/88x31.png', 2, 1),
(3, 'CC-BY-SA-4.0', '4.0', 'https://creativecommons.org/licenses/by-sa/4.0/', 1, 1, 1, 1, 'cc-by-sa.png', 'https://licensebuttons.net/l/by-sa/4.0/88x31.png', 3, 1),
(4, 'CC-BY-NC-4.0', '4.0', 'https://creativecommons.org/licenses/by-nc/4.0/', 0, 1, 0, 1, 'cc-by-nc.png', 'https://licensebuttons.net/l/by-nc/4.0/88x31.png', 4, 1),
(5, 'CC-BY-NC-SA-4.0', '4.0', 'https://creativecommons.org/licenses/by-nc-sa/4.0/', 0, 1, 1, 1, 'cc-by-nc-sa.png', 'https://licensebuttons.net/l/by-nc-sa/4.0/88x31.png', 5, 1),
(6, 'CC-BY-ND-4.0', '4.0', 'https://creativecommons.org/licenses/by-nd/4.0/', 1, 0, 0, 1, 'cc-by-nd.png', 'https://licensebuttons.net/l/by-nd/4.0/88x31.png', 6, 1),
(7, 'CC-BY-NC-ND-4.0', '4.0', 'https://creativecommons.org/licenses/by-nc-nd/4.0/', 0, 0, 0, 1, 'cc-by-nc-nd.png', 'https://licensebuttons.net/l/by-nc-nd/4.0/88x31.png', 7, 1),
(8, 'PDM-1.0', '1.0', 'https://creativecommons.org/publicdomain/mark/1.0/', 1, 1, 0, 0, 'publicdomain.png', NULL, 8, 1);

INSERT IGNORE INTO `rights_cc_license_i18n` (`id`, `culture`, `name`, `description`) VALUES
(1, 'en', 'CC0 1.0 Universal (Public Domain)', 'No rights reserved.'),
(2, 'en', 'Attribution 4.0 International', 'Credit must be given to the creator.'),
(3, 'en', 'Attribution-ShareAlike 4.0 International', 'Credit must be given. Derivatives shared under same license.'),
(4, 'en', 'Attribution-NonCommercial 4.0 International', 'Credit required. Only noncommercial uses permitted.'),
(5, 'en', 'Attribution-NonCommercial-ShareAlike 4.0', 'Credit required. Noncommercial. Same license for derivatives.'),
(6, 'en', 'Attribution-NoDerivatives 4.0 International', 'Credit required. No derivatives or adaptations permitted.'),
(7, 'en', 'Attribution-NonCommercial-NoDerivatives 4.0', 'Credit required. Noncommercial. No derivatives allowed.'),
(8, 'en', 'Public Domain Mark 1.0', 'Free of known copyright restrictions.');

--
-- Table structure for table `rights_derivative_log`
--



CREATE TABLE IF NOT EXISTS `rights_derivative_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `rule_id` int DEFAULT NULL,
  `derivative_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `derivative_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_by` int DEFAULT NULL,
  `request_purpose` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_rule` (`rule_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_derivative_rule`
--



CREATE TABLE IF NOT EXISTS `rights_derivative_rule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL COMMENT 'NULL = applies to collection or global',
  `collection_id` int DEFAULT NULL COMMENT 'NULL = applies to object or global',
  `is_global` tinyint(1) DEFAULT '0',
  `rule_type` VARCHAR(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'watermark, redaction, resize, format_conversion, metadata_strip',
  `priority` int DEFAULT '0',
  `applies_to_roles` json DEFAULT NULL COMMENT 'Array of role IDs, NULL = all',
  `applies_to_clearance_levels` json DEFAULT NULL COMMENT 'Array of clearance level codes',
  `applies_to_purposes` json DEFAULT NULL COMMENT 'Array of purpose codes',
  `watermark_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `watermark_position` VARCHAR(72) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'bottom_right' COMMENT 'center, top_left, top_right, bottom_left, bottom_right, tile',
  `watermark_opacity` int DEFAULT '50' COMMENT '0-100',
  `redaction_areas` json DEFAULT NULL COMMENT 'Array of {x, y, width, height, page}',
  `redaction_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#000000',
  `max_width` int DEFAULT NULL,
  `max_height` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_rule_type` (`rule_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_embargo`
--



CREATE TABLE IF NOT EXISTS `rights_embargo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `embargo_type` VARCHAR(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT 'full, metadata_only, digital_only, partial',
  `reason` VARCHAR(105) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'donor_restriction, copyright, privacy, legal, commercial, research, cultural, security, other',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'NULL = indefinite',
  `auto_release` tinyint(1) DEFAULT '1' COMMENT 'Auto-lift on end_date',
  `review_date` date DEFAULT NULL,
  `review_interval_months` int DEFAULT '12',
  `last_reviewed_at` datetime DEFAULT NULL,
  `last_reviewed_by` int DEFAULT NULL,
  `status` VARCHAR(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, pending, lifted, expired, extended',
  `lifted_at` datetime DEFAULT NULL,
  `lifted_by` int DEFAULT NULL,
  `lift_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notify_before_days` int DEFAULT '30',
  `notification_sent` tinyint(1) DEFAULT '0',
  `notify_emails` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of emails',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_end_date` (`end_date`),
  KEY `idx_review_date` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_embargo_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_embargo_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `reason_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `internal_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_embargo_i18n` FOREIGN KEY (`id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_embargo_log`
--



CREATE TABLE IF NOT EXISTS `rights_embargo_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `embargo_id` int NOT NULL,
  `action` VARCHAR(81) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created, extended, lifted, reviewed, notification_sent, auto_released',
  `old_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_end_date` date DEFAULT NULL,
  `new_end_date` date DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_embargo` (`embargo_id`),
  CONSTRAINT `fk_embargo_log` FOREIGN KEY (`embargo_id`) REFERENCES `rights_embargo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_grant`
--



CREATE TABLE IF NOT EXISTS `rights_grant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `act` VARCHAR(119) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'render, disseminate, replicate, migrate, modify, delete, print, use, publish, excerpt, annotate, move, sell',
  `restriction` VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'allow' COMMENT 'allow, disallow, conditional',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `condition_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_act` (`act`),
  KEY `idx_restriction` (`restriction`),
  CONSTRAINT `fk_rights_grant_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_grant_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_grant_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_grant_i18n` FOREIGN KEY (`id`) REFERENCES `rights_grant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_holder`
--



CREATE TABLE IF NOT EXISTS `rights_holder` (
  `id` int NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `rights_holder_FK_1` FOREIGN KEY (`id`) REFERENCES `actor` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `rights_object_tk_label`
--



CREATE TABLE IF NOT EXISTS `rights_object_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `tk_label_id` int NOT NULL,
  `community_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `community_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `custom_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `verified` tinyint(1) DEFAULT '0',
  `verified_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verified_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_object_label` (`object_id`,`tk_label_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_label` (`tk_label_id`),
  CONSTRAINT `fk_object_tk_label` FOREIGN KEY (`tk_label_id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_orphan_search_step`
--



CREATE TABLE IF NOT EXISTS `rights_orphan_search_step` (
  `id` int NOT NULL AUTO_INCREMENT,
  `orphan_work_id` int NOT NULL,
  `source_type` VARCHAR(103) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'database, registry, publisher, author_society, archive, library, internet, newspaper, other',
  `source_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `search_date` date NOT NULL,
  `search_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `results_found` tinyint(1) DEFAULT '0',
  `results_description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evidence_file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `screenshot_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_orphan_work` (`orphan_work_id`),
  CONSTRAINT `fk_orphan_search_step` FOREIGN KEY (`orphan_work_id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_orphan_work`
--



CREATE TABLE IF NOT EXISTS `rights_orphan_work` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `status` VARCHAR(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'in_progress' COMMENT 'in_progress, completed, rights_holder_found, abandoned',
  `work_type` VARCHAR(127) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'literary, dramatic, musical, artistic, film, sound_recording, broadcast, typographical, database, photograph, other',
  `search_started_date` date DEFAULT NULL,
  `search_completed_date` date DEFAULT NULL,
  `search_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA',
  `rights_holder_found` tinyint(1) DEFAULT '0',
  `rights_holder_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rights_holder_contact` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `contact_attempted` tinyint(1) DEFAULT '0',
  `contact_date` date DEFAULT NULL,
  `contact_response` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `intended_use` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `proposed_fee` decimal(10,2) DEFAULT NULL,
  `fee_held_in_escrow` tinyint(1) DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_orphan_work_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_orphan_work_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `search_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_orphan_work_i18n` FOREIGN KEY (`id`) REFERENCES `rights_orphan_work` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_record`
--



CREATE TABLE IF NOT EXISTS `rights_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL COMMENT 'FK to information_object.id',
  `basis` VARCHAR(61) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'copyright' COMMENT 'copyright, license, statute, donor, policy, other',
  `rights_statement_id` int DEFAULT NULL,
  `cc_license_id` int DEFAULT NULL,
  `copyright_status` VARCHAR(47) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'unknown' COMMENT 'copyrighted, public_domain, unknown',
  `copyright_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `copyright_holder_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `copyright_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZA' COMMENT 'ISO 3166-1 alpha-2',
  `copyright_determination_date` date DEFAULT NULL,
  `copyright_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_terms` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `license_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `statute_citation` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_jurisdiction` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statute_determination_date` date DEFAULT NULL,
  `statute_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `donor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_actor_id` int DEFAULT NULL COMMENT 'FK to actor.id',
  `donor_agreement_date` date DEFAULT NULL,
  `donor_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `policy_identifier` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `policy_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `documentation_identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `documentation_role` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL COMMENT 'FK to user.id',
  `updated_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_basis` (`basis`),
  KEY `idx_status` (`copyright_status`),
  KEY `fk_rights_statement` (`rights_statement_id`),
  KEY `fk_rights_cc_license` (`cc_license_id`),
  CONSTRAINT `fk_rights_cc_license` FOREIGN KEY (`cc_license_id`) REFERENCES `rights_cc_license` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_record_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_record_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `rights_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `restriction_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_record_i18n` FOREIGN KEY (`id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_statement`
--



CREATE TABLE IF NOT EXISTS `rights_statement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` VARCHAR(45) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'in-copyright, no-copyright, other',
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rights_statement_uri` (`uri`),
  UNIQUE KEY `uq_rights_statement_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_statement_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_statement_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rights_statement_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `definition` text COLLATE utf8mb4_unicode_ci,
  `scope_note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rs_i18n` (`rights_statement_id`,`culture`),
  KEY `idx_rs_i18n_parent` (`rights_statement_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_territory`
--



CREATE TABLE IF NOT EXISTS `rights_territory` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rights_record_id` int NOT NULL,
  `territory_type` VARCHAR(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'include' COMMENT 'include, exclude',
  `country_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ISO 3166-1 alpha-2 or region code',
  `is_gdpr_territory` tinyint(1) DEFAULT '0',
  `gdpr_legal_basis` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rights_record` (`rights_record_id`),
  KEY `idx_country` (`country_code`),
  CONSTRAINT `fk_rights_territory_record` FOREIGN KEY (`rights_record_id`) REFERENCES `rights_record` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_tk_label`
--



CREATE TABLE IF NOT EXISTS `rights_tk_label` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` VARCHAR(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tk' COMMENT 'tk, bc, attribution',
  `uri` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Hex color code',
  `icon_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_category` (`category`),
  KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `rights_tk_label_i18n`
--



CREATE TABLE IF NOT EXISTS `rights_tk_label_i18n` (
  `id` int NOT NULL,
  `culture` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `usage_protocol` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_rights_tk_label_i18n` FOREIGN KEY (`id`) REFERENCES `rights_tk_label` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `creative_commons_license`
--



CREATE TABLE IF NOT EXISTS `creative_commons_license` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uri` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '4.0',
  `allows_adaptation` tinyint(1) DEFAULT '1',
  `allows_commercial` tinyint(1) DEFAULT '1',
  `requires_attribution` tinyint(1) DEFAULT '1',
  `requires_sharealike` tinyint(1) DEFAULT '0',
  `icon_filename` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_uri` (`uri`),
  UNIQUE KEY `uq_cc_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `creative_commons_license_i18n`
--



CREATE TABLE IF NOT EXISTS `creative_commons_license_i18n` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `creative_commons_license_id` bigint unsigned NOT NULL,
  `culture` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cc_i18n` (`creative_commons_license_id`,`culture`),
  KEY `idx_cc_i18n_parent` (`creative_commons_license_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;












-- Seed Data
--












--
-- Dumping data for table `rights_statement`
--

LOCK TABLES `rights_statement` WRITE;

INSERT IGNORE INTO `rights_statement` VALUES (1,'http://rightsstatements.org/vocab/InC/1.0/','InC','in-copyright','InC.png',1,1,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (2,'http://rightsstatements.org/vocab/InC-OW-EU/1.0/','InC-OW-EU','in-copyright','InC-OW-EU.png',1,2,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (3,'http://rightsstatements.org/vocab/InC-EDU/1.0/','InC-EDU','in-copyright','InC-EDU.png',1,3,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (4,'http://rightsstatements.org/vocab/InC-NC/1.0/','InC-NC','in-copyright','InC-NC.png',1,4,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (5,'http://rightsstatements.org/vocab/InC-RUU/1.0/','InC-RUU','in-copyright','InC-RUU.png',1,5,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (6,'http://rightsstatements.org/vocab/NoC-CR/1.0/','NoC-CR','no-copyright','NoC-CR.png',1,10,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (7,'http://rightsstatements.org/vocab/NoC-NC/1.0/','NoC-NC','no-copyright','NoC-NC.png',1,11,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (8,'http://rightsstatements.org/vocab/NoC-OKLR/1.0/','NoC-OKLR','no-copyright','NoC-OKLR.png',1,12,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (9,'http://rightsstatements.org/vocab/NoC-US/1.0/','NoC-US','no-copyright','NoC-US.png',1,13,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (10,'http://rightsstatements.org/vocab/CNE/1.0/','CNE','other','CNE.png',1,20,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (11,'http://rightsstatements.org/vocab/UND/1.0/','UND','other','UND.png',1,21,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);
INSERT IGNORE INTO `rights_statement` VALUES (12,'http://rightsstatements.org/vocab/NKC/1.0/','NKC','other','NKC.png',1,22,'2025-12-16 15:58:48','2025-12-16 16:10:45',NULL);

UNLOCK TABLES;

--
-- Dumping data for table `rights_statement_i18n`
--

LOCK TABLES `rights_statement_i18n` WRITE;

INSERT IGNORE INTO `rights_statement_i18n` VALUES (1,1,'en','In Copyright','This Item is protected by copyright.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (2,2,'en','In Copyright - EU Orphan Work','Identified as an orphan work in the EU.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (3,3,'en','In Copyright - Educational Use Permitted','Educational use permitted.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (4,4,'en','In Copyright - Non-Commercial Use Permitted','Non-commercial use permitted.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (5,5,'en','In Copyright - Rights-holder(s) Unlocatable','Rights-holder cannot be found.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (6,6,'en','No Copyright - Contractual Restrictions','Contractual restrictions apply.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (7,7,'en','No Copyright - Non-Commercial Use Only','Non-commercial use only.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (8,8,'en','No Copyright - Other Known Legal Restrictions','Other legal restrictions apply.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (9,9,'en','No Copyright - United States','Not protected in the US.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (10,10,'en','Copyright Not Evaluated','Status not evaluated.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (11,11,'en','Copyright Undetermined','Status could not be determined.',NULL);
INSERT IGNORE INTO `rights_statement_i18n` VALUES (12,12,'en','No Known Copyright','No restrictions believed to apply.',NULL);

UNLOCK TABLES;

--
-- Dumping data for table `creative_commons_license`
--

LOCK TABLES `creative_commons_license` WRITE;

INSERT IGNORE INTO `creative_commons_license` VALUES (1,'https://creativecommons.org/publicdomain/zero/1.0/','https://licensebuttons.net/l/zero/1.0/88x31.png','CC0-1.0','1.0',1,1,0,0,'cc-zero.png',1,1,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (2,'https://creativecommons.org/licenses/by/4.0/','https://licensebuttons.net/l/by/4.0/88x31.png','CC-BY-4.0','4.0',1,1,1,0,'cc-by.png',1,2,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (3,'https://creativecommons.org/licenses/by-sa/4.0/','https://licensebuttons.net/l/by-sa/4.0/88x31.png','CC-BY-SA-4.0','4.0',1,1,1,1,'cc-by-sa.png',1,3,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (4,'https://creativecommons.org/licenses/by-nc/4.0/','https://licensebuttons.net/l/by-nc/4.0/88x31.png','CC-BY-NC-4.0','4.0',1,0,1,0,'cc-by-nc.png',1,4,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (5,'https://creativecommons.org/licenses/by-nc-sa/4.0/','https://licensebuttons.net/l/by-nc-sa/4.0/88x31.png','CC-BY-NC-SA-4.0','4.0',1,0,1,1,'cc-by-nc-sa.png',1,5,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (6,'https://creativecommons.org/licenses/by-nd/4.0/','https://licensebuttons.net/l/by-nd/4.0/88x31.png','CC-BY-ND-4.0','4.0',0,1,1,0,'cc-by-nd.png',1,6,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (7,'https://creativecommons.org/licenses/by-nc-nd/4.0/','https://licensebuttons.net/l/by-nc-nd/4.0/88x31.png','CC-BY-NC-ND-4.0','4.0',0,0,1,0,'cc-by-nc-nd.png',1,7,'2025-12-16 15:58:48','2025-12-16 17:10:02');
INSERT IGNORE INTO `creative_commons_license` VALUES (8,'https://creativecommons.org/publicdomain/mark/1.0/',NULL,'PDM-1.0','1.0',1,1,0,0,'publicdomain.png',1,8,'2025-12-16 15:58:48','2025-12-16 16:10:45');

UNLOCK TABLES;

--
-- Dumping data for table `creative_commons_license_i18n`
--

LOCK TABLES `creative_commons_license_i18n` WRITE;

INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (1,1,'en','CC0 1.0 Universal (Public Domain)','No rights reserved.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (2,2,'en','Attribution 4.0 International','Credit required.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (3,3,'en','Attribution-ShareAlike 4.0 International','Credit, share alike.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (4,4,'en','Attribution-NonCommercial 4.0 International','Credit, non-commercial.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (5,5,'en','Attribution-NonCommercial-ShareAlike 4.0','Credit, NC, share alike.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (6,6,'en','Attribution-NoDerivatives 4.0 International','Credit, no derivatives.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (7,7,'en','Attribution-NonCommercial-NoDerivatives 4.0','Most restrictive.');
INSERT IGNORE INTO `creative_commons_license_i18n` VALUES (8,8,'en','Public Domain Mark 1.0','Free of restrictions.');

UNLOCK TABLES;

--
-- Dumping data for table `rights_tk_label`
--

LOCK TABLES `rights_tk_label` WRITE;

INSERT IGNORE INTO `rights_tk_label` VALUES (1,'TK-A','attribution','https://localcontexts.org/label/tk-attribution/','#4A90D9',NULL,1,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (2,'TK-NC','tk','https://localcontexts.org/label/tk-non-commercial/','#7B8D42',NULL,2,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (3,'TK-C','tk','https://localcontexts.org/label/tk-community/','#D35400',NULL,3,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (4,'TK-CV','tk','https://localcontexts.org/label/tk-culturally-sensitive/','#8E44AD',NULL,4,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (5,'TK-SS','tk','https://localcontexts.org/label/tk-secret-sacred/','#C0392B',NULL,5,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (6,'TK-MC','tk','https://localcontexts.org/label/tk-multiple-communities/','#16A085',NULL,6,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (7,'TK-MR','tk','https://localcontexts.org/label/tk-men-restricted/','#2C3E50',NULL,7,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (8,'TK-WR','tk','https://localcontexts.org/label/tk-women-restricted/','#E74C3C',NULL,8,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (9,'TK-SR','tk','https://localcontexts.org/label/tk-seasonal/','#F39C12',NULL,9,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (10,'TK-F','tk','https://localcontexts.org/label/tk-family/','#27AE60',NULL,10,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (11,'TK-O','tk','https://localcontexts.org/label/tk-outreach/','#3498DB',NULL,11,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (12,'TK-V','tk','https://localcontexts.org/label/tk-verified/','#1ABC9C',NULL,12,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (13,'TK-NV','tk','https://localcontexts.org/label/tk-non-verified/','#95A5A6',NULL,13,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (14,'BC-R','bc','https://localcontexts.org/label/bc-research/','#9B59B6',NULL,14,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (15,'BC-CB','bc','https://localcontexts.org/label/bc-consent-before/','#E67E22',NULL,15,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (16,'BC-P','bc','https://localcontexts.org/label/bc-provenance/','#1ABC9C',NULL,16,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (17,'BC-MC','bc','https://localcontexts.org/label/bc-multiple-communities/','#3498DB',NULL,17,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (18,'BC-CL','bc','https://localcontexts.org/label/bc-clan/','#9B59B6',NULL,18,1,'2025-12-11 07:48:41');
INSERT IGNORE INTO `rights_tk_label` VALUES (19,'BC-O','bc','https://localcontexts.org/label/bc-outreach/','#2ECC71',NULL,19,1,'2025-12-11 07:48:41');

UNLOCK TABLES;

--
-- Dumping data for table `rights_tk_label_i18n`
--

LOCK TABLES `rights_tk_label_i18n` WRITE;

INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (1,'en','TK Attribution','Attribution is required. This label asks users to respect traditional citation practices.','Include attribution to the community when using this material.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (2,'en','TK Non-Commercial','This material should only be used for non-commercial purposes.','Do not use this material for commercial gain without community permission.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (3,'en','TK Community Voice','This material should only be used with the consent of the community.','Contact the community before any use.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (4,'en','TK Culturally Sensitive','This material contains culturally sensitive content.','Treat this material with cultural respect and sensitivity.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (5,'en','TK Secret/Sacred','This material contains secret or sacred content with restricted access.','Access is restricted. Do not share without explicit permission.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (6,'en','TK Multiple Communities','Multiple communities have interests in this material.','Consult with all relevant communities before use.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (7,'en','TK Men Restricted','Access to this material is restricted to men only within the community.','Respect gender-specific cultural protocols.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (8,'en','TK Women Restricted','Access to this material is restricted to women only within the community.','Respect gender-specific cultural protocols.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (9,'en','TK Seasonal','Access to this material may be seasonally or ceremonially restricted.','Check with the community about appropriate times for access.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (10,'en','TK Family','This material belongs to a specific family within the community.','Contact the specific family for permissions.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (11,'en','TK Outreach','The community has designated this material for educational outreach.','May be used for educational purposes with attribution.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (12,'en','TK Verified','Community protocols for this material have been verified.','Follow the verified community protocols.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (13,'en','TK Non-Verified','Community protocols for this material have not yet been verified.','Exercise additional caution; protocols may change.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (14,'en','BC Research Use','This material has been collected with consent for research purposes.','Use is limited to approved research activities.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (15,'en','BC Consent Before','Consent was obtained prior to collection of this material.','Original consent terms apply to subsequent use.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (16,'en','BC Provenance','The provenance and history of this material is documented.','Review provenance information before use.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (17,'en','BC Multiple Communities','Multiple communities contributed to this collection.','Acknowledge all contributing communities.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (18,'en','BC Clan','This material relates to a specific clan within the community.','Contact the relevant clan for permissions.');
INSERT IGNORE INTO `rights_tk_label_i18n` VALUES (19,'en','BC Outreach','This material is designated for educational outreach by the community.','May be used for education with community acknowledgment.');

UNLOCK TABLES;











SET FOREIGN_KEY_CHECKS = 1;
