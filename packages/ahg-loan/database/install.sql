-- ============================================================================
-- ahg-loan — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgLoanPlugin/database/install.sql
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
-- AHG Loan Plugin - Unified Database Schema
-- Version: 1.0.0
-- Author: Johan Pieterse <johan@theahg.co.za>
-- =====================================================
-- Shared loan management for all GLAM sectors:
-- Museums, Galleries, Archives, Digital Assets
-- =====================================================

-- =====================================================
-- CORE LOAN TABLES
-- =====================================================

-- Main loan record (sector-agnostic)
CREATE TABLE IF NOT EXISTS ahg_loan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Loan identification
    loan_number VARCHAR(50) NOT NULL UNIQUE,
    loan_type VARCHAR(20) COMMENT 'out, in' NOT NULL,
    sector VARCHAR(50) COMMENT 'museum, gallery, archive, library, dam' NOT NULL DEFAULT 'museum',

    -- Basic information
    title VARCHAR(500),
    description TEXT,
    purpose VARCHAR(100) DEFAULT 'exhibition',

    -- Partner institution (borrower for loan_out, lender for loan_in)
    partner_institution VARCHAR(500) NOT NULL,
    partner_contact_name VARCHAR(255),
    partner_contact_email VARCHAR(255),
    partner_contact_phone VARCHAR(100),
    partner_address TEXT,

    -- Key dates
    request_date DATETIME,
    start_date DATE,
    end_date DATE,
    return_date DATE,

    -- Insurance
    insurance_type VARCHAR(60) COMMENT 'borrower, lender, shared, government, self, none' DEFAULT 'borrower',
    insurance_value DECIMAL(15,2),
    insurance_currency VARCHAR(3) DEFAULT 'ZAR',
    insurance_policy_number VARCHAR(100),
    insurance_provider VARCHAR(255),

    -- Fees
    loan_fee DECIMAL(12,2),
    loan_fee_currency VARCHAR(3) DEFAULT 'ZAR',

    -- Status
    status VARCHAR(50) DEFAULT 'draft',

    -- Approval
    internal_approver_id INT,
    approved_date DATETIME,

    -- Related entities
    exhibition_id BIGINT UNSIGNED,
    repository_id INT,

    -- Sector-specific data (JSON for flexibility)
    sector_data JSON,

    -- Notes
    notes TEXT,

    -- Tracking
    created_by INT,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_loan_number (loan_number),
    INDEX idx_loan_type (loan_type),
    INDEX idx_loan_sector (sector),
    INDEX idx_loan_status (status),
    INDEX idx_loan_partner (partner_institution(100)),
    INDEX idx_loan_dates (start_date, end_date),
    INDEX idx_loan_return (return_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objects included in a loan
CREATE TABLE IF NOT EXISTS ahg_loan_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    -- Object reference (AtoM or external)
    information_object_id INT,
    external_object_id VARCHAR(255),

    -- Object details (cached/external)
    object_title VARCHAR(500),
    object_identifier VARCHAR(255),
    object_type VARCHAR(100),

    -- Insurance
    insurance_value DECIMAL(15,2),

    -- Condition
    condition_report_id BIGINT UNSIGNED,
    condition_on_departure TEXT,
    condition_on_return TEXT,

    -- Requirements
    special_requirements TEXT,
    display_requirements TEXT,

    -- Status
    status VARCHAR(91) COMMENT 'pending, approved, prepared, dispatched, received, on_display, packed, returned' DEFAULT 'pending',

    -- Dates
    dispatched_date DATE,
    received_date DATE,
    returned_date DATE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_loan_object_loan (loan_id),
    INDEX idx_loan_object_info (information_object_id),
    INDEX idx_loan_object_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loan documents
CREATE TABLE IF NOT EXISTS ahg_loan_document (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    document_type VARCHAR(50) NOT NULL,
    file_path VARCHAR(500),
    file_name VARCHAR(255),
    mime_type VARCHAR(100),
    file_size BIGINT,
    description TEXT,

    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_loan_doc_loan (loan_id),
    INDEX idx_loan_doc_type (document_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Loan extensions
CREATE TABLE IF NOT EXISTS ahg_loan_extension (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    previous_end_date DATE NOT NULL,
    new_end_date DATE NOT NULL,
    reason TEXT,
    approved_by INT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_loan_ext_loan (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status change history
CREATE TABLE IF NOT EXISTS ahg_loan_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    from_status VARCHAR(50),
    to_status VARCHAR(50) NOT NULL,
    changed_by INT,
    comment TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_loan_status_loan (loan_id),
    INDEX idx_loan_status_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FACILITY REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS ahg_loan_facility_report (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    venue_name VARCHAR(255) NOT NULL,
    venue_address TEXT,
    venue_contact_name VARCHAR(255),
    venue_contact_email VARCHAR(255),
    venue_contact_phone VARCHAR(100),

    assessment_date DATE,
    assessed_by INT,

    -- Environmental
    has_climate_control TINYINT(1) DEFAULT 0,
    temperature_min DECIMAL(5,2),
    temperature_max DECIMAL(5,2),
    humidity_min DECIMAL(5,2),
    humidity_max DECIMAL(5,2),
    has_uv_filtering TINYINT(1) DEFAULT 0,
    light_levels_lux INT,

    -- Security
    has_24hr_security TINYINT(1) DEFAULT 0,
    has_cctv TINYINT(1) DEFAULT 0,
    has_alarm_system TINYINT(1) DEFAULT 0,
    has_fire_suppression TINYINT(1) DEFAULT 0,
    fire_suppression_type VARCHAR(100),
    security_notes TEXT,

    -- Display/Storage
    display_case_type VARCHAR(100),
    mounting_method VARCHAR(100),
    barrier_distance DECIMAL(5,2),
    storage_type VARCHAR(100),

    -- Access
    public_access_hours TEXT,
    staff_supervision TINYINT(1) DEFAULT 0,
    photography_allowed TINYINT(1) DEFAULT 1,

    -- Assessment
    overall_rating VARCHAR(63) COMMENT 'excellent, good, acceptable, marginal, unacceptable' DEFAULT 'acceptable',
    recommendations TEXT,
    conditions_required TEXT,

    -- Approval
    approved TINYINT(1) DEFAULT 0,
    approved_by INT,
    approved_date DATETIME,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_facility_loan (loan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_loan_facility_image (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_report_id BIGINT UNSIGNED NOT NULL,

    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    mime_type VARCHAR(100),
    caption TEXT,
    image_type VARCHAR(50) DEFAULT 'other',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (facility_report_id) REFERENCES ahg_loan_facility_report(id) ON DELETE CASCADE,
    INDEX idx_facility_img_report (facility_report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CONDITION REPORTS
-- =====================================================

CREATE TABLE IF NOT EXISTS ahg_loan_condition_report (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    loan_object_id BIGINT UNSIGNED,
    information_object_id INT,

    report_type VARCHAR(53) COMMENT 'pre_loan, post_loan, in_transit, periodic' NOT NULL DEFAULT 'pre_loan',

    examination_date DATETIME NOT NULL,
    examiner_id INT,
    examiner_name VARCHAR(255),
    location VARCHAR(255),

    overall_condition VARCHAR(49) COMMENT 'excellent, good, fair, poor, critical' NOT NULL DEFAULT 'good',
    condition_stable TINYINT(1) DEFAULT 1,

    structural_condition TEXT,
    surface_condition TEXT,

    has_damage TINYINT(1) DEFAULT 0,
    damage_description TEXT,
    has_previous_repairs TINYINT(1) DEFAULT 0,
    repair_description TEXT,
    has_active_deterioration TINYINT(1) DEFAULT 0,
    deterioration_description TEXT,

    height_cm DECIMAL(10,2),
    width_cm DECIMAL(10,2),
    depth_cm DECIMAL(10,2),
    weight_kg DECIMAL(10,2),

    handling_requirements TEXT,
    mounting_requirements TEXT,
    environmental_requirements TEXT,

    treatment_recommendations TEXT,
    display_recommendations TEXT,

    signed_by_lender INT,
    signed_by_borrower INT,
    lender_signature_date DATETIME,
    borrower_signature_date DATETIME,

    pdf_generated TINYINT(1) DEFAULT 0,
    pdf_path VARCHAR(500),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_object_id) REFERENCES ahg_loan_object(id) ON DELETE SET NULL,
    INDEX idx_condition_loan (loan_id),
    INDEX idx_condition_type (report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_loan_condition_image (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    condition_report_id BIGINT UNSIGNED NOT NULL,

    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255),
    mime_type VARCHAR(100),
    image_type VARCHAR(50) DEFAULT 'overall',
    caption TEXT,
    annotation_data JSON,
    view_position VARCHAR(50) DEFAULT 'front',
    sort_order INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (condition_report_id) REFERENCES ahg_loan_condition_report(id) ON DELETE CASCADE,
    INDEX idx_condition_img_report (condition_report_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COURIER/TRANSPORT
-- =====================================================

CREATE TABLE IF NOT EXISTS ahg_loan_courier (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(100),
    address TEXT,
    website VARCHAR(255),

    is_art_specialist TINYINT(1) DEFAULT 0,
    has_climate_control TINYINT(1) DEFAULT 0,
    has_gps_tracking TINYINT(1) DEFAULT 0,
    insurance_coverage DECIMAL(15,2),
    insurance_currency VARCHAR(3) DEFAULT 'ZAR',

    quality_rating DECIMAL(3,2),
    notes TEXT,

    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_courier_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_loan_shipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    courier_id BIGINT UNSIGNED,

    shipment_type VARCHAR(28) COMMENT 'outbound, return' NOT NULL DEFAULT 'outbound',
    shipment_number VARCHAR(100),
    tracking_number VARCHAR(255),
    waybill_number VARCHAR(255),

    origin_address TEXT,
    destination_address TEXT,

    scheduled_pickup DATETIME,
    actual_pickup DATETIME,
    scheduled_delivery DATETIME,
    actual_delivery DATETIME,

    status VARCHAR(98) COMMENT 'planned, picked_up, in_transit, customs, out_for_delivery, delivered, failed, returned' DEFAULT 'planned',

    handling_instructions TEXT,
    special_requirements TEXT,

    shipping_cost DECIMAL(12,2),
    insurance_cost DECIMAL(12,2),
    customs_cost DECIMAL(12,2),
    total_cost DECIMAL(12,2),
    cost_currency VARCHAR(3) DEFAULT 'ZAR',

    notes TEXT,

    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    FOREIGN KEY (courier_id) REFERENCES ahg_loan_courier(id) ON DELETE SET NULL,
    INDEX idx_shipment_loan (loan_id),
    INDEX idx_shipment_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_loan_shipment_event (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shipment_id BIGINT UNSIGNED NOT NULL,

    event_time DATETIME NOT NULL,
    event_type VARCHAR(100),
    location VARCHAR(255),
    description TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (shipment_id) REFERENCES ahg_loan_shipment(id) ON DELETE CASCADE,
    INDEX idx_shipment_event (shipment_id, event_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- NOTIFICATIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS ahg_loan_notification_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    code VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sector VARCHAR(50),

    subject_template VARCHAR(500),
    body_template TEXT,

    trigger_event VARCHAR(100),
    trigger_days_before INT,

    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_notif_code (code),
    INDEX idx_notif_sector (sector)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_loan_notification_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED,

    notification_type VARCHAR(100),
    recipient_email VARCHAR(255),
    recipient_name VARCHAR(255),

    subject VARCHAR(500),
    body TEXT,

    status VARCHAR(42) COMMENT 'pending, sent, failed, bounced' DEFAULT 'pending',
    sent_at DATETIME,
    error_message TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES ahg_loan_notification_template(id) ON DELETE SET NULL,
    INDEX idx_notif_loan (loan_id),
    INDEX idx_notif_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- COST TRACKING
-- =====================================================

CREATE TABLE IF NOT EXISTS ahg_loan_cost (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    loan_id BIGINT UNSIGNED NOT NULL,

    cost_type VARCHAR(50) NOT NULL,
    description VARCHAR(500),

    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'ZAR',

    vendor VARCHAR(255),
    invoice_number VARCHAR(100),
    invoice_date DATE,
    paid TINYINT(1) DEFAULT 0,
    paid_date DATE,

    paid_by VARCHAR(36) COMMENT 'lender, borrower, shared' DEFAULT 'borrower',

    notes TEXT,

    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (loan_id) REFERENCES ahg_loan(id) ON DELETE CASCADE,
    INDEX idx_cost_loan (loan_id),
    INDEX idx_cost_type (cost_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- INSERT DEFAULT DATA
-- =====================================================

-- Default notification templates
INSERT IGNORE INTO ahg_loan_notification_template (code, name, description, subject_template, body_template, trigger_event, trigger_days_before, is_active)
VALUES
('loan_due_30', 'Loan Due in 30 Days', 'Reminder 30 days before due', 'Loan {{loan_number}} Due in 30 Days', 'Dear {{partner_contact_name}},\n\nThis is a reminder that loan {{loan_number}} is due to be returned on {{end_date}}.\n\nPlease begin making arrangements for the return.\n\nBest regards,\n{{institution_name}}', 'due_date', 30, 1),
('loan_due_14', 'Loan Due in 14 Days', 'Reminder 14 days before due', 'Loan {{loan_number}} Due in 14 Days - Action Required', 'Dear {{partner_contact_name}},\n\nLoan {{loan_number}} is due to be returned on {{end_date}}.\n\nPlease ensure arrangements are in place.\n\nBest regards,\n{{institution_name}}', 'due_date', 14, 1),
('loan_due_7', 'Loan Due in 7 Days', 'Final reminder', 'URGENT: Loan {{loan_number}} Due in 7 Days', 'Dear {{partner_contact_name}},\n\nFinal reminder: Loan {{loan_number}} is due on {{end_date}}.\n\nPlease confirm return arrangements.\n\nBest regards,\n{{institution_name}}', 'due_date', 7, 1),
('loan_overdue', 'Loan Overdue', 'Overdue notification', 'OVERDUE: Loan {{loan_number}} - Immediate Action Required', 'Dear {{partner_contact_name}},\n\nLoan {{loan_number}} was due on {{end_date}} and is now overdue.\n\nPlease contact us immediately.\n\nBest regards,\n{{institution_name}}', 'overdue', 0, 1),
('loan_approved', 'Loan Approved', 'Approval notification', 'Loan Request {{loan_number}} Approved', 'Dear {{partner_contact_name}},\n\nYour loan request {{loan_number}} has been approved.\n\nLoan Period: {{start_date}} to {{end_date}}\n\nWe will be in touch regarding next steps.\n\nBest regards,\n{{institution_name}}', 'status_change', 0, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Default couriers (South African)
INSERT IGNORE INTO ahg_loan_courier (company_name, contact_email, is_art_specialist, has_climate_control, has_gps_tracking, notes, is_active)
VALUES
('Mtunzini Group', 'info@mtunzini.co.za', 1, 1, 1, 'Specialist art and heritage logistics in Southern Africa', 1),
('Crown Fine Art', 'southafrica@crownfineart.com', 1, 1, 1, 'International art logistics with SA presence', 1),
('DHL Express', 'info@dhl.co.za', 0, 0, 1, 'General courier with tracking', 1),
('RAM Hand-to-Hand', 'info@ram.co.za', 0, 0, 1, 'Secure courier service', 1),
('The Courier Guy', 'info@thecourierguy.co.za', 0, 0, 1, 'General courier', 1)
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- Plugin registration removed - plugins are enabled manually via:
-- php bin/atom extension:enable ahgLoanPlugin

SET FOREIGN_KEY_CHECKS = 1;
