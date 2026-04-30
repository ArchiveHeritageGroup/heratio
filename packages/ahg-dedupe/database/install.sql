-- ============================================================================
-- ahg-dedupe — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDedupePlugin/database/install.sql
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
-- ahgDedupePlugin Database Schema
-- Duplicate Detection System
-- ============================================

-- Detected Duplicates
CREATE TABLE IF NOT EXISTS ahg_duplicate_detection (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_a_id INT NOT NULL COMMENT 'First record ID',
    record_b_id INT NOT NULL COMMENT 'Second record ID',
    similarity_score DECIMAL(5,4) NOT NULL COMMENT 'Score 0.0000 to 1.0000',
    detection_method VARCHAR(50) NOT NULL COMMENT 'title_match, identifier_match, date_creator_match, checksum, combined',
    detection_details JSON COMMENT 'Detailed matching information',
    status VARCHAR(49) COMMENT 'pending, confirmed, dismissed, merged' NOT NULL DEFAULT 'pending',
    reviewed_by INT,
    reviewed_at DATETIME,
    review_notes TEXT,
    auto_detected TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Automatically detected vs manually flagged',
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pair (record_a_id, record_b_id),
    INDEX idx_record_a (record_a_id),
    INDEX idx_record_b (record_b_id),
    INDEX idx_status (status),
    INDEX idx_score (similarity_score DESC),
    INDEX idx_method (detection_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detection Rules (configurable per repository)
CREATE TABLE IF NOT EXISTS ahg_duplicate_rule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = global',
    name VARCHAR(255) NOT NULL,
    rule_type VARCHAR(106) COMMENT 'title_similarity, identifier_exact, identifier_fuzzy, date_creator, checksum, combined, custom' NOT NULL,
    threshold DECIMAL(5,4) NOT NULL DEFAULT 0.8000 COMMENT 'Minimum similarity score',
    config_json JSON COMMENT 'Rule-specific configuration',
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_blocking TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Block save if duplicate found',
    priority INT NOT NULL DEFAULT 100 COMMENT 'Higher = runs first',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_repository (repository_id),
    INDEX idx_is_enabled (is_enabled),
    INDEX idx_priority (priority DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merge Log (audit trail of merges)
CREATE TABLE IF NOT EXISTS ahg_merge_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_id INT NOT NULL COMMENT 'Record kept as primary',
    merged_id INT NOT NULL COMMENT 'Record merged into primary',
    detection_id BIGINT UNSIGNED COMMENT 'Original detection record',
    field_choices_json JSON COMMENT 'Which fields were taken from which record',
    slugs_redirected JSON COMMENT 'Old slugs now redirecting',
    digital_objects_moved JSON COMMENT 'Digital objects transferred',
    merged_by INT NOT NULL,
    merged_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    INDEX idx_primary (primary_id),
    INDEX idx_merged (merged_id),
    INDEX idx_merged_at (merged_at),
    FOREIGN KEY (detection_id) REFERENCES ahg_duplicate_detection(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Digital Object Checksums (for exact file duplicate detection)
CREATE TABLE IF NOT EXISTS ahg_file_checksum (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    information_object_id INT NOT NULL,
    checksum_md5 CHAR(32),
    checksum_sha256 CHAR(64),
    file_size BIGINT UNSIGNED,
    file_name VARCHAR(500),
    mime_type VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_digital_object (digital_object_id),
    INDEX idx_information_object (information_object_id),
    INDEX idx_md5 (checksum_md5),
    INDEX idx_sha256 (checksum_sha256),
    INDEX idx_file_size (file_size)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scan Jobs (batch scanning status)
CREATE TABLE IF NOT EXISTS ahg_dedupe_scan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    repository_id INT COMMENT 'NULL = all repositories',
    status VARCHAR(58) COMMENT 'pending, running, completed, failed, cancelled' NOT NULL DEFAULT 'pending',
    total_records INT DEFAULT 0,
    processed_records INT DEFAULT 0,
    duplicates_found INT DEFAULT 0,
    started_at DATETIME,
    completed_at DATETIME,
    error_message TEXT,
    started_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA: Default Detection Rules
-- ============================================

-- Title Similarity (Levenshtein distance)
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('Title Similarity', 'title_similarity', 0.8500, '{"algorithm": "levenshtein", "normalize": true, "ignore_case": true, "min_length": 10}', 1, 0, 100);

-- Exact Identifier Match
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('Identifier Exact Match', 'identifier_exact', 1.0000, '{"fields": ["identifier", "alternate_identifiers"]}', 1, 1, 200);

-- Fuzzy Identifier Match
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('Identifier Fuzzy Match', 'identifier_fuzzy', 0.9000, '{"fields": ["identifier"], "algorithm": "jaro_winkler"}', 1, 0, 150);

-- Date + Creator Combination
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('Date Range + Creator', 'date_creator', 0.9000, '{"date_overlap_required": true, "creator_similarity": 0.8}', 1, 0, 80);

-- File Checksum (exact duplicates)
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('File Checksum Match', 'checksum', 1.0000, '{"algorithm": "sha256", "same_filename_bonus": 0.1}', 1, 0, 250);

-- Combined Multi-factor
INSERT IGNORE INTO ahg_duplicate_rule (name, rule_type, threshold, config_json, is_enabled, is_blocking, priority) VALUES
('Combined Analysis', 'combined', 0.7500, '{"weights": {"title": 0.4, "identifier": 0.3, "date": 0.15, "creator": 0.15}}', 1, 0, 50);

SET FOREIGN_KEY_CHECKS = 1;
