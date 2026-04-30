-- ============================================================================
-- ahg-accession-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgAccessionManagePlugin/database/install.sql
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

-- ahgAccessionManagePlugin: Accession V2 Tables
-- Issue #200: First-Class Accessions (M1 + M2 + M3)

-- =============================================================================
-- M1: INTAKE QUEUE (5 tables)
-- =============================================================================

-- 1. accession_v2: 1:1 extension of base accession table
CREATE TABLE IF NOT EXISTS accession_v2 (
    accession_id INT NOT NULL PRIMARY KEY,
    status VARCHAR(20) NOT NULL DEFAULT 'draft'
        COMMENT 'draft, submitted, under_review, accepted, rejected, returned',
    priority VARCHAR(10) NOT NULL DEFAULT 'normal'
        COMMENT 'low, normal, high, urgent',
    assigned_to INT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,
    intake_notes TEXT NULL,
    donor_agreement_id INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_tenant (tenant_id)
);

-- 2. accession_intake_checklist: configurable checklist items per accession
CREATE TABLE IF NOT EXISTS accession_intake_checklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    is_completed TINYINT(1) NOT NULL DEFAULT 0,
    completed_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    sort_order INT NOT NULL DEFAULT 0,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 3. accession_intake_template: reusable checklist templates
CREATE TABLE IF NOT EXISTS accession_intake_template (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    items JSON NOT NULL COMMENT 'Array of {label, sort_order}',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. accession_timeline: event log for chain-of-custody
CREATE TABLE IF NOT EXISTS accession_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    event_type VARCHAR(30) NOT NULL
        COMMENT 'created, submitted, assigned, reviewed, accepted, rejected, returned, appraised, containerized, rights_assigned, deaccessioned, note',
    actor_id INT UNSIGNED NULL,
    description TEXT NULL,
    metadata JSON NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id),
    INDEX idx_tenant (tenant_id)
);

-- 5. accession_attachment: file attachments (deed of gift, photos, etc.)
CREATE TABLE IF NOT EXISTS accession_attachment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    category VARCHAR(30) NOT NULL DEFAULT 'general'
        COMMENT 'general, deed_of_gift, photo, correspondence, inventory, other',
    description TEXT NULL,
    uploaded_by INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id)
);

-- =============================================================================
-- M2: APPRAISAL & VALUATION (4 tables)
-- =============================================================================

-- 6. accession_appraisal: formal appraisal record
CREATE TABLE IF NOT EXISTS accession_appraisal (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    appraiser_id INT UNSIGNED NULL,
    appraisal_type VARCHAR(20) NOT NULL DEFAULT 'archival'
        COMMENT 'archival, monetary, insurance, historical, research',
    monetary_value DECIMAL(15,2) NULL,
    currency VARCHAR(3) NULL DEFAULT 'ZAR',
    significance VARCHAR(20) NULL
        COMMENT 'low, medium, high, exceptional, national_significance',
    recommendation VARCHAR(20) NOT NULL DEFAULT 'pending'
        COMMENT 'pending, accept, reject, partial, defer',
    summary TEXT NULL,
    detailed_notes TEXT NULL,
    appraised_at DATETIME NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id)
);

-- 7. accession_appraisal_criterion: scoring criteria per appraisal
CREATE TABLE IF NOT EXISTS accession_appraisal_criterion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appraisal_id INT UNSIGNED NOT NULL,
    criterion_name VARCHAR(100) NOT NULL,
    score INT NULL COMMENT '1-5 scale',
    weight DECIMAL(3,2) NOT NULL DEFAULT 1.00,
    notes TEXT NULL
);

-- 8. accession_appraisal_template: reusable appraisal criteria templates
CREATE TABLE IF NOT EXISTS accession_appraisal_template (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    criteria JSON NOT NULL COMMENT 'Array of {criterion_name, weight, description}',
    sector VARCHAR(20) NULL COMMENT 'archive, library, museum, gallery, dam',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 9. accession_valuation_history: track value changes over time (GRAP 103/IPSAS 45)
CREATE TABLE IF NOT EXISTS accession_valuation_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    valuation_type VARCHAR(20) NOT NULL
        COMMENT 'initial, revaluation, impairment, disposal',
    monetary_value DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'ZAR',
    valuation_date DATE NOT NULL,
    valuer VARCHAR(255) NULL,
    method VARCHAR(50) NULL
        COMMENT 'cost, market, income, replacement, nominal',
    reference_document VARCHAR(255) NULL,
    notes TEXT NULL,
    recorded_by INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id)
);

-- =============================================================================
-- M3: CONTAINERS & RIGHTS (6 tables)
-- =============================================================================

-- 10. accession_container: physical container tracking
CREATE TABLE IF NOT EXISTS accession_container (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    container_type VARCHAR(30) NOT NULL DEFAULT 'box'
        COMMENT 'box, folder, envelope, crate, tube, flat_file, digital_media, other',
    label VARCHAR(255) NOT NULL,
    barcode VARCHAR(100) NULL,
    location_id INT UNSIGNED NULL,
    location_detail VARCHAR(255) NULL,
    dimensions VARCHAR(100) NULL,
    item_count INT UNSIGNED NULL,
    weight_kg DECIMAL(8,2) NULL,
    condition_status VARCHAR(20) NULL
        COMMENT 'excellent, good, fair, poor, critical',
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id),
    INDEX idx_barcode (barcode),
    INDEX idx_location (location_id)
);

-- 11. accession_container_item: items within containers
CREATE TABLE IF NOT EXISTS accession_container_item (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    container_id INT UNSIGNED NOT NULL,
    information_object_id INT NULL,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    format VARCHAR(50) NULL,
    date_range VARCHAR(100) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 12. accession_rights: rights attached to accession (pre-arrangement)
CREATE TABLE IF NOT EXISTS accession_rights (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    rights_basis VARCHAR(30) NOT NULL
        COMMENT 'copyright, license, statute, policy, donor, other',
    rights_holder VARCHAR(255) NULL,
    rights_holder_id INT UNSIGNED NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    restriction_type VARCHAR(20) NOT NULL DEFAULT 'none'
        COMMENT 'none, restricted, conditional, closed, partial',
    conditions TEXT NULL,
    grant_act VARCHAR(30) NULL
        COMMENT 'publish, disseminate, modify, migrate, replicate, delete, discover',
    grant_restriction VARCHAR(20) NULL
        COMMENT 'allow, disallow, conditional',
    notes TEXT NULL,
    inherit_to_children TINYINT(1) NOT NULL DEFAULT 1,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accession (accession_id)
);

-- 13. accession_rights_inherited: tracks rights pushed to child IOs
CREATE TABLE IF NOT EXISTS accession_rights_inherited (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rights_id INT UNSIGNED NOT NULL,
    information_object_id INT NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_by INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NULL,
    INDEX idx_rights (rights_id),
    INDEX idx_io (information_object_id)
);

-- 14. accession_numbering_sequence: per-repo accession number sequences
CREATE TABLE IF NOT EXISTS accession_numbering_sequence (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT UNSIGNED NULL,
    mask VARCHAR(255) NOT NULL DEFAULT '{YEAR}-{SEQ:5}'
        COMMENT 'Token pattern: {YEAR}, {MONTH}, {DAY}, {REPO}, {SEQ:n}',
    last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
    last_year INT UNSIGNED NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_repo_tenant (repository_id, tenant_id)
);

-- 15. accession_config: per-tenant accession settings
CREATE TABLE IF NOT EXISTS accession_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NULL,
    tenant_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_key_tenant (config_key, tenant_id)
);

-- =============================================================================
-- SEED DATA
-- =============================================================================

-- Default intake checklist template
INSERT IGNORE INTO accession_intake_template (name, description, items, is_default) VALUES
('Standard Intake', 'Default checklist for archival accessions', '[
    {"label": "Donor agreement signed", "sort_order": 1},
    {"label": "Preliminary inventory completed", "sort_order": 2},
    {"label": "Physical condition assessed", "sort_order": 3},
    {"label": "Restrictions identified", "sort_order": 4},
    {"label": "Storage location assigned", "sort_order": 5},
    {"label": "Accession number assigned", "sort_order": 6}
]', 1);

-- Default appraisal template (archival)
INSERT IGNORE INTO accession_appraisal_template (name, description, criteria, sector, is_default) VALUES
('Archival Appraisal', 'Standard archival appraisal criteria', '[
    {"criterion_name": "Evidential value", "weight": 1.0, "description": "Value as evidence of organization functions and activities"},
    {"criterion_name": "Informational value", "weight": 1.0, "description": "Value of information content about persons, places, subjects"},
    {"criterion_name": "Intrinsic value", "weight": 0.8, "description": "Inherent worth based on age, uniqueness, physical form"},
    {"criterion_name": "Research potential", "weight": 0.8, "description": "Anticipated use by researchers"},
    {"criterion_name": "Institutional relevance", "weight": 1.0, "description": "Alignment with collection development policy"},
    {"criterion_name": "Physical condition", "weight": 0.6, "description": "State of preservation and conservation needs"},
    {"criterion_name": "Completeness", "weight": 0.7, "description": "Degree to which records are complete and comprehensive"}
]', 'archive', 1);

-- Default accession config
INSERT IGNORE INTO accession_config (config_key, config_value) VALUES
('numbering_mask', '{YEAR}-{SEQ:5}'),
('auto_assign_enabled', '0'),
('require_donor_agreement', '0'),
('require_appraisal', '0'),
('default_priority', 'normal'),
('intake_checklist_template_id', '1'),
('appraisal_template_id', '1'),
('allow_container_barcodes', '1'),
('rights_inheritance_enabled', '1');

SET FOREIGN_KEY_CHECKS = 1;
