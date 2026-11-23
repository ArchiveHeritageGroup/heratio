-- ============================================================================
-- ahg-portable-export - install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgPortableExportPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install - Phase 1 #3.
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
-- ahgPortableExportPlugin Database Schema
-- =====================================================

-- Export job tracking
CREATE TABLE IF NOT EXISTS portable_export (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    scope_type VARCHAR(20) NOT NULL DEFAULT 'all' COMMENT 'all, fonds, repository, custom',
    scope_slug VARCHAR(255) DEFAULT NULL,
    scope_repository_id INT DEFAULT NULL,
    scope_items JSON DEFAULT NULL,
    mode VARCHAR(20) DEFAULT 'read_only' COMMENT 'read_only, editable, archive',
    include_objects TINYINT(1) DEFAULT 1,
    include_masters TINYINT(1) DEFAULT 0,
    include_thumbnails TINYINT(1) DEFAULT 1,
    include_references TINYINT(1) DEFAULT 1,
    branding JSON DEFAULT NULL,
    culture VARCHAR(16) DEFAULT 'en',
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, processing, completed, failed',
    progress INT DEFAULT 0,
    total_descriptions INT DEFAULT 0,
    total_objects INT DEFAULT 0,
    output_path VARCHAR(1024) DEFAULT NULL,
    output_size BIGINT UNSIGNED DEFAULT 0,
    error_message TEXT DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portable_export_user (user_id),
    INDEX idx_portable_export_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Export download tokens (secure sharing)
CREATE TABLE IF NOT EXISTS portable_export_token (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    download_count INT DEFAULT 0,
    max_downloads INT DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (export_id) REFERENCES portable_export(id) ON DELETE CASCADE,
    INDEX idx_portable_export_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Admin menu entry: Admin > Portable Export
-- Inserts as last child of the Admin menu node (name='admin')
-- Uses MPTT: shift rgt values to make room, then insert
-- =====================================================
SET @admin_rgt = (SELECT rgt FROM menu WHERE name = 'admin' LIMIT 1);

-- Only insert if not already present
SET @exists = (SELECT COUNT(*) FROM menu WHERE name = 'portableExport');

-- Make room in the nested set: shift nodes to the right
UPDATE menu SET rgt = rgt + 2 WHERE rgt >= @admin_rgt AND @exists = 0;
UPDATE menu SET lft = lft + 2 WHERE lft > @admin_rgt AND @exists = 0;

-- Insert the menu node as last child of Admin
INSERT IGNORE INTO menu (parent_id, name, path, lft, rgt, created_at, updated_at, source_culture, serial_number)
SELECT id, 'portableExport', 'portableExport/index', @admin_rgt, @admin_rgt + 1, NOW(), NOW(), 'en', 0
FROM menu WHERE name = 'admin' AND @exists = 0
LIMIT 1;

-- Insert the i18n label
INSERT IGNORE INTO menu_i18n (id, culture, label, description)
SELECT m.id, 'en', 'Portable Export', 'Export catalogue to CD/USB/ZIP for offline viewing'
FROM menu m WHERE m.name = 'portableExport' AND NOT EXISTS (
    SELECT 1 FROM menu_i18n mi WHERE mi.id = m.id AND mi.culture = 'en'
);

-- =====================================================
-- Import job tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS portable_import (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    source_version VARCHAR(50) DEFAULT NULL,
    archive_path VARCHAR(1024) DEFAULT NULL,
    mode VARCHAR(20) DEFAULT 'merge' COMMENT 'merge, replace, dry_run',
    entity_types JSON DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, validating, validated, importing, completed, failed',
    progress INT DEFAULT 0,
    total_entities INT DEFAULT 0,
    imported_entities INT DEFAULT 0,
    skipped_entities INT DEFAULT 0,
    error_count INT DEFAULT 0,
    id_mapping JSON DEFAULT NULL,
    error_log TEXT DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_portable_import_user (user_id),
    INDEX idx_portable_import_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Settings defaults for portable export
-- =====================================================
INSERT IGNORE INTO ahg_settings (setting_key, setting_value, setting_group, created_at, updated_at)
VALUES
('portable_export_enabled', 'true', 'portable_export', NOW(), NOW()),
('portable_export_retention_days', '30', 'portable_export', NOW(), NOW()),
('portable_export_max_size_mb', '2048', 'portable_export', NOW(), NOW()),
('portable_export_default_mode', 'read_only', 'portable_export', NOW(), NOW()),
('portable_export_include_objects', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_thumbnails', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_references', 'true', 'portable_export', NOW(), NOW()),
('portable_export_include_masters', 'false', 'portable_export', NOW(), NOW()),
('portable_export_default_culture', 'en', 'portable_export', NOW(), NOW()),
('portable_export_description_button', 'true', 'portable_export', NOW(), NOW()),
('portable_export_clipboard_button', 'true', 'portable_export', NOW(), NOW());

-- portable-release-begin (#1586)
CREATE TABLE IF NOT EXISTS portable_release_series (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    series_key CHAR(12) NOT NULL COMMENT 'Random, stable; used in DOIs and public URLs',
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    creator_name VARCHAR(500) NOT NULL,
    publisher VARCHAR(500) NOT NULL,
    license VARCHAR(64) NULL COMMENT 'SPDX-style id; required for an external deposit',
    repository_id INT NULL,
    scope_hash CHAR(64) NOT NULL COMMENT 'Every version must come from an export of this scope',
    scope_type VARCHAR(20) NOT NULL,
    scope_slug VARCHAR(255) NULL,
    scope_repository_id INT NULL,
    concept_doi VARCHAR(255) NULL,
    concept_doi_state VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'none, draft, findable',
    created_by INT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_portable_series_key (series_key),
    UNIQUE KEY uk_portable_series_concept (concept_doi),
    KEY idx_portable_series_repo (repository_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    series_id BIGINT UNSIGNED NOT NULL,
    version_number INT UNSIGNED NOT NULL,
    export_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'frozen' COMMENT 'ahg_dropdown portable_release_status',
    frozen_path VARCHAR(1024) NOT NULL,
    package_sha256 CHAR(64) NOT NULL,
    package_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    manifest_sha256 CHAR(64) NOT NULL,
    changelog_sha256 CHAR(64) NOT NULL,
    file_count INT UNSIGNED NOT NULL DEFAULT 0,
    record_count INT UNSIGNED NOT NULL DEFAULT 0,
    version_doi VARCHAR(255) NOT NULL,
    doi_state VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'none, draft, findable',
    changelog_json LONGTEXT NULL,
    release_notes TEXT NULL,
    last_error TEXT NULL,
    frozen_by INT NOT NULL,
    frozen_at DATETIME NOT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    released_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_portable_release_version (series_id, version_number),
    UNIQUE KEY uk_portable_release_export (series_id, export_id),
    UNIQUE KEY uk_portable_release_doi (version_doi),
    KEY idx_portable_release_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release_correction (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    series_id BIGINT UNSIGNED NOT NULL,
    release_id BIGINT UNSIGNED NULL COMMENT 'The version that carries it; NULL until the next freeze',
    correction_type VARCHAR(50) NOT NULL COMMENT 'ahg_dropdown portable_release_correction_type',
    record_ref VARCHAR(255) NULL,
    field_name VARCHAR(255) NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME NULL,
    KEY idx_portable_correction_series (series_id, release_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release_target (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    target_type VARCHAR(30) NOT NULL COMMENT 'ahg_dropdown portable_release_target_type',
    base_url VARCHAR(255) NULL,
    api_token TEXT NULL COMMENT 'Encrypted with the application key',
    community VARCHAR(100) NULL,
    repository_id INT NULL COMMENT 'NULL = available to every repository',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NOT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_portable_target_repo (repository_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release_deposit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    release_id BIGINT UNSIGNED NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'approved' COMMENT 'ahg_dropdown portable_release_deposit_status',
    external_id VARCHAR(255) NULL,
    external_url VARCHAR(1000) NULL,
    last_error TEXT NULL,
    approved_by INT NOT NULL,
    approved_at DATETIME NOT NULL,
    deposited_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    UNIQUE KEY uk_portable_deposit_release (release_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release_abandonment (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    release_id BIGINT UNSIGNED NOT NULL,
    previous_status VARCHAR(20) NOT NULL,
    reason TEXT NOT NULL,
    abandoned_by INT NOT NULL,
    abandoned_at DATETIME NOT NULL,
    UNIQUE KEY uk_portable_abandon_release (release_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS portable_release_freeze_request (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    series_id BIGINT UNSIGNED NOT NULL,
    export_id BIGINT UNSIGNED NOT NULL,
    release_notes TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued' COMMENT 'ahg_dropdown portable_release_freeze_status',
    release_id BIGINT UNSIGNED NULL,
    failures TEXT NULL,
    requested_by INT NOT NULL,
    created_at DATETIME NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    KEY idx_portable_freeze_series (series_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- portable-release-end

SET FOREIGN_KEY_CHECKS = 1;
