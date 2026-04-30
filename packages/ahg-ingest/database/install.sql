-- ============================================================================
-- ahg-ingest — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgIngestPlugin/database/install.sql
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
-- ahgIngestPlugin Database Schema
-- =====================================================

-- Ingest sessions (wizard state persistence)
CREATE TABLE IF NOT EXISTS ingest_session (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(500),
    sector VARCHAR(50) COMMENT 'archive, museum, library, gallery, dam' NOT NULL DEFAULT 'archive',
    standard VARCHAR(47) COMMENT 'isadg, dc, spectrum, cco, rad, dacs' NOT NULL DEFAULT 'isadg',
    repository_id INT DEFAULT NULL,
    parent_id INT DEFAULT NULL,
    parent_placement VARCHAR(51) COMMENT 'existing, new, top_level, csv_hierarchy' DEFAULT 'top_level',
    new_parent_title VARCHAR(500) DEFAULT NULL,
    new_parent_level VARCHAR(100) DEFAULT NULL,
    output_create_records TINYINT(1) DEFAULT 1,
    output_generate_sip TINYINT(1) DEFAULT 0,
    output_generate_aip TINYINT(1) DEFAULT 0,
    output_generate_dip TINYINT(1) DEFAULT 0,
    output_sip_path VARCHAR(1000) DEFAULT NULL,
    output_aip_path VARCHAR(1000) DEFAULT NULL,
    output_dip_path VARCHAR(1000) DEFAULT NULL,
    derivative_thumbnails TINYINT(1) DEFAULT 1,
    derivative_reference TINYINT(1) DEFAULT 1,
    derivative_normalize_format VARCHAR(50) DEFAULT NULL,
    security_classification_id INT DEFAULT NULL,
    process_ner TINYINT(1) DEFAULT 0,
    process_ocr TINYINT(1) DEFAULT 0,
    process_virus_scan TINYINT(1) DEFAULT 1,
    process_summarize TINYINT(1) DEFAULT 0,
    process_spellcheck TINYINT(1) DEFAULT 0,
    process_translate TINYINT(1) DEFAULT 0,
    process_translate_lang VARCHAR(10) DEFAULT NULL,
    process_format_id TINYINT(1) DEFAULT 0,
    process_face_detect TINYINT(1) DEFAULT 0,
    entity_type VARCHAR(30) NOT NULL DEFAULT 'description' COMMENT 'description, accession',
    status VARCHAR(91) COMMENT 'configure, upload, map, validate, preview, commit, completed, failed, cancelled' DEFAULT 'configure',
    config JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_user (user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uploaded files for an ingest session
CREATE TABLE IF NOT EXISTS ingest_file (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    file_type VARCHAR(36) COMMENT 'csv, zip, ead, directory' NOT NULL,
    original_name VARCHAR(500),
    stored_path VARCHAR(1000) NOT NULL,
    file_size BIGINT DEFAULT 0,
    mime_type VARCHAR(100),
    row_count INT DEFAULT NULL,
    `delimiter` VARCHAR(5) DEFAULT NULL,
    encoding VARCHAR(50) DEFAULT NULL,
    headers JSON DEFAULT NULL,
    extracted_path VARCHAR(1000) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Column mapping for this session
CREATE TABLE IF NOT EXISTS ingest_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    source_column VARCHAR(255) NOT NULL,
    target_field VARCHAR(255) DEFAULT NULL,
    is_ignored TINYINT(1) DEFAULT 0,
    default_value VARCHAR(500) DEFAULT NULL,
    transform VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    KEY idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Validation results
CREATE TABLE IF NOT EXISTS ingest_validation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    `row_number` INT NOT NULL,
    severity VARCHAR(32) COMMENT 'error, warning, info' DEFAULT 'error',
    field_name VARCHAR(255) DEFAULT NULL,
    message TEXT NOT NULL,
    is_excluded TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (session_id),
    KEY idx_row (`row_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parsed rows with enriched data
CREATE TABLE IF NOT EXISTS ingest_row (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    `row_number` INT NOT NULL,
    legacy_id VARCHAR(255) DEFAULT NULL,
    parent_id_ref VARCHAR(255) DEFAULT NULL,
    level_of_description VARCHAR(100) DEFAULT NULL,
    title VARCHAR(1000),
    `data` JSON NOT NULL,
    enriched_data JSON DEFAULT NULL,
    digital_object_path VARCHAR(1000) DEFAULT NULL,
    digital_object_matched TINYINT(1) DEFAULT 0,
    metadata_extracted JSON DEFAULT NULL,
    checksum_sha256 VARCHAR(64) DEFAULT NULL,
    is_valid TINYINT(1) DEFAULT 1,
    is_excluded TINYINT(1) DEFAULT 0,
    created_atom_id INT DEFAULT NULL,
    created_do_id INT DEFAULT NULL,
    created_accession_id INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (session_id),
    KEY idx_legacy (legacy_id),
    KEY idx_valid (is_valid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Commit job tracking
CREATE TABLE IF NOT EXISTS ingest_job (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    status VARCHAR(57) COMMENT 'queued, running, completed, failed, cancelled' DEFAULT 'queued',
    total_rows INT DEFAULT 0,
    processed_rows INT DEFAULT 0,
    created_records INT DEFAULT 0,
    created_dos INT DEFAULT 0,
    sip_package_id INT DEFAULT NULL,
    aip_package_id INT DEFAULT NULL,
    dip_package_id INT DEFAULT NULL,
    error_count INT DEFAULT 0,
    error_log JSON DEFAULT NULL,
    manifest_path VARCHAR(1000) DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_session (session_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
