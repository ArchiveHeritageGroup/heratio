-- ============================================================================
-- ahg-ipsas — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgIPSASPlugin/database/install.sql
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

-- ahgIPSASPlugin Database Schema
-- IPSAS Heritage Asset Management
--
-- Implements International Public Sector Accounting Standards:
-- - IPSAS 17 (Property, Plant & Equipment) for heritage
-- - IPSAS 45 (proposed heritage assets standard)
-- - Aligned with SA GRAP 103

-- Configuration
CREATE TABLE IF NOT EXISTS ipsas_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default configuration
INSERT IGNORE INTO ipsas_config (config_key, config_value, description) VALUES
('default_currency', 'USD', 'Default currency for asset values'),
('financial_year_start', '01-01', 'Financial year start (MM-DD)'),
('depreciation_policy', 'none', 'Default depreciation policy (none|straight_line|reducing_balance)'),
('valuation_frequency_years', '5', 'Frequency of revaluations in years'),
('insurance_review_months', '12', 'Insurance review frequency in months'),
('impairment_threshold_percent', '10', 'Threshold % for impairment recognition'),
('nominal_value', '1.00', 'Nominal value for items not reliably measurable'),
('organization_name', '', 'Organization name for reports'),
('accounting_standard', 'IPSAS', 'Primary accounting standard (IPSAS|GRAP|IFRS)')
ON DUPLICATE KEY UPDATE config_key = config_key;

-- Asset categories (heritage vs operational)
CREATE TABLE IF NOT EXISTS ipsas_asset_category (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    asset_type VARCHAR(40) COMMENT 'heritage, operational, mixed' DEFAULT 'heritage',
    depreciation_policy VARCHAR(49) COMMENT 'none, straight_line, reducing_balance' DEFAULT 'none',
    useful_life_years INT COMMENT 'Default useful life, NULL for heritage',
    account_code VARCHAR(50) COMMENT 'GL account code',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default categories
INSERT IGNORE INTO ipsas_asset_category (code, name, description, asset_type, depreciation_policy) VALUES
('ART', 'Art Collections', 'Paintings, sculptures, artworks', 'heritage', 'none'),
('ARCH', 'Archival Records', 'Historical documents, manuscripts', 'heritage', 'none'),
('HIST', 'Historical Objects', 'Artifacts, antiques, memorabilia', 'heritage', 'none'),
('NAT', 'Natural Heritage', 'Specimens, fossils, geological samples', 'heritage', 'none'),
('BLDG', 'Heritage Buildings', 'Historic structures, monuments', 'heritage', 'none'),
('LIB', 'Library Collections', 'Rare books, special collections', 'heritage', 'none'),
('PHOTO', 'Photographic Collections', 'Historical photographs, negatives', 'heritage', 'none'),
('AV', 'Audio-Visual', 'Film, recordings, oral histories', 'heritage', 'none'),
('EQUIP', 'Equipment', 'Operational equipment', 'operational', 'straight_line')
ON DUPLICATE KEY UPDATE code = code;

-- Main heritage asset register
CREATE TABLE IF NOT EXISTS ipsas_heritage_asset (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_number VARCHAR(50) NOT NULL UNIQUE,
    information_object_id INT COMMENT 'Link to AtoM record',
    category_id BIGINT UNSIGNED,
    title VARCHAR(500) NOT NULL,
    description TEXT,
    location VARCHAR(255),
    repository_id INT COMMENT 'Link to AtoM repository',

    -- Acquisition details
    acquisition_date DATE,
    acquisition_method VARCHAR(75) COMMENT 'purchase, donation, bequest, transfer, found, exchange, unknown' DEFAULT 'unknown',
    acquisition_source VARCHAR(255),
    acquisition_cost DECIMAL(15, 2),
    acquisition_currency VARCHAR(3) DEFAULT 'USD',

    -- Valuation
    valuation_basis VARCHAR(64) COMMENT 'historical_cost, fair_value, nominal, not_recognized' DEFAULT 'nominal',
    current_value DECIMAL(15, 2),
    current_value_currency VARCHAR(3) DEFAULT 'USD',
    current_value_date DATE,

    -- Depreciation (for operational assets only)
    depreciation_policy VARCHAR(49) COMMENT 'none, straight_line, reducing_balance' DEFAULT 'none',
    useful_life_years INT,
    residual_value DECIMAL(15, 2) DEFAULT 0,
    accumulated_depreciation DECIMAL(15, 2) DEFAULT 0,

    -- Insurance
    insured_value DECIMAL(15, 2),
    insurance_policy VARCHAR(100),
    insurance_expiry DATE,

    -- Status
    status VARCHAR(86) COMMENT 'active, on_loan, in_storage, under_conservation, disposed, lost, destroyed' DEFAULT 'active',
    condition_rating VARCHAR(49) COMMENT 'excellent, good, fair, poor, critical' DEFAULT 'good',

    -- Risk assessment
    risk_level VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'low',
    risk_notes TEXT,

    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_repository (repository_id),
    INDEX idx_valuation_basis (valuation_basis),
    FOREIGN KEY (category_id) REFERENCES ipsas_asset_category(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valuation history
CREATE TABLE IF NOT EXISTS ipsas_valuation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    valuation_date DATE NOT NULL,
    valuation_type VARCHAR(64) COMMENT 'initial, revaluation, impairment, reversal, disposal' NOT NULL,
    valuation_basis VARCHAR(66) COMMENT 'historical_cost, fair_value, nominal, replacement_cost' NOT NULL,
    previous_value DECIMAL(15, 2),
    new_value DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    change_amount DECIMAL(15, 2),
    change_percent DECIMAL(8, 4),

    -- Valuer details
    valuer_name VARCHAR(255),
    valuer_qualification VARCHAR(255),
    valuer_type VARCHAR(42) COMMENT 'internal, external, government' DEFAULT 'internal',

    -- Supporting documentation
    valuation_method TEXT COMMENT 'Description of method used',
    market_evidence TEXT,
    comparable_sales TEXT,
    documentation_ref VARCHAR(255),

    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_asset (asset_id),
    INDEX idx_date (valuation_date),
    INDEX idx_type (valuation_type),
    FOREIGN KEY (asset_id) REFERENCES ipsas_heritage_asset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Impairment assessments
CREATE TABLE IF NOT EXISTS ipsas_impairment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    assessment_date DATE NOT NULL,

    -- Indicators of impairment
    physical_damage TINYINT(1) DEFAULT 0,
    obsolescence TINYINT(1) DEFAULT 0,
    decline_in_demand TINYINT(1) DEFAULT 0,
    market_value_decline TINYINT(1) DEFAULT 0,
    other_indicator TINYINT(1) DEFAULT 0,
    indicator_description TEXT,

    -- Values
    carrying_amount DECIMAL(15, 2) NOT NULL,
    recoverable_amount DECIMAL(15, 2),
    impairment_loss DECIMAL(15, 2),

    -- Decision
    impairment_recognized TINYINT(1) DEFAULT 0,
    recognition_date DATE,

    -- Reversal (if previous impairment reversed)
    is_reversal TINYINT(1) DEFAULT 0,
    reversal_amount DECIMAL(15, 2),

    notes TEXT,
    assessed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_asset (asset_id),
    INDEX idx_date (assessment_date),
    FOREIGN KEY (asset_id) REFERENCES ipsas_heritage_asset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insurance records
CREATE TABLE IF NOT EXISTS ipsas_insurance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED COMMENT 'NULL for blanket policies',
    policy_number VARCHAR(100) NOT NULL,
    policy_type VARCHAR(65) COMMENT 'all_risks, named_perils, blanket, transit, exhibition' NOT NULL,
    insurer VARCHAR(255) NOT NULL,

    coverage_start DATE NOT NULL,
    coverage_end DATE NOT NULL,

    sum_insured DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    premium DECIMAL(12, 2),
    deductible DECIMAL(12, 2),

    coverage_details TEXT,
    exclusions TEXT,

    status VARCHAR(55) COMMENT 'active, expired, cancelled, pending_renewal' DEFAULT 'active',
    renewal_reminder_sent TINYINT(1) DEFAULT 0,

    broker_name VARCHAR(255),
    broker_contact VARCHAR(255),

    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_asset (asset_id),
    INDEX idx_policy (policy_number),
    INDEX idx_status (status),
    INDEX idx_expiry (coverage_end),
    FOREIGN KEY (asset_id) REFERENCES ipsas_heritage_asset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Depreciation schedule (for operational assets)
CREATE TABLE IF NOT EXISTS ipsas_depreciation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    financial_year VARCHAR(10) NOT NULL COMMENT 'e.g., 2025',
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    opening_value DECIMAL(15, 2) NOT NULL,
    depreciation_amount DECIMAL(15, 2) NOT NULL,
    closing_value DECIMAL(15, 2) NOT NULL,
    accumulated_depreciation DECIMAL(15, 2) NOT NULL,

    calculation_method VARCHAR(43) COMMENT 'straight_line, reducing_balance' NOT NULL,
    rate_percent DECIMAL(8, 4),

    notes TEXT,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_asset (asset_id),
    INDEX idx_year (financial_year),
    UNIQUE KEY unique_asset_year (asset_id, financial_year),
    FOREIGN KEY (asset_id) REFERENCES ipsas_heritage_asset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Disposal records
CREATE TABLE IF NOT EXISTS ipsas_disposal (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_id BIGINT UNSIGNED NOT NULL,
    disposal_date DATE NOT NULL,
    disposal_method VARCHAR(75) COMMENT 'sale, donation, destruction, loss, theft, transfer, deaccession' NOT NULL,

    carrying_value DECIMAL(15, 2) NOT NULL COMMENT 'Book value at disposal',
    disposal_proceeds DECIMAL(15, 2) DEFAULT 0,
    gain_loss DECIMAL(15, 2),

    recipient VARCHAR(255) COMMENT 'Buyer/recipient if applicable',
    authorization_ref VARCHAR(100),
    authorized_by VARCHAR(255),
    authorization_date DATE,

    reason TEXT,
    documentation_ref VARCHAR(255),

    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_asset (asset_id),
    INDEX idx_date (disposal_date),
    INDEX idx_method (disposal_method),
    FOREIGN KEY (asset_id) REFERENCES ipsas_heritage_asset(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Financial year summary
CREATE TABLE IF NOT EXISTS ipsas_financial_year_summary (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_year VARCHAR(10) NOT NULL UNIQUE,
    year_start DATE NOT NULL,
    year_end DATE NOT NULL,

    -- Opening balances
    opening_total_assets INT DEFAULT 0,
    opening_total_value DECIMAL(15, 2) DEFAULT 0,

    -- Movements
    additions_count INT DEFAULT 0,
    additions_value DECIMAL(15, 2) DEFAULT 0,
    disposals_count INT DEFAULT 0,
    disposals_value DECIMAL(15, 2) DEFAULT 0,
    revaluations_increase DECIMAL(15, 2) DEFAULT 0,
    revaluations_decrease DECIMAL(15, 2) DEFAULT 0,
    impairments DECIMAL(15, 2) DEFAULT 0,
    depreciation DECIMAL(15, 2) DEFAULT 0,

    -- Closing balances
    closing_total_assets INT DEFAULT 0,
    closing_total_value DECIMAL(15, 2) DEFAULT 0,

    status VARCHAR(33) COMMENT 'open, closed, audited' DEFAULT 'open',
    closed_by INT,
    closed_at DATETIME,

    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log
CREATE TABLE IF NOT EXISTS ipsas_audit_log (
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
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
