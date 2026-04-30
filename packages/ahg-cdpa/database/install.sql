-- ============================================================================
-- ahg-cdpa — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgCDPAPlugin/database/install.sql
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

-- ============================================
-- ahgCDPAPlugin Database Schema
-- Zimbabwe Cyber and Data Protection Act [Chapter 12:07]
-- POTRAZ Regulated
-- ============================================

-- Data Controller License (POTRAZ Registration)
CREATE TABLE IF NOT EXISTS cdpa_controller_license (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    license_number VARCHAR(100) NOT NULL COMMENT 'POTRAZ license number',
    tier VARCHAR(38) COMMENT 'tier1, tier2, tier3, tier4' NOT NULL COMMENT 'Tier 1: 50-1000, Tier 2: 1001-10000, Tier 3: 10001-500000, Tier 4: 500000+',
    organization_name VARCHAR(255) NOT NULL,
    registration_date DATE NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    potraz_ref VARCHAR(100) COMMENT 'POTRAZ reference number',
    certificate_path VARCHAR(500) COMMENT 'Path to license certificate document',
    data_subjects_count INT COMMENT 'Estimated number of data subjects',
    renewal_reminder_sent TINYINT(1) DEFAULT 0,
    status VARCHAR(55) COMMENT 'active, expired, suspended, pending_renewal' DEFAULT 'active',
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_number (license_number),
    INDEX idx_expiry_date (expiry_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Protection Officer
CREATE TABLE IF NOT EXISTS cdpa_dpo (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    qualifications TEXT COMMENT 'Professional qualifications',
    hit_cert_number VARCHAR(100) COMMENT 'Harare Institute of Technology certification number',
    appointment_date DATE NOT NULL,
    term_end_date DATE COMMENT 'End of appointment term',
    form_dp2_submitted TINYINT(1) DEFAULT 0 COMMENT 'Form DP2 submitted to POTRAZ',
    form_dp2_date DATE COMMENT 'Date Form DP2 was submitted',
    form_dp2_ref VARCHAR(100) COMMENT 'POTRAZ reference for Form DP2',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Subject Requests (Access, Rectification, Erasure, Object)
CREATE TABLE IF NOT EXISTS cdpa_data_subject_request (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_type VARCHAR(76) COMMENT 'access, rectification, erasure, object, portability, restriction' NOT NULL,
    reference_number VARCHAR(50) NOT NULL COMMENT 'Internal reference number',
    data_subject_name VARCHAR(255) NOT NULL,
    data_subject_email VARCHAR(255),
    data_subject_phone VARCHAR(50),
    data_subject_id_number VARCHAR(50) COMMENT 'National ID or passport number',
    request_date DATE NOT NULL,
    due_date DATE NOT NULL COMMENT '30 days from request date',
    description TEXT COMMENT 'Details of the request',
    status VARCHAR(63) COMMENT 'pending, in_progress, completed, rejected, extended' DEFAULT 'pending',
    completed_date DATE,
    handled_by INT COMMENT 'User ID who handled the request',
    response_notes TEXT,
    rejection_reason TEXT,
    extension_reason TEXT COMMENT 'Reason if deadline was extended',
    verification_method VARCHAR(100) COMMENT 'How identity was verified',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reference (reference_number),
    INDEX idx_status (status),
    INDEX idx_request_type (request_type),
    INDEX idx_due_date (due_date),
    INDEX idx_request_date (request_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Processing Activities Register
CREATE TABLE IF NOT EXISTS cdpa_processing_activity (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Name of processing activity',
    category VARCHAR(100) NOT NULL COMMENT 'Category of data subjects (employees, customers, etc.)',
    data_types TEXT NOT NULL COMMENT 'Types of personal data processed (JSON array)',
    purpose TEXT NOT NULL COMMENT 'Purpose of processing',
    legal_basis VARCHAR(101) COMMENT 'consent, contract, legal_obligation, vital_interest, public_interest, legitimate_interest' NOT NULL,
    storage_location VARCHAR(41) COMMENT 'zimbabwe, international, both' DEFAULT 'zimbabwe',
    international_country VARCHAR(100) COMMENT 'Country if stored internationally',
    retention_period VARCHAR(100) COMMENT 'How long data is retained',
    safeguards TEXT COMMENT 'Security safeguards description',
    cross_border TINYINT(1) DEFAULT 0 COMMENT 'Involves cross-border transfer',
    cross_border_safeguards TEXT COMMENT 'Safeguards for cross-border transfer',
    automated_decision TINYINT(1) DEFAULT 0 COMMENT 'Involves automated decision-making',
    children_data TINYINT(1) DEFAULT 0 COMMENT 'Processes children data',
    biometric_data TINYINT(1) DEFAULT 0 COMMENT 'Processes biometric data',
    health_data TINYINT(1) DEFAULT 0 COMMENT 'Processes health data',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Privacy Impact Assessment (DPIA)
CREATE TABLE IF NOT EXISTS cdpa_dpia (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    processing_activity_id BIGINT UNSIGNED COMMENT 'Link to processing activity',
    description TEXT,
    necessity_assessment TEXT COMMENT 'Why is this processing necessary?',
    risk_level VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'medium',
    assessment_date DATE NOT NULL,
    assessor_name VARCHAR(255),
    next_review_date DATE,
    status VARCHAR(51) COMMENT 'draft, in_progress, completed, approved' DEFAULT 'draft',
    findings_json JSON COMMENT 'Detailed risk findings',
    risks_identified TEXT,
    mitigation_measures TEXT,
    residual_risk_level VARCHAR(39) COMMENT 'low, medium, high, critical',
    dpo_approval TINYINT(1) DEFAULT 0,
    dpo_approval_date DATE,
    dpo_comments TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_risk_level (risk_level),
    INDEX idx_next_review (next_review_date),
    FOREIGN KEY (processing_activity_id) REFERENCES cdpa_processing_activity(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Consent Records
CREATE TABLE IF NOT EXISTS cdpa_consent (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_subject_name VARCHAR(255) NOT NULL,
    data_subject_email VARCHAR(255),
    data_subject_id VARCHAR(100) COMMENT 'ID or unique identifier',
    purpose VARCHAR(255) NOT NULL COMMENT 'Purpose consent was given for',
    processing_activity_id BIGINT UNSIGNED COMMENT 'Link to processing activity',
    consent_date DATETIME NOT NULL,
    consent_method VARCHAR(47) COMMENT 'written, electronic, verbal, opt_in' DEFAULT 'electronic',
    withdrawal_date DATETIME,
    withdrawal_reason TEXT,
    is_biometric TINYINT(1) DEFAULT 0 COMMENT 'Consent for biometric data',
    is_children TINYINT(1) DEFAULT 0 COMMENT 'Consent for children data (guardian consent)',
    guardian_name VARCHAR(255) COMMENT 'Guardian name if children consent',
    evidence_path VARCHAR(500) COMMENT 'Path to consent evidence document',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data_subject (data_subject_id),
    INDEX idx_is_active (is_active),
    INDEX idx_consent_date (consent_date),
    FOREIGN KEY (processing_activity_id) REFERENCES cdpa_processing_activity(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data Breach Register
CREATE TABLE IF NOT EXISTS cdpa_breach (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_number VARCHAR(50) NOT NULL,
    incident_date DATETIME NOT NULL COMMENT 'When the breach occurred',
    discovery_date DATETIME NOT NULL COMMENT 'When breach was discovered',
    description TEXT NOT NULL,
    breach_type VARCHAR(99) COMMENT 'unauthorized_access, data_loss, data_theft, accidental_disclosure, system_breach, other' NOT NULL,
    data_affected TEXT COMMENT 'Types of data affected',
    records_affected INT COMMENT 'Number of records affected',
    data_subjects_affected INT COMMENT 'Number of individuals affected',
    severity VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'medium',
    potraz_notified TINYINT(1) DEFAULT 0,
    potraz_notified_date DATETIME COMMENT 'Must be within 72 hours',
    potraz_reference VARCHAR(100),
    subjects_notified TINYINT(1) DEFAULT 0,
    subjects_notified_date DATETIME,
    notification_method TEXT COMMENT 'How subjects were notified',
    root_cause TEXT,
    remediation TEXT COMMENT 'Actions taken to address breach',
    prevention_measures TEXT COMMENT 'Measures to prevent recurrence',
    status VARCHAR(55) COMMENT 'investigating, contained, resolved, ongoing' DEFAULT 'investigating',
    reported_by INT,
    closed_date DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_reference (reference_number),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_incident_date (incident_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuration Settings
CREATE TABLE IF NOT EXISTS cdpa_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(42) COMMENT 'string, integer, boolean, json' DEFAULT 'string',
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Log for CDPA actions
CREATE TABLE IF NOT EXISTS cdpa_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED,
    user_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA: Default Configuration
-- ============================================

INSERT IGNORE INTO cdpa_config (setting_key, setting_value, setting_type, description) VALUES
('response_deadline_days', '30', 'integer', 'Default deadline for data subject requests (days)'),
('license_reminder_days', '90', 'integer', 'Days before license expiry to send reminder'),
('breach_notification_hours', '72', 'integer', 'Hours within which POTRAZ must be notified of breach'),
('dpia_review_months', '12', 'integer', 'Months between DPIA reviews'),
('organization_name', '', 'string', 'Organization name for reports'),
('organization_address', '', 'string', 'Organization address'),
('dpo_email', '', 'string', 'DPO notification email'),
('admin_emails', '[]', 'json', 'Admin notification emails')
ON DUPLICATE KEY UPDATE updated_at = NOW();

SET FOREIGN_KEY_CHECKS = 1;
