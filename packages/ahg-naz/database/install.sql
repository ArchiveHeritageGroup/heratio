-- ============================================================================
-- ahg-naz — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgNAZPlugin/database/install.sql
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

-- ahgNAZPlugin Database Schema
-- National Archives of Zimbabwe Act [Chapter 25:06] Compliance
--
-- Key provisions:
-- - 25-year closure period for restricted records (Section 10)
-- - Research permits required for foreign researchers (US$200 fee)
-- - Records schedules for government agencies
-- - Transfer of records to NAZ after retention period

-- Configuration table
CREATE TABLE IF NOT EXISTS naz_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration
INSERT IGNORE INTO naz_config (config_key, config_value, description) VALUES
('closure_period_years', '25', 'Default closure period in years per Section 10'),
('foreign_permit_fee_usd', '200', 'Research permit fee for foreign researchers (US$)'),
('local_permit_fee_usd', '0', 'Research permit fee for local researchers (US$)'),
('permit_validity_months', '12', 'Research permit validity period in months'),
('transfer_reminder_months', '6', 'Months before due date to send transfer reminder'),
('naz_repository_name', 'National Archives of Zimbabwe', 'Name of the National Archives'),
('director_name', '', 'Name of the Director of National Archives'),
('naz_email', 'info@archives.gov.zw', 'NAZ contact email'),
('naz_phone', '+263 242 792741', 'NAZ contact phone')
ON DUPLICATE KEY UPDATE config_key = config_key;

-- Closure periods for restricted records
CREATE TABLE IF NOT EXISTS naz_closure_period (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    closure_type VARCHAR(55) COMMENT 'standard, extended, indefinite, ministerial' NOT NULL DEFAULT 'standard',
    closure_reason VARCHAR(255),
    start_date DATE NOT NULL,
    end_date DATE,
    years INT DEFAULT 25,
    authority_reference VARCHAR(100) COMMENT 'Ministerial order or authority reference',
    review_date DATE,
    status VARCHAR(47) COMMENT 'active, expired, extended, released' DEFAULT 'active',
    release_notes TEXT,
    released_by INT,
    released_at DATETIME,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_io_closure (information_object_id, status),
    INDEX idx_end_date (end_date),
    INDEX idx_status (status),
    INDEX idx_closure_type (closure_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Researchers registry
CREATE TABLE IF NOT EXISTS naz_researcher (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT 'Link to AtoM user if applicable',
    researcher_type VARCHAR(41) COMMENT 'local, foreign, institutional' NOT NULL DEFAULT 'local',
    title VARCHAR(20),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    nationality VARCHAR(100),
    passport_number VARCHAR(50),
    national_id VARCHAR(50),
    institution VARCHAR(255),
    position VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    research_interests TEXT,
    registration_date DATE NOT NULL,
    status VARCHAR(52) COMMENT 'active, inactive, suspended, blacklisted' DEFAULT 'active',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_researcher_type (researcher_type),
    INDEX idx_status (status),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Research permits
CREATE TABLE IF NOT EXISTS naz_research_permit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permit_number VARCHAR(50) NOT NULL UNIQUE,
    researcher_id BIGINT UNSIGNED NOT NULL,
    permit_type VARCHAR(40) COMMENT 'general, restricted, special' NOT NULL DEFAULT 'general',
    research_topic VARCHAR(500) NOT NULL,
    research_purpose TEXT,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    fee_amount DECIMAL(10, 2) DEFAULT 0,
    fee_currency VARCHAR(3) DEFAULT 'USD',
    fee_paid TINYINT(1) DEFAULT 0,
    fee_receipt VARCHAR(100),
    payment_date DATE,
    approved_by INT,
    approved_date DATE,
    status VARCHAR(65) COMMENT 'pending, approved, rejected, active, expired, revoked' DEFAULT 'pending',
    rejection_reason TEXT,
    collections_access JSON COMMENT 'Array of collection/repository IDs permitted',
    restrictions TEXT COMMENT 'Any special restrictions on access',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_researcher (researcher_id),
    INDEX idx_status (status),
    INDEX idx_end_date (end_date),
    INDEX idx_permit_type (permit_type),
    FOREIGN KEY (researcher_id) REFERENCES naz_researcher(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Records schedules (retention schedules for government agencies)
CREATE TABLE IF NOT EXISTS naz_records_schedule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_number VARCHAR(50) NOT NULL UNIQUE,
    agency_name VARCHAR(255) NOT NULL,
    agency_code VARCHAR(50),
    record_series VARCHAR(255) NOT NULL,
    description TEXT,
    retention_period_active INT NOT NULL COMMENT 'Years in active storage',
    retention_period_semi INT DEFAULT 0 COMMENT 'Years in semi-active storage',
    disposal_action VARCHAR(48) COMMENT 'destroy, transfer, review, permanent' NOT NULL,
    legal_authority TEXT COMMENT 'Legal basis for retention',
    classification VARCHAR(51) COMMENT 'vital, important, useful, non-essential' DEFAULT 'useful',
    access_restriction VARCHAR(50) COMMENT 'open, restricted, confidential, secret' DEFAULT 'open',
    approved_by VARCHAR(255),
    approval_date DATE,
    effective_date DATE,
    review_date DATE,
    status VARCHAR(58) COMMENT 'draft, pending, approved, superseded, archived' DEFAULT 'draft',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agency (agency_name),
    INDEX idx_status (status),
    INDEX idx_disposal (disposal_action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Records transfers to NAZ
CREATE TABLE IF NOT EXISTS naz_transfer (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(50) NOT NULL UNIQUE,
    transferring_agency VARCHAR(255) NOT NULL,
    agency_contact VARCHAR(255),
    agency_email VARCHAR(255),
    agency_phone VARCHAR(50),
    schedule_id BIGINT UNSIGNED COMMENT 'Link to records schedule',
    transfer_type VARCHAR(50) COMMENT 'scheduled, voluntary, rescue, donation' DEFAULT 'scheduled',
    description TEXT,
    date_range_start DATE,
    date_range_end DATE,
    quantity_linear_metres DECIMAL(10, 2),
    quantity_boxes INT,
    quantity_items INT,
    contains_restricted TINYINT(1) DEFAULT 0,
    restriction_details TEXT,
    accession_number VARCHAR(100) COMMENT 'NAZ accession number if assigned',
    proposed_date DATE,
    actual_date DATE,
    received_by INT,
    status VARCHAR(87) COMMENT 'proposed, scheduled, in_transit, received, accessioned, rejected, cancelled' DEFAULT 'proposed',
    rejection_reason TEXT,
    location_assigned VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_agency (transferring_agency),
    INDEX idx_status (status),
    INDEX idx_proposed_date (proposed_date),
    FOREIGN KEY (schedule_id) REFERENCES naz_records_schedule(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Transfer items (individual series/items within a transfer)
CREATE TABLE IF NOT EXISTS naz_transfer_item (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transfer_id BIGINT UNSIGNED NOT NULL,
    series_title VARCHAR(500) NOT NULL,
    description TEXT,
    date_range VARCHAR(100),
    quantity INT DEFAULT 1,
    format VARCHAR(100),
    condition_notes TEXT,
    access_restriction VARCHAR(42) COMMENT 'open, restricted, confidential' DEFAULT 'open',
    restriction_end_date DATE,
    information_object_id INT COMMENT 'Link to AtoM record if created',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_transfer (transfer_id),
    FOREIGN KEY (transfer_id) REFERENCES naz_transfer(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Protected records register (Section 12 - records exempt from access)
CREATE TABLE IF NOT EXISTS naz_protected_record (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    protection_type VARCHAR(58) COMMENT 'cabinet, security, personal, legal, commercial' NOT NULL,
    protection_reason TEXT NOT NULL,
    authority_reference VARCHAR(255) COMMENT 'Minister/authority reference',
    protection_start DATE NOT NULL,
    protection_end DATE COMMENT 'NULL = indefinite',
    review_date DATE,
    status VARCHAR(35) COMMENT 'active, expired, lifted' DEFAULT 'active',
    lifted_by INT,
    lifted_date DATE,
    lifted_reason TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_io_protection (information_object_id, protection_type, status),
    INDEX idx_status (status),
    INDEX idx_protection_type (protection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log for NAZ compliance actions
CREATE TABLE IF NOT EXISTS naz_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    old_value JSON,
    new_value JSON,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action_type),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Research visit log (track researcher visits)
CREATE TABLE IF NOT EXISTS naz_research_visit (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permit_id BIGINT UNSIGNED NOT NULL,
    researcher_id BIGINT UNSIGNED NOT NULL,
    visit_date DATE NOT NULL,
    check_in_time TIME,
    check_out_time TIME,
    materials_requested TEXT,
    materials_provided TEXT,
    reading_room VARCHAR(100),
    supervisor_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permit (permit_id),
    INDEX idx_researcher (researcher_id),
    INDEX idx_visit_date (visit_date),
    FOREIGN KEY (permit_id) REFERENCES naz_research_permit(id) ON DELETE CASCADE,
    FOREIGN KEY (researcher_id) REFERENCES naz_researcher(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
