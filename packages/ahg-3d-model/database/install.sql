-- ============================================================================
-- ahg-3d-model — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahg3DModelPlugin/database/install.sql
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
-- ahg3DModelPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `iiif_3d_manifest`
--



CREATE TABLE IF NOT EXISTS `iiif_3d_manifest` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `manifest_json` longtext,
  `manifest_hash` varchar(64) DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `model_id` (`model_id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `iiif_3d_manifest_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_audit_log`
--



CREATE TABLE IF NOT EXISTS `object_3d_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `user_name` varchar(255) DEFAULT NULL,
  `action` VARCHAR(88) NOT NULL COMMENT 'upload, update, delete, view, ar_view, download, hotspot_add, hotspot_delete',
  `details` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_hotspot`
--



CREATE TABLE IF NOT EXISTS `object_3d_hotspot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `hotspot_type` VARCHAR(50) DEFAULT 'annotation' COMMENT 'annotation, info, link, damage, detail',
  `position_x` decimal(10,6) NOT NULL,
  `position_y` decimal(10,6) NOT NULL,
  `position_z` decimal(10,6) NOT NULL,
  `normal_x` decimal(10,6) DEFAULT '0.000000',
  `normal_y` decimal(10,6) DEFAULT '1.000000',
  `normal_z` decimal(10,6) DEFAULT '0.000000',
  `icon` varchar(50) DEFAULT 'info',
  `color` varchar(20) DEFAULT '#1a73e8',
  `link_url` varchar(500) DEFAULT NULL,
  `link_target` VARCHAR(25) DEFAULT '_blank' COMMENT '_self, _blank',
  `display_order` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_hotspot_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_hotspot_i18n`
--



CREATE TABLE IF NOT EXISTS `object_3d_hotspot_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hotspot_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_hotspot_culture` (`hotspot_id`,`culture`),
  CONSTRAINT `object_3d_hotspot_i18n_ibfk_1` FOREIGN KEY (`hotspot_id`) REFERENCES `object_3d_hotspot` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_model`
--



CREATE TABLE IF NOT EXISTS `object_3d_model` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `format` VARCHAR(47) DEFAULT 'glb' COMMENT 'glb, gltf, obj, stl, ply, usdz',
  `vertex_count` int DEFAULT NULL,
  `face_count` int DEFAULT NULL,
  `texture_count` int DEFAULT NULL,
  `animation_count` int DEFAULT '0',
  `has_materials` tinyint(1) DEFAULT '1',
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) DEFAULT '0deg 75deg 105%',
  `min_camera_orbit` varchar(100) DEFAULT NULL,
  `max_camera_orbit` varchar(100) DEFAULT NULL,
  `field_of_view` varchar(20) DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `shadow_softness` decimal(3,2) DEFAULT '1.00',
  `environment_image` varchar(255) DEFAULT NULL,
  `skybox_image` varchar(255) DEFAULT NULL,
  `background_color` varchar(20) DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) DEFAULT 'auto',
  `ar_placement` VARCHAR(23) DEFAULT 'floor' COMMENT 'floor, wall',
  `poster_image` varchar(500) DEFAULT NULL,
  `thumbnail` varchar(500) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `display_order` int DEFAULT '0',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object_id` (`object_id`),
  KEY `idx_format` (`format`),
  KEY `idx_is_public` (`is_public`),
  CONSTRAINT `object_3d_model_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_model_i18n`
--



CREATE TABLE IF NOT EXISTS `object_3d_model_i18n` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `description` text,
  `alt_text` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_model_culture` (`model_id`,`culture`),
  CONSTRAINT `object_3d_model_i18n_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `object_3d_settings`
--



CREATE TABLE IF NOT EXISTS `object_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `digital_object_id` int NOT NULL,
  `auto_rotate` tinyint(1) DEFAULT '1',
  `rotation_speed` decimal(3,2) DEFAULT '1.00',
  `camera_orbit` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '0deg 75deg 105%',
  `field_of_view` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '30deg',
  `exposure` decimal(3,2) DEFAULT '1.00',
  `shadow_intensity` decimal(3,2) DEFAULT '1.00',
  `background_color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#f5f5f5',
  `ar_enabled` tinyint(1) DEFAULT '1',
  `ar_scale` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `ar_placement` VARCHAR(23) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'floor' COMMENT 'floor, wall',
  `poster_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_object_id` (`digital_object_id`),
  KEY `idx_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `object_3d_texture`
--



CREATE TABLE IF NOT EXISTS `object_3d_texture` (
  `id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `texture_type` VARCHAR(75) DEFAULT 'diffuse' COMMENT 'diffuse, normal, roughness, metallic, ao, emissive, environment',
  `filename` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_model_id` (`model_id`),
  CONSTRAINT `object_3d_texture_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `object_3d_model` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `viewer_3d_settings`
--



CREATE TABLE IF NOT EXISTS `viewer_3d_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` VARCHAR(42) DEFAULT 'string' COMMENT 'string, integer, boolean, json',
  `description` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












SET FOREIGN_KEY_CHECKS = 1;
