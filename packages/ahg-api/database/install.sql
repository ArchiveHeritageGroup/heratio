-- ============================================================================
-- ahg-api — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgAPIPlugin/database/install.sql
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

-- ============================================================================
-- ahgAPIPlugin Database Schema - Enhanced REST API v2
-- ============================================================================

-- API Keys (enhanced)
CREATE TABLE IF NOT EXISTS ahg_api_key (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    api_key_prefix VARCHAR(8) NOT NULL,
    scopes JSON DEFAULT NULL,
    rate_limit INT DEFAULT 1000,
    expires_at DATETIME DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_api_key_prefix (api_key_prefix),
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Request Log
CREATE TABLE IF NOT EXISTS ahg_api_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    method VARCHAR(10) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    status_code INT NOT NULL,
    request_body MEDIUMTEXT DEFAULT NULL,
    response_size INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    duration_ms INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_api_key_id (api_key_id),
    INDEX idx_created_at (created_at),
    INDEX idx_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rate Limiting
CREATE TABLE IF NOT EXISTS ahg_api_rate_limit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT NOT NULL,
    window_start DATETIME NOT NULL,
    request_count INT DEFAULT 1,
    UNIQUE KEY unique_key_window (api_key_id, window_start),
    INDEX idx_window_start (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhooks
CREATE TABLE IF NOT EXISTS ahg_webhook (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(64) NOT NULL,
    events JSON NOT NULL,
    entity_types JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    failure_count INT DEFAULT 0,
    last_triggered_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Webhook Delivery Log
CREATE TABLE IF NOT EXISTS ahg_webhook_delivery (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    payload JSON NOT NULL,
    response_code INT DEFAULT NULL,
    response_body TEXT DEFAULT NULL,
    attempt_count INT DEFAULT 1,
    status VARCHAR(46) COMMENT 'pending, success, failed, retrying' DEFAULT 'pending',
    next_retry_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME DEFAULT NULL,
    INDEX idx_webhook_id (webhook_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Display standard to sector mapping (no hardcoded values in PHP)
CREATE TABLE IF NOT EXISTS display_standard_sector (
    id INT AUTO_INCREMENT PRIMARY KEY,
    term_id INT NOT NULL,
    sector VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_term_id (term_id),
    KEY idx_sector (sector)
);

SET FOREIGN_KEY_CHECKS = 1;
