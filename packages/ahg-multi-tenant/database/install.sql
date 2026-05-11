-- ============================================================================
-- ahg-multi-tenant - install schema
-- ============================================================================
-- Heratio standalone multi-tenancy. Single-database, repository-scoped tenants.
-- Each tenant maps to a `repository` row; data scoping is enforced via the
-- existing `repository_id` FK on `information_object`, `digital_object`, etc.
-- Tenant roles (ahg_tenant_user.role) SUPPLEMENT ahg-acl - they do not
-- replace it.
--
-- Idempotent. Safe to re-run on existing installs.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- ahg_tenant - one row per tenant; FK to repository for data scoping
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ahg_tenant` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NOT NULL COMMENT 'Unique tenant code / slug',
    `name` VARCHAR(255) NOT NULL COMMENT 'Display name',
    `description` TEXT DEFAULT NULL,
    `domain` VARCHAR(255) DEFAULT NULL COMMENT 'Full custom domain (e.g. tenant1.example.com)',
    `subdomain` VARCHAR(100) DEFAULT NULL COMMENT 'Subdomain segment (alternative to full domain)',
    `repository_id` INT DEFAULT NULL COMMENT 'Primary repository this tenant scopes to',
    `contact_email` VARCHAR(255) DEFAULT NULL,
    `contact_phone` VARCHAR(50) DEFAULT NULL,
    `max_users` INT DEFAULT NULL COMMENT 'Quota; NULL = unlimited',
    `max_storage_gb` INT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Fallback tenant when no host / session match',
    `status` VARCHAR(36) NOT NULL DEFAULT 'active' COMMENT 'active, suspended, trial',
    `trial_ends_at` DATETIME DEFAULT NULL,
    `suspended_at` DATETIME DEFAULT NULL,
    `suspended_reason` VARCHAR(500) DEFAULT NULL,
    `settings` JSON DEFAULT NULL COMMENT 'Free-form per-tenant settings',
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_tenant_code` (`code`),
    UNIQUE KEY `uk_tenant_domain` (`domain`),
    UNIQUE KEY `uk_tenant_subdomain` (`subdomain`),
    KEY `idx_tenant_status` (`status`, `is_active`),
    KEY `idx_tenant_repository` (`repository_id`),
    KEY `idx_tenant_default` (`is_default`),

    CONSTRAINT `fk_ahg_tenant_repository` FOREIGN KEY (`repository_id`) REFERENCES `repository` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_ahg_tenant_created_by` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ahg_tenant_user - per-tenant role assignments. Supplements ahg-acl.
-- A user may belong to multiple tenants; is_primary picks the default.
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ahg_tenant_user` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `role` VARCHAR(50) NOT NULL DEFAULT 'viewer' COMMENT 'owner, super_user, editor, contributor, viewer',
    `is_super_user` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Convenience flag mirroring role=super_user|owner',
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Default tenant for this user when no host/session match',
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `assigned_by` INT DEFAULT NULL,

    UNIQUE KEY `uk_tenant_user` (`tenant_id`, `user_id`),
    KEY `idx_tu_user` (`user_id`),
    KEY `idx_tu_role` (`role`),
    KEY `idx_tu_primary` (`user_id`, `is_primary`),

    CONSTRAINT `fk_ahg_tu_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ahg_tenant` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ahg_tu_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ahg_tu_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ahg_tenant_branding - per-tenant theme overrides (logo, colours, custom CSS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ahg_tenant_branding` (
    `tenant_id` INT PRIMARY KEY,
    `logo_url` VARCHAR(500) DEFAULT NULL,
    `primary_color` VARCHAR(20) DEFAULT NULL,
    `secondary_color` VARCHAR(20) DEFAULT NULL,
    `header_bg_color` VARCHAR(20) DEFAULT NULL,
    `header_text_color` VARCHAR(20) DEFAULT NULL,
    `link_color` VARCHAR(20) DEFAULT NULL,
    `button_color` VARCHAR(20) DEFAULT NULL,
    `header_html` TEXT DEFAULT NULL,
    `footer_html` TEXT DEFAULT NULL,
    `custom_css` MEDIUMTEXT DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_ahg_tb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ahg_tenant` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ahg_tenant_settings_override - per-tenant overrides on ahg_settings keys
-- ============================================================================
CREATE TABLE IF NOT EXISTS `ahg_tenant_settings_override` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT DEFAULT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updated_by` INT DEFAULT NULL,

    UNIQUE KEY `uk_tenant_setting` (`tenant_id`, `setting_key`),
    KEY `idx_tso_key` (`setting_key`),

    CONSTRAINT `fk_ahg_tso_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `ahg_tenant` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ahg_tso_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
