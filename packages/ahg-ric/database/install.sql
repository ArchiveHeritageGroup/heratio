-- ============================================================================
-- ahg-ric — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgRicExplorerPlugin/database/install.sql
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
-- ahgRicExplorerPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `ric_orphan_tracking`
--



CREATE TABLE IF NOT EXISTS `ric_orphan_tracking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_entity_id` int DEFAULT NULL,
  `detected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `detection_method` VARCHAR(49) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'integrity_check, sync_failure, manual',
  `status` VARCHAR(59) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detected' COMMENT 'detected, reviewed, cleaned, retained, restored',
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci,
  `triple_count` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_orphan_uri` (`ric_uri`(255)),
  KEY `idx_ric_orphan_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--

SET @saved_cs_client     = @@character_set_client;


SET character_set_client = @saved_cs_client;

--

SET @saved_cs_client     = @@character_set_client;


SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ric_sync_config`
--



CREATE TABLE IF NOT EXISTS `ric_sync_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_unicode_ci,
  `description` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_config_key` (`config_key`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ric_sync_log`
--



CREATE TABLE IF NOT EXISTS `ric_sync_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `operation` VARCHAR(74) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create, update, delete, move, resync, cleanup, integrity_check',
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(46) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'success, failure, partial, skipped',
  `triples_affected` int DEFAULT NULL,
  `details` json DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `execution_time_ms` int DEFAULT NULL,
  `triggered_by` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system' COMMENT 'user, system, cron, api, cli',
  `user_id` int DEFAULT NULL,
  `batch_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_log_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_log_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ric_sync_queue`
--



CREATE TABLE IF NOT EXISTS `ric_sync_queue` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `operation` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'create, update, delete, move',
  `priority` tinyint NOT NULL DEFAULT '5',
  `status` VARCHAR(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued' COMMENT 'queued, processing, completed, failed, cancelled',
  `attempts` int NOT NULL DEFAULT '0',
  `max_attempts` int NOT NULL DEFAULT '3',
  `old_parent_id` int DEFAULT NULL,
  `new_parent_id` int DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `last_error` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ric_queue_status` (`status`,`priority`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `ric_sync_status`
--



CREATE TABLE IF NOT EXISTS `ric_sync_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `ric_uri` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ric_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sync_status` VARCHAR(54) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'synced, pending, failed, deleted, orphaned',
  `last_synced_at` datetime DEFAULT NULL,
  `last_sync_attempt` datetime DEFAULT NULL,
  `sync_error` text COLLATE utf8mb4_unicode_ci,
  `retry_count` int NOT NULL DEFAULT '0',
  `content_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `atom_updated_at` datetime DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `hierarchy_path` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_ric_sync_entity` (`entity_type`,`entity_id`),
  KEY `idx_ric_sync_uri` (`ric_uri`(255)),
  KEY `idx_ric_sync_status` (`sync_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--

SET @saved_cs_client     = @@character_set_client;


SET character_set_client = @saved_cs_client;

--














--














--
























SET FOREIGN_KEY_CHECKS = 1;
