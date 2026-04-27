-- ahgRecordsManagePlugin — install schema
--
-- Idempotent. Safe to re-run on existing installs (CREATE TABLE IF NOT EXISTS).
-- Creates the 11 tables backing Heratio's Records Management module:
--   - 6 tables that drive Phase 2.1-2.3 + 2.5 (retention schedules, disposal classes, file plan, disposal workflow)
--   - 5 tables for Phase 2.4, 2.6, 2.8, 4.2 (review schedules, email capture, compliance reporting, classification rules)
--
-- ISO 15489, MoReq2010, DoD 5015.2 alignment is enforced at the service layer;
-- this file is the data foundation only.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later

-- =============================================================================
-- P2.1 — Retention schedules + disposal classes
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_retention_schedule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `schedule_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `authority` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jurisdiction` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `version` int unsigned NOT NULL DEFAULT '1',
  `previous_version_id` bigint unsigned DEFAULT NULL,
  `naz_schedule_id` bigint unsigned DEFAULT NULL,
  `approved_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_schedule_ref` (`schedule_ref`),
  KEY `idx_rs_status` (`status`),
  KEY `idx_rs_review` (`review_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rm_disposal_class` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `retention_schedule_id` bigint unsigned NOT NULL,
  `class_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `retention_period_years` int DEFAULT NULL,
  `retention_period_months` int DEFAULT NULL,
  `retention_trigger` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'creation_date',
  `disposal_action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `disposal_confirmation_required` tinyint(1) NOT NULL DEFAULT '1',
  `review_required` tinyint(1) NOT NULL DEFAULT '1',
  `citation` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_class_ref` (`retention_schedule_id`,`class_ref`),
  KEY `idx_dc_schedule` (`retention_schedule_id`),
  KEY `idx_dc_action` (`disposal_action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rm_record_disposal_class` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `disposal_class_id` bigint unsigned NOT NULL,
  `assigned_by` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `retention_start_date` date DEFAULT NULL,
  `calculated_disposal_date` date DEFAULT NULL,
  `override_disposal_date` date DEFAULT NULL,
  `override_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rdc_io` (`information_object_id`),
  KEY `idx_rdc_class` (`disposal_class_id`),
  KEY `idx_rdc_disposal` (`calculated_disposal_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P2.2-P2.3 — Disposal workflow + execution
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_disposal_action` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `disposal_class_id` bigint unsigned DEFAULT NULL,
  `workflow_task_id` int DEFAULT NULL,
  `action_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `initiated_by` int NOT NULL,
  `initiated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `recommended_by` int DEFAULT NULL,
  `recommended_at` datetime DEFAULT NULL,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `legal_cleared` tinyint(1) NOT NULL DEFAULT '0',
  `legal_cleared_by` int DEFAULT NULL,
  `legal_cleared_at` datetime DEFAULT NULL,
  `executed_by` int DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `transfer_destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `certificate_id` bigint unsigned DEFAULT NULL,
  `verification_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `verified_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `checks` json DEFAULT NULL,
  `failures` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_da_io` (`information_object_id`),
  KEY `idx_da_status` (`status`),
  KEY `idx_da_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P2.4 — Review triggers + scheduled review queue (NEW)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_review_schedule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int NOT NULL,
  `disposal_class_id` bigint unsigned DEFAULT NULL,
  `review_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'periodic',
  `review_due_date` date NOT NULL,
  `review_completed_date` date DEFAULT NULL,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `assigned_to` int DEFAULT NULL,
  `decision` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `next_review_due_date` date DEFAULT NULL,
  `triggered_disposal_action_id` bigint unsigned DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rv_io` (`information_object_id`),
  KEY `idx_rv_due` (`review_due_date`,`status`),
  KEY `idx_rv_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P2.5 — File plan (nested set)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_fileplan_node` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint unsigned DEFAULT NULL,
  `function_object_id` int DEFAULT NULL,
  `node_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'series',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `disposal_class_id` bigint unsigned DEFAULT NULL,
  `retention_period` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disposal_action` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `source_department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_agency_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_session_id` bigint unsigned DEFAULT NULL,
  `lft` int DEFAULT NULL,
  `rgt` int DEFAULT NULL,
  `depth` int NOT NULL DEFAULT '0',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fp_parent` (`parent_id`),
  KEY `idx_fp_code` (`code`),
  KEY `idx_fp_nested` (`lft`,`rgt`),
  KEY `idx_fp_dept` (`source_department`(100)),
  KEY `idx_fp_import` (`import_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rm_fileplan_import_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agency_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_nodes` int unsigned NOT NULL DEFAULT '0',
  `imported_nodes` int unsigned NOT NULL DEFAULT '0',
  `linked_records` int unsigned NOT NULL DEFAULT '0',
  `errors_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `column_mapping_json` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `imported_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fis_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P2.6 — Email capture (NEW)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_email_capture` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `message_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_addresses` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `cc_addresses` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `body_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body_html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `attachment_count` int unsigned NOT NULL DEFAULT '0',
  `eml_storage_path` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `information_object_id` int DEFAULT NULL,
  `fileplan_node_id` bigint unsigned DEFAULT NULL,
  `disposal_class_id` bigint unsigned DEFAULT NULL,
  `capture_source` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'imap',
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'captured',
  `captured_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_message_id` (`message_id`),
  KEY `idx_ec_io` (`information_object_id`),
  KEY `idx_ec_node` (`fileplan_node_id`),
  KEY `idx_ec_from` (`from_address`),
  KEY `idx_ec_sent` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P2.8 — Compliance assessment (NEW)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_compliance_assessment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `framework` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `assessment_ref` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `score_total` decimal(5,2) DEFAULT NULL,
  `score_max` decimal(5,2) DEFAULT NULL,
  `findings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendations_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `assessed_by` int NOT NULL,
  `assessed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `signed_off_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_off_at` datetime DEFAULT NULL,
  `report_pdf_path` varchar(2048) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_assessment_ref` (`assessment_ref`),
  KEY `idx_ca_framework` (`framework`),
  KEY `idx_ca_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- P4.2 — Auto-classification rules + log (NEW)
-- (Lives in rm_ namespace because RM owns the file plan that classification targets.)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `rm_classification_rule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rule_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `match_pattern` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fileplan_node_id` bigint unsigned NOT NULL,
  `disposal_class_id` bigint unsigned DEFAULT NULL,
  `priority` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `apply_on` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'declare',
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cr_type` (`rule_type`),
  KEY `idx_cr_node` (`fileplan_node_id`),
  KEY `idx_cr_active` (`is_active`,`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rm_classification_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_id` bigint unsigned DEFAULT NULL,
  `information_object_id` int DEFAULT NULL,
  `rule_id` bigint unsigned NOT NULL,
  `fileplan_node_id` bigint unsigned NOT NULL,
  `match_detail` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `classified_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cl_doc` (`document_id`),
  KEY `idx_cl_io` (`information_object_id`),
  KEY `idx_cl_rule` (`rule_id`),
  KEY `idx_cl_node` (`fileplan_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
