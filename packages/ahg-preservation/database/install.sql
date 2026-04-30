-- ============================================================================
-- ahg-preservation — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgPreservationPlugin/database/install.sql
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

--
-- AHG Preservation Plugin - Database Schema
-- Digital preservation features: checksums, fixity, PREMIS events, format registry
--

-- =============================================
-- CHECKSUM STORAGE
-- Stores cryptographic checksums for digital objects
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_checksum (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    algorithm VARCHAR(37) COMMENT 'md5, sha1, sha256, sha512' NOT NULL DEFAULT 'sha256',
    checksum_value VARCHAR(128) NOT NULL,
    file_size BIGINT UNSIGNED,
    generated_at DATETIME NOT NULL,
    verified_at DATETIME,
    verification_status VARCHAR(42) COMMENT 'pending, valid, invalid, error' DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_algorithm (algorithm),
    INDEX idx_status (verification_status),
    INDEX idx_verified_at (verified_at),
    UNIQUE KEY uk_object_algorithm (digital_object_id, algorithm),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FIXITY CHECK LOG
-- Records all fixity verification runs
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_fixity_check (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    checksum_id BIGINT UNSIGNED,
    algorithm VARCHAR(37) COMMENT 'md5, sha1, sha256, sha512' NOT NULL,
    expected_value VARCHAR(128) NOT NULL,
    actual_value VARCHAR(128),
    status VARCHAR(38) COMMENT 'pass, fail, error, missing' NOT NULL,
    error_message TEXT,
    checked_at DATETIME NOT NULL,
    checked_by VARCHAR(100) COMMENT 'user or system/cron',
    duration_ms INT UNSIGNED COMMENT 'Check duration in milliseconds',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_status (status),
    INDEX idx_checked_at (checked_at),
    INDEX idx_checksum (checksum_id),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE,
    FOREIGN KEY (checksum_id) REFERENCES preservation_checksum(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PREMIS EVENTS
-- Preservation metadata events (PREMIS standard)
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_event (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT,
    information_object_id INT,
    event_type VARCHAR(224) COMMENT 'creation, capture, ingestion, validation, fixity_check, virus_check, format_identification, normalization, migration, replication, deletion, deaccession, modification, metadata_modification, access, dissemination' NOT NULL,
    event_datetime DATETIME NOT NULL,
    event_detail TEXT,
    event_outcome VARCHAR(46) COMMENT 'success, failure, warning, unknown' DEFAULT 'unknown',
    event_outcome_detail TEXT,
    linking_agent_type VARCHAR(48) COMMENT 'user, system, software, organization' DEFAULT 'system',
    linking_agent_value VARCHAR(255),
    linking_object_type VARCHAR(100),
    linking_object_value VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_information_object (information_object_id),
    INDEX idx_event_type (event_type),
    INDEX idx_event_datetime (event_datetime),
    INDEX idx_outcome (event_outcome),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE,
    FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FORMAT REGISTRY
-- Tracks file formats and their preservation risk
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_format (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    puid VARCHAR(50) COMMENT 'PRONOM Unique Identifier',
    mime_type VARCHAR(100) NOT NULL,
    format_name VARCHAR(255) NOT NULL,
    format_version VARCHAR(50),
    extension VARCHAR(20),
    risk_level VARCHAR(39) COMMENT 'low, medium, high, critical' DEFAULT 'medium',
    risk_notes TEXT,
    preservation_action VARCHAR(45) COMMENT 'none, monitor, migrate, normalize' DEFAULT 'monitor',
    migration_target_id BIGINT UNSIGNED COMMENT 'Target format for migration',
    is_preservation_format TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_puid (puid),
    INDEX idx_mime_type (mime_type),
    INDEX idx_risk_level (risk_level),
    UNIQUE KEY uk_mime_version (mime_type, format_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DIGITAL OBJECT FORMAT IDENTIFICATION
-- Links digital objects to identified formats
-- Supports Siegfried/DROID PRONOM identification
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_object_format (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    format_id BIGINT UNSIGNED,
    puid VARCHAR(50) COMMENT 'PRONOM Unique Identifier from Siegfried/DROID',
    mime_type VARCHAR(255),
    format_name VARCHAR(255),
    format_version VARCHAR(50),
    identification_tool VARCHAR(100) COMMENT 'e.g., siegfried, DROID, file, finfo',
    identification_date DATETIME NOT NULL,
    confidence VARCHAR(38) COMMENT 'low, medium, high, certain' DEFAULT 'medium',
    basis VARCHAR(500) COMMENT 'How identified: extension, signature, container, byte match',
    warning TEXT COMMENT 'Identification warnings from tool',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_format (format_id),
    INDEX idx_puid (puid),
    INDEX idx_mime_type (mime_type),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE,
    FOREIGN KEY (format_id) REFERENCES preservation_format(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PRESERVATION POLICIES
-- Defines preservation rules and schedules
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_policy (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    policy_type VARCHAR(50) COMMENT 'fixity, format, retention, replication' NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    schedule_cron VARCHAR(100) COMMENT 'Cron expression for scheduled runs',
    last_run_at DATETIME,
    next_run_at DATETIME,
    config JSON COMMENT 'Policy-specific configuration',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_type (policy_type),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PRESERVATION STATISTICS (for dashboard)
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    total_objects INT UNSIGNED DEFAULT 0,
    total_size_bytes BIGINT UNSIGNED DEFAULT 0,
    objects_with_checksum INT UNSIGNED DEFAULT 0,
    fixity_checks_run INT UNSIGNED DEFAULT 0,
    fixity_failures INT UNSIGNED DEFAULT 0,
    formats_at_risk INT UNSIGNED DEFAULT 0,
    events_logged INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA: Common format registry entries
-- =============================================
INSERT IGNORE INTO preservation_format (puid, mime_type, format_name, format_version, extension, risk_level, is_preservation_format, preservation_action) VALUES
-- Images (preservation formats)
('fmt/353', 'image/tiff', 'Tagged Image File Format', '6.0', 'tif', 'low', 1, 'none'),
('fmt/44', 'image/jpeg', 'JPEG File Interchange Format', '1.02', 'jpg', 'low', 0, 'monitor'),
('fmt/11', 'image/png', 'Portable Network Graphics', '1.0', 'png', 'low', 1, 'none'),
('fmt/41', 'image/gif', 'Graphics Interchange Format', '89a', 'gif', 'medium', 0, 'monitor'),
('fmt/645', 'image/webp', 'WebP', '', 'webp', 'medium', 0, 'monitor'),

-- Documents (preservation formats)
('fmt/95', 'application/pdf', 'PDF', '1.4', 'pdf', 'low', 0, 'monitor'),
('fmt/354', 'application/pdf', 'PDF/A-1a', '1a', 'pdf', 'low', 1, 'none'),
('fmt/476', 'application/pdf', 'PDF/A-2b', '2b', 'pdf', 'low', 1, 'none'),

-- Office documents (higher risk)
('fmt/412', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'Microsoft Word', '2007+', 'docx', 'medium', 0, 'migrate'),
('fmt/214', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Microsoft Excel', '2007+', 'xlsx', 'medium', 0, 'migrate'),

-- Audio (preservation formats)
('fmt/141', 'audio/x-wav', 'Waveform Audio', '', 'wav', 'low', 1, 'none'),
('fmt/134', 'audio/mpeg', 'MPEG Audio Layer 3', '', 'mp3', 'low', 0, 'monitor'),
('fmt/199', 'audio/flac', 'Free Lossless Audio Codec', '', 'flac', 'low', 1, 'none'),

-- Video
('fmt/199', 'video/mp4', 'MPEG-4 Video', '', 'mp4', 'medium', 0, 'monitor'),
('fmt/569', 'video/x-matroska', 'Matroska Video', '', 'mkv', 'medium', 0, 'monitor'),

-- Plain text (preservation)
('x-fmt/111', 'text/plain', 'Plain Text', '', 'txt', 'low', 1, 'none'),
('fmt/101', 'text/xml', 'XML', '1.0', 'xml', 'low', 1, 'none'),

-- Archives (monitor)
('x-fmt/263', 'application/zip', 'ZIP Archive', '', 'zip', 'low', 0, 'monitor'),
('fmt/289', 'application/x-tar', 'TAR Archive', '', 'tar', 'low', 0, 'monitor');

-- =============================================
-- DEFAULT PRESERVATION POLICIES
-- =============================================
INSERT IGNORE INTO preservation_policy (name, description, policy_type, is_active, schedule_cron, config) VALUES
('Daily Fixity Check', 'Verify checksums for a sample of digital objects daily', 'fixity', 1, '0 2 * * *', '{"sample_percentage": 5, "algorithm": "sha256"}'),
('Weekly Full Fixity', 'Full fixity verification weekly', 'fixity', 0, '0 3 * * 0', '{"sample_percentage": 100, "algorithm": "sha256"}'),
('Format Risk Monitor', 'Monitor objects with at-risk formats', 'format', 1, '0 4 * * 1', '{"risk_levels": ["high", "critical"]}');

-- =============================================
-- VIRUS SCAN LOG
-- Records virus scan results for digital objects
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_virus_scan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    scan_engine VARCHAR(50) NOT NULL DEFAULT 'clamav',
    engine_version VARCHAR(50),
    signature_version VARCHAR(100),
    status VARCHAR(43) COMMENT 'clean, infected, error, skipped' NOT NULL,
    threat_name VARCHAR(255) COMMENT 'Name of detected threat if infected',
    file_path VARCHAR(1024),
    file_size BIGINT UNSIGNED,
    scanned_at DATETIME NOT NULL,
    scanned_by VARCHAR(100) COMMENT 'user or system/cron',
    duration_ms INT UNSIGNED,
    error_message TEXT,
    quarantined TINYINT(1) DEFAULT 0,
    quarantine_path VARCHAR(1024),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_status (status),
    INDEX idx_scanned_at (scanned_at),
    INDEX idx_threat (threat_name),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FORMAT CONVERSION LOG
-- Records format migration/normalization jobs
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_format_conversion (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    digital_object_id INT NOT NULL,
    source_format VARCHAR(100) NOT NULL,
    source_mime_type VARCHAR(100),
    target_format VARCHAR(100) NOT NULL,
    target_mime_type VARCHAR(100),
    conversion_tool VARCHAR(100) NOT NULL COMMENT 'imagemagick, ffmpeg, ghostscript, etc.',
    tool_version VARCHAR(50),
    status VARCHAR(50) COMMENT 'pending, processing, completed, failed' NOT NULL DEFAULT 'pending',
    source_path VARCHAR(1024),
    source_size BIGINT UNSIGNED,
    source_checksum VARCHAR(128),
    output_path VARCHAR(1024),
    output_size BIGINT UNSIGNED,
    output_checksum VARCHAR(128),
    conversion_options JSON COMMENT 'Tool-specific options used',
    quality_score DECIMAL(5,2) COMMENT 'Quality assessment score if applicable',
    started_at DATETIME,
    completed_at DATETIME,
    duration_ms INT UNSIGNED,
    error_message TEXT,
    created_by VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_digital_object (digital_object_id),
    INDEX idx_status (status),
    INDEX idx_source_format (source_format),
    INDEX idx_target_format (target_format),
    INDEX idx_created_at (created_at),

    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- BACKUP VERIFICATION LOG
-- Records backup integrity verification results
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_backup_verification (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    backup_id BIGINT UNSIGNED COMMENT 'Reference to atom_backup if exists',
    backup_type VARCHAR(46) COMMENT 'database, files, full, incremental' NOT NULL,
    backup_path VARCHAR(1024) NOT NULL,
    backup_size BIGINT UNSIGNED,
    original_checksum VARCHAR(128),
    verified_checksum VARCHAR(128),
    status VARCHAR(53) COMMENT 'valid, invalid, missing, error, corrupted' NOT NULL,
    verification_method VARCHAR(50) DEFAULT 'sha256',
    files_checked INT UNSIGNED DEFAULT 0,
    files_valid INT UNSIGNED DEFAULT 0,
    files_invalid INT UNSIGNED DEFAULT 0,
    files_missing INT UNSIGNED DEFAULT 0,
    verified_at DATETIME NOT NULL,
    verified_by VARCHAR(100),
    duration_ms INT UNSIGNED,
    error_message TEXT,
    details JSON COMMENT 'Detailed verification results',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_backup_id (backup_id),
    INDEX idx_status (status),
    INDEX idx_verified_at (verified_at),
    INDEX idx_backup_type (backup_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- REPLICATION TARGETS
-- Defines remote backup/replication destinations
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_replication_target (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    target_type VARCHAR(46) COMMENT 'local, sftp, s3, azure, gcs, rsync' NOT NULL,
    connection_config JSON NOT NULL COMMENT 'Encrypted connection details',
    is_active TINYINT(1) DEFAULT 1,
    sync_schedule VARCHAR(100) COMMENT 'Cron expression',
    last_sync_at DATETIME,
    last_sync_status VARCHAR(36) COMMENT 'success, partial, failed' DEFAULT NULL,
    last_sync_files INT UNSIGNED DEFAULT 0,
    last_sync_bytes BIGINT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_active (is_active),
    INDEX idx_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- REPLICATION LOG
-- Records replication sync operations
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_replication_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_id BIGINT UNSIGNED NOT NULL,
    operation VARCHAR(33) COMMENT 'sync, verify, restore' NOT NULL,
    status VARCHAR(47) COMMENT 'started, completed, failed, partial' NOT NULL,
    files_total INT UNSIGNED DEFAULT 0,
    files_synced INT UNSIGNED DEFAULT 0,
    files_failed INT UNSIGNED DEFAULT 0,
    bytes_transferred BIGINT UNSIGNED DEFAULT 0,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    duration_ms INT UNSIGNED,
    error_message TEXT,
    details JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_target (target_id),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),

    FOREIGN KEY (target_id) REFERENCES preservation_replication_target(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FORMAT CONVERSION PRESETS
-- Predefined conversion configurations
-- =============================================
INSERT IGNORE INTO preservation_format (puid, mime_type, format_name, format_version, extension, risk_level, is_preservation_format, preservation_action, migration_target_id) VALUES
-- Add more formats with migration targets
('fmt/40', 'image/bmp', 'Windows Bitmap', '3.0', 'bmp', 'high', 0, 'migrate', NULL),
('fmt/116', 'image/x-pict', 'Apple PICT', '', 'pct', 'critical', 0, 'migrate', NULL),
('fmt/112', 'application/msword', 'Microsoft Word Document', '97-2003', 'doc', 'high', 0, 'migrate', NULL),
('fmt/61', 'application/vnd.ms-excel', 'Microsoft Excel', '97-2003', 'xls', 'high', 0, 'migrate', NULL),
('fmt/126', 'application/vnd.ms-powerpoint', 'Microsoft PowerPoint', '97-2003', 'ppt', 'high', 0, 'migrate', NULL),
('fmt/5', 'audio/x-aiff', 'Audio Interchange File Format', '', 'aif', 'medium', 0, 'monitor', NULL),
('fmt/527', 'video/quicktime', 'QuickTime Movie', '', 'mov', 'medium', 0, 'monitor', NULL),
('fmt/585', 'video/x-msvideo', 'AVI Video', '', 'avi', 'high', 0, 'migrate', NULL),
('fmt/596', 'video/x-ms-wmv', 'Windows Media Video', '', 'wmv', 'high', 0, 'migrate', NULL);

-- Update migration targets (TIFF for images, PDF/A for documents)
-- Using temporary variable approach to avoid MySQL self-reference error
SET @tiff_id = (SELECT id FROM preservation_format WHERE mime_type = 'image/tiff' LIMIT 1);
SET @pdfa_id = (SELECT id FROM preservation_format WHERE format_name = 'PDF/A-2b' LIMIT 1);
UPDATE preservation_format SET migration_target_id = @tiff_id WHERE mime_type IN ('image/bmp', 'image/x-pict') AND migration_target_id IS NULL AND @tiff_id IS NOT NULL;
UPDATE preservation_format SET migration_target_id = @pdfa_id WHERE mime_type IN ('application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint') AND migration_target_id IS NULL AND @pdfa_id IS NOT NULL;

-- Add virus scan and backup verification policies
INSERT IGNORE INTO preservation_policy (name, description, policy_type, is_active, schedule_cron, config) VALUES
('Daily Virus Scan', 'Scan new uploads for viruses daily', 'fixity', 1, '0 1 * * *', '{"scan_new_only": true, "quarantine_infected": true}'),
('Weekly Backup Verification', 'Verify backup integrity weekly', 'replication', 1, '0 5 * * 0', '{"verify_checksums": true, "sample_files": 100}');

-- =============================================
-- WORKFLOW SCHEDULES
-- Configurable scheduled preservation tasks
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_workflow_schedule (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    workflow_type VARCHAR(112) COMMENT 'format_identification, fixity_check, virus_scan, format_conversion, backup_verification, replication' NOT NULL,
    is_enabled TINYINT(1) DEFAULT 1,

    -- Schedule configuration
    schedule_type VARCHAR(34) COMMENT 'cron, interval, manual' NOT NULL DEFAULT 'cron',
    cron_expression VARCHAR(100) COMMENT 'Cron expression (e.g., 0 2 * * *)',
    interval_hours INT UNSIGNED COMMENT 'Hours between runs for interval type',

    -- Execution limits
    batch_limit INT UNSIGNED DEFAULT 100 COMMENT 'Max objects per run',
    timeout_minutes INT UNSIGNED DEFAULT 60 COMMENT 'Max runtime in minutes',

    -- Options
    options JSON COMMENT 'Workflow-specific options',

    -- Tracking
    last_run_at DATETIME,
    last_run_status VARCHAR(45) COMMENT 'success, partial, failed, timeout' DEFAULT NULL,
    last_run_processed INT UNSIGNED DEFAULT 0,
    last_run_duration_ms INT UNSIGNED,
    next_run_at DATETIME,
    total_runs INT UNSIGNED DEFAULT 0,
    total_processed INT UNSIGNED DEFAULT 0,

    -- Notifications
    notify_on_failure TINYINT(1) DEFAULT 1,
    notify_email VARCHAR(255),

    -- Metadata
    created_by VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workflow_type (workflow_type),
    INDEX idx_enabled (is_enabled),
    INDEX idx_next_run (next_run_at),
    INDEX idx_schedule_type (schedule_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- WORKFLOW RUN LOG
-- Records each execution of a scheduled workflow
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_workflow_run (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id BIGINT UNSIGNED NOT NULL,
    workflow_type VARCHAR(50) NOT NULL,

    -- Execution details
    status VARCHAR(58) COMMENT 'running, completed, failed, timeout, cancelled' NOT NULL DEFAULT 'running',
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    duration_ms INT UNSIGNED,

    -- Results
    objects_processed INT UNSIGNED DEFAULT 0,
    objects_succeeded INT UNSIGNED DEFAULT 0,
    objects_failed INT UNSIGNED DEFAULT 0,
    objects_skipped INT UNSIGNED DEFAULT 0,

    -- Details
    error_message TEXT,
    summary JSON COMMENT 'Detailed run summary',

    -- Execution context
    triggered_by VARCHAR(34) COMMENT 'scheduler, manual, api' DEFAULT 'scheduler',
    triggered_by_user VARCHAR(100),

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_schedule (schedule_id),
    INDEX idx_workflow_type (workflow_type),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at),

    FOREIGN KEY (schedule_id) REFERENCES preservation_workflow_schedule(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- DEFAULT WORKFLOW SCHEDULES
-- =============================================
INSERT IGNORE INTO preservation_workflow_schedule
(name, description, workflow_type, is_enabled, schedule_type, cron_expression, batch_limit, options, created_by) VALUES
('Daily Format Identification', 'Identify formats for new digital objects', 'format_identification', 1, 'cron', '0 1 * * *', 500, '{"unidentified_only": true, "update_registry": true}', 'system'),
('Daily Fixity Check', 'Verify checksums for digital objects', 'fixity_check', 1, 'cron', '0 2 * * *', 500, '{"min_age_days": 7, "algorithm": "sha256"}', 'system'),
('Daily Virus Scan', 'Scan digital objects for malware', 'virus_scan', 1, 'cron', '0 3 * * *', 200, '{"new_only": true, "quarantine": true}', 'system'),
('Weekly Format Conversion', 'Convert at-risk formats to preservation formats', 'format_conversion', 0, 'cron', '0 4 * * 0', 50, '{"target_formats": {"image": "tiff", "document": "pdf"}}', 'system'),
('Weekly Backup Verification', 'Verify backup file integrity', 'backup_verification', 1, 'cron', '0 6 * * 6', 100, '{"verify_checksums": true}', 'system'),
('Daily Replication', 'Replicate files to backup targets', 'replication', 0, 'cron', '0 5 * * *', 500, '{"targets": "all", "verify_after_sync": true}', 'system');

-- =============================================
-- OAIS PACKAGES (SIP/AIP/DIP)
-- Archival information packages per OAIS standard
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_package (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL UNIQUE COMMENT 'Unique package identifier',
    name VARCHAR(255) NOT NULL,
    description TEXT,

    -- Package type per OAIS model
    package_type VARCHAR(25) COMMENT 'sip, aip, dip' NOT NULL,

    -- Package status
    status VARCHAR(65) COMMENT 'draft, building, complete, validated, exported, error' NOT NULL DEFAULT 'draft',

    -- Package format
    package_format VARCHAR(38) COMMENT 'bagit, zip, tar, directory' NOT NULL DEFAULT 'bagit',
    bagit_version VARCHAR(10) DEFAULT '1.0',

    -- Content information
    object_count INT UNSIGNED DEFAULT 0,
    total_size BIGINT UNSIGNED DEFAULT 0 COMMENT 'Total size in bytes',

    -- Checksums
    manifest_algorithm VARCHAR(20) DEFAULT 'sha256',
    package_checksum VARCHAR(128) COMMENT 'Checksum of packaged file',

    -- Paths
    source_path VARCHAR(1024) COMMENT 'Path to package directory or file',
    export_path VARCHAR(1024) COMMENT 'Path to exported package',

    -- OAIS metadata
    originator VARCHAR(255) COMMENT 'Producer/creator of content',
    submission_agreement VARCHAR(255) COMMENT 'Reference to submission agreement',
    retention_period VARCHAR(100) COMMENT 'Retention period if applicable',

    -- Relationships
    parent_package_id BIGINT UNSIGNED COMMENT 'Parent package (e.g., AIP from SIP)',
    information_object_id INT COMMENT 'Root information object if applicable',

    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by VARCHAR(100),
    built_at DATETIME,
    validated_at DATETIME,
    exported_at DATETIME,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    -- Extra metadata as JSON
    metadata JSON COMMENT 'Additional package metadata',

    INDEX idx_package_type (package_type),
    INDEX idx_status (status),
    INDEX idx_uuid (uuid),
    INDEX idx_parent (parent_package_id),
    INDEX idx_info_object (information_object_id),

    FOREIGN KEY (parent_package_id) REFERENCES preservation_package(id) ON DELETE SET NULL,
    FOREIGN KEY (information_object_id) REFERENCES information_object(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PACKAGE CONTENTS
-- Links packages to digital objects
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_package_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT UNSIGNED NOT NULL,
    digital_object_id INT NOT NULL,

    -- File information within package
    relative_path VARCHAR(1024) NOT NULL COMMENT 'Path within package',
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED,

    -- Checksums
    checksum_algorithm VARCHAR(20) DEFAULT 'sha256',
    checksum_value VARCHAR(128),

    -- Format information
    mime_type VARCHAR(100),
    puid VARCHAR(50) COMMENT 'PRONOM identifier',

    -- Object role in package
    object_role VARCHAR(48) COMMENT 'payload, metadata, manifest, tagfile' DEFAULT 'payload',

    -- Sequence for ordering
    sequence INT UNSIGNED DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_package (package_id),
    INDEX idx_digital_object (digital_object_id),
    INDEX idx_role (object_role),

    UNIQUE KEY uk_package_path (package_id, relative_path(500)),
    FOREIGN KEY (package_id) REFERENCES preservation_package(id) ON DELETE CASCADE,
    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- PACKAGE EVENTS
-- Tracks package lifecycle events
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_package_event (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package_id BIGINT UNSIGNED NOT NULL,

    event_type VARCHAR(99) COMMENT 'creation, modification, building, validation, export, import, transfer, deletion, error' NOT NULL,

    event_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_detail TEXT,
    event_outcome VARCHAR(37) COMMENT 'success, failure, warning' DEFAULT 'success',
    event_outcome_detail TEXT,

    -- Agent information
    agent_type VARCHAR(34) COMMENT 'user, system, software' DEFAULT 'system',
    agent_value VARCHAR(255),

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_package (package_id),
    INDEX idx_event_type (event_type),
    INDEX idx_datetime (event_datetime),

    FOREIGN KEY (package_id) REFERENCES preservation_package(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MIGRATION PATHWAY DEFINITIONS
-- Defines format migration routes with tools and quality impact
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_migration_pathway (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_puid VARCHAR(50) NOT NULL COMMENT 'PRONOM identifier of source format',
    target_puid VARCHAR(50) NOT NULL COMMENT 'PRONOM identifier of target format',
    migration_tool VARCHAR(100) NOT NULL COMMENT 'Tool to perform migration (imagemagick, ffmpeg, etc.)',
    migration_command TEXT COMMENT 'Command template with placeholders {input} {output}',
    quality_impact VARCHAR(52) COMMENT 'lossless, minimal, moderate, significant' DEFAULT 'minimal',
    fidelity_score DECIMAL(5,2) COMMENT 'Quality fidelity score 0-100',
    is_recommended TINYINT(1) DEFAULT 0 COMMENT 'Recommended pathway for this source format',
    is_automated TINYINT(1) DEFAULT 1 COMMENT 'Can be run automatically without review',
    priority INT DEFAULT 100 COMMENT 'Priority order when multiple pathways exist',
    notes TEXT COMMENT 'Additional notes about this pathway',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_source (source_puid),
    INDEX idx_target (target_puid),
    INDEX idx_recommended (is_recommended),
    INDEX idx_tool (migration_tool),
    UNIQUE KEY uk_source_target_tool (source_puid, target_puid, migration_tool)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- FORMAT OBSOLESCENCE TRACKING
-- Tracks obsolescence risk for formats in use
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_format_obsolescence (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    format_id BIGINT UNSIGNED NOT NULL COMMENT 'Reference to preservation_format',
    puid VARCHAR(50) NOT NULL COMMENT 'PRONOM identifier',
    current_risk_level VARCHAR(39) COMMENT 'low, medium, high, critical' NOT NULL,
    migration_urgency VARCHAR(45) COMMENT 'none, low, medium, high, critical' DEFAULT 'none',
    affected_object_count INT UNSIGNED DEFAULT 0,
    storage_size_bytes BIGINT UNSIGNED DEFAULT 0 COMMENT 'Total storage for affected objects',
    recommended_action TEXT,
    recommended_pathway_id BIGINT UNSIGNED COMMENT 'Suggested migration pathway',
    last_assessed_at DATETIME,
    next_assessment_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_format (format_id),
    INDEX idx_puid (puid),
    INDEX idx_risk (current_risk_level),
    INDEX idx_urgency (migration_urgency),

    FOREIGN KEY (format_id) REFERENCES preservation_format(id) ON DELETE CASCADE,
    FOREIGN KEY (recommended_pathway_id) REFERENCES preservation_migration_pathway(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MIGRATION PLANNING
-- Plans for batch format migration operations
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_migration_plan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    source_puid VARCHAR(50) NOT NULL,
    target_puid VARCHAR(50) NOT NULL,
    pathway_id BIGINT UNSIGNED COMMENT 'Selected migration pathway',
    status VARCHAR(70) COMMENT 'draft, approved, in_progress, completed, cancelled, failed' DEFAULT 'draft',

    -- Scope
    scope_type VARCHAR(47) COMMENT 'all, repository, collection, custom' DEFAULT 'all',
    scope_criteria JSON COMMENT 'Criteria for object selection',

    -- Progress tracking
    total_objects INT UNSIGNED DEFAULT 0,
    objects_queued INT UNSIGNED DEFAULT 0,
    objects_processed INT UNSIGNED DEFAULT 0,
    objects_succeeded INT UNSIGNED DEFAULT 0,
    objects_failed INT UNSIGNED DEFAULT 0,
    objects_skipped INT UNSIGNED DEFAULT 0,

    -- Storage impact
    original_size_bytes BIGINT UNSIGNED DEFAULT 0,
    converted_size_bytes BIGINT UNSIGNED DEFAULT 0,

    -- Workflow
    created_by INT COMMENT 'User who created the plan',
    approved_by INT COMMENT 'User who approved the plan',
    approved_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,

    -- Options
    keep_originals TINYINT(1) DEFAULT 1 COMMENT 'Keep original files after migration',
    create_preservation_copies TINYINT(1) DEFAULT 1 COMMENT 'Store as preservation copies',
    run_fixity_after TINYINT(1) DEFAULT 1 COMMENT 'Run fixity check after conversion',

    -- Scheduling
    scheduled_start DATETIME COMMENT 'When to start if scheduled',
    max_concurrent INT UNSIGNED DEFAULT 5 COMMENT 'Max concurrent conversions',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_source (source_puid),
    INDEX idx_target (target_puid),
    INDEX idx_pathway (pathway_id),
    INDEX idx_created_by (created_by),

    FOREIGN KEY (pathway_id) REFERENCES preservation_migration_pathway(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- MIGRATION PLAN OBJECTS
-- Links migration plans to specific digital objects
-- =============================================
CREATE TABLE IF NOT EXISTS preservation_migration_plan_object (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    digital_object_id INT NOT NULL,
    status VARCHAR(67) COMMENT 'pending, queued, processing, completed, failed, skipped' DEFAULT 'pending',

    -- Source info
    source_path VARCHAR(1024),
    source_size BIGINT UNSIGNED,
    source_checksum VARCHAR(128),

    -- Result info
    output_path VARCHAR(1024),
    output_size BIGINT UNSIGNED,
    output_checksum VARCHAR(128),

    -- Processing
    queued_at DATETIME,
    started_at DATETIME,
    completed_at DATETIME,
    duration_ms INT UNSIGNED,

    -- Error handling
    error_message TEXT,
    retry_count INT UNSIGNED DEFAULT 0,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_plan (plan_id),
    INDEX idx_object (digital_object_id),
    INDEX idx_status (status),

    UNIQUE KEY uk_plan_object (plan_id, digital_object_id),
    FOREIGN KEY (plan_id) REFERENCES preservation_migration_plan(id) ON DELETE CASCADE,
    FOREIGN KEY (digital_object_id) REFERENCES digital_object(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- SEED DATA: Common Migration Pathways
-- =============================================
INSERT IGNORE INTO preservation_migration_pathway
(source_puid, target_puid, migration_tool, migration_command, quality_impact, fidelity_score, is_recommended, is_automated, priority, notes) VALUES
-- Image conversions to TIFF (preservation format)
('fmt/44', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'lossless', 100.00, 1, 1, 10, 'JPEG to TIFF - standard preservation conversion'),
('fmt/11', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'lossless', 100.00, 1, 1, 10, 'PNG to TIFF - lossless preservation conversion'),
('fmt/41', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'lossless', 100.00, 1, 1, 10, 'GIF to TIFF - lossless conversion'),
('fmt/40', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'lossless', 100.00, 1, 1, 10, 'BMP to TIFF - lossless conversion'),
('fmt/116', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'minimal', 98.00, 1, 1, 10, 'PICT to TIFF - legacy format conversion'),
('fmt/645', 'fmt/353', 'imagemagick', 'convert {input} -compress lzw {output}', 'lossless', 100.00, 1, 1, 10, 'WebP to TIFF'),

-- Image conversions to JPEG 2000 (alternative preservation format)
('fmt/44', 'fmt/392', 'imagemagick', 'convert {input} {output}', 'minimal', 99.00, 0, 1, 20, 'JPEG to JP2 - optional preservation format'),
('fmt/353', 'fmt/392', 'imagemagick', 'convert {input} {output}', 'minimal', 99.00, 0, 1, 20, 'TIFF to JP2 - optional preservation format'),

-- PDF conversions to PDF/A
('fmt/17', 'fmt/354', 'ghostscript', 'gs -dPDFA -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 -sOutputFile={output} {input}', 'minimal', 99.00, 1, 1, 10, 'PDF 1.4 to PDF/A-1a'),
('fmt/18', 'fmt/354', 'ghostscript', 'gs -dPDFA -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 -sOutputFile={output} {input}', 'minimal', 99.00, 1, 1, 10, 'PDF 1.5 to PDF/A-1a'),
('fmt/19', 'fmt/354', 'ghostscript', 'gs -dPDFA -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 -sOutputFile={output} {input}', 'minimal', 99.00, 1, 1, 10, 'PDF 1.6 to PDF/A-1a'),
('fmt/95', 'fmt/354', 'ghostscript', 'gs -dPDFA -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -dPDFACompatibilityPolicy=1 -sOutputFile={output} {input}', 'minimal', 99.00, 1, 1, 10, 'PDF to PDF/A-1a'),

-- PDF to PDF/A-2b (more compatible)
('fmt/95', 'fmt/476', 'ghostscript', 'gs -dPDFA=2 -dBATCH -dNOPAUSE -sColorConversionStrategy=UseDeviceIndependentColor -sDEVICE=pdfwrite -sOutputFile={output} {input}', 'minimal', 99.50, 0, 1, 15, 'PDF to PDF/A-2b - more compatible'),

-- Office documents to PDF/A
('fmt/412', 'fmt/354', 'libreoffice', 'libreoffice --headless --convert-to pdf --outdir {outdir} {input}', 'moderate', 90.00, 1, 0, 10, 'DOCX to PDF/A - requires manual review'),
('fmt/112', 'fmt/354', 'libreoffice', 'libreoffice --headless --convert-to pdf --outdir {outdir} {input}', 'moderate', 85.00, 1, 0, 10, 'DOC to PDF/A - legacy format, requires review'),
('fmt/214', 'fmt/354', 'libreoffice', 'libreoffice --headless --convert-to pdf --outdir {outdir} {input}', 'moderate', 88.00, 1, 0, 10, 'XLSX to PDF/A'),
('fmt/61', 'fmt/354', 'libreoffice', 'libreoffice --headless --convert-to pdf --outdir {outdir} {input}', 'moderate', 82.00, 1, 0, 10, 'XLS to PDF/A - legacy format'),
('fmt/126', 'fmt/354', 'libreoffice', 'libreoffice --headless --convert-to pdf --outdir {outdir} {input}', 'moderate', 80.00, 1, 0, 10, 'PPT to PDF/A - legacy format'),

-- Audio conversions to FLAC (preservation)
('fmt/134', 'fmt/527', 'ffmpeg', 'ffmpeg -i {input} -c:a flac {output}', 'moderate', 75.00, 0, 1, 20, 'MP3 to FLAC - lossy source, cannot restore quality'),
('fmt/141', 'fmt/527', 'ffmpeg', 'ffmpeg -i {input} -c:a flac {output}', 'lossless', 100.00, 1, 1, 10, 'WAV to FLAC - lossless compression'),
('fmt/5', 'fmt/527', 'ffmpeg', 'ffmpeg -i {input} -c:a flac {output}', 'lossless', 100.00, 1, 1, 10, 'AIFF to FLAC - lossless compression'),

-- Audio conversions to WAV (preservation)
('fmt/134', 'fmt/141', 'ffmpeg', 'ffmpeg -i {input} -c:a pcm_s16le {output}', 'moderate', 75.00, 0, 1, 20, 'MP3 to WAV - lossy source'),
('fmt/527', 'fmt/141', 'ffmpeg', 'ffmpeg -i {input} -c:a pcm_s16le {output}', 'lossless', 100.00, 0, 1, 15, 'FLAC to WAV - lossless expansion'),

-- Video conversions
('fmt/585', 'fmt/199', 'ffmpeg', 'ffmpeg -i {input} -c:v libx264 -crf 18 -c:a aac {output}', 'minimal', 95.00, 1, 1, 10, 'AVI to MP4 - good quality H.264'),
('fmt/596', 'fmt/199', 'ffmpeg', 'ffmpeg -i {input} -c:v libx264 -crf 18 -c:a aac {output}', 'minimal', 95.00, 1, 1, 10, 'WMV to MP4 - good quality H.264'),
('fmt/527', 'fmt/199', 'ffmpeg', 'ffmpeg -i {input} -c:v libx264 -crf 18 -c:a aac {output}', 'minimal', 95.00, 0, 1, 15, 'MOV to MP4'),

-- Video to FFV1 (archival lossless codec)
('fmt/585', 'fmt/569', 'ffmpeg', 'ffmpeg -i {input} -c:v ffv1 -level 3 -c:a flac {output}', 'lossless', 100.00, 0, 0, 30, 'AVI to MKV/FFV1 - archival lossless, large files'),
('fmt/199', 'fmt/569', 'ffmpeg', 'ffmpeg -i {input} -c:v ffv1 -level 3 -c:a flac {output}', 'lossless', 100.00, 0, 0, 30, 'MP4 to MKV/FFV1 - archival lossless');

-- =============================================
-- DEFAULT OBSOLESCENCE ASSESSMENTS
-- Initialize obsolescence tracking for high-risk formats
-- =============================================
INSERT IGNORE INTO preservation_format_obsolescence
(format_id, puid, current_risk_level, migration_urgency, recommended_action, last_assessed_at)
SELECT
    pf.id,
    pf.puid,
    pf.risk_level,
    CASE
        WHEN pf.risk_level = 'critical' THEN 'critical'
        WHEN pf.risk_level = 'high' THEN 'high'
        ELSE 'none'
    END,
    CASE
        WHEN pf.risk_level = 'critical' THEN 'Immediate migration required - format no longer supported'
        WHEN pf.risk_level = 'high' THEN 'Migration recommended within 12 months'
        ELSE NULL
    END,
    NOW()
FROM preservation_format pf
WHERE pf.risk_level IN ('high', 'critical') AND pf.puid IS NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
