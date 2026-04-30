-- ============================================================================
-- ahg-security-clearance — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgSecurityClearancePlugin/database/install.sql
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
-- ahgSecurityClearancePlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `object_security_classification`
--



CREATE TABLE IF NOT EXISTS `object_security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `classified_by` int DEFAULT NULL,
  `classified_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int unsigned DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `review_date` date DEFAULT NULL,
  `declassify_date` date DEFAULT NULL,
  `declassify_to_id` int unsigned DEFAULT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `inherit_to_children` tinyint(1) DEFAULT '1',
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_osc_object` (`object_id`),
  KEY `idx_osc_classification_review_declassify` (`classification_id`,`review_date`,`declassify_date`),
  KEY `idx_osc_assigned_by` (`assigned_by`),
  KEY `fk_osc_classified_by` (`classified_by`),
  CONSTRAINT `fk_osc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_osc_classified_by` FOREIGN KEY (`classified_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_osc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `security_2fa_session`
--



CREATE TABLE IF NOT EXISTS `security_2fa_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `verified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_fingerprint` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_session` (`session_id`),
  KEY `idx_user_session` (`user_id`,`session_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_access_condition_link`
--



CREATE TABLE IF NOT EXISTS `security_access_condition_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `access_conditions` text,
  `reproduction_conditions` text,
  `narssa_ref` varchar(100) DEFAULT NULL,
  `retention_period` varchar(50) DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_classification` (`classification_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_access_log`
--



CREATE TABLE IF NOT EXISTS `security_access_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `object_id` int NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_granted` tinyint(1) NOT NULL,
  `denial_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `justification` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sal_object` (`object_id`),
  KEY `idx_sal_user` (`user_id`),
  KEY `idx_sal_classification` (`classification_id`),
  CONSTRAINT `fk_sal_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_sal_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `security_audit_log`
--



CREATE TABLE IF NOT EXISTS `security_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int DEFAULT NULL,
  `object_type` varchar(50) DEFAULT 'information_object',
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `action_category` varchar(50) DEFAULT 'access',
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_category` (`action_category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_classification`
--



CREATE TABLE IF NOT EXISTS `security_classification` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_justification` tinyint(1) NOT NULL DEFAULT '0',
  `requires_approval` tinyint(1) NOT NULL DEFAULT '0',
  `requires_2fa` tinyint(1) NOT NULL DEFAULT '0',
  `max_session_hours` int DEFAULT NULL,
  `watermark_required` tinyint(1) NOT NULL DEFAULT '0',
  `watermark_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `print_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `copy_allowed` tinyint(1) NOT NULL DEFAULT '1',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_security_classification_level` (`level`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `security_clearance_history`
--



CREATE TABLE IF NOT EXISTS `security_clearance_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `previous_classification_id` int unsigned DEFAULT NULL,
  `new_classification_id` int unsigned DEFAULT NULL,
  `action` VARCHAR(95) NOT NULL COMMENT 'granted, upgraded, downgraded, revoked, renewed, expired, 2fa_enabled, 2fa_disabled',
  `changed_by` int unsigned NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `previous_classification_id` (`previous_classification_id`),
  KEY `new_classification_id` (`new_classification_id`),
  CONSTRAINT `security_clearance_history_ibfk_1` FOREIGN KEY (`previous_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_clearance_history_ibfk_2` FOREIGN KEY (`new_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_compartment`
--



CREATE TABLE IF NOT EXISTS `security_compartment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `min_clearance_id` int unsigned NOT NULL,
  `requires_need_to_know` tinyint(1) DEFAULT '1',
  `requires_briefing` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_active` (`active`),
  KEY `min_clearance_id` (`min_clearance_id`),
  CONSTRAINT `security_compartment_ibfk_1` FOREIGN KEY (`min_clearance_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_compliance_log`
--



CREATE TABLE IF NOT EXISTS `security_compliance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `details` text,
  `ip_address` varchar(45) DEFAULT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_declassification_schedule`
--



CREATE TABLE IF NOT EXISTS `security_declassification_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `from_classification_id` int unsigned NOT NULL,
  `to_classification_id` int unsigned DEFAULT NULL,
  `trigger_type` VARCHAR(34) NOT NULL DEFAULT 'date' COMMENT 'date, event, retention',
  `trigger_event` varchar(255) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT '0',
  `processed_at` timestamp NULL DEFAULT NULL,
  `processed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scheduled` (`scheduled_date`,`processed`),
  KEY `idx_object` (`object_id`),
  KEY `from_classification_id` (`from_classification_id`),
  KEY `to_classification_id` (`to_classification_id`),
  CONSTRAINT `security_declassification_schedule_ibfk_1` FOREIGN KEY (`from_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `security_declassification_schedule_ibfk_2` FOREIGN KEY (`to_classification_id`) REFERENCES `security_classification` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_retention_schedule`
--



CREATE TABLE IF NOT EXISTS `security_retention_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `narssa_ref` varchar(100) NOT NULL,
  `record_type` varchar(255) NOT NULL,
  `retention_period` varchar(100) NOT NULL,
  `disposal_action` varchar(100) NOT NULL,
  `legal_reference` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_narssa` (`narssa_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `security_watermark_log`
--



CREATE TABLE IF NOT EXISTS `security_watermark_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `object_id` int unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `watermark_type` VARCHAR(36) NOT NULL DEFAULT 'visible' COMMENT 'visible, invisible, both',
  `watermark_text` varchar(500) NOT NULL,
  `watermark_code` varchar(100) NOT NULL,
  `file_hash` varchar(64) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`,`created_at`),
  KEY `idx_object` (`object_id`),
  KEY `idx_code` (`watermark_code`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `user_security_clearance`
--



CREATE TABLE IF NOT EXISTS `user_security_clearance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned NOT NULL,
  `granted_by` int unsigned DEFAULT NULL,
  `granted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usc_user` (`user_id`),
  KEY `idx_usc_classification_id` (`classification_id`),
  KEY `idx_usc_expires_at` (`expires_at`),
  KEY `idx_usc_granted_by` (`granted_by`),
  CONSTRAINT `fk_usc_classification` FOREIGN KEY (`classification_id`) REFERENCES `security_classification` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `user_security_clearance_log`
--



CREATE TABLE IF NOT EXISTS `user_security_clearance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `classification_id` int unsigned DEFAULT NULL,
  `action` VARCHAR(46) NOT NULL COMMENT 'granted, revoked, updated, expired',
  `changed_by` int unsigned DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_changed_by` (`changed_by`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












-- Seed Data
--












--
-- Dumping data for table `security_classification`
--

LOCK TABLES `security_classification` WRITE;

INSERT IGNORE INTO `security_classification` VALUES (1,'PUBLIC',0,'Public','Publicly accessible material',NULL,NULL,0,0,0,NULL,0,NULL,1,1,1,1,'2025-12-02 08:13:59','2025-12-04 13:58:31');
INSERT IGNORE INTO `security_classification` VALUES (2,'INTERNAL',1,'Internal','Internal institutional use',NULL,NULL,0,0,0,NULL,0,NULL,1,1,1,1,'2025-12-02 08:13:59','2025-12-04 13:58:31');
INSERT IGNORE INTO `security_classification` VALUES (3,'RESTRICTED',2,'Restricted','Restricted material, limited staff',NULL,NULL,1,0,0,NULL,0,NULL,1,1,0,1,'2025-12-02 08:13:59','2025-12-04 13:58:31');
INSERT IGNORE INTO `security_classification` VALUES (4,'CONFIDENTIAL',3,'Confidential','Confidential archival material',NULL,NULL,1,1,0,NULL,1,'confidential.png',0,0,0,1,'2025-12-02 08:13:59','2025-12-04 16:18:54');
INSERT IGNORE INTO `security_classification` VALUES (5,'SECRET',4,'Secret','Highly sensitive material',NULL,NULL,1,1,1,NULL,1,'secret_copyright.png',0,0,0,1,'2025-12-02 08:13:59','2025-12-04 16:18:54');
INSERT IGNORE INTO `security_classification` VALUES (7,'TOP_SECRET',5,'Top Secret',NULL,'#6f42c1','fa-user-secret',0,0,0,NULL,1,'top_secret_copyright.png',1,1,1,1,'2025-12-04 14:14:15','2025-12-04 16:18:54');

UNLOCK TABLES;











SET FOREIGN_KEY_CHECKS = 1;
