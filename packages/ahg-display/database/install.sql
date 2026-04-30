-- ============================================================================
-- ahg-display — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgDisplayPlugin/database/install.sql
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
-- ahgDisplayPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `display_collection_type`
--



CREATE TABLE IF NOT EXISTS `display_collection_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `default_profile_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_collection_type_i18n`
--



CREATE TABLE IF NOT EXISTS `display_collection_type_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dcti_type` FOREIGN KEY (`id`) REFERENCES `display_collection_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_field`
--



CREATE TABLE IF NOT EXISTS `display_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `field_group` VARCHAR(68) DEFAULT 'description' COMMENT 'identity, description, context, access, technical, admin',
  `data_type` VARCHAR(101) DEFAULT 'text' COMMENT 'text, textarea, date, daterange, number, select, multiselect, relation, file, actor, term',
  `source_table` varchar(100) DEFAULT NULL,
  `source_column` varchar(100) DEFAULT NULL,
  `source_i18n` tinyint(1) DEFAULT '0',
  `property_type_id` int DEFAULT NULL,
  `taxonomy_id` int DEFAULT NULL,
  `relation_type_id` int DEFAULT NULL,
  `event_type_id` int DEFAULT NULL,
  `isad_element` varchar(50) DEFAULT NULL,
  `spectrum_unit` varchar(50) DEFAULT NULL,
  `dc_element` varchar(50) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_field_i18n`
--



CREATE TABLE IF NOT EXISTS `display_field_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `help_text` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dfi_field` FOREIGN KEY (`id`) REFERENCES `display_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_level`
--



CREATE TABLE IF NOT EXISTS `display_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_code` varchar(30) DEFAULT NULL,
  `domain` varchar(20) DEFAULT 'universal',
  `valid_parent_codes` json DEFAULT NULL,
  `valid_child_codes` json DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `atom_term_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_level_i18n`
--



CREATE TABLE IF NOT EXISTS `display_level_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dli_level` FOREIGN KEY (`id`) REFERENCES `display_level` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_mode_global`
--



CREATE TABLE IF NOT EXISTS `display_mode_global` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `available_modes` json DEFAULT NULL COMMENT 'JSON array of enabled modes for this module',
  `allow_user_override` tinyint(1) DEFAULT '1' COMMENT 'Allow users to change from default',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module` (`module`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `display_object_config`
--



CREATE TABLE IF NOT EXISTS `display_object_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `object_type` varchar(30) DEFAULT 'archive',
  `primary_profile_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`object_type`),
  CONSTRAINT `fk_doc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=304 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_object_profile`
--



CREATE TABLE IF NOT EXISTS `display_object_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `profile_id` int NOT NULL,
  `context` varchar(30) DEFAULT 'default',
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`object_id`,`profile_id`,`context`),
  KEY `idx_object` (`object_id`),
  KEY `fk_dop_profile` (`profile_id`),
  CONSTRAINT `fk_dop_profile` FOREIGN KEY (`profile_id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_profile`
--



CREATE TABLE IF NOT EXISTS `display_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `domain` varchar(20) DEFAULT NULL,
  `layout_mode` VARCHAR(74) DEFAULT 'detail' COMMENT 'detail, hierarchy, grid, gallery, list, card, masonry, catalog',
  `thumbnail_size` VARCHAR(50) DEFAULT 'medium' COMMENT 'none, small, medium, large, hero, full',
  `thumbnail_position` VARCHAR(48) DEFAULT 'left' COMMENT 'left, right, top, background, inline',
  `identity_fields` json DEFAULT NULL,
  `description_fields` json DEFAULT NULL,
  `context_fields` json DEFAULT NULL,
  `access_fields` json DEFAULT NULL,
  `technical_fields` json DEFAULT NULL,
  `hidden_fields` json DEFAULT NULL,
  `field_labels` json DEFAULT NULL,
  `available_actions` json DEFAULT NULL,
  `css_class` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_profile_i18n`
--



CREATE TABLE IF NOT EXISTS `display_profile_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dpi_profile` FOREIGN KEY (`id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `user_display_preference`
--



CREATE TABLE IF NOT EXISTS `user_display_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module context: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list' COMMENT 'tree, grid, gallery, list, timeline',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_custom` tinyint(1) DEFAULT '1' COMMENT 'True if user explicitly set, false if inherited from global',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_udp_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;












-- Seed Data
--












--
-- Dumping data for table `display_profile`
--

LOCK TABLES `display_profile` WRITE;

INSERT IGNORE INTO `display_profile` VALUES (1,'isad_full','archive','detail','small','left','[\"identifier\", \"title\", \"dates\", \"level\", \"extent\", \"creator\"]','[\"scope_content\", \"arrangement\", \"appraisal\"]','[\"provenance\", \"custodial_history\", \"acquisition\"]','[\"access_conditions\", \"reproduction\", \"language\", \"finding_aids\"]',NULL,NULL,NULL,'[\"view\", \"download\", \"request\", \"cite\", \"print\"]',NULL,1,1,1,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (2,'isad_hierarchy','archive','hierarchy','none','left','[\"identifier\", \"title\", \"dates\", \"level\"]','[\"scope_content\"]','[]','[]',NULL,NULL,NULL,'[\"view\", \"expand\"]',NULL,0,1,2,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (3,'isad_list','archive','list','small','left','[\"identifier\", \"title\", \"dates\", \"level\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,3,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (4,'spectrum_full','museum','detail','large','left','[\"object_number\", \"object_name\", \"classification\", \"materials\", \"dimensions\", \"technique\"]','[\"description\", \"inscription\", \"condition\", \"completeness\"]','[\"production\", \"provenance\", \"acquisition\", \"associations\"]','[\"access_conditions\", \"reproduction\"]',NULL,NULL,NULL,'[\"view\", \"condition_report\", \"movement\", \"loan_request\", \"print\"]',NULL,1,1,10,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (5,'spectrum_card','museum','card','medium','top','[\"object_number\", \"object_name\", \"materials\"]','[\"description\"]','[]','[]',NULL,NULL,NULL,'[\"view\", \"add_to_exhibition\"]',NULL,0,1,11,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (6,'spectrum_catalog','museum','catalog','medium','left','[\"object_number\", \"object_name\", \"materials\", \"dimensions\", \"date\"]','[\"description\"]','[\"provenance\"]','[]',NULL,NULL,NULL,'[\"view\", \"print\"]',NULL,0,1,12,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (7,'gallery_full','gallery','gallery','hero','top','[\"artist\", \"title\", \"date\", \"medium\", \"dimensions\", \"edition_info\"]','[\"description\", \"artist_statement\"]','[\"provenance\", \"exhibition_history\", \"bibliography\"]','[\"rights\", \"reproduction\"]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"add_to_exhibition\", \"license\", \"print\"]',NULL,1,1,20,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (8,'gallery_wall','gallery','gallery','full','background','[\"artist\", \"title\", \"date\", \"medium\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"info\"]',NULL,0,1,21,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (9,'gallery_catalog','gallery','catalog','medium','left','[\"artist\", \"title\", \"date\", \"medium\", \"dimensions\"]','[\"description\"]','[\"provenance\", \"literature\"]','[]',NULL,NULL,NULL,'[\"view\", \"print\"]',NULL,0,1,22,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (10,'book_full','library','detail','small','left','[\"call_number\", \"title\", \"author\", \"publisher\", \"date\", \"isbn\", \"edition\"]','[\"abstract\", \"subjects\", \"table_of_contents\"]','[\"provenance\", \"notes\"]','[\"access_conditions\", \"location\"]',NULL,NULL,NULL,'[\"view\", \"request\", \"cite\", \"print\"]',NULL,1,1,30,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (11,'book_list','library','list','none','left','[\"call_number\", \"author\", \"title\", \"date\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"request\"]',NULL,0,1,31,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (12,'book_card','library','card','small','left','[\"title\", \"author\", \"date\"]','[\"abstract\"]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,32,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (13,'photo_full','dam','detail','large','top','[\"asset_id\", \"title\", \"photographer\", \"date_taken\", \"location\"]','[\"caption\", \"keywords\"]','[\"provenance\", \"collection\"]','[\"rights\", \"usage_terms\", \"restrictions\"]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"download\", \"add_to_lightbox\", \"license\", \"derivatives\"]',NULL,1,1,40,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (14,'photo_grid','dam','grid','medium','top','[\"title\", \"date\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"select\", \"add_to_lightbox\"]',NULL,0,1,41,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (15,'photo_lightbox','dam','masonry','large','top','[\"title\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"select\", \"compare\", \"download\"]',NULL,0,1,42,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (16,'search_result','universal','card','small','left','[\"identifier\", \"title\", \"creator\", \"date\", \"level\"]','[\"description\"]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,100,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (17,'print_record','universal','detail','medium','right','[\"identifier\", \"title\", \"creator\", \"date\", \"level\", \"extent\"]','[\"scope_content\", \"description\"]','[\"provenance\", \"acquisition\"]','[\"access_conditions\", \"rights\"]',NULL,NULL,NULL,'[]',NULL,0,1,101,'2025-12-11 07:18:24');

UNLOCK TABLES;

--
-- Dumping data for table `display_profile_i18n`
--

LOCK TABLES `display_profile_i18n` WRITE;

INSERT IGNORE INTO `display_profile_i18n` VALUES (1,'en','Isad full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (2,'en','Isad hierarchy',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (3,'en','Isad list',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (4,'en','Spectrum full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (5,'en','Spectrum card',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (6,'en','Spectrum catalog',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (7,'en','Gallery full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (8,'en','Gallery wall',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (9,'en','Gallery catalog',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (10,'en','Book full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (11,'en','Book list',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (12,'en','Book card',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (13,'en','Photo full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (14,'en','Photo grid',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (15,'en','Photo lightbox',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (16,'en','Search result',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (17,'en','Print record',NULL);

UNLOCK TABLES;

--
-- Dumping data for table `display_level`
--

LOCK TABLES `display_level` WRITE;

INSERT IGNORE INTO `display_level` VALUES (1,'repository',NULL,'universal',NULL,'[\"fonds\", \"collection\", \"holding\"]','fa-building',NULL,1,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (2,'collection',NULL,'universal','[\"repository\"]','[\"fonds\", \"series\", \"album\", \"object\", \"item\", \"book\"]','fa-folder-tree',NULL,5,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (3,'fonds',NULL,'archive','[\"repository\"]','[\"subfonds\", \"series\"]','fa-archive',NULL,10,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (4,'subfonds',NULL,'archive','[\"fonds\"]','[\"series\"]','fa-folder',NULL,11,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (5,'series',NULL,'archive','[\"fonds\", \"subfonds\", \"collection\"]','[\"subseries\", \"file\"]','fa-folder-open',NULL,12,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (6,'subseries',NULL,'archive','[\"series\"]','[\"file\"]','fa-folder-open',NULL,13,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (7,'file',NULL,'archive','[\"series\", \"subseries\"]','[\"item\", \"piece\"]','fa-file-alt',NULL,14,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (8,'item',NULL,'archive','[\"file\", \"series\", \"collection\"]','[\"piece\", \"component\"]','fa-file',NULL,15,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (9,'piece',NULL,'archive','[\"item\", \"file\"]',NULL,'fa-puzzle-piece',NULL,16,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (10,'holding',NULL,'museum','[\"repository\"]','[\"object_group\", \"object\"]','fa-landmark',NULL,20,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (11,'object_group',NULL,'museum','[\"holding\", \"collection\"]','[\"object\"]','fa-cubes',NULL,21,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (12,'object',NULL,'museum','[\"object_group\", \"holding\", \"collection\"]','[\"component\", \"fragment\"]','fa-cube',NULL,22,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (13,'component',NULL,'museum','[\"object\"]',NULL,'fa-puzzle-piece',NULL,23,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (14,'specimen',NULL,'museum','[\"collection\", \"holding\"]','[\"sample\"]','fa-leaf',NULL,25,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (15,'artist_archive',NULL,'gallery','[\"repository\"]','[\"artwork_series\", \"artwork\"]','fa-palette',NULL,30,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (16,'artwork_series',NULL,'gallery','[\"artist_archive\", \"collection\"]','[\"artwork\"]','fa-layer-group',NULL,31,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (17,'artwork',NULL,'gallery','[\"artwork_series\", \"collection\"]','[\"study\", \"edition\"]','fa-image',NULL,32,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (18,'study',NULL,'gallery','[\"artwork\"]',NULL,'fa-pencil-alt',NULL,33,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (19,'edition',NULL,'gallery','[\"artwork\"]','[\"impression\"]','fa-clone',NULL,34,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (20,'impression',NULL,'gallery','[\"edition\"]',NULL,'fa-stamp',NULL,35,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (21,'book_collection',NULL,'library','[\"repository\"]','[\"book\", \"periodical\", \"volume\"]','fa-books',NULL,40,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (22,'book',NULL,'library','[\"book_collection\", \"collection\"]','[\"chapter\"]','fa-book',NULL,41,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (23,'periodical',NULL,'library','[\"book_collection\"]','[\"issue\"]','fa-newspaper',NULL,42,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (24,'volume',NULL,'library','[\"periodical\", \"book\"]','[\"issue\", \"chapter\"]','fa-book-open',NULL,43,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (25,'issue',NULL,'library','[\"periodical\", \"volume\"]','[\"article\"]','fa-file-alt',NULL,44,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (26,'chapter',NULL,'library','[\"book\", \"volume\"]',NULL,'fa-bookmark',NULL,45,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (27,'pamphlet',NULL,'library','[\"book_collection\", \"collection\"]',NULL,'fa-scroll',NULL,46,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (28,'map',NULL,'library','[\"collection\"]',NULL,'fa-map',NULL,47,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (29,'photo_collection',NULL,'dam','[\"repository\"]','[\"album\", \"shoot\", \"photograph\"]','fa-images',NULL,50,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (30,'album',NULL,'dam','[\"photo_collection\", \"collection\"]','[\"photograph\"]','fa-book-open',NULL,51,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (31,'shoot',NULL,'dam','[\"photo_collection\"]','[\"photograph\"]','fa-camera',NULL,52,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (32,'photograph',NULL,'dam','[\"album\", \"shoot\", \"photo_collection\", \"collection\"]','[\"derivative\"]','fa-image',NULL,53,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (33,'negative',NULL,'dam','[\"album\", \"collection\"]',NULL,'fa-film',NULL,54,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (34,'slide',NULL,'dam','[\"album\", \"collection\"]',NULL,'fa-square',NULL,55,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (35,'digital_asset',NULL,'dam','[\"collection\", \"album\"]',NULL,'fa-file-image',NULL,56,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (36,'av_collection',NULL,'archive','[\"repository\"]','[\"recording\", \"film\"]','fa-film',NULL,60,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (37,'film',NULL,'archive','[\"av_collection\", \"collection\"]','[\"reel\", \"clip\"]','fa-video',NULL,61,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (38,'recording',NULL,'archive','[\"av_collection\", \"collection\"]','[\"segment\"]','fa-record-vinyl',NULL,62,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (39,'reel',NULL,'archive','[\"film\"]','[\"clip\"]','fa-circle',NULL,63,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (40,'segment',NULL,'archive','[\"recording\"]',NULL,'fa-cut',NULL,64,1,NULL,'2025-12-11 07:18:24');

UNLOCK TABLES;

--
-- Dumping data for table `display_level_i18n`
--

LOCK TABLES `display_level_i18n` WRITE;

INSERT IGNORE INTO `display_level_i18n` VALUES (1,'en','Repository',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (2,'en','Collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (3,'en','Fonds',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (4,'en','Subfonds',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (5,'en','Series',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (6,'en','Subseries',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (7,'en','File',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (8,'en','Item',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (9,'en','Piece',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (10,'en','Holding',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (11,'en','Object group',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (12,'en','Object',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (13,'en','Component',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (14,'en','Specimen',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (15,'en','Artist archive',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (16,'en','Artwork series',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (17,'en','Artwork',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (18,'en','Study',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (19,'en','Edition',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (20,'en','Impression',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (21,'en','Book collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (22,'en','Book',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (23,'en','Periodical',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (24,'en','Volume',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (25,'en','Issue',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (26,'en','Chapter',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (27,'en','Pamphlet',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (28,'en','Map',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (29,'en','Photo collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (30,'en','Album',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (31,'en','Shoot',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (32,'en','Photograph',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (33,'en','Negative',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (34,'en','Slide',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (35,'en','Digital asset',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (36,'en','Av collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (37,'en','Film',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (38,'en','Recording',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (39,'en','Reel',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (40,'en','Segment',NULL);

UNLOCK TABLES;











-- ============================================================
-- User Browse Settings Table
-- Per-user settings for browse interface preferences
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_browse_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `use_glam_browse` tinyint(1) DEFAULT '0' COMMENT 'Use GLAM browse as default browse interface',
  `default_sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `default_sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `default_view` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `show_facets` tinyint(1) DEFAULT '1',
  `remember_filters` tinyint(1) DEFAULT '1' COMMENT 'Remember last used filters',
  `last_filters` json DEFAULT NULL COMMENT 'JSON of last used filter values',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Display Facet Cache Table
-- Caches facet counts for fast guest user browse
-- Refreshed by: php symfony ahg:refresh-facet-cache
-- ============================================================

CREATE TABLE IF NOT EXISTS `display_facet_cache` (
  `id` int NOT NULL AUTO_INCREMENT,
  `facet_type` varchar(50) NOT NULL COMMENT 'Type: subject, place, genre, level, repository, creator, glam_type, media_type',
  `term_id` int NOT NULL DEFAULT '0' COMMENT 'Term ID or 0 for non-term facets',
  `term_name` varchar(255) NOT NULL COMMENT 'Display name for the facet value',
  `count` int NOT NULL DEFAULT '0' COMMENT 'Number of published items',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_facet_type` (`facet_type`),
  KEY `idx_facet_count` (`facet_type`, `count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgDisplayPlugin on 2026-04-30
-- ============================================================================
-- ============================================================
-- ahgDisplayPlugin - Database Schema
-- Generated from actual database structure
-- DO NOT include INSERT INTO atom_plugin
-- ============================================================

--












--
-- Table structure for table `display_collection_type`
--



CREATE TABLE IF NOT EXISTS `display_collection_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_id` int DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `default_profile_id` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_collection_type_i18n`
--



CREATE TABLE IF NOT EXISTS `display_collection_type_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dcti_type` FOREIGN KEY (`id`) REFERENCES `display_collection_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_field`
--



CREATE TABLE IF NOT EXISTS `display_field` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `field_group` VARCHAR(68) DEFAULT 'description' COMMENT 'identity, description, context, access, technical, admin',
  `data_type` VARCHAR(101) DEFAULT 'text' COMMENT 'text, textarea, date, daterange, number, select, multiselect, relation, file, actor, term',
  `source_table` varchar(100) DEFAULT NULL,
  `source_column` varchar(100) DEFAULT NULL,
  `source_i18n` tinyint(1) DEFAULT '0',
  `property_type_id` int DEFAULT NULL,
  `taxonomy_id` int DEFAULT NULL,
  `relation_type_id` int DEFAULT NULL,
  `event_type_id` int DEFAULT NULL,
  `isad_element` varchar(50) DEFAULT NULL,
  `spectrum_unit` varchar(50) DEFAULT NULL,
  `dc_element` varchar(50) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_field_i18n`
--



CREATE TABLE IF NOT EXISTS `display_field_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `help_text` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dfi_field` FOREIGN KEY (`id`) REFERENCES `display_field` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_level`
--



CREATE TABLE IF NOT EXISTS `display_level` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `parent_code` varchar(30) DEFAULT NULL,
  `domain` varchar(20) DEFAULT 'universal',
  `valid_parent_codes` json DEFAULT NULL,
  `valid_child_codes` json DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(20) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `atom_term_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_level_i18n`
--



CREATE TABLE IF NOT EXISTS `display_level_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dli_level` FOREIGN KEY (`id`) REFERENCES `display_level` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_mode_global`
--



CREATE TABLE IF NOT EXISTS `display_mode_global` (
  `id` int NOT NULL AUTO_INCREMENT,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `available_modes` json DEFAULT NULL COMMENT 'JSON array of enabled modes for this module',
  `allow_user_override` tinyint(1) DEFAULT '1' COMMENT 'Allow users to change from default',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module` (`module`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `display_object_config`
--



CREATE TABLE IF NOT EXISTS `display_object_config` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `object_type` varchar(30) DEFAULT 'archive',
  `primary_profile_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `object_id` (`object_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`object_type`),
  CONSTRAINT `fk_doc_object` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=304 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_object_profile`
--



CREATE TABLE IF NOT EXISTS `display_object_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `profile_id` int NOT NULL,
  `context` varchar(30) DEFAULT 'default',
  `is_primary` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`object_id`,`profile_id`,`context`),
  KEY `idx_object` (`object_id`),
  KEY `fk_dop_profile` (`profile_id`),
  CONSTRAINT `fk_dop_profile` FOREIGN KEY (`profile_id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_profile`
--



CREATE TABLE IF NOT EXISTS `display_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `domain` varchar(20) DEFAULT NULL,
  `layout_mode` VARCHAR(74) DEFAULT 'detail' COMMENT 'detail, hierarchy, grid, gallery, list, card, masonry, catalog',
  `thumbnail_size` VARCHAR(50) DEFAULT 'medium' COMMENT 'none, small, medium, large, hero, full',
  `thumbnail_position` VARCHAR(48) DEFAULT 'left' COMMENT 'left, right, top, background, inline',
  `identity_fields` json DEFAULT NULL,
  `description_fields` json DEFAULT NULL,
  `context_fields` json DEFAULT NULL,
  `access_fields` json DEFAULT NULL,
  `technical_fields` json DEFAULT NULL,
  `hidden_fields` json DEFAULT NULL,
  `field_labels` json DEFAULT NULL,
  `available_actions` json DEFAULT NULL,
  `css_class` varchar(100) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `display_profile_i18n`
--



CREATE TABLE IF NOT EXISTS `display_profile_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) NOT NULL DEFAULT 'en',
  `name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_dpi_profile` FOREIGN KEY (`id`) REFERENCES `display_profile` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


--
-- Table structure for table `user_display_preference`
--



CREATE TABLE IF NOT EXISTS `user_display_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `module` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Module context: informationobject, actor, repository, etc.',
  `display_mode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list' COMMENT 'tree, grid, gallery, list, timeline',
  `items_per_page` int DEFAULT '30',
  `sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `show_thumbnails` tinyint(1) DEFAULT '1',
  `show_descriptions` tinyint(1) DEFAULT '1',
  `card_size` VARCHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT 'medium' COMMENT 'small, medium, large',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_custom` tinyint(1) DEFAULT '1' COMMENT 'True if user explicitly set, false if inherited from global',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_module` (`user_id`,`module`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_module` (`module`),
  KEY `idx_udp_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;












-- Seed Data
--












--
-- Dumping data for table `display_profile`
--

LOCK TABLES `display_profile` WRITE;

INSERT IGNORE INTO `display_profile` VALUES (1,'isad_full','archive','detail','small','left','[\"identifier\", \"title\", \"dates\", \"level\", \"extent\", \"creator\"]','[\"scope_content\", \"arrangement\", \"appraisal\"]','[\"provenance\", \"custodial_history\", \"acquisition\"]','[\"access_conditions\", \"reproduction\", \"language\", \"finding_aids\"]',NULL,NULL,NULL,'[\"view\", \"download\", \"request\", \"cite\", \"print\"]',NULL,1,1,1,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (2,'isad_hierarchy','archive','hierarchy','none','left','[\"identifier\", \"title\", \"dates\", \"level\"]','[\"scope_content\"]','[]','[]',NULL,NULL,NULL,'[\"view\", \"expand\"]',NULL,0,1,2,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (3,'isad_list','archive','list','small','left','[\"identifier\", \"title\", \"dates\", \"level\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,3,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (4,'spectrum_full','museum','detail','large','left','[\"object_number\", \"object_name\", \"classification\", \"materials\", \"dimensions\", \"technique\"]','[\"description\", \"inscription\", \"condition\", \"completeness\"]','[\"production\", \"provenance\", \"acquisition\", \"associations\"]','[\"access_conditions\", \"reproduction\"]',NULL,NULL,NULL,'[\"view\", \"condition_report\", \"movement\", \"loan_request\", \"print\"]',NULL,1,1,10,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (5,'spectrum_card','museum','card','medium','top','[\"object_number\", \"object_name\", \"materials\"]','[\"description\"]','[]','[]',NULL,NULL,NULL,'[\"view\", \"add_to_exhibition\"]',NULL,0,1,11,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (6,'spectrum_catalog','museum','catalog','medium','left','[\"object_number\", \"object_name\", \"materials\", \"dimensions\", \"date\"]','[\"description\"]','[\"provenance\"]','[]',NULL,NULL,NULL,'[\"view\", \"print\"]',NULL,0,1,12,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (7,'gallery_full','gallery','gallery','hero','top','[\"artist\", \"title\", \"date\", \"medium\", \"dimensions\", \"edition_info\"]','[\"description\", \"artist_statement\"]','[\"provenance\", \"exhibition_history\", \"bibliography\"]','[\"rights\", \"reproduction\"]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"add_to_exhibition\", \"license\", \"print\"]',NULL,1,1,20,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (8,'gallery_wall','gallery','gallery','full','background','[\"artist\", \"title\", \"date\", \"medium\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"info\"]',NULL,0,1,21,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (9,'gallery_catalog','gallery','catalog','medium','left','[\"artist\", \"title\", \"date\", \"medium\", \"dimensions\"]','[\"description\"]','[\"provenance\", \"literature\"]','[]',NULL,NULL,NULL,'[\"view\", \"print\"]',NULL,0,1,22,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (10,'book_full','library','detail','small','left','[\"call_number\", \"title\", \"author\", \"publisher\", \"date\", \"isbn\", \"edition\"]','[\"abstract\", \"subjects\", \"table_of_contents\"]','[\"provenance\", \"notes\"]','[\"access_conditions\", \"location\"]',NULL,NULL,NULL,'[\"view\", \"request\", \"cite\", \"print\"]',NULL,1,1,30,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (11,'book_list','library','list','none','left','[\"call_number\", \"author\", \"title\", \"date\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"request\"]',NULL,0,1,31,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (12,'book_card','library','card','small','left','[\"title\", \"author\", \"date\"]','[\"abstract\"]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,32,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (13,'photo_full','dam','detail','large','top','[\"asset_id\", \"title\", \"photographer\", \"date_taken\", \"location\"]','[\"caption\", \"keywords\"]','[\"provenance\", \"collection\"]','[\"rights\", \"usage_terms\", \"restrictions\"]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"download\", \"add_to_lightbox\", \"license\", \"derivatives\"]',NULL,1,1,40,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (14,'photo_grid','dam','grid','medium','top','[\"title\", \"date\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"select\", \"add_to_lightbox\"]',NULL,0,1,41,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (15,'photo_lightbox','dam','masonry','large','top','[\"title\"]','[]','[]','[]',NULL,NULL,NULL,'[\"view\", \"zoom\", \"select\", \"compare\", \"download\"]',NULL,0,1,42,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (16,'search_result','universal','card','small','left','[\"identifier\", \"title\", \"creator\", \"date\", \"level\"]','[\"description\"]','[]','[]',NULL,NULL,NULL,'[\"view\"]',NULL,0,1,100,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_profile` VALUES (17,'print_record','universal','detail','medium','right','[\"identifier\", \"title\", \"creator\", \"date\", \"level\", \"extent\"]','[\"scope_content\", \"description\"]','[\"provenance\", \"acquisition\"]','[\"access_conditions\", \"rights\"]',NULL,NULL,NULL,'[]',NULL,0,1,101,'2025-12-11 07:18:24');

UNLOCK TABLES;

--
-- Dumping data for table `display_profile_i18n`
--

LOCK TABLES `display_profile_i18n` WRITE;

INSERT IGNORE INTO `display_profile_i18n` VALUES (1,'en','Isad full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (2,'en','Isad hierarchy',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (3,'en','Isad list',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (4,'en','Spectrum full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (5,'en','Spectrum card',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (6,'en','Spectrum catalog',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (7,'en','Gallery full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (8,'en','Gallery wall',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (9,'en','Gallery catalog',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (10,'en','Book full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (11,'en','Book list',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (12,'en','Book card',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (13,'en','Photo full',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (14,'en','Photo grid',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (15,'en','Photo lightbox',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (16,'en','Search result',NULL);
INSERT IGNORE INTO `display_profile_i18n` VALUES (17,'en','Print record',NULL);

UNLOCK TABLES;

--
-- Dumping data for table `display_level`
--

LOCK TABLES `display_level` WRITE;

INSERT IGNORE INTO `display_level` VALUES (1,'repository',NULL,'universal',NULL,'[\"fonds\", \"collection\", \"holding\"]','fa-building',NULL,1,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (2,'collection',NULL,'universal','[\"repository\"]','[\"fonds\", \"series\", \"album\", \"object\", \"item\", \"book\"]','fa-folder-tree',NULL,5,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (3,'fonds',NULL,'archive','[\"repository\"]','[\"subfonds\", \"series\"]','fa-archive',NULL,10,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (4,'subfonds',NULL,'archive','[\"fonds\"]','[\"series\"]','fa-folder',NULL,11,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (5,'series',NULL,'archive','[\"fonds\", \"subfonds\", \"collection\"]','[\"subseries\", \"file\"]','fa-folder-open',NULL,12,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (6,'subseries',NULL,'archive','[\"series\"]','[\"file\"]','fa-folder-open',NULL,13,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (7,'file',NULL,'archive','[\"series\", \"subseries\"]','[\"item\", \"piece\"]','fa-file-alt',NULL,14,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (8,'item',NULL,'archive','[\"file\", \"series\", \"collection\"]','[\"piece\", \"component\"]','fa-file',NULL,15,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (9,'piece',NULL,'archive','[\"item\", \"file\"]',NULL,'fa-puzzle-piece',NULL,16,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (10,'holding',NULL,'museum','[\"repository\"]','[\"object_group\", \"object\"]','fa-landmark',NULL,20,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (11,'object_group',NULL,'museum','[\"holding\", \"collection\"]','[\"object\"]','fa-cubes',NULL,21,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (12,'object',NULL,'museum','[\"object_group\", \"holding\", \"collection\"]','[\"component\", \"fragment\"]','fa-cube',NULL,22,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (13,'component',NULL,'museum','[\"object\"]',NULL,'fa-puzzle-piece',NULL,23,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (14,'specimen',NULL,'museum','[\"collection\", \"holding\"]','[\"sample\"]','fa-leaf',NULL,25,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (15,'artist_archive',NULL,'gallery','[\"repository\"]','[\"artwork_series\", \"artwork\"]','fa-palette',NULL,30,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (16,'artwork_series',NULL,'gallery','[\"artist_archive\", \"collection\"]','[\"artwork\"]','fa-layer-group',NULL,31,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (17,'artwork',NULL,'gallery','[\"artwork_series\", \"collection\"]','[\"study\", \"edition\"]','fa-image',NULL,32,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (18,'study',NULL,'gallery','[\"artwork\"]',NULL,'fa-pencil-alt',NULL,33,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (19,'edition',NULL,'gallery','[\"artwork\"]','[\"impression\"]','fa-clone',NULL,34,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (20,'impression',NULL,'gallery','[\"edition\"]',NULL,'fa-stamp',NULL,35,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (21,'book_collection',NULL,'library','[\"repository\"]','[\"book\", \"periodical\", \"volume\"]','fa-books',NULL,40,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (22,'book',NULL,'library','[\"book_collection\", \"collection\"]','[\"chapter\"]','fa-book',NULL,41,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (23,'periodical',NULL,'library','[\"book_collection\"]','[\"issue\"]','fa-newspaper',NULL,42,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (24,'volume',NULL,'library','[\"periodical\", \"book\"]','[\"issue\", \"chapter\"]','fa-book-open',NULL,43,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (25,'issue',NULL,'library','[\"periodical\", \"volume\"]','[\"article\"]','fa-file-alt',NULL,44,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (26,'chapter',NULL,'library','[\"book\", \"volume\"]',NULL,'fa-bookmark',NULL,45,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (27,'pamphlet',NULL,'library','[\"book_collection\", \"collection\"]',NULL,'fa-scroll',NULL,46,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (28,'map',NULL,'library','[\"collection\"]',NULL,'fa-map',NULL,47,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (29,'photo_collection',NULL,'dam','[\"repository\"]','[\"album\", \"shoot\", \"photograph\"]','fa-images',NULL,50,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (30,'album',NULL,'dam','[\"photo_collection\", \"collection\"]','[\"photograph\"]','fa-book-open',NULL,51,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (31,'shoot',NULL,'dam','[\"photo_collection\"]','[\"photograph\"]','fa-camera',NULL,52,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (32,'photograph',NULL,'dam','[\"album\", \"shoot\", \"photo_collection\", \"collection\"]','[\"derivative\"]','fa-image',NULL,53,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (33,'negative',NULL,'dam','[\"album\", \"collection\"]',NULL,'fa-film',NULL,54,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (34,'slide',NULL,'dam','[\"album\", \"collection\"]',NULL,'fa-square',NULL,55,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (35,'digital_asset',NULL,'dam','[\"collection\", \"album\"]',NULL,'fa-file-image',NULL,56,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (36,'av_collection',NULL,'archive','[\"repository\"]','[\"recording\", \"film\"]','fa-film',NULL,60,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (37,'film',NULL,'archive','[\"av_collection\", \"collection\"]','[\"reel\", \"clip\"]','fa-video',NULL,61,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (38,'recording',NULL,'archive','[\"av_collection\", \"collection\"]','[\"segment\"]','fa-record-vinyl',NULL,62,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (39,'reel',NULL,'archive','[\"film\"]','[\"clip\"]','fa-circle',NULL,63,1,NULL,'2025-12-11 07:18:24');
INSERT IGNORE INTO `display_level` VALUES (40,'segment',NULL,'archive','[\"recording\"]',NULL,'fa-cut',NULL,64,1,NULL,'2025-12-11 07:18:24');

UNLOCK TABLES;

--
-- Dumping data for table `display_level_i18n`
--

LOCK TABLES `display_level_i18n` WRITE;

INSERT IGNORE INTO `display_level_i18n` VALUES (1,'en','Repository',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (2,'en','Collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (3,'en','Fonds',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (4,'en','Subfonds',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (5,'en','Series',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (6,'en','Subseries',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (7,'en','File',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (8,'en','Item',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (9,'en','Piece',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (10,'en','Holding',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (11,'en','Object group',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (12,'en','Object',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (13,'en','Component',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (14,'en','Specimen',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (15,'en','Artist archive',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (16,'en','Artwork series',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (17,'en','Artwork',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (18,'en','Study',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (19,'en','Edition',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (20,'en','Impression',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (21,'en','Book collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (22,'en','Book',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (23,'en','Periodical',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (24,'en','Volume',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (25,'en','Issue',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (26,'en','Chapter',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (27,'en','Pamphlet',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (28,'en','Map',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (29,'en','Photo collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (30,'en','Album',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (31,'en','Shoot',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (32,'en','Photograph',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (33,'en','Negative',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (34,'en','Slide',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (35,'en','Digital asset',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (36,'en','Av collection',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (37,'en','Film',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (38,'en','Recording',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (39,'en','Reel',NULL);
INSERT IGNORE INTO `display_level_i18n` VALUES (40,'en','Segment',NULL);

UNLOCK TABLES;











-- ============================================================
-- User Browse Settings Table
-- Per-user settings for browse interface preferences
-- ============================================================

CREATE TABLE IF NOT EXISTS `user_browse_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `use_glam_browse` tinyint(1) DEFAULT '0' COMMENT 'Use GLAM browse as default browse interface',
  `default_sort_field` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'updated_at',
  `default_sort_direction` VARCHAR(21) COLLATE utf8mb4_unicode_ci DEFAULT 'desc' COMMENT 'asc, desc',
  `default_view` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'list',
  `items_per_page` int DEFAULT '30',
  `show_facets` tinyint(1) DEFAULT '1',
  `remember_filters` tinyint(1) DEFAULT '1' COMMENT 'Remember last used filters',
  `last_filters` json DEFAULT NULL COMMENT 'JSON of last used filter values',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_id` (`user_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Display Facet Cache Table
-- Caches facet counts for fast guest user browse
-- Refreshed by: php symfony ahg:refresh-facet-cache
-- ============================================================

CREATE TABLE IF NOT EXISTS `display_facet_cache` (
  `id` int NOT NULL AUTO_INCREMENT,
  `facet_type` varchar(50) NOT NULL COMMENT 'Type: subject, place, genre, level, repository, creator, glam_type, media_type',
  `term_id` int NOT NULL DEFAULT '0' COMMENT 'Term ID or 0 for non-term facets',
  `term_name` varchar(255) NOT NULL COMMENT 'Display name for the facet value',
  `count` int NOT NULL DEFAULT '0' COMMENT 'Number of published items',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_facet_type` (`facet_type`),
  KEY `idx_facet_count` (`facet_type`, `count` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
