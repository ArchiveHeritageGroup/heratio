-- ============================================================================
-- ahg-access-request — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgAccessRequestPlugin/database/install.sql
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
-- ahgAccessRequestPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `access_request`
--



CREATE TABLE IF NOT EXISTS `access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_type` VARCHAR(64) DEFAULT 'clearance' COMMENT 'clearance, object, repository, authority, researcher',
  `scope_type` VARCHAR(70) DEFAULT 'single' COMMENT 'single, with_children, collection, repository_all, renewal',
  `user_id` int unsigned NOT NULL,
  `requested_classification_id` int unsigned NOT NULL,
  `current_classification_id` int unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `justification` text,
  `urgency` VARCHAR(39) DEFAULT 'normal' COMMENT 'low, normal, high, critical',
  `status` VARCHAR(57) DEFAULT 'pending' COMMENT 'pending, approved, denied, cancelled, expired',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_classification` (`requested_classification_id`),
  KEY `idx_reviewed_by` (`reviewed_by`),
  KEY `current_classification_id` (`current_classification_id`),
  CONSTRAINT `access_request_ibfk_1` FOREIGN KEY (`requested_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE CASCADE,
  CONSTRAINT `access_request_ibfk_2` FOREIGN KEY (`current_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `access_request_approver`
--



CREATE TABLE IF NOT EXISTS `access_request_approver` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `min_classification_level` int unsigned DEFAULT '0',
  `max_classification_level` int unsigned DEFAULT '5',
  `email_notifications` tinyint(1) DEFAULT '1',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `access_request_justification`
--



CREATE TABLE IF NOT EXISTS `access_request_justification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `template_id` int unsigned DEFAULT NULL,
  `justification_text` text NOT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `access_request_log`
--



CREATE TABLE IF NOT EXISTS `access_request_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `action` VARCHAR(77) NOT NULL COMMENT 'created, updated, approved, denied, cancelled, expired, escalated',
  `actor_id` int unsigned DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_actor_id` (`actor_id`),
  CONSTRAINT `access_request_log_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `access_request_scope`
--



CREATE TABLE IF NOT EXISTS `access_request_scope` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int unsigned NOT NULL,
  `object_type` VARCHAR(49) NOT NULL COMMENT 'information_object, repository, actor',
  `object_id` int unsigned NOT NULL,
  `include_descendants` tinyint(1) DEFAULT '0',
  `object_title` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request_id` (`request_id`),
  KEY `idx_object` (`object_type`,`object_id`),
  CONSTRAINT `access_request_scope_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `access_request` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_access_request`
--



CREATE TABLE IF NOT EXISTS `security_access_request` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned DEFAULT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `compartment_id` int unsigned DEFAULT NULL,
  `request_type` VARCHAR(81) NOT NULL COMMENT 'view, download, print, clearance_upgrade, compartment_access, renewal',
  `justification` text NOT NULL,
  `duration_hours` int DEFAULT NULL,
  `priority` VARCHAR(37) DEFAULT 'normal' COMMENT 'normal, urgent, immediate',
  `status` VARCHAR(57) DEFAULT 'pending' COMMENT 'pending, approved, denied, expired, cancelled',
  `reviewed_by` int unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text,
  `access_granted_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority_status` (`priority`,`status`,`created_at`),
  KEY `classification_id` (`classification_id`),
  KEY `compartment_id` (`compartment_id`),
  CONSTRAINT `security_access_request_ibfk_1` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_access_request_ibfk_2` FOREIGN KEY (`compartment_id`) REFERENCES `security_compartment` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












SET FOREIGN_KEY_CHECKS = 1;
