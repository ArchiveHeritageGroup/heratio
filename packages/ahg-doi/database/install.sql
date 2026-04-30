-- ============================================================================
-- ahg-doi — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDoiPlugin/database/install.sql
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
-- ahgDoiPlugin Database Schema
-- DOI Integration via DataCite
-- ============================================

-- DOI Records
CREATE TABLE IF NOT EXISTS ahg_doi (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    doi VARCHAR(255) NOT NULL COMMENT 'Full DOI string (10.xxxxx/xxxxxx)',
    doi_url VARCHAR(500) GENERATED ALWAYS AS (CONCAT('https://doi.org/', doi)) STORED,
    status VARCHAR(56) COMMENT 'draft, registered, findable, failed, deleted' NOT NULL DEFAULT 'draft',
    minted_at DATETIME,
    minted_by INT COMMENT 'User who minted',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    datacite_response JSON COMMENT 'Last DataCite API response',
    metadata_json JSON COMMENT 'Cached DataCite metadata',
    last_sync_at DATETIME COMMENT 'Last metadata sync to DataCite',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_doi (doi),
    UNIQUE KEY uk_object (information_object_id),
    INDEX idx_status (status),
    INDEX idx_minted_at (minted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DataCite Configuration per Repository
CREATE TABLE IF NOT EXISTS ahg_doi_config (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = global default',
    datacite_repo_id VARCHAR(100) NOT NULL COMMENT 'DataCite repository ID',
    datacite_prefix VARCHAR(50) NOT NULL COMMENT 'DOI prefix (e.g., 10.12345)',
    datacite_password VARCHAR(255) COMMENT 'Encrypted password',
    datacite_url VARCHAR(255) DEFAULT 'https://api.datacite.org' COMMENT 'API endpoint',
    environment VARCHAR(28) COMMENT 'test, production' NOT NULL DEFAULT 'test',
    auto_mint TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Auto-mint on publish',
    auto_mint_levels JSON COMMENT 'Levels to auto-mint: ["fonds", "collection", "item"]',
    require_digital_object TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Only mint if has digital object',
    default_publisher VARCHAR(255) COMMENT 'Default publisher name',
    default_resource_type VARCHAR(100) DEFAULT 'Text' COMMENT 'Default DataCite resourceType',
    suffix_pattern VARCHAR(100) DEFAULT '{repository_code}/{year}/{object_id}' COMMENT 'Pattern for DOI suffix',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_repository (repository_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOI Minting Queue
CREATE TABLE IF NOT EXISTS ahg_doi_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    action VARCHAR(40) COMMENT 'mint, update, delete, verify' NOT NULL DEFAULT 'mint',
    status VARCHAR(50) COMMENT 'pending, processing, completed, failed' NOT NULL DEFAULT 'pending',
    priority INT NOT NULL DEFAULT 100 COMMENT 'Higher = processed first',
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    last_error TEXT,
    scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME,
    completed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_priority (status, priority DESC),
    INDEX idx_object_id (information_object_id),
    INDEX idx_scheduled_at (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOI Metadata Mapping (customize DataCite mapping)
CREATE TABLE IF NOT EXISTS ahg_doi_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = global default',
    datacite_field VARCHAR(100) NOT NULL COMMENT 'DataCite schema field',
    source_type VARCHAR(53) COMMENT 'field, property, note, constant, template' NOT NULL DEFAULT 'field',
    source_value VARCHAR(255) NOT NULL COMMENT 'AtoM field name, property type, or constant value',
    transformation VARCHAR(100) COMMENT 'Optional transformation function',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    fallback_value VARCHAR(255) COMMENT 'Value if source is empty',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    INDEX idx_datacite_field (datacite_field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DOI Activity Log
CREATE TABLE IF NOT EXISTS ahg_doi_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doi_id BIGINT UNSIGNED,
    information_object_id INT,
    action VARCHAR(50) NOT NULL COMMENT 'minted, updated, deleted, verified, failed',
    status_before VARCHAR(50),
    status_after VARCHAR(50),
    details JSON,
    performed_by INT,
    performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_doi_id (doi_id),
    INDEX idx_object_id (information_object_id),
    INDEX idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA: Default Metadata Mapping
-- ============================================

-- Required DataCite fields mapping to AtoM ISAD-G
INSERT IGNORE INTO ahg_doi_mapping (datacite_field, source_type, source_value, is_required, fallback_value, sort_order) VALUES
-- Required fields
('creators', 'field', 'creators', 1, 'Unknown', 1),
('title', 'field', 'title', 1, 'Untitled', 2),
('publisher', 'constant', '', 0, NULL, 3),
('publicationYear', 'template', '{date_year}', 1, NULL, 4),
('resourceType', 'constant', 'Text', 1, 'Text', 5),
-- Recommended fields
('subjects', 'field', 'subject_access_points', 0, NULL, 10),
('descriptions', 'field', 'scope_and_content', 0, NULL, 11),
('dates', 'field', 'dates', 0, NULL, 12),
('language', 'field', 'language', 0, NULL, 13),
('alternateIdentifiers', 'field', 'identifier', 0, NULL, 14),
('relatedIdentifiers', 'field', 'related_units_of_description', 0, NULL, 15),
('sizes', 'field', 'extent_and_medium', 0, NULL, 16),
('formats', 'constant', 'application/pdf', 0, NULL, 17),
('rights', 'field', 'access_conditions', 0, NULL, 18),
('geoLocations', 'field', 'place_access_points', 0, NULL, 19);

-- Default global configuration (placeholder)
INSERT IGNORE INTO ahg_doi_config (repository_id, datacite_repo_id, datacite_prefix, datacite_url, environment, default_publisher, default_resource_type) VALUES
(NULL, 'DATACITE.EXAMPLE', '10.12345', 'https://api.test.datacite.org', 'test', 'Archive Repository', 'Text');

SET FOREIGN_KEY_CHECKS = 1;
