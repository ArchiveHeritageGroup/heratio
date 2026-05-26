-- ============================================================================
-- ahg-backup — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgBackupPlugin/database/install.sql
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
-- ahgBackupPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `backup_history`
--



CREATE TABLE IF NOT EXISTS `backup_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `backup_id` varchar(100) NOT NULL,
  `backup_path` varchar(500) NOT NULL,
  `backup_type` VARCHAR(46) DEFAULT 'full' COMMENT 'full, database, files, incremental',
  `status` VARCHAR(51) DEFAULT 'pending' COMMENT 'pending, in_progress, completed, failed',
  `size_bytes` bigint DEFAULT '0',
  `components` json DEFAULT NULL,
  `error_message` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `backup_id` (`backup_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `backup_schedule`
--



CREATE TABLE IF NOT EXISTS `backup_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `frequency` VARCHAR(42) DEFAULT 'daily' COMMENT 'hourly, daily, weekly, monthly',
  `time` time DEFAULT '02:00:00',
  `day_of_week` tinyint DEFAULT NULL,
  `day_of_month` tinyint DEFAULT NULL,
  `include_database` tinyint(1) DEFAULT '1',
  `include_uploads` tinyint(1) DEFAULT '1',
  `include_plugins` tinyint(1) DEFAULT '1',
  `include_framework` tinyint(1) DEFAULT '1',
  `retention_days` int DEFAULT '30',
  `is_active` tinyint(1) DEFAULT '1',
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `backup_setting`
--



CREATE TABLE IF NOT EXISTS `backup_setting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(42) DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












--
-- Table structure for table `ahg_backup_replication`
--
-- Issue #671 Phase 3 - off-site replication ledger. One row per local
-- backup file that has been pushed to (or attempted against) an off-site
-- driver. Used by `backup:replicate` to skip already-replicated files and
-- by `backup:verify-integrity` to walk what needs re-checking.
--

CREATE TABLE IF NOT EXISTS `ahg_backup_replication` (
  `id` int NOT NULL AUTO_INCREMENT,
  `local_path` varchar(500) NOT NULL,
  `remote_path` varchar(500) NOT NULL,
  `driver` varchar(32) NOT NULL COMMENT 's3, rsync, localfs',
  `size_bytes` bigint DEFAULT '0',
  `sha256` char(64) DEFAULT NULL,
  `encrypted` tinyint(1) DEFAULT '0',
  `replicated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `verified_at` datetime DEFAULT NULL,
  `status` VARCHAR(24) DEFAULT 'replicated' COMMENT 'replicated, verified, failed',
  `error` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_local_path_driver` (`local_path`,`driver`),
  KEY `idx_status` (`status`),
  KEY `idx_replicated_at` (`replicated_at`),
  KEY `idx_verified_at` (`verified_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


SET FOREIGN_KEY_CHECKS = 1;
