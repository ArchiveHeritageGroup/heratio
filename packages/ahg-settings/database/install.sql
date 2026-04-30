-- ============================================================================
-- ahg-settings — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgSettingsPlugin/database/install.sql
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

-- Numbering Scheme Tables for GLAM/DAM Sectors
-- Version: 1.0.0

-- Numbering scheme definitions
CREATE TABLE IF NOT EXISTS numbering_scheme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    sector VARCHAR(55) COMMENT 'archive, library, museum, gallery, dam, all' NOT NULL,
    pattern VARCHAR(255) NOT NULL,
    description TEXT,

    -- Sequence settings
    current_sequence BIGINT DEFAULT 0,
    sequence_reset VARCHAR(34) COMMENT 'never, yearly, monthly' DEFAULT 'never',
    last_reset_date DATE,
    fill_gaps TINYINT(1) DEFAULT 0,

    -- Validation
    validation_regex VARCHAR(255),
    allow_manual_override TINYINT(1) DEFAULT 1,

    -- Status
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_sector (sector),
    INDEX idx_sector_default (sector, is_default),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Track used numbers for gap detection and duplicate prevention
CREATE TABLE IF NOT EXISTS numbering_sequence_used (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheme_id INT NOT NULL,
    sequence_number BIGINT NOT NULL,
    generated_reference VARCHAR(255) NOT NULL,
    object_id INT,
    object_type VARCHAR(50) DEFAULT 'information_object',
    year_context YEAR,
    month_context TINYINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY idx_scheme_seq_year (scheme_id, sequence_number, year_context),
    UNIQUE KEY idx_reference (generated_reference),
    INDEX idx_object (object_id, object_type),
    FOREIGN KEY (scheme_id) REFERENCES numbering_scheme(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scheme assignments per repository (optional override)
CREATE TABLE IF NOT EXISTS numbering_scheme_repository (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheme_id INT NOT NULL,
    repository_id INT NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY idx_repo_scheme (repository_id, scheme_id),
    FOREIGN KEY (scheme_id) REFERENCES numbering_scheme(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default schemes for each sector
INSERT IGNORE INTO numbering_scheme (name, sector, pattern, description, is_default, is_active) VALUES
('Archive Standard', 'archive', '{REPO}/{FONDS}/{SEQ:4}', 'Hierarchical archival reference following arrangement', 1, 1),
('Archive Year-Based', 'archive', '{YEAR}/{SEQ:4}', 'Year-based sequential numbering', 0, 1),
('Archive Simple', 'archive', 'ARCH-{SEQ:5}', 'Simple sequential with prefix', 0, 1),

('Library Accession', 'library', 'LIB{YEAR}{SEQ:5}', 'Standard library accession numbering', 1, 1),
('Library Barcode', 'library', '3{SEQ:12}', 'Barcode-style numbering', 0, 1),
('Library Call Number', 'library', '{PREFIX}/{YEAR}/{SEQ:4}', 'Call number style with year', 0, 1),

('Museum Object Number', 'museum', '{YEAR}.{SEQ:4}', 'Spectrum standard object numbering', 1, 1),
('Museum Department', 'museum', '{DEPT}-{YEAR}-{SEQ:4}', 'Department-prefixed numbering', 0, 1),
('Museum Accession Lot', 'museum', '{YEAR}/{SEQ:3}/{ITEM}', 'Accession lot and item numbering', 0, 1),

('Gallery Artwork', 'gallery', 'GAL-{SEQ:6}', 'Simple gallery artwork numbering', 1, 1),
('Gallery Year-Based', 'gallery', 'GAL-{YEAR}-{SEQ:4}', 'Year-based gallery numbering', 0, 1),

('DAM Asset ID', 'dam', 'DAM-{YEAR}-{SEQ:6}', 'Standard DAM asset identifier', 1, 1),
('DAM Media Type', 'dam', '{TYPE}-{SEQ:6}', 'Media type prefixed numbering', 0, 1),
('DAM Project', 'dam', '{PROJECT}-{SEQ:4}', 'Project-based numbering', 0, 1)
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET FOREIGN_KEY_CHECKS = 1;
