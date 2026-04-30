-- ============================================================================
-- ahg-multi-tenant — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgMultiTenantPlugin/database/install.sql
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

-- ahgMultiTenantPlugin Installation SQL
-- Version: 1.1.0
-- This plugin uses both dedicated tenant tables and ahg_settings for storage.

-- ============================================================================
-- Legacy Support: ahg_settings table (for backward compatibility)
-- ============================================================================
CREATE TABLE IF NOT EXISTS ahg_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(49) COMMENT 'string, integer, boolean, json, float' DEFAULT 'string',
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    description VARCHAR(500),
    is_sensitive TINYINT(1) DEFAULT 0,
    updated_by INT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_setting_group (setting_group),
    FOREIGN KEY (updated_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_tenant
-- Stores tenant (organization) information for multi-tenancy
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_tenant (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE COMMENT 'Unique tenant code/slug',
    name VARCHAR(255) NOT NULL COMMENT 'Display name of the tenant',
    domain VARCHAR(255) DEFAULT NULL COMMENT 'Custom domain for the tenant',
    subdomain VARCHAR(100) DEFAULT NULL COMMENT 'Subdomain for the tenant',
    settings JSON DEFAULT NULL COMMENT 'Tenant-specific settings override',
    status VARCHAR(36) COMMENT 'active, suspended, trial' NOT NULL DEFAULT 'trial' COMMENT 'Tenant status',
    trial_ends_at DATETIME DEFAULT NULL COMMENT 'Trial expiration date',
    suspended_at DATETIME DEFAULT NULL COMMENT 'When tenant was suspended',
    suspended_reason VARCHAR(500) DEFAULT NULL COMMENT 'Reason for suspension',
    repository_id INT DEFAULT NULL COMMENT 'Link to AtoM repository (optional)',
    contact_name VARCHAR(255) DEFAULT NULL COMMENT 'Primary contact name',
    contact_email VARCHAR(255) DEFAULT NULL COMMENT 'Primary contact email',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT DEFAULT NULL COMMENT 'User who created the tenant',

    INDEX idx_tenant_code (code),
    INDEX idx_tenant_status (status),
    INDEX idx_tenant_domain (domain),
    INDEX idx_tenant_subdomain (subdomain),
    INDEX idx_tenant_repository (repository_id),

    FOREIGN KEY (repository_id) REFERENCES repository(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_tenant_user
-- Maps users to tenants with roles
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_tenant_user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(58) COMMENT 'owner, super_user, editor, contributor, viewer' NOT NULL DEFAULT 'viewer' COMMENT 'User role within tenant',
    is_primary TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Is this the users primary tenant',
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT DEFAULT NULL COMMENT 'User who assigned this user',

    UNIQUE KEY uk_tenant_user (tenant_id, user_id),
    INDEX idx_tenant_user_tenant (tenant_id),
    INDEX idx_tenant_user_user (user_id),
    INDEX idx_tenant_user_role (role),

    FOREIGN KEY (tenant_id) REFERENCES heritage_tenant(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Table: heritage_tenant_settings_override
-- Stores per-tenant settings that override global defaults
-- ============================================================================
CREATE TABLE IF NOT EXISTS heritage_tenant_settings_override (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT DEFAULT NULL,

    UNIQUE KEY uk_tenant_setting (tenant_id, setting_key),
    INDEX idx_tenant_setting_key (setting_key),

    FOREIGN KEY (tenant_id) REFERENCES heritage_tenant(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Legacy Settings Format (still supported for backward compatibility):
-- tenant_repo_{repository_id}_super_users = "5,12,18" (comma-separated user IDs)
-- tenant_repo_{repository_id}_users = "22,25,30" (comma-separated user IDs)
-- tenant_repo_{repository_id}_primary_color = "#336699"
-- tenant_repo_{repository_id}_secondary_color = "#6c757d"
-- tenant_repo_{repository_id}_header_bg_color = "#212529"
-- tenant_repo_{repository_id}_header_text_color = "#ffffff"
-- tenant_repo_{repository_id}_link_color = "#0d6efd"
-- tenant_repo_{repository_id}_button_color = "#198754"
-- tenant_repo_{repository_id}_logo = "/uploads/tenants/{repository_id}/logo.png"
-- tenant_repo_{repository_id}_custom_css = "..."
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
