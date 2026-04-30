-- ============================================================================
-- ahg-user-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgUserManagePlugin/database/install.sql
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

-- ahgUserManagePlugin: No custom tables required

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgUserRegistrationPlugin on 2026-04-30
-- ============================================================================
-- ahgUserRegistrationPlugin - Registration request table
-- Stores pending registration requests with email verification tokens

CREATE TABLE IF NOT EXISTS ahg_registration_request (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    salt VARCHAR(64) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    institution VARCHAR(255) DEFAULT NULL,
    research_interest TEXT DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, verified, approved, rejected, expired',
    email_token VARCHAR(64) NOT NULL,
    email_verified_at TIMESTAMP NULL,
    admin_notes TEXT DEFAULT NULL,
    reviewed_by INT DEFAULT NULL,
    reviewed_at TIMESTAMP NULL,
    assigned_group_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_email (email),
    UNIQUE KEY uk_email_token (email_token),
    INDEX idx_status (status),
    INDEX idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
