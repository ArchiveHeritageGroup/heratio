-- ============================================================================
-- ahg-sharepoint — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgSharePointPlugin/database/install.sql
-- on 2026-05-10. Heratio standalone install — Phase 1.
--
-- Transforms applied (matching ahg-ingest convention):
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data.
--
-- Schema MUST stay byte-equivalent to the AtoM plugin install.sql (modulo
-- this header). Any drift is a parity bug.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- 1. sharepoint_tenant
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_tenant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Friendly label',
    tenant_id VARCHAR(64) NOT NULL COMMENT 'Azure AD tenant GUID',
    client_id VARCHAR(64) NOT NULL COMMENT 'App registration GUID',
    client_secret_ref VARCHAR(255) NOT NULL COMMENT 'Reference to encrypted blob in ahg_settings',
    previous_secret_ref VARCHAR(255) DEFAULT NULL COMMENT 'Prior secret kept during rotation overlap',
    previous_secret_until DATETIME DEFAULT NULL COMMENT 'When previous_secret_ref expires',
    graph_endpoint VARCHAR(255) DEFAULT 'https://graph.microsoft.com/v1.0' COMMENT 'Graph base URL',
    default_site_id VARCHAR(255) DEFAULT NULL COMMENT 'Graph site identifier',
    webhook_client_state VARCHAR(64) NOT NULL COMMENT 'Random secret echoed by Graph for validation',
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, disabled, error',
    last_token_at DATETIME DEFAULT NULL,
    last_error TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_status (status),
    UNIQUE KEY uniq_tenant (tenant_id, client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. sharepoint_drive
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_drive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    site_id VARCHAR(255) NOT NULL COMMENT 'Graph site identifier',
    site_url VARCHAR(1000) NOT NULL,
    site_title VARCHAR(500) DEFAULT NULL,
    drive_id VARCHAR(255) NOT NULL,
    drive_name VARCHAR(500) DEFAULT NULL,
    ingest_enabled TINYINT(1) NOT NULL DEFAULT 0,
    sector VARCHAR(50) NOT NULL DEFAULT 'archive' COMMENT 'archive, museum, library, gallery, dam',
    default_repository_id INT DEFAULT NULL COMMENT 'Heratio repository for ingested records',
    default_parent_id INT DEFAULT NULL COMMENT 'Heratio info_object for placement',
    default_parent_placement VARCHAR(51) NOT NULL DEFAULT 'top_level' COMMENT 'existing, new, top_level, csv_hierarchy',
    ai_processing_inherit TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Inherit AI flags from ingest defaults',
    content_type_filter VARCHAR(500) DEFAULT NULL COMMENT 'Comma list of SP content type IDs',
    last_full_sync_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tenant (tenant_id),
    KEY idx_ingest_enabled (ingest_enabled),
    UNIQUE KEY uniq_tenant_drive (tenant_id, drive_id),
    CONSTRAINT fk_sp_drive_tenant FOREIGN KEY (tenant_id) REFERENCES sharepoint_tenant(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. sharepoint_mapping
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drive_id INT NOT NULL,
    content_type_id VARCHAR(255) DEFAULT NULL COMMENT 'NULL = applies to all CTs in drive',
    source_field VARCHAR(255) NOT NULL COMMENT 'SP column internal name',
    target_field VARCHAR(255) NOT NULL COMMENT 'Heratio field name',
    target_standard VARCHAR(47) NOT NULL DEFAULT 'isadg',
    transform VARCHAR(100) DEFAULT NULL COMMENT 'date_iso, taxonomy_lookup, html_strip, etc.',
    default_value VARCHAR(500) DEFAULT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_drive (drive_id),
    CONSTRAINT fk_sp_mapping_drive FOREIGN KEY (drive_id) REFERENCES sharepoint_drive(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. sharepoint_sync_state
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_sync_state (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drive_id INT NOT NULL,
    delta_link TEXT DEFAULT NULL COMMENT 'Opaque Graph delta URL',
    last_run_at DATETIME DEFAULT NULL,
    last_status VARCHAR(20) DEFAULT NULL COMMENT 'ok, error, in_progress',
    last_error TEXT DEFAULT NULL,
    items_processed INT NOT NULL DEFAULT 0,
    UNIQUE KEY uniq_drive (drive_id),
    CONSTRAINT fk_sp_sync_drive FOREIGN KEY (drive_id) REFERENCES sharepoint_drive(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. sharepoint_subscription (Phase 2)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_subscription (
    id INT AUTO_INCREMENT PRIMARY KEY,
    drive_id INT NOT NULL,
    subscription_id VARCHAR(64) NOT NULL COMMENT 'Graph-issued GUID',
    resource VARCHAR(500) NOT NULL COMMENT 'e.g. /sites/{id}/drives/{id}/root',
    change_type VARCHAR(50) NOT NULL DEFAULT 'updated' COMMENT 'created, updated, deleted',
    notification_url VARCHAR(1000) NOT NULL,
    client_state VARCHAR(64) NOT NULL COMMENT 'Echoed back for validation',
    expires_at DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, expired, renewing, error, deleted',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_renewed_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_subscription (subscription_id),
    KEY idx_status_expires (status, expires_at),
    CONSTRAINT fk_sp_sub_drive FOREIGN KEY (drive_id) REFERENCES sharepoint_drive(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. sharepoint_event (Phase 2)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_event (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    drive_id INT NOT NULL,
    sp_item_id VARCHAR(255) DEFAULT NULL,
    sp_etag VARCHAR(255) DEFAULT NULL,
    change_type VARCHAR(50) NOT NULL,
    raw_payload JSON NOT NULL COMMENT 'As received from Graph',
    status VARCHAR(20) NOT NULL DEFAULT 'received' COMMENT 'received, queued, processing, completed, failed, skipped_duplicate',
    attempts INT NOT NULL DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    queue_job_id INT DEFAULT NULL COMMENT 'FK to ahg_queue_job (no constraint to avoid cross-package coupling)',
    ingest_job_id INT DEFAULT NULL COMMENT 'FK to ingest_job',
    information_object_id INT DEFAULT NULL COMMENT 'Heratio IO created/updated',
    received_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    KEY idx_status (status),
    KEY idx_drive_item (drive_id, sp_item_id),
    KEY idx_received_at (received_at),
    CONSTRAINT fk_sp_event_sub FOREIGN KEY (subscription_id) REFERENCES sharepoint_subscription(id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_event_drive FOREIGN KEY (drive_id) REFERENCES sharepoint_drive(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 7. sharepoint_user_mapping (Phase 2.B)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS sharepoint_user_mapping (
    id INT AUTO_INCREMENT PRIMARY KEY,
    aad_object_id VARCHAR(64) NOT NULL COMMENT 'AAD oid claim',
    aad_upn VARCHAR(255) DEFAULT NULL,
    aad_email VARCHAR(255) DEFAULT NULL,
    atom_user_id INT NOT NULL COMMENT 'FK to user.id',
    created_by VARCHAR(20) NOT NULL DEFAULT 'auto' COMMENT 'auto, manual, admin',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME DEFAULT NULL,
    UNIQUE KEY uniq_aad (aad_object_id),
    KEY idx_user (atom_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
