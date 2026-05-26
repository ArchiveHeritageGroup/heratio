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


--
-- Table structure for table `ahg_backup_run`
--
-- Issue #671 Phase 4 - PITR coordinate map. One row per *full* backup
-- (mysqldump) capturing the binary-log coordinates at dump time. PITR
-- restore reads the row that ran most recently before the target time,
-- replays mysqlbinlog from (`binlog_file`,`binlog_pos`) up to the
-- target timestamp. `gtid_executed` is captured when GTID is enabled
-- (preferred over file+pos when both are available).
--
-- Empty `binlog_file` is legitimate - it means binary logging was not
-- enabled on the server when the dump ran, so PITR replay is not
-- possible from this run. The PITR command refuses to use such rows.
--

CREATE TABLE IF NOT EXISTS `ahg_backup_run` (
  `id` int NOT NULL AUTO_INCREMENT,
  `backup_path` varchar(500) NOT NULL,
  `backup_filename` varchar(255) NOT NULL,
  `dumped_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `db_name` varchar(64) NOT NULL,
  `binlog_file` varchar(255) DEFAULT NULL COMMENT 'mysql-bin.XXXXXX from SHOW MASTER STATUS at dump time',
  `binlog_pos` bigint DEFAULT NULL,
  `gtid_executed` text COMMENT 'GTID set from SHOW MASTER STATUS, when GTID is enabled',
  `binlog_format` varchar(16) DEFAULT NULL COMMENT 'ROW (required for PITR), STATEMENT, MIXED',
  `log_bin_enabled` tinyint(1) DEFAULT '0',
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_backup_filename` (`backup_filename`),
  KEY `idx_dumped_at` (`dumped_at`),
  KEY `idx_db_name` (`db_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `ahg_backup_binlog`
--
-- Issue #671 Phase 4 - archive of rotated binary-log files. The
-- `backup:archive-binlogs` command runs hourly, executes
-- `FLUSH BINARY LOGS`, copies the now-closed log files into
-- `storage/backups/binlogs/`, and records one row per archived file
-- here. The Phase 3 off-site replicator picks these up from the
-- backups directory on its next sweep.
--
-- `archived_at` is the wall-clock time of the copy operation, which is
-- also the upper bound of activity that can be recovered from this
-- file via PITR.
--

CREATE TABLE IF NOT EXISTS `ahg_backup_binlog` (
  `id` int NOT NULL AUTO_INCREMENT,
  `binlog_file` varchar(255) NOT NULL,
  `archive_path` varchar(500) NOT NULL,
  `size_bytes` bigint DEFAULT '0',
  `sha256` char(64) DEFAULT NULL,
  `archived_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `first_event_at` datetime DEFAULT NULL COMMENT 'best-effort earliest event timestamp, when known',
  `last_event_at` datetime DEFAULT NULL COMMENT 'best-effort latest event timestamp, when known',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_binlog_file` (`binlog_file`),
  KEY `idx_archived_at` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


SET FOREIGN_KEY_CHECKS = 1;
