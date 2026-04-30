-- ============================================================================
-- ahg-condition — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/database/install.sql
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
-- ahgConditionPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `condition_assessment_schedule`
--



CREATE TABLE IF NOT EXISTS `condition_assessment_schedule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `frequency_months` int DEFAULT '12',
  `last_assessment_date` date DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'normal',
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_due` (`next_due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `condition_conservation_link`
--



CREATE TABLE IF NOT EXISTS `condition_conservation_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `condition_event_id` int unsigned NOT NULL,
  `treatment_id` int unsigned NOT NULL,
  `link_type` varchar(50) DEFAULT 'treatment',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event` (`condition_event_id`),
  KEY `idx_treatment` (`treatment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `condition_damage`
--



CREATE TABLE IF NOT EXISTS `condition_damage` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `damage_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'overall',
  `severity` VARCHAR(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'minor' COMMENT 'minor, moderate, severe',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `dimensions` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `treatment_required` tinyint(1) NOT NULL DEFAULT '0',
  `treatment_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cd_report` (`condition_report_id`),
  KEY `idx_cd_type` (`damage_type`),
  KEY `idx_cd_severity` (`severity`),
  CONSTRAINT `condition_damage_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `condition_event`
--



CREATE TABLE IF NOT EXISTS `condition_event` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `event_type` varchar(50) NOT NULL DEFAULT 'assessment',
  `event_date` date NOT NULL,
  `assessor` varchar(255) DEFAULT NULL,
  `condition_status` varchar(50) DEFAULT NULL,
  `damage_types` json DEFAULT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `notes` text,
  `risk_score` decimal(5,2) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_date` (`event_date`),
  KEY `idx_status` (`condition_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `condition_image`
--



CREATE TABLE IF NOT EXISTS `condition_image` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `condition_report_id` bigint unsigned NOT NULL,
  `digital_object_id` int unsigned DEFAULT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_type` VARCHAR(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'general, detail, damage, before, after, raking, uv',
  `annotations` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ci_report` (`condition_report_id`),
  CONSTRAINT `condition_image_condition_report_id_foreign` FOREIGN KEY (`condition_report_id`) REFERENCES `condition_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `condition_report`
--



CREATE TABLE IF NOT EXISTS `condition_report` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `information_object_id` int unsigned NOT NULL,
  `assessor_user_id` int unsigned DEFAULT NULL,
  `assessment_date` date NOT NULL,
  `context` VARCHAR(133) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'routine' COMMENT 'acquisition, loan_out, loan_in, loan_return, exhibition, storage, conservation, routine, incident, insurance, deaccession',
  `overall_rating` VARCHAR(53) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good' COMMENT 'excellent, good, fair, poor, unacceptable',
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `recommendations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `priority` VARCHAR(37) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'low, normal, high, urgent',
  `next_check_date` date DEFAULT NULL,
  `environmental_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `storage_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cr_object` (`information_object_id`),
  KEY `idx_cr_date` (`assessment_date`),
  KEY `idx_cr_rating` (`overall_rating`),
  KEY `idx_cr_next_check` (`next_check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `condition_vocabulary`
--



CREATE TABLE IF NOT EXISTS `condition_vocabulary` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` VARCHAR(79) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'damage_type, severity, condition, priority, material, location_zone',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'For UI display',
  `icon` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'FontAwesome icon class',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_code` (`vocabulary_type`,`code`),
  KEY `idx_type_active` (`vocabulary_type`,`is_active`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `condition_vocabulary_term`
--



CREATE TABLE IF NOT EXISTS `condition_vocabulary_term` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `vocabulary_type` varchar(50) NOT NULL,
  `term_code` varchar(50) NOT NULL,
  `term_label` varchar(255) NOT NULL,
  `term_description` text,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vocab_term` (`vocabulary_type`,`term_code`),
  KEY `idx_type` (`vocabulary_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_check`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_check` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `condition_reference` varchar(50) DEFAULT NULL,
  `check_date` datetime NOT NULL,
  `check_reason` varchar(100) DEFAULT NULL,
  `checked_by` varchar(255) NOT NULL,
  `overall_condition` varchar(50) DEFAULT NULL,
  `condition_note` text,
  `completeness_note` text,
  `hazard_note` text,
  `technical_assessment` text,
  `recommended_treatment` text,
  `treatment_priority` varchar(50) DEFAULT NULL,
  `next_check_date` date DEFAULT NULL,
  `environment_recommendation` text,
  `handling_recommendation` text,
  `display_recommendation` text,
  `storage_recommendation` text,
  `packing_recommendation` text,
  `image_reference` text,
  `photo_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `condition_check_reference` varchar(255) DEFAULT NULL,
  `completeness` varchar(50) DEFAULT NULL,
  `condition_description` text,
  `hazards_noted` text,
  `recommendations` text,
  `workflow_state` varchar(50) DEFAULT 'scheduled',
  `condition_rating` varchar(50) DEFAULT NULL COMMENT 'Overall condition rating',
  `condition_notes` text COMMENT 'Detailed condition notes',
  `template_id` int DEFAULT NULL,
  `material_type` varchar(50) DEFAULT NULL,
  `template_data` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `object_id` (`object_id`),
  KEY `idx_condition_date` (`check_date`),
  KEY `idx_condition_reference` (`condition_reference`),
  KEY `idx_overall_condition` (`overall_condition`),
  KEY `idx_wf_cond` (`workflow_state`),
  KEY `idx_check_date` (`check_date`),
  CONSTRAINT `spectrum_condition_check_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_check_data`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_check_data` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `template_id` int NOT NULL,
  `field_id` int NOT NULL,
  `field_value` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_check_field` (`condition_check_id`,`field_id`),
  KEY `template_id` (`template_id`),
  KEY `field_id` (`field_id`),
  KEY `idx_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_check_data_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_check_data_ibfk_3` FOREIGN KEY (`field_id`) REFERENCES `spectrum_condition_template_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_photo`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photo` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `photo_type` VARCHAR(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail' COMMENT 'before, after, detail, damage, overall, other',
  `caption` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location_on_object` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `width` int DEFAULT NULL,
  `height` int DEFAULT NULL,
  `photographer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_date` date DEFAULT NULL,
  `camera_info` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `annotations` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `digital_object_id` (`digital_object_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_condition_check` (`condition_check_id`),
  KEY `idx_photo_type` (`photo_type`),
  KEY `idx_photo_date` (`photo_date`),
  KEY `idx_primary` (`is_primary`),
  CONSTRAINT `spectrum_condition_photo_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spectrum_condition_photo_ibfk_2` FOREIGN KEY (`digital_object_id`) REFERENCES `digital_object` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `user` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spectrum_condition_photo_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `user` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_condition_photo_comparison`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photo_comparison` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL,
  `before_photo_id` int NOT NULL,
  `after_photo_id` int NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_condition_check` (`condition_check_id`),
  CONSTRAINT `spectrum_condition_photo_comparison_ibfk_1` FOREIGN KEY (`condition_check_id`) REFERENCES `spectrum_condition_check` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_photos`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_photos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `condition_check_id` int NOT NULL COMMENT 'Reference to spectrum_condition_check',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stored filename',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Original uploaded filename',
  `category` VARCHAR(61) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'overall' COMMENT 'Photo category' COMMENT 'overall, detail, damage, before, after, reference',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'Description or notes about the photo',
  `annotations` json DEFAULT NULL COMMENT 'JSON annotations for damage markers',
  `file_size` int DEFAULT NULL COMMENT 'File size in bytes',
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME type of the image',
  `width` int DEFAULT NULL COMMENT 'Image width in pixels',
  `height` int DEFAULT NULL COMMENT 'Image height in pixels',
  `captured_at` datetime DEFAULT NULL COMMENT 'When photo was taken (from EXIF)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL COMMENT 'User who uploaded the photo',
  `updated_at` datetime DEFAULT NULL COMMENT 'Last update timestamp',
  PRIMARY KEY (`id`),
  KEY `idx_check` (`condition_check_id`),
  KEY `idx_category` (`category`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `spectrum_condition_template`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `material_type` varchar(100) NOT NULL,
  `description` text,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_material_type` (`material_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_template_field`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_type` VARCHAR(86) NOT NULL COMMENT 'text, textarea, select, multiselect, checkbox, radio, rating, date, number',
  `options` json DEFAULT NULL COMMENT 'For select/multiselect/radio - array of options',
  `default_value` varchar(255) DEFAULT NULL,
  `placeholder` varchar(255) DEFAULT NULL,
  `help_text` text,
  `is_required` tinyint(1) DEFAULT '0',
  `validation_rules` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_section` (`section_id`),
  CONSTRAINT `spectrum_condition_template_field_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `spectrum_condition_template_section` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=105 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `spectrum_condition_template_section`
--



CREATE TABLE IF NOT EXISTS `spectrum_condition_template_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `sort_order` int DEFAULT '0',
  `is_required` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_template` (`template_id`),
  CONSTRAINT `spectrum_condition_template_section_ibfk_1` FOREIGN KEY (`template_id`) REFERENCES `spectrum_condition_template` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












-- View for condition with photos












--
-- Dumping data for table `condition_vocabulary`
--

LOCK TABLES `condition_vocabulary` WRITE;

INSERT IGNORE INTO `condition_vocabulary` VALUES (1,'damage_type','tear','Tear','Physical tear or rip in material','#dc3545',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (2,'damage_type','stain','Stain','Discoloration or marks','#fd7e14',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (3,'damage_type','foxing','Foxing','Brown spots typically on paper','#ffc107',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (4,'damage_type','fading','Fading','Loss of color intensity','#6c757d',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (5,'damage_type','water_damage','Water Damage','Damage from moisture or flooding','#0dcaf0',NULL,50,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (6,'damage_type','mold','Mold/Mildew','Fungal growth','#198754',NULL,60,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (7,'damage_type','pest_damage','Pest Damage','Damage from insects or rodents','#6f42c1',NULL,70,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (8,'damage_type','abrasion','Abrasion','Surface wear or scratching','#adb5bd',NULL,80,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (9,'damage_type','brittleness','Brittleness','Material becoming fragile','#495057',NULL,90,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (10,'damage_type','loss','Loss/Missing','Missing portions of material','#212529',NULL,100,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (11,'severity','minor','Minor','Minimal impact, low priority','#28a745',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (12,'severity','moderate','Moderate','Noticeable damage, should address','#ffc107',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (13,'severity','severe','Severe','Significant damage requiring attention','#fd7e14',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (14,'severity','critical','Critical','Immediate action required','#dc3545',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (15,'condition','excellent','Excellent','Like new, no visible issues','#198754',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (16,'condition','good','Good','Minor wear consistent with age','#28a745',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (17,'condition','fair','Fair','Some damage but stable','#ffc107',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (18,'condition','poor','Poor','Significant damage or deterioration','#fd7e14',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (19,'condition','critical','Critical','Severe damage, at risk','#dc3545',NULL,50,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (20,'priority','low','Low','Can be addressed when convenient','#6c757d',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (21,'priority','medium','Medium','Should be addressed in normal workflow','#17a2b8',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (22,'priority','high','High','Needs prompt attention','#fd7e14',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (23,'priority','urgent','Urgent','Requires immediate action','#dc3545',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (24,'material','paper','Paper','Paper-based materials','#f8f9fa',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (25,'material','parchment','Parchment/Vellum','Animal skin materials','#e9ecef',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (26,'material','textile','Textile','Fabric and cloth materials','#dee2e6',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (27,'material','leather','Leather','Leather bindings and materials','#795548',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (28,'material','photographic','Photographic','Photos, negatives, slides','#212529',NULL,50,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (29,'material','metal','Metal','Metal objects or components','#adb5bd',NULL,60,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (30,'material','wood','Wood','Wooden items or frames','#8d6e63',NULL,70,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (31,'material','glass','Glass','Glass plates, frames','#90caf9',NULL,80,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (32,'material','plastic','Plastic/Polymer','Synthetic materials','#ce93d8',NULL,90,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (33,'material','audiovisual','Audiovisual','Tapes, films, discs','#424242',NULL,100,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (34,'location_zone','recto','Recto (Front)','Front side of item','#e3f2fd',NULL,10,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (35,'location_zone','verso','Verso (Back)','Back side of item','#fce4ec',NULL,20,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (36,'location_zone','edge_top','Top Edge','Top edge of item','#f3e5f5',NULL,30,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (37,'location_zone','edge_bottom','Bottom Edge','Bottom edge of item','#e8eaf6',NULL,40,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (38,'location_zone','edge_left','Left Edge','Left edge of item','#e0f2f1',NULL,50,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (39,'location_zone','edge_right','Right Edge','Right edge of item','#fff3e0',NULL,60,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (40,'location_zone','spine','Spine','Spine/binding area','#efebe9',NULL,70,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (41,'location_zone','cover_front','Front Cover','Front cover of bound item','#eceff1',NULL,80,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (42,'location_zone','cover_back','Back Cover','Back cover of bound item','#fafafa',NULL,90,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');
INSERT IGNORE INTO `condition_vocabulary` VALUES (43,'location_zone','center','Center','Central area of item','#fff8e1',NULL,100,1,'2025-12-19 10:05:43','2025-12-19 10:05:43');

UNLOCK TABLES;

--
-- Dumping data for table `condition_vocabulary_term`
--

LOCK TABLES `condition_vocabulary_term` WRITE;


UNLOCK TABLES;











SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgAiConditionPlugin on 2026-04-30
-- ============================================================================
-- ============================================================================
-- ahgAiConditionPlugin Database Tables
-- Version: 1.0.0
-- Last Updated: 2026-02-21
-- DO NOT include INSERT INTO atom_plugin - plugins are enabled manually
-- ============================================================================

-- AI Condition Assessments (links to condition_report from ahgConditionPlugin)
CREATE TABLE IF NOT EXISTS `ahg_ai_condition_assessment` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `information_object_id` INT DEFAULT NULL,
    `condition_report_id` INT DEFAULT NULL COMMENT 'FK to condition_report if linked',
    `digital_object_id` INT DEFAULT NULL,
    `image_path` VARCHAR(1024) DEFAULT NULL COMMENT 'Path to analyzed image',
    `overlay_path` VARCHAR(1024) DEFAULT NULL COMMENT 'Path to annotated overlay image',
    `overall_score` DECIMAL(5,2) DEFAULT NULL COMMENT '0-100 condition score',
    `condition_grade` VARCHAR(50) DEFAULT NULL COMMENT 'Dropdown: condition_grade',
    `damage_count` INT DEFAULT 0,
    `recommendations` TEXT DEFAULT NULL,
    `model_version` VARCHAR(50) DEFAULT NULL,
    `processing_time_ms` INT DEFAULT NULL,
    `confidence_threshold` DECIMAL(3,2) DEFAULT 0.25,
    `source` VARCHAR(50) DEFAULT 'manual' COMMENT 'manual, bulk, auto, api',
    `is_confirmed` TINYINT(1) DEFAULT 0 COMMENT 'Human reviewed and confirmed',
    `confirmed_by` INT DEFAULT NULL,
    `confirmed_at` DATETIME DEFAULT NULL,
    `api_client_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'If submitted via SaaS API',
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_aic_object` (`information_object_id`),
    INDEX `idx_aic_report` (`condition_report_id`),
    INDEX `idx_aic_grade` (`condition_grade`),
    INDEX `idx_aic_score` (`overall_score`),
    INDEX `idx_aic_source` (`source`),
    INDEX `idx_aic_confirmed` (`is_confirmed`),
    INDEX `idx_aic_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Individual damage detections per assessment
CREATE TABLE IF NOT EXISTS `ahg_ai_condition_damage` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `assessment_id` BIGINT UNSIGNED NOT NULL,
    `damage_type` VARCHAR(50) NOT NULL COMMENT 'Dropdown: damage_type',
    `severity` VARCHAR(50) DEFAULT NULL COMMENT 'Dropdown: damage_severity',
    `confidence` DECIMAL(4,3) NOT NULL COMMENT '0.000-1.000',
    `bbox_x` INT DEFAULT NULL COMMENT 'Bounding box top-left X (pixels)',
    `bbox_y` INT DEFAULT NULL COMMENT 'Bounding box top-left Y (pixels)',
    `bbox_w` INT DEFAULT NULL COMMENT 'Bounding box width (pixels)',
    `bbox_h` INT DEFAULT NULL COMMENT 'Bounding box height (pixels)',
    `area_percent` DECIMAL(5,2) DEFAULT NULL COMMENT 'Damage area as % of total image',
    `location_zone` VARCHAR(50) DEFAULT NULL COMMENT 'Dropdown: condition location_zone',
    `description` TEXT DEFAULT NULL,
    `score_deduction` DECIMAL(5,2) DEFAULT NULL COMMENT 'Points deducted from score',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_acd_assessment` (`assessment_id`),
    INDEX `idx_acd_type` (`damage_type`),
    INDEX `idx_acd_severity` (`severity`),
    CONSTRAINT `fk_acd_assessment` FOREIGN KEY (`assessment_id`)
        REFERENCES `ahg_ai_condition_assessment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Condition score history for trend tracking
CREATE TABLE IF NOT EXISTS `ahg_ai_condition_history` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `information_object_id` INT NOT NULL,
    `assessment_id` BIGINT UNSIGNED NOT NULL,
    `score` DECIMAL(5,2) NOT NULL,
    `condition_grade` VARCHAR(50) NOT NULL,
    `damage_count` INT DEFAULT 0,
    `assessed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ach_object` (`information_object_id`),
    INDEX `idx_ach_date` (`assessed_at`),
    CONSTRAINT `fk_ach_assessment` FOREIGN KEY (`assessment_id`)
        REFERENCES `ahg_ai_condition_assessment` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SaaS API clients
CREATE TABLE IF NOT EXISTS `ahg_ai_service_client` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `organization` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) NOT NULL,
    `api_key` VARCHAR(64) NOT NULL UNIQUE,
    `tier` VARCHAR(50) DEFAULT 'free' COMMENT 'Dropdown: ai_service_tier',
    `monthly_limit` INT DEFAULT 50,
    `can_contribute_training` TINYINT(1) DEFAULT 0 COMMENT 'Client has opted in to contribute training data',
    `training_approved` TINYINT(1) DEFAULT 0 COMMENT 'Admin approved client data for training',
    `training_approved_at` DATETIME DEFAULT NULL,
    `training_approved_by` INT DEFAULT NULL,
    `training_approval_doc` VARCHAR(1024) DEFAULT NULL COMMENT 'Path to uploaded consent document',
    `is_active` TINYINT(1) DEFAULT 1,
    `last_used_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_asc_key` (`api_key`),
    INDEX `idx_asc_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SaaS usage metering
CREATE TABLE IF NOT EXISTS `ahg_ai_service_usage` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_id` BIGINT UNSIGNED NOT NULL,
    `year_month` VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    `scans_used` INT DEFAULT 0,
    `last_scan_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_client_month` (`client_id`, `year_month`),
    CONSTRAINT `fk_asu_client` FOREIGN KEY (`client_id`)
        REFERENCES `ahg_ai_service_client` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Training data contributions (from condition photos, annotation studio, SaaS clients)
CREATE TABLE IF NOT EXISTS `ahg_ai_training_contribution` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `source` VARCHAR(50) NOT NULL COMMENT 'condition_photos, annotation_studio, saas_client, manual',
    `object_id` INT DEFAULT NULL COMMENT 'FK to information_object if linked',
    `contributor` VARCHAR(255) DEFAULT NULL COMMENT 'User ID or client name',
    `client_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'FK to ahg_ai_service_client if SaaS',
    `image_filename` VARCHAR(255) NOT NULL,
    `annotation_filename` VARCHAR(255) NOT NULL,
    `damage_types` JSON DEFAULT NULL COMMENT 'Array of damage types in this contribution',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_atc_source` (`source`),
    INDEX `idx_atc_status` (`status`),
    INDEX `idx_atc_object` (`object_id`),
    INDEX `idx_atc_client` (`client_id`),
    INDEX `idx_atc_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Seed Data: Dropdown Manager taxonomies (section: ai)
-- Wrapped in a stored procedure with a table-exists guard. ahg_dropdown is
-- created by ahg-settings (alphabetically loads later than ahg-condition);
-- on a fresh install, this seed will silently skip on first pass and land
-- on the second bin/install pass.
-- ============================================================================
DROP PROCEDURE IF EXISTS ahg_condition_seed_ai_dropdowns;
DELIMITER //
CREATE PROCEDURE ahg_condition_seed_ai_dropdowns()
proc: BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ahg_dropdown') THEN
        LEAVE proc;
    END IF;

    INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
        ('ai_assessment_source', 'AI Assessment Source', 'ai', 'manual', 'Manual Upload', '#6c757d', 10, 1),
        ('ai_assessment_source', 'AI Assessment Source', 'ai', 'bulk', 'Bulk Scan', '#0d6efd', 20, 0),
        ('ai_assessment_source', 'AI Assessment Source', 'ai', 'auto', 'Auto (On Upload)', '#198754', 30, 0),
        ('ai_assessment_source', 'AI Assessment Source', 'ai', 'api', 'External API', '#6f42c1', 40, 0),
        ('ai_assessment_source', 'AI Assessment Source', 'ai', 'manual_entry', 'Manual Entry', '#495057', 50, 0);

    INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
        ('ai_service_tier', 'AI Service Tier', 'ai', 'free', 'Free (50/month)', '#6c757d', 10, 1),
        ('ai_service_tier', 'AI Service Tier', 'ai', 'standard', 'Standard (500/month)', '#0d6efd', 20, 0),
        ('ai_service_tier', 'AI Service Tier', 'ai', 'pro', 'Professional (5000/month)', '#198754', 30, 0),
        ('ai_service_tier', 'AI Service Tier', 'ai', 'enterprise', 'Enterprise (Unlimited)', '#dc3545', 40, 0);

    INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `sort_order`, `is_default`) VALUES
        ('ai_confidence_level', 'AI Confidence Level', 'ai', 'low', 'Low (<50%)', '#dc3545', 10, 0),
        ('ai_confidence_level', 'AI Confidence Level', 'ai', 'medium', 'Medium (50-75%)', '#ffc107', 20, 0),
        ('ai_confidence_level', 'AI Confidence Level', 'ai', 'high', 'High (75-90%)', '#198754', 30, 1),
        ('ai_confidence_level', 'AI Confidence Level', 'ai', 'very_high', 'Very High (>90%)', '#0d6efd', 40, 0);
END proc //
DELIMITER ;
CALL ahg_condition_seed_ai_dropdowns();
DROP PROCEDURE IF EXISTS ahg_condition_seed_ai_dropdowns;

-- Seed internal API client
INSERT IGNORE INTO `ahg_ai_service_client` (`id`, `name`, `organization`, `email`, `api_key`, `tier`, `monthly_limit`, `is_active`) VALUES
    (1, 'AtoM Internal', 'The Archive and Heritage Group', 'johan@theahg.co.za', 'ahg_ai_condition_internal_2026', 'enterprise', 999999, 1);

-- ============================================================================
-- Settings seed data
-- ============================================================================

INSERT IGNORE INTO `ahg_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
    ('ai_condition_service_url', 'http://localhost:8100', 'ai_condition'),
    ('ai_condition_api_key', 'ahg_ai_condition_internal_2026', 'ai_condition'),
    ('ai_condition_auto_scan', '0', 'ai_condition'),
    ('ai_condition_min_confidence', '0.25', 'ai_condition'),
    ('ai_condition_overlay_enabled', '1', 'ai_condition'),
    ('ai_condition_notify_grade', 'poor', 'ai_condition');

SET FOREIGN_KEY_CHECKS = 1;
