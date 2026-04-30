-- ============================================================================
-- ahg-vendor — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgVendorPlugin/database/install.sql
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

-- =====================================================
-- ahgVendorPlugin - Database Schema
-- Vendor and supplier management
-- =====================================================

-- =====================================================
-- Vendor Service Types (lookup table - create first)
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_service_types` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `requires_insurance` TINYINT(1) DEFAULT 0,
    `requires_valuation` TINYINT(1) DEFAULT 0,
    `typical_duration_days` INT DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    KEY `idx_service_slug` (`slug`),
    KEY `idx_service_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed service types
INSERT IGNORE INTO `ahg_vendor_service_types` (`name`, `slug`, `requires_insurance`, `requires_valuation`, `typical_duration_days`, `display_order`) VALUES
('Conservation', 'conservation', 1, 1, 30, 1),
('Restoration', 'restoration', 1, 1, 45, 2),
('Framing', 'framing', 1, 1, 14, 3),
('Digitization', 'digitization', 1, 0, 7, 4),
('Photography', 'photography', 1, 0, 3, 5),
('Binding', 'binding', 0, 0, 21, 6),
('Cleaning', 'cleaning', 0, 0, 5, 7),
('Pest Treatment', 'pest-treatment', 0, 0, 7, 8),
('Storage Materials', 'storage-materials', 0, 0, 3, 9),
('Transport', 'transport', 1, 1, 1, 10),
('Valuation', 'valuation', 0, 0, 14, 11),
('Insurance', 'insurance', 0, 0, 7, 12),
('Mounting', 'mounting', 1, 1, 7, 13),
('Deacidification', 'deacidification', 0, 0, 14, 14),
('Encapsulation', 'encapsulation', 0, 0, 7, 15),
('Other', 'other', 0, 0, NULL, 99);

-- =====================================================
-- Vendors
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendors` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `vendor_code` VARCHAR(50) DEFAULT NULL,
    `vendor_type` VARCHAR(50) DEFAULT 'company',
    `registration_number` VARCHAR(100) DEFAULT NULL,
    `vat_number` VARCHAR(50) DEFAULT NULL,
    `street_address` TEXT,
    `city` VARCHAR(100) DEFAULT NULL,
    `province` VARCHAR(100) DEFAULT NULL,
    `postal_code` VARCHAR(20) DEFAULT NULL,
    `country` VARCHAR(100) DEFAULT 'South Africa',
    `phone` VARCHAR(50) DEFAULT NULL,
    `phone_alt` VARCHAR(50) DEFAULT NULL,
    `fax` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `website` VARCHAR(255) DEFAULT NULL,
    `bank_name` VARCHAR(100) DEFAULT NULL,
    `bank_branch` VARCHAR(100) DEFAULT NULL,
    `bank_account_number` VARCHAR(50) DEFAULT NULL,
    `bank_branch_code` VARCHAR(20) DEFAULT NULL,
    `bank_account_type` VARCHAR(50) DEFAULT NULL,
    `has_insurance` TINYINT(1) DEFAULT 0,
    `insurance_provider` VARCHAR(255) DEFAULT NULL,
    `insurance_policy_number` VARCHAR(100) DEFAULT NULL,
    `insurance_expiry_date` DATE DEFAULT NULL,
    `insurance_coverage_amount` DECIMAL(15,2) DEFAULT NULL,
    `quality_rating` TINYINT DEFAULT NULL COMMENT '1-5 stars',
    `reliability_rating` TINYINT DEFAULT NULL COMMENT '1-5 stars',
    `price_rating` TINYINT DEFAULT NULL COMMENT '1-5 stars',
    `status` VARCHAR(50) DEFAULT 'active',
    `approved_by` INT DEFAULT NULL,
    `approved_at` DATETIME DEFAULT NULL,
    `notes` TEXT,
    `is_preferred` TINYINT(1) DEFAULT 0,
    `is_bbbee_compliant` TINYINT(1) DEFAULT 0,
    `created_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`),
    UNIQUE KEY `vendor_code` (`vendor_code`),
    KEY `idx_vendor_name` (`name`),
    KEY `idx_vendor_status` (`status`),
    KEY `idx_vendor_type` (`vendor_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Contacts
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_contacts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `vendor_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `position` VARCHAR(100) DEFAULT NULL,
    `department` VARCHAR(100) DEFAULT NULL,
    `phone` VARCHAR(50) DEFAULT NULL,
    `mobile` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_contact_vendor` (`vendor_id`),
    KEY `idx_contact_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Services (which services each vendor provides)
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_services` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `vendor_id` INT NOT NULL,
    `service_type_id` INT NOT NULL,
    `hourly_rate` DECIMAL(10,2) DEFAULT NULL,
    `fixed_rate` DECIMAL(10,2) DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'ZAR',
    `notes` TEXT,
    `is_preferred` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_vendor_service` (`vendor_id`, `service_type_id`),
    KEY `idx_vs_vendor` (`vendor_id`),
    KEY `idx_vs_service` (`service_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Transactions
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_transactions` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `transaction_number` VARCHAR(50) NOT NULL,
    `vendor_id` INT NOT NULL,
    `service_type_id` INT NOT NULL,
    `status` VARCHAR(50) DEFAULT 'pending_approval',
    `request_date` DATE NOT NULL,
    `approval_date` DATE DEFAULT NULL,
    `dispatch_date` DATE DEFAULT NULL,
    `expected_return_date` DATE DEFAULT NULL,
    `actual_return_date` DATE DEFAULT NULL,
    `requested_by` INT NOT NULL,
    `approved_by` INT DEFAULT NULL,
    `dispatched_by` INT DEFAULT NULL,
    `received_by` INT DEFAULT NULL,
    `estimated_cost` DECIMAL(12,2) DEFAULT NULL,
    `actual_cost` DECIMAL(12,2) DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'ZAR',
    `quote_reference` VARCHAR(100) DEFAULT NULL,
    `invoice_reference` VARCHAR(100) DEFAULT NULL,
    `invoice_date` DATE DEFAULT NULL,
    `payment_status` VARCHAR(50) DEFAULT 'pending',
    `payment_date` DATE DEFAULT NULL,
    `total_insured_value` DECIMAL(15,2) DEFAULT NULL,
    `insurance_arranged` TINYINT(1) DEFAULT 0,
    `insurance_reference` VARCHAR(100) DEFAULT NULL,
    `shipping_method` VARCHAR(100) DEFAULT NULL,
    `tracking_number` VARCHAR(100) DEFAULT NULL,
    `courier_company` VARCHAR(100) DEFAULT NULL,
    `dispatch_notes` TEXT,
    `vendor_notes` TEXT,
    `return_notes` TEXT,
    `internal_notes` TEXT,
    `has_quotes` TINYINT(1) DEFAULT 0,
    `has_invoices` TINYINT(1) DEFAULT 0,
    `has_condition_reports` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `transaction_number` (`transaction_number`),
    KEY `idx_trans_vendor` (`vendor_id`),
    KEY `idx_trans_service` (`service_type_id`),
    KEY `idx_trans_status` (`status`),
    KEY `idx_trans_dispatch` (`dispatch_date`),
    KEY `idx_trans_expected` (`expected_return_date`),
    KEY `idx_trans_payment` (`payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Transaction Items
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_items` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `transaction_id` INT NOT NULL,
    `information_object_id` INT NOT NULL,
    `item_title` VARCHAR(1024) DEFAULT NULL,
    `item_reference` VARCHAR(255) DEFAULT NULL,
    `condition_before` TEXT,
    `condition_before_rating` VARCHAR(50) DEFAULT NULL,
    `condition_after` TEXT,
    `condition_after_rating` VARCHAR(50) DEFAULT NULL,
    `declared_value` DECIMAL(15,2) DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'ZAR',
    `service_description` TEXT,
    `service_completed` TINYINT(1) DEFAULT 0,
    `service_notes` TEXT,
    `item_cost` DECIMAL(10,2) DEFAULT NULL,
    `dispatched_at` DATETIME DEFAULT NULL,
    `returned_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ti_transaction` (`transaction_id`),
    KEY `idx_ti_object` (`information_object_id`),
    KEY `idx_ti_completed` (`service_completed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Transaction History
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `transaction_id` INT NOT NULL,
    `status_from` VARCHAR(50) DEFAULT NULL,
    `status_to` VARCHAR(50) NOT NULL,
    `changed_by` INT NOT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_th_transaction` (`transaction_id`),
    KEY `idx_th_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Transaction Attachments
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_transaction_attachments` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `transaction_id` INT NOT NULL,
    `attachment_type` VARCHAR(50) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `file_size` INT DEFAULT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `description` TEXT,
    `uploaded_by` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ta_transaction` (`transaction_id`),
    KEY `idx_ta_type` (`attachment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Vendor Metrics
-- =====================================================
CREATE TABLE IF NOT EXISTS `ahg_vendor_metrics` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `vendor_id` INT NOT NULL,
    `year` INT NOT NULL,
    `month` INT DEFAULT NULL,
    `total_transactions` INT DEFAULT 0,
    `completed_transactions` INT DEFAULT 0,
    `on_time_returns` INT DEFAULT 0,
    `late_returns` INT DEFAULT 0,
    `total_items_handled` INT DEFAULT 0,
    `total_value_handled` DECIMAL(15,2) DEFAULT 0.00,
    `total_cost` DECIMAL(15,2) DEFAULT 0.00,
    `avg_turnaround_days` DECIMAL(5,1) DEFAULT NULL,
    `avg_quality_score` DECIMAL(3,2) DEFAULT NULL,
    `calculated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_vendor_period` (`vendor_id`, `year`, `month`),
    KEY `idx_vm_vendor` (`vendor_id`),
    KEY `idx_vm_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CONTRACT MANAGEMENT
-- =====================================================

-- Contract Types
CREATE TABLE IF NOT EXISTS `ahg_contract_type` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `prefix` VARCHAR(10) DEFAULT 'CON',
    `description` TEXT,
    `default_duration_months` INT DEFAULT 12,
    `requires_witness` TINYINT(1) DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `sort_order` INT DEFAULT 0,
    `color` VARCHAR(7) DEFAULT '#6c757d',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed contract types
INSERT IGNORE INTO `ahg_contract_type` (`name`, `slug`, `prefix`, `description`, `default_duration_months`, `requires_witness`, `sort_order`, `color`) VALUES
('Service Agreement', 'service-agreement', 'SVC', 'Agreement for provision of services', 12, 0, 1, '#007bff'),
('Service Level Agreement', 'sla', 'SLA', 'Agreement defining service levels and metrics', 12, 0, 2, '#6c757d'),
('Collaboration Agreement', 'collaboration', 'COL', 'Partnership for joint projects, digitization, research', 36, 0, 3, '#17a2b8'),
('License Agreement', 'license', 'LIC', 'License for use of materials or software', 12, 0, 4, '#e83e8c'),
('Memorandum of Understanding', 'mou', 'MOU', 'Non-binding agreement outlining intentions', 24, 0, 5, '#6c757d'),
('Non-Disclosure Agreement', 'nda', 'NDA', 'Confidentiality agreement', 60, 0, 6, '#dc3545'),
('Data Processing Agreement', 'dpa', 'DPA', 'POPIA/GDPR data processing terms', 36, 0, 7, '#28a745'),
('Maintenance Agreement', 'maintenance', 'MNT', 'Equipment or system maintenance contract', 12, 0, 8, '#fd7e14'),
('Lease Agreement', 'lease', 'LEA', 'Equipment or space rental', 12, 0, 9, '#ffc107'),
('Supply Agreement', 'supply', 'SUP', 'Regular supply of materials or goods', 12, 0, 10, '#20c997'),
('Framework Agreement', 'framework', 'FRM', 'Master agreement governing future transactions', 36, 0, 11, '#6f42c1'),
('Consultancy Agreement', 'consultancy', 'CST', 'Professional consulting services', 6, 0, 12, '#007bff');

-- Contracts
CREATE TABLE IF NOT EXISTS `ahg_contract` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `contract_number` VARCHAR(100) NOT NULL,
    `contract_type_id` INT UNSIGNED NOT NULL,
    `vendor_id` INT DEFAULT NULL,
    `title` VARCHAR(500) NOT NULL,
    `description` TEXT,
    `counterparty_name` VARCHAR(255) NOT NULL,
    `counterparty_type` VARCHAR(50) DEFAULT 'vendor',
    `counterparty_contact` TEXT,
    `counterparty_representative` VARCHAR(255) DEFAULT NULL,
    `counterparty_representative_title` VARCHAR(255) DEFAULT NULL,
    `our_representative` VARCHAR(255) DEFAULT NULL,
    `our_representative_title` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'draft',
    `effective_date` DATE DEFAULT NULL,
    `expiry_date` DATE DEFAULT NULL,
    `review_date` DATE DEFAULT NULL,
    `auto_renew` TINYINT(1) DEFAULT 0,
    `renewal_notice_days` INT DEFAULT 30,
    `termination_notice_days` INT DEFAULT 30,
    `termination_date` DATE DEFAULT NULL,
    `termination_reason` TEXT,
    `has_financial_terms` TINYINT(1) DEFAULT 0,
    `contract_value` DECIMAL(15,2) DEFAULT NULL,
    `currency` VARCHAR(3) DEFAULT 'ZAR',
    `payment_terms` TEXT,
    `payment_frequency` VARCHAR(50) DEFAULT NULL,
    `scope_of_work` TEXT,
    `deliverables` TEXT,
    `general_terms` TEXT,
    `special_conditions` TEXT,
    `ip_terms` TEXT,
    `confidentiality_terms` TEXT,
    `liability_terms` TEXT,
    `dispute_resolution` TEXT,
    `governing_law` VARCHAR(100) DEFAULT 'South Africa',
    `jurisdiction` VARCHAR(100) DEFAULT NULL,
    `our_signature_date` DATE DEFAULT NULL,
    `our_signature_name` VARCHAR(255) DEFAULT NULL,
    `counterparty_signature_date` DATE DEFAULT NULL,
    `counterparty_signature_name` VARCHAR(255) DEFAULT NULL,
    `witness_name` VARCHAR(255) DEFAULT NULL,
    `witness_date` DATE DEFAULT NULL,
    `logo_path` VARCHAR(500) DEFAULT NULL,
    `logo_filename` VARCHAR(255) DEFAULT NULL,
    `is_template` TINYINT(1) DEFAULT 0,
    `parent_contract_id` INT DEFAULT NULL,
    `replaces_contract_id` INT DEFAULT NULL,
    `internal_notes` TEXT,
    `risk_level` VARCHAR(50) DEFAULT 'low',
    `compliance_requirements` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `contract_number` (`contract_number`),
    KEY `idx_contract_type` (`contract_type_id`),
    KEY `idx_contract_vendor` (`vendor_id`),
    KEY `idx_contract_status` (`status`),
    KEY `idx_contract_dates` (`effective_date`, `expiry_date`),
    KEY `idx_contract_review` (`review_date`),
    KEY `idx_contract_parent` (`parent_contract_id`),
    CONSTRAINT `fk_contract_type` FOREIGN KEY (`contract_type_id`) REFERENCES `ahg_contract_type` (`id`),
    CONSTRAINT `fk_contract_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `ahg_vendors` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_contract_parent` FOREIGN KEY (`parent_contract_id`) REFERENCES `ahg_contract` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_contract_replaces` FOREIGN KEY (`replaces_contract_id`) REFERENCES `ahg_contract` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract Documents
CREATE TABLE IF NOT EXISTS `ahg_contract_document` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT NOT NULL,
    `document_type` VARCHAR(50) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(100) DEFAULT NULL,
    `file_size` INT UNSIGNED DEFAULT NULL,
    `checksum_md5` VARCHAR(32) DEFAULT NULL,
    `title` VARCHAR(255) DEFAULT NULL,
    `description` TEXT,
    `document_date` DATE DEFAULT NULL,
    `version` VARCHAR(50) DEFAULT NULL,
    `is_signed` TINYINT(1) DEFAULT 0,
    `signature_date` DATE DEFAULT NULL,
    `signed_by` TEXT,
    `is_confidential` TINYINT(1) DEFAULT 0,
    `uploaded_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_doc_contract` (`contract_id`),
    KEY `idx_doc_type` (`document_type`),
    CONSTRAINT `fk_contract_doc` FOREIGN KEY (`contract_id`) REFERENCES `ahg_contract` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract Reminders
CREATE TABLE IF NOT EXISTS `ahg_contract_reminder` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `contract_id` INT NOT NULL,
    `reminder_type` VARCHAR(50) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `reminder_date` DATE NOT NULL,
    `advance_days` INT DEFAULT 30,
    `is_recurring` TINYINT(1) DEFAULT 0,
    `recurrence_pattern` VARCHAR(50) DEFAULT NULL,
    `recurrence_end_date` DATE DEFAULT NULL,
    `priority` VARCHAR(50) DEFAULT 'normal',
    `notify_email` TINYINT(1) DEFAULT 1,
    `notification_recipients` TEXT,
    `status` VARCHAR(50) DEFAULT 'active',
    `snooze_until` DATE DEFAULT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `completed_by` INT UNSIGNED DEFAULT NULL,
    `completion_notes` TEXT,
    `created_by` INT UNSIGNED DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reminder_contract` (`contract_id`),
    KEY `idx_reminder_date` (`reminder_date`),
    KEY `idx_reminder_status` (`status`),
    CONSTRAINT `fk_contract_reminder` FOREIGN KEY (`contract_id`) REFERENCES `ahg_contract` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contract History/Audit
CREATE TABLE IF NOT EXISTS `ahg_contract_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `contract_id` INT NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `field_changed` VARCHAR(100) DEFAULT NULL,
    `old_value` TEXT,
    `new_value` TEXT,
    `notes` TEXT,
    `user_id` INT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_history_contract` (`contract_id`),
    KEY `idx_history_action` (`action`),
    KEY `idx_history_date` (`created_at`),
    CONSTRAINT `fk_contract_history` FOREIGN KEY (`contract_id`) REFERENCES `ahg_contract` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
