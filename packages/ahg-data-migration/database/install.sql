-- ============================================================================
-- ahg-data-migration — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDataMigrationPlugin/database/install.sql
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
-- ahgDataMigrationPlugin Database Schema
-- =====================================================

-- Saved field mappings
CREATE TABLE IF NOT EXISTS atom_data_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    target_type VARCHAR(100) NOT NULL COMMENT 'information_object, repository, accession, actor, subject, place, event',
    description TEXT,
    field_mappings JSON NOT NULL COMMENT 'Array of field mapping objects',
    source_template VARCHAR(100) COMMENT 'archivesspace, vernon, dbtextworks, etc.',
    is_default TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_name_type (name, target_type),
    INDEX idx_target_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration jobs tracking
CREATE TABLE IF NOT EXISTS atom_migration_job (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    target_type VARCHAR(100) NOT NULL,
    source_file VARCHAR(500),
    source_format VARCHAR(50) COMMENT 'csv, xml, json',
    mapping_id BIGINT UNSIGNED,
    mapping_snapshot JSON COMMENT 'Copy of mapping used',
    import_options JSON COMMENT 'Match field, update mode, etc.',
    status VARCHAR(58) COMMENT 'pending, running, completed, failed, cancelled' DEFAULT 'pending',
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    imported_records INT DEFAULT 0,
    updated_records INT DEFAULT 0,
    skipped_records INT DEFAULT 0,
    error_count INT DEFAULT 0,
    error_log JSON,
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_target_type (target_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Import log for rollback and audit
CREATE TABLE IF NOT EXISTS atom_migration_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED NOT NULL,
    `row_number` INT,
    source_identifier VARCHAR(255),
    target_type VARCHAR(100),
    target_id INT COMMENT 'AtoM object ID',
    target_slug VARCHAR(255),
    action VARCHAR(45) COMMENT 'created, updated, skipped, failed' NOT NULL,
    source_data JSON,
    mapped_data JSON,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job_id (job_id),
    INDEX idx_action (action),
    FOREIGN KEY (job_id) REFERENCES atom_migration_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Validation rules configuration
CREATE TABLE IF NOT EXISTS atom_validation_rule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sector_code VARCHAR(50) NOT NULL COMMENT 'archive, museum, library, gallery, dam',
    rule_type VARCHAR(77) COMMENT 'required, type, pattern, enum, range, length, referential, custom' NOT NULL,
    field_name VARCHAR(255) NOT NULL,
    rule_config JSON NOT NULL COMMENT 'Rule parameters: pattern, values, min/max, etc.',
    error_message VARCHAR(500) COMMENT 'Custom error message',
    severity VARCHAR(32) COMMENT 'error, warning, info' DEFAULT 'error',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_sector (sector_code),
    INDEX idx_field (field_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Validation results log
CREATE TABLE IF NOT EXISTS atom_validation_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id BIGINT UNSIGNED,
    `row_number` INT,
    column_name VARCHAR(255),
    rule_type VARCHAR(50),
    severity VARCHAR(32) COMMENT 'error, warning, info',
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_job (job_id),
    INDEX idx_severity (severity),
    INDEX idx_row (`row_number`),
    FOREIGN KEY (job_id) REFERENCES atom_migration_job(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add columns to atom_data_mapping for sharing profiles
-- Uses procedure to safely add columns (MySQL 8 does not support ADD COLUMN IF NOT EXISTS)
SET @dbname = DATABASE();
SET @tablename = 'atom_data_mapping';

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'is_shared';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_data_mapping ADD COLUMN is_shared TINYINT(1) DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'shared_by';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_data_mapping ADD COLUMN shared_by INT UNSIGNED', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns WHERE table_schema = @dbname AND table_name = @tablename AND column_name = 'sector_code';
SET @sql = IF(@col_exists = 0, 'ALTER TABLE atom_data_mapping ADD COLUMN sector_code VARCHAR(50)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
