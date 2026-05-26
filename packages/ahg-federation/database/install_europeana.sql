-- ============================================================================
-- ahg-federation - Europeana EDM publish - install schema
-- ============================================================================
-- Phase 4 of #670 (Federation audit). Tracks Europeana EDM export runs so
-- the admin dashboard can show last-run timestamp, record count, bundle
-- path/size, and any error message.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS ahg_europeana_export (
    id INT PRIMARY KEY AUTO_INCREMENT,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    record_count INT NOT NULL DEFAULT 0,
    bundle_path VARCHAR(1024) NULL,
    bundle_size_bytes BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'running'
        COMMENT 'running | success | error',
    error TEXT NULL,
    created_at DATETIME NULL,
    INDEX idx_aee_status (status),
    INDEX idx_aee_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
