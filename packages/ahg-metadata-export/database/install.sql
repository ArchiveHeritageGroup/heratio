-- ============================================================================
-- ahg-metadata-export — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgMetadataExportPlugin/database/install.sql
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

-- ahgMetadataExportPlugin Database Schema
-- GLAM Export Framework - Export configuration and logging

-- Export configuration table
-- Stores enabled formats and default options
CREATE TABLE IF NOT EXISTS metadata_export_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format_code VARCHAR(20) NOT NULL COMMENT 'Format code (ead3, rico, lido, etc.)',
    format_name VARCHAR(100) NOT NULL COMMENT 'Display name',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT 'Whether format is available for export',
    default_options JSON DEFAULT NULL COMMENT 'Default export options as JSON',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_format_code (format_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Metadata export format configuration';

-- Export log table
-- Tracks all exports for analytics and audit
CREATE TABLE IF NOT EXISTS metadata_export_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format_code VARCHAR(20) NOT NULL COMMENT 'Format code used',
    resource_type VARCHAR(50) DEFAULT NULL COMMENT 'Type of resource exported',
    resource_id INT DEFAULT NULL COMMENT 'ID of resource exported',
    export_count INT DEFAULT 1 COMMENT 'Number of records in export',
    file_path VARCHAR(500) DEFAULT NULL COMMENT 'Output file path',
    file_size BIGINT DEFAULT NULL COMMENT 'Output file size in bytes',
    user_id INT DEFAULT NULL COMMENT 'User who initiated export',
    options JSON DEFAULT NULL COMMENT 'Export options used',
    duration_ms INT DEFAULT NULL COMMENT 'Export duration in milliseconds',
    status VARCHAR(36) COMMENT 'success, failed, partial' DEFAULT 'success',
    error_message TEXT DEFAULT NULL COMMENT 'Error message if failed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_format (format_code),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Metadata export activity log';

-- Insert default format configurations
INSERT IGNORE INTO metadata_export_config (format_code, format_name, is_enabled, default_options) VALUES
('ead3', 'EAD3', 1, '{"includeDigitalObjects": true, "includeChildren": true}'),
('rico', 'RIC-O', 1, '{"includeDigitalObjects": true, "includeChildren": true, "outputFormat": "jsonld"}'),
('lido', 'LIDO', 1, '{"includeDigitalObjects": true, "includeChildren": true}'),
('cidoc-crm', 'CIDOC-CRM', 1, '{"includeDigitalObjects": true, "outputFormat": "jsonld"}'),
('marc21', 'MARC21', 1, '{"includeDigitalObjects": true, "includeChildren": true}'),
('bibframe', 'BIBFRAME', 1, '{"includeDigitalObjects": true, "outputFormat": "jsonld"}'),
('vra-core', 'VRA Core 4', 1, '{"includeDigitalObjects": true}'),
('pbcore', 'PBCore', 1, '{"includeDigitalObjects": true}'),
('ebucore', 'EBUCore', 1, '{"includeDigitalObjects": true}'),
('premis', 'PREMIS', 1, '{"includeDigitalObjects": true}')
ON DUPLICATE KEY UPDATE
    format_name = VALUES(format_name),
    default_options = VALUES(default_options);

-- View for export statistics
CREATE OR REPLACE VIEW v_metadata_export_stats AS
SELECT
    format_code,
    COUNT(*) as total_exports,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_exports,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_exports,
    SUM(export_count) as total_records_exported,
    SUM(file_size) as total_bytes_exported,
    AVG(duration_ms) as avg_duration_ms,
    MAX(created_at) as last_export_at
FROM metadata_export_log
GROUP BY format_code;

-- View for daily export activity
CREATE OR REPLACE VIEW v_metadata_export_daily AS
SELECT
    DATE(created_at) as export_date,
    format_code,
    COUNT(*) as export_count,
    SUM(export_count) as records_exported,
    SUM(file_size) as bytes_exported
FROM metadata_export_log
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), format_code
ORDER BY export_date DESC, format_code;

-- ============================================================================
-- Phase 2 of #663: MARC21 RDA carrier mapping (336/337/338)
-- ============================================================================
-- Operator-extensible MIME / carrier -> RDA tag mapping. The importer +
-- exporter both consult this table so jurisdictional variants can be added
-- without code changes. Defaults follow the RDA Toolkit value vocabularies
-- (rdacontent / rdamedia / rdacarrier).
CREATE TABLE IF NOT EXISTS ahg_marc_rda_mapping (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- match_kind = mime_prefix / mime_exact / carrier (physical IO carrier code)
    match_kind VARCHAR(20) NOT NULL,
    match_value VARCHAR(100) NOT NULL,
    -- RDA 336 content type term + 2 (source vocabulary)
    content_type_term VARCHAR(60) DEFAULT NULL,
    content_type_source VARCHAR(20) DEFAULT 'rdacontent',
    -- RDA 337 media type term
    media_type_term VARCHAR(60) DEFAULT NULL,
    media_type_source VARCHAR(20) DEFAULT 'rdamedia',
    -- RDA 338 carrier type term
    carrier_type_term VARCHAR(60) DEFAULT NULL,
    carrier_type_source VARCHAR(20) DEFAULT 'rdacarrier',
    sort_order INT DEFAULT 100,
    is_active TINYINT(1) DEFAULT 1,
    notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_marc_rda_match (match_kind, match_value),
    INDEX idx_marc_rda_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='MARC21 RDA 336/337/338 mapping (operator-extensible)';

-- Default mapping seeds. MIME-prefix rules are tried in sort_order before
-- the catch-all "*" fallback. carrier-* rows feed physical-object IOs.
INSERT IGNORE INTO ahg_marc_rda_mapping
    (match_kind, match_value, content_type_term, media_type_term, carrier_type_term, sort_order, notes)
VALUES
    ('mime_prefix', 'text/',         'text',                       'computer', 'online resource', 10, 'plain/HTML/PDF text bodies'),
    ('mime_exact',  'application/pdf','text',                      'computer', 'online resource', 11, 'PDF documents'),
    ('mime_prefix', 'image/',        'still image',                'computer', 'online resource', 20, 'photographs, scans, derivatives'),
    ('mime_prefix', 'audio/',        'spoken word',                'computer', 'online resource', 30, 'default for audio - operators may switch to performed music'),
    ('mime_prefix', 'video/',        'two-dimensional moving image','computer','online resource', 40, 'film, video, born-digital moving images'),
    ('mime_prefix', 'model/',        'three-dimensional moving image','computer','online resource',50, '3D models and scenes (GLTF, OBJ, STL)'),
    ('mime_prefix', 'application/x-3d','three-dimensional moving image','computer','online resource',51, '3D vendor-prefixed MIME'),
    ('mime_prefix', 'application/',  'computer dataset',           'computer', 'online resource', 90, 'generic application/* fallback'),
    ('mime_exact',  '*',             'computer dataset',           'computer', 'online resource', 999,'catch-all when no MIME matches'),
    ('carrier',     'volume',        'text',                       'unmediated','volume',          200, 'physical bound volume'),
    ('carrier',     'sheet',         'text',                       'unmediated','sheet',           210, 'physical loose sheet'),
    ('carrier',     'box',           'text',                       'unmediated','object',          220, 'physical archival box'),
    ('carrier',     'audio-cassette','spoken word',                'audio',     'audiocassette',   300, 'physical audio cassette'),
    ('carrier',     'audio-disc',    'performed music',            'audio',     'audio disc',      310, 'physical audio disc (vinyl, CD)'),
    ('carrier',     'film-reel',     'two-dimensional moving image','video',    'film reel',       400, 'physical motion picture reel'),
    ('carrier',     'videotape',     'two-dimensional moving image','video',    'videocassette',   410, 'physical videotape')
ON DUPLICATE KEY UPDATE
    content_type_term = VALUES(content_type_term),
    media_type_term = VALUES(media_type_term),
    carrier_type_term = VALUES(carrier_type_term);

-- ============================================================================
-- Phase 3 of #662: RAD + DACS per-standard sidecar tables
-- ============================================================================
-- RAD = Rules for Archival Description (Canadian national standard).
-- One row per information_object whose preferred description standard is
-- RAD. Mirrors RAD 1.0 / 2008-revision element groupings (1.1 Title and
-- statement of responsibility / 1.2 Edition / 1.4 Date of creation /
-- 1.5 Physical description / 1.7 Notes etc).
CREATE TABLE IF NOT EXISTS ahg_io_rad (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    title_proper VARCHAR(512) DEFAULT NULL COMMENT 'RAD 1.1B: title proper',
    parallel_title VARCHAR(512) DEFAULT NULL COMMENT 'RAD 1.1D: parallel titles',
    other_title_info TEXT DEFAULT NULL COMMENT 'RAD 1.1E: other title information',
    statements_of_responsibility TEXT DEFAULT NULL COMMENT 'RAD 1.1F',
    edition VARCHAR(255) DEFAULT NULL COMMENT 'RAD 1.2: edition',
    dates_of_creation TEXT DEFAULT NULL COMMENT 'RAD 1.4: dates of creation, publication, distribution',
    physical_description TEXT DEFAULT NULL COMMENT 'RAD 1.5: physical description',
    custodial_history TEXT DEFAULT NULL COMMENT 'RAD 1.7B7a',
    scope_and_content_rad TEXT DEFAULT NULL COMMENT 'RAD 1.7D',
    system_of_arrangement TEXT DEFAULT NULL COMMENT 'RAD 3.3A1 (item-level arrangement)',
    language_of_material TEXT DEFAULT NULL COMMENT 'RAD 1.7B8',
    finding_aids TEXT DEFAULT NULL COMMENT 'RAD 1.7B10',
    accruals TEXT DEFAULT NULL COMMENT 'RAD 1.7B12',
    general_note TEXT DEFAULT NULL COMMENT 'RAD 1.7B22 (general note)',
    archivist_note TEXT DEFAULT NULL COMMENT 'RAD 1.7B23 (archivist note)',
    rules_or_conventions VARCHAR(255) DEFAULT 'RAD' COMMENT 'RAD 1.7B24',
    date_of_descriptions DATE DEFAULT NULL COMMENT 'RAD 1.7B25',
    status VARCHAR(32) DEFAULT NULL COMMENT 'draft / final / revised',
    level_of_detail VARCHAR(32) DEFAULT NULL COMMENT 'minimum / partial / full',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_rad_io (information_object_id),
    INDEX idx_rad_status (status),
    INDEX idx_rad_level (level_of_detail)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rules for Archival Description (Canadian) per-IO sidecar';

-- DACS = Describing Archives: A Content Standard (United States). Element
-- groupings follow DACS 2013 (Single-Level Optimum):
--   Reference Code / Name and Location / Title / Date / Extent / Creator /
--   Scope and Content / Conditions Governing Access / Languages /
--   Biographical or Historical / Immediate Source of Acquisition /
--   System of Arrangement / Related Archival Materials / Publication /
--   Processing Information.
CREATE TABLE IF NOT EXISTS ahg_io_dacs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    information_object_id INT NOT NULL,
    reference_code VARCHAR(255) DEFAULT NULL COMMENT 'DACS 2.1',
    name_and_location TEXT DEFAULT NULL COMMENT 'DACS 2.2',
    title_dacs VARCHAR(512) DEFAULT NULL COMMENT 'DACS 2.3',
    date_dacs TEXT DEFAULT NULL COMMENT 'DACS 2.4',
    extent TEXT DEFAULT NULL COMMENT 'DACS 2.5',
    name_of_creator TEXT DEFAULT NULL COMMENT 'DACS 2.6',
    scope_and_content_dacs TEXT DEFAULT NULL COMMENT 'DACS 3.1',
    conditions_governing_access TEXT DEFAULT NULL COMMENT 'DACS 4.1',
    languages_of_material TEXT DEFAULT NULL COMMENT 'DACS 4.5',
    biographical_or_historical TEXT DEFAULT NULL COMMENT 'DACS 2.7',
    immediate_source_of_acquisition TEXT DEFAULT NULL COMMENT 'DACS 5.2',
    system_of_arrangement TEXT DEFAULT NULL COMMENT 'DACS 3.2',
    related_archival_materials TEXT DEFAULT NULL COMMENT 'DACS 6.3',
    publication_note TEXT DEFAULT NULL COMMENT 'DACS 6.4',
    processing_information TEXT DEFAULT NULL COMMENT 'DACS 5.1',
    dacs_rules VARCHAR(255) DEFAULT 'DACS 2013' COMMENT 'DACS edition / cataloging rules',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_dacs_io (information_object_id),
    INDEX idx_dacs_ref (reference_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Describing Archives: A Content Standard (US) per-IO sidecar';

-- Phase 3 also adds two metadata-export formats so operators see the
-- new exporters in /admin/metadata-export. The seed is idempotent.
INSERT IGNORE INTO metadata_export_config (format_code, format_name, is_enabled, default_options) VALUES
('dcterms-qualified', 'Dublin Core Qualified (dcterms)', 1, '{"includeDigitalObjects": false}'),
('rad', 'RAD (Canadian) XML', 1, '{"includeChildren": false}'),
('dacs', 'DACS (US) XML', 1, '{"includeChildren": false}')
ON DUPLICATE KEY UPDATE
    format_name = VALUES(format_name),
    default_options = VALUES(default_options);

SET FOREIGN_KEY_CHECKS = 1;
