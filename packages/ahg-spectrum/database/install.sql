-- ============================================================================
-- ahg-spectrum — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgSpectrumPlugin/database/install.sql
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
-- ahgSpectrumPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `spectrum_acquisition`
--



CREATE TABLE IF NOT EXISTS `spectrum_acquisition` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `acquisition_number` varchar(50) NOT NULL,
  `acquisition_date` date DEFAULT NULL,
  `acquisition_method` varchar(50) DEFAULT NULL,
  `acquisition_source` varchar(255) DEFAULT NULL,
  `source_contact` text,
  `acquisition_reason` text,
  `acquisition_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `funding_source` varchar(255) DEFAULT NULL,
  `purchase_price` decimal(15,2) DEFAULT NULL,
  `price_currency` varchar(10) DEFAULT NULL,
  `group_purchase_price` decimal(15,2) DEFAULT NULL,
  `accession_date` date DEFAULT NULL,
  `accession_number` varchar(50) DEFAULT NULL,
  `title_transfer_date` date DEFAULT NULL,
  `ownership_history` text,
  `acquisition_note` text,
  `provenance_note` text,
  `legal_title` text,
  `conditions_of_acquisition` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_acquisition_number` (`acquisition_number`),
  KEY `idx_accession_number` (`accession_number`),
  KEY `idx_acquisition_date` (`acquisition_date`),
  KEY `idx_wf_acq` (`workflow_state`),
  CONSTRAINT `spectrum_acquisition_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_approval`
--



CREATE TABLE IF NOT EXISTS `spectrum_approval` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int NOT NULL,
  `approver_id` int NOT NULL,
  `status` VARCHAR(39) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, approved, rejected',
  `comments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`event_id`),
  KEY `idx_approver` (`approver_id`),
  CONSTRAINT `spectrum_approval_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_audit_log`
--



CREATE TABLE IF NOT EXISTS `spectrum_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `procedure_type` varchar(50) NOT NULL,
  `procedure_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `action_date` datetime DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `old_values` text,
  `new_values` text,
  `note` text,
  PRIMARY KEY (`id`),
  KEY `idx_audit_object` (`object_id`),
  KEY `idx_audit_procedure` (`procedure_type`,`procedure_id`),
  KEY `idx_audit_date` (`action_date`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_barcode`
--



CREATE TABLE IF NOT EXISTS `spectrum_barcode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `barcode_type` varchar(20) NOT NULL,
  `barcode_content` varchar(500) NOT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `print_count` int DEFAULT '0',
  `last_printed` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_barcode_object` (`object_id`),
  KEY `idx_barcode_type` (`barcode_type`),
  CONSTRAINT `spectrum_barcode_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_check`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `condition_reference` varchar(50) DEFAULT NULL,
  `check_date` datetime NOT NULL,
  `check_reason` varchar(100) DEFAULT NULL,
  `checked_by` varchar(255) NOT NULL,
  `overall_condition` varchar(50) DEFAULT NULL,
  `condition_note` text,
  `completeness_note` text,
  `hazard_note` text,
  `technical_assessment` text,
  `recommended_treatment` text,
  `treatment_priority` varchar(50) DEFAULT NULL,
  `next_check_date` date DEFAULT NULL,
  `environment_recommendation` text,
  `handling_recommendation` text,
  `display_recommendation` text,
  `storage_recommendation` text,
  `packing_recommendation` text,
  `image_reference` text,
  `photo_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `condition_check_reference` varchar(255) DEFAULT NULL,
  `completeness` varchar(50) DEFAULT NULL,
  `condition_description` text,
  `hazards_noted` text,
  `recommendations` text,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `condition_rating` varchar(50) DEFAULT NULL COMMENT 'Overall condition rating',
  `condition_notes` text COMMENT 'Detailed condition notes',
  `template_id` int DEFAULT NULL,
  `material_type` varchar(50) DEFAULT NULL,
  `template_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_condition_date` (`check_date`),
  KEY `idx_condition_reference` (`condition_reference`),
  KEY `idx_overall_condition` (`overall_condition`),
  KEY `idx_wf_cond` (`workflow_state`),
  KEY `idx_check_date` (`check_date`),
  CONSTRAINT `spectrum_condition_check_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_check_data`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_check_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `template_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_check_field` (`condition_check_id`,`field_id`),
  KEY `template_id` (`template_id`),
  KEY `field_id` (`field_id`),
  KEY `idx_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_check_data_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_3` FOREIGN KEY (`field_id`) REFERENCES `spectrum_condition_template_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_photo`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `photo_type` VARCHAR(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail' COMMENT 'before, after, detail, damage, overall, other',
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_on_object` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `photographer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_date` date DEFAULT NULL,
  `camera_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `annotations` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_condition_check` (`condition_check_id`),
  KEY `idx_photo_type` (`photo_type`),
  KEY `idx_photo_date` (`photo_date`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `spectrum_condition_photo_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_photo_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_condition_photo_comparison`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photo_comparison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `before_photo_id` int NOT NULL,
  `after_photo_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_condition_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_photo_comparison_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_photos`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL COMMENT 'Reference to spectrum_condition_check',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored filename',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original uploaded filename',
  `category` VARCHAR(61) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'overall' COMMENT 'Photo category' COMMENT 'overall, detail, damage, before, after, reference',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Description or notes about the photo',
  `annotations` json DEFAULT NULL COMMENT 'JSON annotations for damage markers',
  `file_size` int DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type of the image',
  `width` int DEFAULT NULL COMMENT 'Image width in pixels',
  `height` int DEFAULT NULL COMMENT 'Image height in pixels',
  `captured_at` datetime DEFAULT NULL COMMENT 'When photo was taken (from EXIF)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who uploaded the photo',
  `updated_at` datetime DEFAULT NULL COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_check` (`condition_check_id`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_condition_template`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_template_field`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` VARCHAR(86) NOT NULL COMMENT 'text, textarea, select, multiselect, checkbox, radio, rating, date, number',
  `options` json DEFAULT NULL COMMENT 'For select/multiselect/radio - array of options',
  `default_value` varchar(255) DEFAULT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `help_text` text,
  `is_required` tinyint(1) DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `spectrum_condition_template_field_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `spectrum_condition_template_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_template_section`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_template` (`template_id`),
  CONSTRAINT `spectrum_condition_template_section_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_conservation`
--



CREATE TABLE IF NOT EXISTS `spectrum_conservation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `conservation_reference` varchar(50) DEFAULT NULL,
  `treatment_date` date NOT NULL,
  `treatment_end_date` date DEFAULT NULL,
  `conservator_name` varchar(255) NOT NULL,
  `conservator_organization` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `treatment_proposal` text,
  `treatment_performed` text,
  `materials_used` text,
  `condition_after` text,
  `treatment_cost` decimal(15,2) DEFAULT NULL,
  `cost_currency` varchar(10) DEFAULT NULL,
  `next_treatment_date` date DEFAULT NULL,
  `treatment_note` text,
  `report_reference` varchar(100) DEFAULT NULL,
  `image_before` text,
  `image_after` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `recommendations` text,
  `conservation_note` text,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_conservation_reference` (`conservation_reference`),
  KEY `idx_treatment_date` (`treatment_date`),
  KEY `idx_wf_cons` (`workflow_state`),
  CONSTRAINT `spectrum_conservation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_conservation_treatment`
--



CREATE TABLE IF NOT EXISTS `spectrum_conservation_treatment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `treatment_reference` varchar(100) DEFAULT NULL,
  `treatment_type` varchar(100) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `conservator` varchar(255) DEFAULT NULL,
  `description` text,
  `materials_used` text,
  `outcome` text,
  `cost` decimal(10,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`treatment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_deaccession`
--



CREATE TABLE IF NOT EXISTS `spectrum_deaccession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `deaccession_number` varchar(50) NOT NULL,
  `deaccession_date` date NOT NULL,
  `proposal_date` date DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `authorized_by` varchar(255) DEFAULT NULL,
  `deaccession_reason` text,
  `disposal_method` varchar(50) DEFAULT NULL,
  `disposal_date` date DEFAULT NULL,
  `disposal_recipient` varchar(255) DEFAULT NULL,
  `disposal_price` decimal(15,2) DEFAULT NULL,
  `disposal_currency` varchar(10) DEFAULT NULL,
  `new_owner` varchar(255) DEFAULT NULL,
  `new_owner_contact` text,
  `legal_requirements_met` tinyint(1) DEFAULT '0',
  `documentation_complete` tinyint(1) DEFAULT '0',
  `deaccession_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'proposed',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_deaccession_number` (`deaccession_number`),
  KEY `idx_deaccession_date` (`deaccession_date`),
  KEY `idx_wf_deacc` (`workflow_state`),
  CONSTRAINT `spectrum_deaccession_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_event`
--



CREATE TABLE IF NOT EXISTS `spectrum_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_from` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_to` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `assigned_to_id` int DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_procedure` (`object_id`,`procedure_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_procedure` (`procedure_id`),
  KEY `idx_created` (`created_at`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status_to`),
  KEY `idx_due_date` (`due_date`),
  CONSTRAINT `spectrum_event_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_loan_agreements`
--



CREATE TABLE IF NOT EXISTS `spectrum_loan_agreements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL COMMENT 'Reference to spectrum_loan_in or spectrum_loan_out',
  `loan_type` VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Type of loan' COMMENT 'in, out',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Generated PDF filename',
  `template` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'standard' COMMENT 'Template used',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who generated the agreement',
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`,`loan_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_loan_document`
--



CREATE TABLE IF NOT EXISTS `spectrum_loan_document` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_type` varchar(20) NOT NULL,
  `loan_id` int NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `generated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `generated_by` int DEFAULT NULL,
  `signed` tinyint(1) DEFAULT '0',
  `signed_at` datetime DEFAULT NULL,
  `signed_by` varchar(255) DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `idx_loan_document` (`loan_type`,`loan_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_loan_in`
--



CREATE TABLE IF NOT EXISTS `spectrum_loan_in` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_in_number` varchar(50) NOT NULL,
  `lender_name` varchar(255) NOT NULL,
  `lender_contact` text,
  `lender_address` text,
  `loan_in_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `insurance_note` text,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `special_requirements` text,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `loan_in_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Lender address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_in_number` (`loan_in_number`),
  KEY `idx_loan_in_status` (`loan_status`),
  KEY `idx_loan_return_date` (`loan_return_date`),
  KEY `idx_wf_lin` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_in_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_loan_out`
--



CREATE TABLE IF NOT EXISTS `spectrum_loan_out` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `loan_out_number` varchar(50) NOT NULL,
  `borrower_name` varchar(255) NOT NULL,
  `borrower_contact` text,
  `borrower_address` text,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_address` text,
  `loan_out_date` date NOT NULL,
  `loan_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_purpose` varchar(100) DEFAULT NULL,
  `loan_conditions` text,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_currency` varchar(10) DEFAULT NULL,
  `insurance_reference` varchar(100) DEFAULT NULL,
  `indemnity_reference` varchar(100) DEFAULT NULL,
  `loan_agreement_date` date DEFAULT NULL,
  `loan_agreement_reference` varchar(100) DEFAULT NULL,
  `exhibition_title` varchar(255) DEFAULT NULL,
  `exhibition_dates` text,
  `special_requirements` text,
  `courier_required` tinyint(1) DEFAULT '0',
  `courier_name` varchar(255) DEFAULT NULL,
  `loan_status` varchar(50) DEFAULT 'active',
  `loan_note` text,
  `agreement_document_id` int DEFAULT NULL,
  `facility_report_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `insurance_policy` varchar(255) DEFAULT NULL,
  `loan_out_note` text,
  `workflow_state` varchar(50) DEFAULT 'requested',
  `loan_number` varchar(50) DEFAULT NULL COMMENT 'Loan reference number',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'Contact person name',
  `contact_email` varchar(255) DEFAULT NULL COMMENT 'Contact email',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT 'Contact phone',
  `address` text COMMENT 'Borrower address',
  `insurance_provider` varchar(255) DEFAULT NULL COMMENT 'Insurance provider',
  `insurance_policy_number` varchar(100) DEFAULT NULL COMMENT 'Policy number',
  `special_conditions` text COMMENT 'Special conditions',
  `handling_requirements` text COMMENT 'Handling requirements',
  `display_requirements` text COMMENT 'Display requirements',
  `environmental_requirements` text COMMENT 'Environmental requirements',
  `object_description` text COMMENT 'Object description for agreement',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_loan_out_number` (`loan_out_number`),
  KEY `idx_loan_out_status` (`loan_status`),
  KEY `idx_borrower` (`borrower_name`),
  KEY `idx_wf_lout` (`workflow_state`),
  KEY `idx_loan_dates` (`loan_start_date`,`loan_end_date`),
  CONSTRAINT `spectrum_loan_out_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_location`
--



CREATE TABLE IF NOT EXISTS `spectrum_location` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `location_type` varchar(50) DEFAULT NULL,
  `location_name` varchar(255) NOT NULL,
  `location_building` varchar(255) DEFAULT NULL,
  `location_floor` varchar(50) DEFAULT NULL,
  `location_room` varchar(100) DEFAULT NULL,
  `location_unit` varchar(100) DEFAULT NULL,
  `location_shelf` varchar(100) DEFAULT NULL,
  `location_box` varchar(100) DEFAULT NULL,
  `location_note` text,
  `fitness_for_purpose` text,
  `security_note` text,
  `environment_note` text,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `location_coordinates` varchar(255) DEFAULT NULL,
  `security_level` varchar(50) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_location_current` (`object_id`,`is_current`),
  KEY `idx_location_name` (`location_name`),
  KEY `idx_wf_loc` (`workflow_state`),
  CONSTRAINT `spectrum_location_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_movement`
--



CREATE TABLE IF NOT EXISTS `spectrum_movement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `movement_reference` varchar(50) DEFAULT NULL,
  `scanned_barcode` varchar(100) DEFAULT NULL,
  `scanned_at` datetime DEFAULT NULL,
  `scanned_by` int DEFAULT NULL,
  `movement_date` datetime NOT NULL,
  `movement_reason` varchar(100) DEFAULT NULL,
  `location_from` int DEFAULT NULL,
  `location_to` int DEFAULT NULL,
  `movement_method` varchar(100) DEFAULT NULL,
  `movement_contact` varchar(255) DEFAULT NULL,
  `handler_name` varchar(255) DEFAULT NULL,
  `condition_before` text,
  `condition_after` text,
  `planned_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `movement_note` text,
  `removal_authorization` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `from_location_id` int DEFAULT NULL,
  `to_location_id` int DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `location_from` (`location_from`),
  KEY `location_to` (`location_to`),
  KEY `idx_movement_date` (`movement_date`),
  KEY `idx_movement_reference` (`movement_reference`),
  KEY `idx_wf_mov` (`workflow_state`),
  CONSTRAINT `spectrum_movement_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_movement_ibfk_2` FOREIGN KEY (`location_from`) REFERENCES `spectrum_location` (`id`),
  CONSTRAINT `spectrum_movement_ibfk_3` FOREIGN KEY (`location_to`) REFERENCES `spectrum_location` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_notification`
--



CREATE TABLE IF NOT EXISTS `spectrum_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_unread` (`user_id`,`read_at`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `spectrum_notification_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `spectrum_event` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_object_entry`
--



CREATE TABLE IF NOT EXISTS `spectrum_object_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `entry_number` varchar(50) NOT NULL,
  `entry_date` date NOT NULL,
  `entry_method` varchar(50) DEFAULT NULL,
  `entry_reason` text,
  `depositor_name` varchar(255) DEFAULT NULL,
  `depositor_contact` text,
  `depositor_address` text,
  `current_owner` varchar(255) DEFAULT NULL,
  `owner_contact` text,
  `return_date` date DEFAULT NULL,
  `entry_note` text,
  `received_by` varchar(255) DEFAULT NULL,
  `packing_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'received',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_entry_number` (`entry_number`),
  KEY `idx_entry_date` (`entry_date`),
  KEY `idx_depositor` (`depositor_name`),
  KEY `idx_wf_entry` (`workflow_state`),
  CONSTRAINT `spectrum_object_entry_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_object_exit`
--



CREATE TABLE IF NOT EXISTS `spectrum_object_exit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `exit_number` varchar(50) NOT NULL,
  `exit_date` date NOT NULL,
  `exit_reason` varchar(50) DEFAULT NULL,
  `exit_destination` varchar(255) DEFAULT NULL,
  `recipient_name` varchar(255) DEFAULT NULL,
  `recipient_contact` text,
  `recipient_address` text,
  `authorization_name` varchar(255) DEFAULT NULL,
  `authorization_date` date DEFAULT NULL,
  `packing_note` text,
  `dispatch_note` text,
  `courier_name` varchar(255) DEFAULT NULL,
  `expected_return_date` date DEFAULT NULL,
  `exit_note` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_exit_number` (`exit_number`),
  KEY `idx_exit_date` (`exit_date`),
  KEY `idx_wf_exit` (`workflow_state`),
  CONSTRAINT `spectrum_object_exit_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_procedure_history`
--



CREATE TABLE IF NOT EXISTS `spectrum_procedure_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `procedure_type` varchar(100) NOT NULL,
  `procedure_id` int DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text,
  `user_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_valuation`
--



CREATE TABLE IF NOT EXISTS `spectrum_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_reference` varchar(50) DEFAULT NULL,
  `valuation_date` date NOT NULL,
  `valuation_type` varchar(50) DEFAULT NULL,
  `valuation_amount` decimal(15,2) NOT NULL,
  `valuation_currency` varchar(10) DEFAULT 'ZAR',
  `valuer_name` varchar(255) DEFAULT NULL,
  `valuer_organization` varchar(255) DEFAULT NULL,
  `valuation_note` text,
  `renewal_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `renewal_cycle_months` int DEFAULT '36' COMMENT 'Months between valuations',
  `valuer` varchar(255) DEFAULT NULL COMMENT 'Name of appraiser/company',
  `currency` varchar(3) DEFAULT 'ZAR' COMMENT 'ISO currency code',
  PRIMARY KEY (`id`),
  KEY `idx_valuation_date` (`valuation_date`),
  KEY `idx_valuation_current` (`object_id`,`is_current`),
  KEY `idx_wf_val` (`workflow_state`),
  KEY `idx_renewal_date` (`renewal_date`),
  KEY `idx_wf_valuation` (`workflow_state`),
  CONSTRAINT `spectrum_valuation_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_valuation_alert`
--



CREATE TABLE IF NOT EXISTS `spectrum_valuation_alert` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_id` int DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `alert_date` date NOT NULL,
  `message` text,
  `is_acknowledged` tinyint(1) DEFAULT '0',
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_alert_date` (`alert_date`),
  KEY `idx_alert_acknowledged` (`is_acknowledged`),
  CONSTRAINT `spectrum_valuation_alert_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_workflow_config`
--



CREATE TABLE IF NOT EXISTS `spectrum_workflow_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `config_json` json NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `version` int DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_type` (`procedure_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_workflow_history`
--



CREATE TABLE IF NOT EXISTS `spectrum_workflow_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `from_state` varchar(50) NOT NULL,
  `to_state` varchar(50) NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `user_id` int DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `note` text,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_procedure_record` (`procedure_type`,`record_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=111 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_workflow_notification`
--



CREATE TABLE IF NOT EXISTS `spectrum_workflow_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `transition_key` varchar(50) NOT NULL,
  `recipient_user_id` int DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `notification_type` varchar(50) DEFAULT 'email',
  `subject` varchar(255) DEFAULT NULL,
  `message` text,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`is_sent`,`created_at`),
  KEY `idx_recipient` (`recipient_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_workflow_state`
--



CREATE TABLE IF NOT EXISTS `spectrum_workflow_state` (
  `id` int NOT NULL AUTO_INCREMENT,
  `procedure_type` varchar(50) NOT NULL,
  `record_id` int NOT NULL,
  `current_state` varchar(50) NOT NULL,
  `assigned_to` int DEFAULT NULL,
  `assigned_at` datetime DEFAULT NULL,
  `assigned_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_record` (`procedure_type`,`record_id`),
  KEY `idx_procedure_state` (`procedure_type`,`current_state`),
  KEY `idx_assigned_to` (`assigned_to`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












-- Seed Data
--












--
-- Dumping data for table `spectrum_workflow_config`
--

LOCK TABLES `spectrum_workflow_config` WRITE;

INSERT IGNORE INTO `spectrum_workflow_config` VALUES (1,'acquisition','Object Acquisition Workflow','{\"steps\": [{\"key\": \"proposal\", \"name\": \"Acquisition Proposal\", \"order\": 1}, {\"key\": \"review\", \"name\": \"Committee Review\", \"order\": 2}, {\"key\": \"approval\", \"name\": \"Formal Approval\", \"order\": 3}, {\"key\": \"accessioning\", \"name\": \"Accessioning\", \"order\": 4}, {\"key\": \"cataloguing\", \"name\": \"Cataloguing\", \"order\": 5}, {\"key\": \"completion\", \"name\": \"Completion\", \"order\": 6}], \"states\": [\"proposed\", \"under_review\", \"approved\", \"accessioned\", \"catalogued\", \"completed\", \"rejected\"], \"transitions\": {\"reject\": {\"to\": \"rejected\", \"from\": [\"under_review\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"under_review\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"catalogued\", \"rejected\"]}, \"accession\": {\"to\": \"accessioned\", \"from\": [\"approved\"]}, \"catalogue\": {\"to\": \"catalogued\", \"from\": [\"accessioned\"]}, \"submit_for_review\": {\"to\": \"under_review\", \"from\": [\"proposed\"]}, \"restart\": {\"to\": \"proposed\", \"from\": [\"completed\", \"rejected\"]}}, \"initial_state\": \"proposed\"}',1,1,NULL,'2025-12-09 13:08:10','2025-12-09 13:08:10');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (5,'cataloguing','Cataloguing Workflow','{\"steps\": [{\"key\": \"identification\", \"name\": \"Object Identification\", \"order\": 1}, {\"key\": \"description\", \"name\": \"Description\", \"order\": 2}, {\"key\": \"measurements\", \"name\": \"Measurements\", \"order\": 3}, {\"key\": \"photography\", \"name\": \"Photography\", \"order\": 4}, {\"key\": \"research\", \"name\": \"Research\", \"order\": 5}, {\"key\": \"review\", \"name\": \"Review\", \"order\": 6}], \"states\": [\"pending\", \"in_progress\", \"review\", \"completed\"], \"transitions\": {\"start\": {\"to\": \"in_progress\", \"from\": [\"pending\"]}, \"revise\": {\"to\": \"in_progress\", \"from\": [\"review\"]}, \"submit\": {\"to\": \"review\", \"from\": [\"in_progress\"]}, \"approve\": {\"to\": \"completed\", \"from\": [\"review\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:08:10','2025-12-09 13:08:10');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (6,'object_entry','Object Entry Workflow','{\"steps\": [{\"name\": \"Receive Object\", \"order\": 1}, {\"name\": \"Document Receipt\", \"order\": 2}, {\"name\": \"Initial Assessment\", \"order\": 3}, {\"name\": \"Process Entry\", \"order\": 4}, {\"name\": \"Complete\", \"order\": 5}], \"states\": [\"pending\", \"received\", \"documented\", \"assessed\", \"processed\", \"completed\"], \"transitions\": {\"assess\": {\"to\": \"assessed\", \"from\": [\"documented\"]}, \"process\": {\"to\": \"processed\", \"from\": [\"assessed\"]}, \"receive\": {\"to\": \"received\", \"from\": [\"pending\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"processed\"]}, \"document\": {\"to\": \"documented\", \"from\": [\"received\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (7,'location_movement','Location & Movement Workflow','{\"steps\": [{\"name\": \"Request Move\", \"order\": 1}, {\"name\": \"Approve\", \"order\": 2}, {\"name\": \"In Transit\", \"order\": 3}, {\"name\": \"Arrival\", \"order\": 4}, {\"name\": \"Verify Location\", \"order\": 5}], \"states\": [\"pending\", \"requested\", \"approved\", \"in_transit\", \"arrived\", \"verified\"], \"transitions\": {\"move\": {\"to\": \"in_transit\", \"from\": [\"approved\"]}, \"arrive\": {\"to\": \"arrived\", \"from\": [\"in_transit\"]}, \"verify\": {\"to\": \"verified\", \"from\": [\"arrived\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"requested\"]}, \"request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"verified\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (8,'inventory_control','Inventory Control Workflow','{\"steps\": [{\"name\": \"Schedule Check\", \"order\": 1}, {\"name\": \"Start Inventory\", \"order\": 2}, {\"name\": \"Count Items\", \"order\": 3}, {\"name\": \"Reconcile\", \"order\": 4}, {\"name\": \"Complete\", \"order\": 5}], \"states\": [\"pending\", \"scheduled\", \"in_progress\", \"counted\", \"reconciled\", \"completed\"], \"transitions\": {\"count\": {\"to\": \"counted\", \"from\": [\"in_progress\"]}, \"start\": {\"to\": \"in_progress\", \"from\": [\"scheduled\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"reconciled\"]}, \"schedule\": {\"to\": \"scheduled\", \"from\": [\"pending\"]}, \"reconcile\": {\"to\": \"reconciled\", \"from\": [\"counted\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (9,'conservation','Conservation Workflow','{\"steps\": [{\"name\": \"Assessment\", \"order\": 1}, {\"name\": \"Treatment Proposal\", \"order\": 2}, {\"name\": \"Approval\", \"order\": 3}, {\"name\": \"Treatment\", \"order\": 4}, {\"name\": \"Complete\", \"order\": 5}], \"states\": [\"pending\", \"assessed\", \"proposed\", \"approved\", \"in_treatment\", \"completed\"], \"transitions\": {\"treat\": {\"to\": \"in_treatment\", \"from\": [\"approved\"]}, \"assess\": {\"to\": \"assessed\", \"from\": [\"pending\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"proposed\"]}, \"propose\": {\"to\": \"proposed\", \"from\": [\"assessed\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"in_treatment\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (10,'valuation','Valuation Workflow','{\"steps\": [{\"name\": \"Request Valuation\", \"order\": 1}, {\"name\": \"Appraisal\", \"order\": 2}, {\"name\": \"Review\", \"order\": 3}, {\"name\": \"Approve\", \"order\": 4}], \"states\": [\"pending\", \"requested\", \"appraised\", \"reviewed\", \"approved\"], \"transitions\": {\"review\": {\"to\": \"reviewed\", \"from\": [\"appraised\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"reviewed\"]}, \"request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"appraise\": {\"to\": \"appraised\", \"from\": [\"requested\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"approved\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (11,'insurance','Insurance Workflow','{\"steps\": [{\"name\": \"Valuation\", \"order\": 1}, {\"name\": \"Get Quote\", \"order\": 2}, {\"name\": \"Approve\", \"order\": 3}, {\"name\": \"Coverage Active\", \"order\": 4}], \"states\": [\"pending\", \"valued\", \"quoted\", \"approved\", \"covered\"], \"transitions\": {\"cover\": {\"to\": \"covered\", \"from\": [\"approved\"]}, \"quote\": {\"to\": \"quoted\", \"from\": [\"valued\"]}, \"value\": {\"to\": \"valued\", \"from\": [\"pending\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"quoted\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"covered\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (12,'loss_damage','Loss & Damage Workflow','{\"steps\": [{\"name\": \"Report Incident\", \"order\": 1}, {\"name\": \"Investigation\", \"order\": 2}, {\"name\": \"Damage Assessment\", \"order\": 3}, {\"name\": \"Resolution\", \"order\": 4}], \"states\": [\"pending\", \"reported\", \"investigated\", \"assessed\", \"resolved\"], \"transitions\": {\"assess\": {\"to\": \"assessed\", \"from\": [\"investigated\"]}, \"report\": {\"to\": \"reported\", \"from\": [\"pending\"]}, \"resolve\": {\"to\": \"resolved\", \"from\": [\"assessed\"]}, \"investigate\": {\"to\": \"investigated\", \"from\": [\"reported\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"resolved\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (13,'deaccession','Deaccession Workflow','{\"steps\": [{\"name\": \"Proposal\", \"order\": 1}, {\"name\": \"Review\", \"order\": 2}, {\"name\": \"Board Approval\", \"order\": 3}, {\"name\": \"Process\", \"order\": 4}, {\"name\": \"Complete\", \"order\": 5}], \"states\": [\"pending\", \"proposed\", \"reviewed\", \"approved\", \"processed\", \"completed\"], \"transitions\": {\"review\": {\"to\": \"reviewed\", \"from\": [\"proposed\"]}, \"approve\": {\"to\": \"approved\", \"from\": [\"reviewed\"]}, \"process\": {\"to\": \"processed\", \"from\": [\"approved\"]}, \"propose\": {\"to\": \"proposed\", \"from\": [\"pending\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"processed\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (14,'disposal','Disposal Workflow','{\"steps\": [{\"name\": \"Approval\", \"order\": 1}, {\"name\": \"Select Method\", \"order\": 2}, {\"name\": \"Disposal\", \"order\": 3}, {\"name\": \"Documentation\", \"order\": 4}], \"states\": [\"pending\", \"approved\", \"method_selected\", \"disposed\", \"documented\"], \"transitions\": {\"approve\": {\"to\": \"approved\", \"from\": [\"pending\"]}, \"dispose\": {\"to\": \"disposed\", \"from\": [\"method_selected\"]}, \"document\": {\"to\": \"documented\", \"from\": [\"disposed\"]}, \"select_method\": {\"to\": \"method_selected\", \"from\": [\"approved\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"documented\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (15,'object_exit','Object Exit Workflow','{\"steps\": [{\"name\": \"Exit Request\", \"order\": 1}, {\"name\": \"Approval\", \"order\": 2}, {\"name\": \"Preparation\", \"order\": 3}, {\"name\": \"Dispatch\", \"order\": 4}, {\"name\": \"Confirm Exit\", \"order\": 5}], \"states\": [\"pending\", \"requested\", \"approved\", \"prepared\", \"dispatched\", \"confirmed\"], \"transitions\": {\"approve\": {\"to\": \"approved\", \"from\": [\"requested\"]}, \"confirm\": {\"to\": \"confirmed\", \"from\": [\"dispatched\"]}, \"prepare\": {\"to\": \"prepared\", \"from\": [\"approved\"]}, \"request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"dispatch\": {\"to\": \"dispatched\", \"from\": [\"prepared\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"confirmed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:19:56','2025-12-09 13:19:56');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (16,'loans_in','Loans In Workflow','{\"steps\": [{\"name\": \"Request\", \"order\": 1}, {\"name\": \"Facility Report\", \"order\": 2}, {\"name\": \"Insurance\", \"order\": 3}, {\"name\": \"Condition In\", \"order\": 4}, {\"name\": \"Installation\", \"order\": 5}, {\"name\": \"Condition Out\", \"order\": 6}, {\"name\": \"Return\", \"order\": 7}], \"states\": [\"pending\", \"requested\", \"facility_report\", \"insurance\", \"condition_in\", \"installed\", \"condition_out\", \"returned\"], \"transitions\": {\"return\": {\"to\": \"returned\", \"from\": [\"condition_out\"]}, \"install\": {\"to\": \"installed\", \"from\": [\"condition_in\"]}, \"request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"facility_report\": {\"to\": \"facility_report\", \"from\": [\"requested\"]}, \"arrange_insurance\": {\"to\": \"insurance\", \"from\": [\"facility_report\"]}, \"condition_check_in\": {\"to\": \"condition_in\", \"from\": [\"insurance\"]}, \"condition_check_out\": {\"to\": \"condition_out\", \"from\": [\"installed\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"returned\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:22:23','2025-12-09 13:22:23');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (17,'loans_out','Loans Out Workflow','{\"steps\": [{\"name\": \"Request Received\", \"order\": 1}, {\"name\": \"Review\", \"order\": 2}, {\"name\": \"Facility Assessment\", \"order\": 3}, {\"name\": \"Insurance\", \"order\": 4}, {\"name\": \"Condition Report\", \"order\": 5}, {\"name\": \"Packing\", \"order\": 6}, {\"name\": \"Dispatch\", \"order\": 7}, {\"name\": \"Return\", \"order\": 8}, {\"name\": \"Final Condition\", \"order\": 9}], \"states\": [\"pending\", \"requested\", \"reviewed\", \"facility_assessed\", \"insurance\", \"condition_report\", \"packed\", \"dispatched\", \"returned\", \"condition_checked\"], \"transitions\": {\"pack\": {\"to\": \"packed\", \"from\": [\"condition_report\"]}, \"review\": {\"to\": \"reviewed\", \"from\": [\"requested\"]}, \"dispatch\": {\"to\": \"dispatched\", \"from\": [\"packed\"]}, \"receive_return\": {\"to\": \"returned\", \"from\": [\"dispatched\"]}, \"assess_facility\": {\"to\": \"facility_assessed\", \"from\": [\"reviewed\"]}, \"final_condition\": {\"to\": \"condition_checked\", \"from\": [\"returned\"]}, \"receive_request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"condition_report\": {\"to\": \"condition_report\", \"from\": [\"insurance\"]}, \"arrange_insurance\": {\"to\": \"insurance\", \"from\": [\"facility_assessed\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"condition_checked\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:22:23','2025-12-09 13:22:23');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (18,'condition_checking','Condition Checking Workflow','{\"steps\": [{\"name\": \"Schedule\", \"order\": 1}, {\"name\": \"Examine\", \"order\": 2}, {\"name\": \"Document\", \"order\": 3}, {\"name\": \"Report\", \"order\": 4}, {\"name\": \"Review\", \"order\": 5}], \"states\": [\"pending\", \"scheduled\", \"examining\", \"documenting\", \"reporting\", \"reviewed\"], \"transitions\": {\"report\": {\"to\": \"reporting\", \"from\": [\"documenting\"]}, \"review\": {\"to\": \"reviewed\", \"from\": [\"reporting\"]}, \"examine\": {\"to\": \"examining\", \"from\": [\"scheduled\"]}, \"document\": {\"to\": \"documenting\", \"from\": [\"examining\"]}, \"schedule\": {\"to\": \"scheduled\", \"from\": [\"pending\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"reviewed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:22:23','2025-12-09 13:22:23');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (19,'risk_management','Risk Management Workflow','{\"steps\": [{\"name\": \"Identify Risks\", \"order\": 1}, {\"name\": \"Assess\", \"order\": 2}, {\"name\": \"Mitigate\", \"order\": 3}, {\"name\": \"Monitor\", \"order\": 4}], \"states\": [\"pending\", \"identified\", \"assessed\", \"mitigated\", \"monitored\"], \"transitions\": {\"assess\": {\"to\": \"assessed\", \"from\": [\"identified\"]}, \"monitor\": {\"to\": \"monitored\", \"from\": [\"mitigated\"]}, \"identify\": {\"to\": \"identified\", \"from\": [\"pending\"]}, \"mitigate\": {\"to\": \"mitigated\", \"from\": [\"assessed\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"monitored\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (20,'audit','Audit Workflow','{\"steps\": [{\"name\": \"Schedule\", \"order\": 1}, {\"name\": \"In Progress\", \"order\": 2}, {\"name\": \"Findings\", \"order\": 3}, {\"name\": \"Report\", \"order\": 4}, {\"name\": \"Close\", \"order\": 5}], \"states\": [\"pending\", \"scheduled\", \"in_progress\", \"findings\", \"reported\", \"closed\"], \"transitions\": {\"close\": {\"to\": \"closed\", \"from\": [\"reported\"]}, \"start\": {\"to\": \"in_progress\", \"from\": [\"scheduled\"]}, \"report\": {\"to\": \"reported\", \"from\": [\"findings\"]}, \"findings\": {\"to\": \"findings\", \"from\": [\"in_progress\"]}, \"schedule\": {\"to\": \"scheduled\", \"from\": [\"pending\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"closed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (21,'rights_management','Rights Management Workflow','{\"steps\": [{\"name\": \"Research\", \"order\": 1}, {\"name\": \"Document\", \"order\": 2}, {\"name\": \"Clear Rights\", \"order\": 3}, {\"name\": \"Monitor\", \"order\": 4}], \"states\": [\"pending\", \"researched\", \"documented\", \"cleared\", \"monitored\"], \"transitions\": {\"clear\": {\"to\": \"cleared\", \"from\": [\"documented\"]}, \"monitor\": {\"to\": \"monitored\", \"from\": [\"cleared\"]}, \"document\": {\"to\": \"documented\", \"from\": [\"researched\"]}, \"research\": {\"to\": \"researched\", \"from\": [\"pending\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"monitored\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (22,'reproduction','Reproduction Workflow','{\"steps\": [{\"name\": \"Request\", \"order\": 1}, {\"name\": \"Rights Check\", \"order\": 2}, {\"name\": \"Approval\", \"order\": 3}, {\"name\": \"Production\", \"order\": 4}, {\"name\": \"Delivery\", \"order\": 5}], \"states\": [\"pending\", \"requested\", \"rights_checked\", \"approved\", \"produced\", \"delivered\"], \"transitions\": {\"approve\": {\"to\": \"approved\", \"from\": [\"rights_checked\"]}, \"deliver\": {\"to\": \"delivered\", \"from\": [\"produced\"]}, \"produce\": {\"to\": \"produced\", \"from\": [\"approved\"]}, \"request\": {\"to\": \"requested\", \"from\": [\"pending\"]}, \"check_rights\": {\"to\": \"rights_checked\", \"from\": [\"requested\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"delivered\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (23,'documentation_planning','Documentation Planning Workflow','{\"steps\": [{\"name\": \"Plan\", \"order\": 1}, {\"name\": \"Prioritize\", \"order\": 2}, {\"name\": \"In Progress\", \"order\": 3}, {\"name\": \"Complete\", \"order\": 4}, {\"name\": \"Review\", \"order\": 5}], \"states\": [\"pending\", \"planned\", \"prioritized\", \"in_progress\", \"completed\", \"reviewed\"], \"transitions\": {\"plan\": {\"to\": \"planned\", \"from\": [\"pending\"]}, \"start\": {\"to\": \"in_progress\", \"from\": [\"prioritized\"]}, \"review\": {\"to\": \"reviewed\", \"from\": [\"completed\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"in_progress\"]}, \"prioritize\": {\"to\": \"prioritized\", \"from\": [\"planned\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"reviewed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');
INSERT IGNORE INTO `spectrum_workflow_config` VALUES (24,'retrospective_documentation','Retrospective Documentation Workflow','{\"steps\": [{\"name\": \"Identify Gaps\", \"order\": 1}, {\"name\": \"Research\", \"order\": 2}, {\"name\": \"Document\", \"order\": 3}, {\"name\": \"Verify\", \"order\": 4}, {\"name\": \"Complete\", \"order\": 5}], \"states\": [\"pending\", \"identified\", \"researched\", \"documented\", \"verified\", \"completed\"], \"transitions\": {\"verify\": {\"to\": \"verified\", \"from\": [\"documented\"]}, \"complete\": {\"to\": \"completed\", \"from\": [\"verified\"]}, \"document\": {\"to\": \"documented\", \"from\": [\"researched\"]}, \"identify\": {\"to\": \"identified\", \"from\": [\"pending\"]}, \"research\": {\"to\": \"researched\", \"from\": [\"identified\"]}, \"restart\": {\"to\": \"pending\", \"from\": [\"completed\"]}}, \"initial_state\": \"pending\"}',1,1,NULL,'2025-12-09 13:48:46','2025-12-09 13:48:46');

UNLOCK TABLES;











-- ============================================================
-- #122: per-object insurance link table.
-- Existing gallery_insurance_policy is institution-level (no FK to
-- information_object); this table makes spectrum_require_insurance
-- enforceable per-object. Lazy-created on first read in
-- SpectrumPublishGuardService for installs that never re-run install.sql.
-- ============================================================
CREATE TABLE IF NOT EXISTS `spectrum_object_insurance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `policy_number` varchar(100) NOT NULL,
  `insurer` varchar(255) NOT NULL,
  `policy_type` varchar(60) DEFAULT 'all_risk',
  `coverage_amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'USD',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_active_dates` (`is_active`, `start_date`, `end_date`),
  CONSTRAINT `spectrum_object_insurance_object_fk` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- #123: centralised per-object barcode link table.
-- Avoids touching every sector blade form to add a barcode field; the
-- spectrum module owns barcodes as a separate cross-sector concern.
-- spectrum_enable_barcodes gates the lookup route + the assign endpoint
-- (route 404s when off so the feature is invisible until enabled).
-- ============================================================
CREATE TABLE IF NOT EXISTS `spectrum_object_barcode` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `barcode` varchar(255) NOT NULL,
  `barcode_type` varchar(40) DEFAULT 'code128',
  `label` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barcode` (`barcode`),
  KEY `idx_object_id` (`object_id`),
  CONSTRAINT `spectrum_object_barcode_object_fk` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
