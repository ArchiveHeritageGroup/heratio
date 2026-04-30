-- ============================================================================
-- ahg-donor-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDonorManagePlugin/database/install.sql
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

-- ahgDonorManagePlugin: No custom tables required

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgDonorAgreementPlugin on 2026-04-30
-- ============================================================================
-- ============================================================
-- ahgDonorAgreementPlugin - Database Schema (from 112)
-- ============================================================

-- Agreement Types (reference table)
CREATE TABLE IF NOT EXISTS `agreement_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `prefix` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'AGR',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#6c757d',
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed agreement types
INSERT IGNORE INTO agreement_type (name, slug, prefix, description, is_active, sort_order, color) VALUES
('Deed of Gift', 'deed-of-gift', 'DOG', 'Unconditional transfer of ownership', 1, 1, '#28a745'),
('Deed of Donation', 'deed-of-donation', 'DON', 'Formal donation under SA law', 1, 2, '#28a745'),
('Deed of Deposit', 'deed-of-deposit', 'DOD', 'Materials deposited, ownership retained', 1, 3, '#17a2b8'),
('Loan Agreement', 'loan-agreement', 'LOA', 'Temporary loan for exhibition/research', 1, 4, '#ffc107'),
('Purchase Agreement', 'purchase-agreement', 'PUR', 'Acquisition through purchase', 1, 5, '#6f42c1'),
('Bequest', 'bequest', 'BEQ', 'Transfer through will or testament', 1, 6, '#20c997'),
('Transfer Agreement', 'transfer-agreement', 'TRA', 'Inter-institutional transfer', 1, 7, '#fd7e14'),
('Custody Agreement', 'custody-agreement', 'CUS', 'Temporary custody pending disposition', 1, 8, '#6c757d'),
('License Agreement', 'license-agreement', 'LIC', 'Rights license without ownership transfer', 1, 9, '#e83e8c'),
('Reproduction Agreement', 'reproduction-agreement', 'REP', 'Agreement for reproduction rights', 1, 10, '#007bff'),
('Access Agreement', 'access-agreement', 'ACC', 'Special access arrangements', 1, 11, '#ffc107'),
('Memorandum of Understanding', 'mou', 'MOU', 'Non-binding agreement outlining intentions', 1, 12, '#6c757d'),
('Service Level Agreement', 'sla', 'SLA', 'Agreement defining service levels', 1, 13, '#343a40'),
('Collaboration Agreement', 'collaboration-agreement', 'COL', 'Partnership for joint projects, digitization, research', 1, 14, '#17a2b8');

-- Main Donor Agreement Table
CREATE TABLE IF NOT EXISTS `donor_agreement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `donor_id` int unsigned DEFAULT NULL,
  `actor_id` int unsigned DEFAULT NULL,
  `accession_id` int unsigned DEFAULT NULL,
  `information_object_id` int unsigned DEFAULT NULL,
  `repository_id` int unsigned DEFAULT NULL,
  `agreement_type_id` int unsigned NOT NULL,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `donor_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `donor_contact_info` text COLLATE utf8mb4_unicode_ci,
  `institution_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution_contact_info` text COLLATE utf8mb4_unicode_ci,
  `legal_representative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative_contact` text COLLATE utf8mb4_unicode_ci,
  `repository_representative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repository_representative_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(93) COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT 'draft, pending_review, pending_signature, active, expired, terminated, superseded',
  `agreement_date` date DEFAULT NULL,
  `effective_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `review_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` text COLLATE utf8mb4_unicode_ci,
  `has_financial_terms` tinyint(1) DEFAULT '0',
  `purchase_amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `payment_terms` text COLLATE utf8mb4_unicode_ci,
  `scope_description` text COLLATE utf8mb4_unicode_ci,
  `extent_statement` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transfer_date` date DEFAULT NULL,
  `received_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `general_terms` text COLLATE utf8mb4_unicode_ci,
  `special_conditions` text COLLATE utf8mb4_unicode_ci,
  `donor_signature_date` date DEFAULT NULL,
  `donor_signature_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repository_signature_date` date DEFAULT NULL,
  `repository_signature_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `witness_date` date DEFAULT NULL,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `logo_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_template` tinyint(1) DEFAULT '0',
  `parent_agreement_id` int DEFAULT NULL,
  `supersedes_agreement_id` int DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `updated_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_agreement_number` (`agreement_number`),
  KEY `idx_donor` (`donor_id`),
  KEY `idx_accession` (`accession_id`),
  KEY `idx_io` (`information_object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`effective_date`,`expiry_date`),
  KEY `idx_review` (`review_date`),
  KEY `idx_agreement_type` (`agreement_type_id`),
  KEY `idx_parent_agreement` (`parent_agreement_id`),
  KEY `idx_supersedes_agreement` (`supersedes_agreement_id`),
  CONSTRAINT `fk_donor_agreement_agreement_type` FOREIGN KEY (`agreement_type_id`) REFERENCES `agreement_type` (`id`),
  CONSTRAINT `fk_donor_agreement_parent` FOREIGN KEY (`parent_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_donor_agreement_supersedes` FOREIGN KEY (`supersedes_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agreement i18n
CREATE TABLE IF NOT EXISTS `donor_agreement_i18n` (
  `id` int NOT NULL,
  `culture` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `restrictions` text COLLATE utf8mb4_unicode_ci,
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `attribution_text` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_donor_agreement_i18n` FOREIGN KEY (`id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reminders
CREATE TABLE IF NOT EXISTS `donor_agreement_reminder` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `reminder_type` VARCHAR(152) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'expiry_warning, review_due, renewal_required, restriction_ending, payment_due, donor_contact, anniversary, audit, preservation_check, custom',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `reminder_date` date NOT NULL,
  `advance_days` int DEFAULT '30',
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurrence_pattern` VARCHAR(53) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'daily, weekly, monthly, quarterly, yearly',
  `recurrence_end_date` date DEFAULT NULL,
  `priority` VARCHAR(37) COLLATE utf8mb4_unicode_ci DEFAULT 'normal' COMMENT 'low, normal, high, urgent',
  `notify_email` tinyint(1) DEFAULT '1',
  `notify_system` tinyint(1) DEFAULT '1',
  `notification_recipients` text COLLATE utf8mb4_unicode_ci,
  `status` VARCHAR(49) COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, snoozed, completed, cancelled',
  `snooze_until` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int unsigned DEFAULT NULL,
  `completion_notes` text COLLATE utf8mb4_unicode_ci,
  `action_required` text COLLATE utf8mb4_unicode_ci,
  `action_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_sent` tinyint(1) DEFAULT '0',
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_date` (`reminder_date`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  CONSTRAINT `fk_donor_agreement_reminder_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rights
CREATE TABLE IF NOT EXISTS `donor_agreement_right` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `right_type` VARCHAR(163) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'replicate, migrate, modify, use, disseminate, delete, display, publish, digitize, reproduce, loan, exhibit, broadcast, commercial_use, derivative_works',
  `permission` VARCHAR(56) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'granted, restricted, prohibited, conditional',
  `conditions` text COLLATE utf8mb4_unicode_ci,
  `applies_to_digital` tinyint(1) DEFAULT '1',
  `applies_to_physical` tinyint(1) DEFAULT '1',
  `applies_to_metadata` tinyint(1) DEFAULT '1',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `requires_donor_approval` tinyint(1) DEFAULT '0',
  `requires_fee` tinyint(1) DEFAULT '0',
  `fee_structure` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`right_type`),
  CONSTRAINT `fk_donor_agreement_right_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Restrictions
CREATE TABLE IF NOT EXISTS `donor_agreement_restriction` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `restriction_type` VARCHAR(237) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'closure, partial_closure, redaction, permission_only, researcher_only, onsite_only, no_copying, no_publication, anonymization, time_embargo, review_required, security_clearance, popia_restricted, legal_hold, cultural_protocol',
  `applies_to_all` tinyint(1) DEFAULT '1',
  `specific_materials` text COLLATE utf8mb4_unicode_ci,
  `box_list` text COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `auto_release` tinyint(1) DEFAULT '0',
  `release_date` date DEFAULT NULL,
  `release_trigger` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_be_overridden` tinyint(1) DEFAULT '0',
  `override_authority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `legal_basis` text COLLATE utf8mb4_unicode_ci,
  `popia_category` VARCHAR(69) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'special_personal, personal, children, criminal, biometric',
  `data_subject_consent` tinyint(1) DEFAULT NULL,
  `security_clearance_level` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`restriction_type`),
  KEY `idx_release` (`release_date`,`auto_release`),
  CONSTRAINT `fk_donor_agreement_restriction_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents
CREATE TABLE IF NOT EXISTS `donor_agreement_document` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `document_type` VARCHAR(265) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'signed_agreement, draft, amendment, addendum, schedule, correspondence, appraisal_report, inventory, deed_of_gift, transfer_form, receipt, payment_record, legal_opinion, board_resolution, donor_id, provenance_evidence, valuation, insurance, photo, other',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `checksum_md5` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_sha256` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `document_date` date DEFAULT NULL,
  `version` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_signed` tinyint(1) DEFAULT '0',
  `signature_date` date DEFAULT NULL,
  `signed_by` text COLLATE utf8mb4_unicode_ci,
  `is_confidential` tinyint(1) DEFAULT '0',
  `access_restriction` text COLLATE utf8mb4_unicode_ci,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agreement` (`donor_agreement_id`),
  KEY `idx_type` (`document_type`),
  CONSTRAINT `fk_donor_agreement_document_agreement` FOREIGN KEY (`donor_agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link to Accessions
CREATE TABLE IF NOT EXISTS `donor_agreement_accession` (
  `id` int NOT NULL AUTO_INCREMENT,
  `donor_agreement_id` int NOT NULL,
  `accession_id` int NOT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `linked_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `linked_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_daa` (`donor_agreement_id`,`accession_id`),
  KEY `idx_daa_accession` (`accession_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link to Information Objects
CREATE TABLE IF NOT EXISTS `donor_agreement_record` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_id` int NOT NULL,
  `information_object_id` int NOT NULL,
  `relationship_type` VARCHAR(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'covers' COMMENT 'covers, partially_covers, references',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agreement_record` (`agreement_id`,`information_object_id`),
  KEY `idx_record_agreement` (`agreement_id`),
  KEY `idx_record_io` (`information_object_id`),
  CONSTRAINT `fk_agreement_record_agreement` FOREIGN KEY (`agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_agreement_record_io` FOREIGN KEY (`information_object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- History/Audit
CREATE TABLE IF NOT EXISTS `donor_agreement_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `agreement_id` int NOT NULL,
  `action` VARCHAR(168) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'created, updated, status_changed, approved, renewed, terminated, document_added, document_removed, record_linked, record_unlinked, reminder_sent, note_added',
  `old_value` text COLLATE utf8mb4_unicode_ci,
  `new_value` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_agreement` (`agreement_id`),
  KEY `idx_history_action` (`action`),
  KEY `idx_history_date` (`created_at`),
  CONSTRAINT `fk_agreement_history` FOREIGN KEY (`agreement_id`) REFERENCES `donor_agreement` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
