-- ============================================================================
-- ahg-audit-trail — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgAuditTrailPlugin/database/install.sql
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

-- ============================================================
-- ahgAuditTrailPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `ahg_audit_access`
--



CREATE TABLE IF NOT EXISTS `ahg_audit_access` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_classification` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `security_clearance_level` int unsigned DEFAULT NULL,
  `clearance_verified` tinyint(1) NOT NULL DEFAULT '0',
  `file_path` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `denial_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_access_uuid` (`uuid`),
  KEY `idx_ahg_access_user` (`user_id`),
  KEY `idx_ahg_access_type` (`access_type`),
  KEY `idx_ahg_access_entity` (`entity_type`,`entity_id`),
  KEY `idx_ahg_access_security` (`security_classification`),
  KEY `idx_ahg_access_created` (`created_at`),
  CONSTRAINT `fk_ahg_audit_access_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ahg_audit_authentication`
--



CREATE TABLE IF NOT EXISTS `ahg_audit_authentication` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failed_attempts` int unsigned NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_auth_uuid` (`uuid`),
  KEY `idx_ahg_auth_user` (`user_id`),
  KEY `idx_ahg_auth_event` (`event_type`),
  KEY `idx_ahg_auth_ip` (`ip_address`),
  KEY `idx_ahg_auth_created` (`created_at`),
  CONSTRAINT `fk_ahg_audit_auth_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ahg_audit_log`
--



CREATE TABLE IF NOT EXISTS `ahg_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `session_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_title` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_method` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_uri` varchar(2000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `changed_fields` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `security_classification` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `culture_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_audit_uuid` (`uuid`),
  KEY `idx_ahg_audit_user` (`user_id`),
  KEY `idx_ahg_audit_action` (`action`),
  KEY `idx_ahg_audit_entity_type` (`entity_type`),
  KEY `idx_ahg_audit_entity_id` (`entity_id`),
  KEY `idx_ahg_audit_created` (`created_at`),
  KEY `idx_ahg_audit_status` (`status`),
  KEY `idx_ahg_audit_ip` (`ip_address`),
  KEY `idx_ahg_audit_security` (`security_classification`),
  KEY `idx_ahg_audit_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_ahg_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ahg_audit_retention_policy`
--



CREATE TABLE IF NOT EXISTS `ahg_audit_retention_policy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `log_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `retention_days` int unsigned NOT NULL DEFAULT '2555',
  `archive_before_delete` tinyint(1) NOT NULL DEFAULT '1',
  `archive_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_cleanup_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_retention_type` (`log_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ahg_audit_settings`
--



CREATE TABLE IF NOT EXISTS `ahg_audit_settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `setting_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ahg_settings_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- Seed: Default audit settings (enabled by default for security compliance)
INSERT IGNORE INTO `ahg_audit_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('audit_enabled', '1', 'boolean', 'Enable audit trail logging'),
('audit_authentication', '1', 'boolean', 'Log authentication events (login, logout, failed login)'),
('audit_views', '0', 'boolean', 'Log view-only actions (high volume — enable only when needed)'),
('retention_days', '365', 'integer', 'Number of days to retain audit log entries');










SET FOREIGN_KEY_CHECKS = 1;
