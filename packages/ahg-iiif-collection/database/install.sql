-- ============================================================================
-- ahg-iiif-collection — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgIiifPlugin/database/install.sql
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
-- ahgIiifCollectionPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `iiif_annotation`
--



CREATE TABLE IF NOT EXISTS `iiif_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `canvas_id` int DEFAULT NULL,
  `target_canvas` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_selector` json DEFAULT NULL,
  `motivation` VARCHAR(94) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'commenting' COMMENT 'commenting, tagging, describing, linking, transcribing, identifying, supplementing',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_canvas` (`target_canvas`(255)),
  KEY `idx_motivation` (`motivation`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `iiif_annotation_body`
--



CREATE TABLE IF NOT EXISTS `iiif_annotation_body` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annotation_id` int NOT NULL,
  `body_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'TextualBody',
  `body_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `body_format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text/plain',
  `body_language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `body_purpose` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_annotation` (`annotation_id`),
  CONSTRAINT `iiif_annotation_body_ibfk_1` FOREIGN KEY (`annotation_id`) REFERENCES `iiif_annotation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `iiif_collection`
--



CREATE TABLE IF NOT EXISTS `iiif_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `attribution` varchar(500) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `viewing_hint` VARCHAR(59) DEFAULT 'individuals' COMMENT 'individuals, paged, continuous, multi-part, top',
  `nav_date` date DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_slug` (`slug`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_public` (`is_public`),
  CONSTRAINT `iiif_collection_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `iiif_collection` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `iiif_collection_i18n`
--



CREATE TABLE IF NOT EXISTS `iiif_collection_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_collection_culture` (`collection_id`,`culture`),
  CONSTRAINT `iiif_collection_i18n_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `iiif_collection_item`
--



CREATE TABLE IF NOT EXISTS `iiif_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `manifest_uri` varchar(1000) DEFAULT NULL,
  `item_type` VARCHAR(32) DEFAULT 'manifest' COMMENT 'manifest, collection',
  `label` varchar(500) DEFAULT NULL,
  `description` text,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`),
  CONSTRAINT `iiif_collection_item_ibfk_1` FOREIGN KEY (`collection_id`) REFERENCES `iiif_collection` (`id`) ON DELETE CASCADE,
  CONSTRAINT `iiif_collection_item_ibfk_2` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `iiif_ocr_block`
--



CREATE TABLE IF NOT EXISTS `iiif_ocr_block` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ocr_id` int NOT NULL,
  `page_number` int DEFAULT '1',
  `block_type` VARCHAR(41) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'word' COMMENT 'word, line, paragraph, region',
  `text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `x` int NOT NULL,
  `y` int NOT NULL,
  `width` int NOT NULL,
  `height` int NOT NULL,
  `confidence` decimal(5,2) DEFAULT NULL,
  `block_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_ocr` (`ocr_id`),
  KEY `idx_page` (`page_number`),
  KEY `idx_type` (`block_type`),
  KEY `idx_text` (`text`(100)),
  CONSTRAINT `iiif_ocr_block_ibfk_1` FOREIGN KEY (`ocr_id`) REFERENCES `iiif_ocr_text` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `iiif_ocr_text`
--



CREATE TABLE IF NOT EXISTS `iiif_ocr_text` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `object_id` int NOT NULL,
  `full_text` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `format` VARCHAR(29) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'plain' COMMENT 'plain, alto, hocr',
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `confidence` decimal(5,2) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_digital_object` (`digital_object_id`),
  KEY `idx_object` (`object_id`),
  FULLTEXT KEY `ft_text` (`full_text`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `iiif_viewer_settings`
--



CREATE TABLE IF NOT EXISTS `iiif_viewer_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












-- Seed Data
--












--
-- Dumping data for table `iiif_viewer_settings`
--

LOCK TABLES `iiif_viewer_settings` WRITE;

INSERT IGNORE INTO `iiif_viewer_settings` VALUES (1,'viewer_type','mirador','Display type: carousel, single, mirador, openseadragon','2025-12-05 13:58:33','2025-12-05 15:32:10');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (2,'carousel_autoplay','1','Auto-rotate carousel (1=yes, 0=no)','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (3,'carousel_interval','5000','Carousel rotation interval in milliseconds','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (4,'carousel_show_thumbnails','1','Show thumbnail navigation','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (5,'carousel_show_controls','1','Show prev/next controls','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (6,'viewer_height','500px','Viewer height','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (7,'show_zoom_controls','1','Show zoom in/out buttons','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (8,'enable_fullscreen','1','Enable fullscreen button','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (9,'default_zoom','1','Default zoom level (1 = fit)','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (10,'background_color','#b1aaaa','Viewer background color','2025-12-05 13:58:33','2025-12-12 21:41:01');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (11,'show_on_browse','1','Show viewer on browse page','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (12,'show_on_view','1','Show viewer on record view page','2025-12-05 13:58:33','2025-12-05 13:58:33');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (13,'homepage_collection_enabled','1',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (14,'homepage_collection_id','2',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (15,'homepage_carousel_height','450px',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (16,'homepage_carousel_autoplay','1',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (17,'homepage_carousel_interval','5000',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (18,'homepage_show_captions','1',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');
INSERT IGNORE INTO `iiif_viewer_settings` VALUES (19,'homepage_max_items','12',NULL,'2025-12-05 14:44:46','2025-12-05 14:44:46');

UNLOCK TABLES;











-- =============================================================================
-- IIIF Authentication (IIIF Auth API 1.0)
-- Added: 2025-01-24
-- =============================================================================

-- Auth services configuration
CREATE TABLE IF NOT EXISTS `iiif_auth_service` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `profile` VARCHAR(48) NOT NULL DEFAULT 'login' COMMENT 'login, clickthrough, kiosk, external',
    `auth_version` VARCHAR(10) NOT NULL DEFAULT '1.0' COMMENT '1.0, 2.0',
    `access_profile` VARCHAR(20) DEFAULT NULL COMMENT 'active, kiosk, external -- Auth 2.0 access profile',
    `label` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `confirm_label` VARCHAR(100) DEFAULT 'Login',
    `failure_header` VARCHAR(255) DEFAULT 'Authentication Required',
    `failure_description` TEXT NULL,
    `heading` VARCHAR(255) DEFAULT NULL COMMENT 'Auth 2.0 heading for probe error response',
    `note` TEXT DEFAULT NULL COMMENT 'Auth 2.0 note for probe error response',
    `probe_substitute_width` INT DEFAULT NULL COMMENT 'Width for substitute/degraded image in Auth 2.0 probe response',
    `login_url` VARCHAR(500) NULL COMMENT 'External login URL for login profile',
    `logout_url` VARCHAR(500) NULL,
    `token_ttl` INT DEFAULT 3600 COMMENT 'Token time-to-live in seconds',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_profile` (`profile`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Access tokens
CREATE TABLE IF NOT EXISTS `iiif_auth_token` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `token_hash` CHAR(64) NOT NULL UNIQUE COMMENT 'SHA-256 hash of token',
    `user_id` INT NULL COMMENT 'AtoM user ID, NULL for anonymous',
    `service_id` INT UNSIGNED NOT NULL,
    `session_id` VARCHAR(128) NULL COMMENT 'Browser session identifier',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `issued_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    `last_used_at` TIMESTAMP NULL,
    `is_revoked` TINYINT(1) DEFAULT 0,
    FOREIGN KEY (`service_id`) REFERENCES `iiif_auth_service`(`id`) ON DELETE CASCADE,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_session` (`session_id`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_revoked` (`is_revoked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resource-level access control
CREATE TABLE IF NOT EXISTS `iiif_auth_resource` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `object_id` INT NOT NULL COMMENT 'information_object.id',
    `service_id` INT UNSIGNED NOT NULL,
    `apply_to_children` TINYINT(1) DEFAULT 1 COMMENT 'Apply to descendant objects',
    `degraded_access` TINYINT(1) DEFAULT 0 COMMENT 'Allow degraded (thumbnail) access',
    `degraded_width` INT DEFAULT 200 COMMENT 'Max width for degraded access',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`service_id`) REFERENCES `iiif_auth_service`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `idx_object_service` (`object_id`, `service_id`),
    INDEX `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repository-level access control
CREATE TABLE IF NOT EXISTS `iiif_auth_repository` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `repository_id` INT NOT NULL COMMENT 'repository.id',
    `service_id` INT UNSIGNED NOT NULL,
    `degraded_access` TINYINT(1) DEFAULT 0,
    `degraded_width` INT DEFAULT 200,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`service_id`) REFERENCES `iiif_auth_service`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `idx_repo_service` (`repository_id`, `service_id`),
    INDEX `idx_repository` (`repository_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Access log for auditing
CREATE TABLE IF NOT EXISTS `iiif_auth_access_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `object_id` INT NULL,
    `user_id` INT NULL,
    `token_id` INT UNSIGNED NULL,
    `action` VARCHAR(74) NOT NULL COMMENT 'view, download, token_request, token_grant, token_deny, logout',
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_object` (`object_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- IIIF Manifest Cache
-- Added: 2026-02-18
-- =============================================================================

CREATE TABLE IF NOT EXISTS `iiif_manifest_cache` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `object_id` INT NOT NULL,
    `culture` VARCHAR(10) NOT NULL DEFAULT 'en',
    `manifest_json` LONGTEXT NOT NULL,
    `cache_key` VARCHAR(64) NOT NULL COMMENT 'SHA-256 of object signature',
    `page_count` INT DEFAULT NULL COMMENT 'Cached multi-TIFF page count',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL,
    INDEX `idx_object_culture` (`object_id`, `culture`),
    UNIQUE INDEX `idx_cache_key` (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default auth services
INSERT IGNORE INTO `iiif_auth_service` (`name`, `profile`, `auth_version`, `access_profile`, `label`, `description`, `confirm_label`, `failure_header`, `failure_description`, `heading`, `note`) VALUES
('public', 'clickthrough', '1.0', NULL, 'Public Access', 'Click to access this resource', 'I Agree', 'Access Required', 'Please click to acknowledge terms of use.', NULL, NULL),
('login', 'login', '1.0', NULL, 'Login Required', 'This resource requires authentication', 'Login', 'Authentication Required', 'Please log in to access this resource.', NULL, NULL),
('restricted', 'login', '1.0', NULL, 'Restricted Access', 'This resource has restricted access', 'Request Access', 'Restricted Content', 'This content is restricted. Please contact the repository for access.', NULL, NULL),
('public-v2', 'clickthrough', '2.0', 'active', 'Public Access', 'Click to access this resource', 'I Agree', 'Access Required', 'Please click to acknowledge terms of use.', 'Terms of Use', 'Please accept the terms of use to access this resource.'),
('login-v2', 'login', '2.0', 'active', 'Login Required', 'This resource requires authentication', 'Login', 'Authentication Required', 'Please log in to access this resource.', 'Authentication Required', 'You need to log in to view this content.'),
('restricted-v2', 'login', '2.0', 'active', 'Restricted Access', 'This resource has restricted access', 'Request Access', 'Restricted Content', 'This content is restricted. Please contact the repository for access.', 'Restricted Content', 'This content is restricted. Contact the archive for access.')
ON DUPLICATE KEY UPDATE `label` = VALUES(`label`);

SET FOREIGN_KEY_CHECKS = 1;
