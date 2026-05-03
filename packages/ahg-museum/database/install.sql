-- ============================================================================
-- ahg-museum — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgMuseumPlugin/database/install.sql
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

-- =====================================================
-- Museum Plugin Install
-- =====================================================

-- Add Museum display standard term (taxonomy_id = 70)
-- Check if already exists first
SET @museum_exists = (SELECT COUNT(*) FROM term WHERE code = 'museum' AND taxonomy_id = 70);

-- Create object only if term doesn't exist
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @museum_exists = 0;

SET @museum_id = LAST_INSERT_ID();

-- Create term only if we just created an object
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @museum_id, 70, 'museum', 'en' FROM DUAL WHERE @museum_exists = 0 AND @museum_id > 0;

-- Create term_i18n only if we just created a term
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @museum_id, 'en', 'Museum (CCO), Cataloging Cultural Objects' FROM DUAL WHERE @museum_exists = 0 AND @museum_id > 0;

-- =====================================================
-- Museum Level of Description Terms (taxonomy_id = 34)
-- =====================================================

-- Object
SET @obj_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Object' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @obj_exists IS NULL;
SET @obj_id = IF(@obj_exists IS NULL, LAST_INSERT_ID(), @obj_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @obj_id, 34, 'en' FROM DUAL WHERE @obj_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @obj_id, 'en', 'Object' FROM DUAL WHERE @obj_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@obj_id, 'level-object');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@obj_id, 'museum', 50);

-- Artwork
SET @art_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Artwork' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @art_exists IS NULL;
SET @art_id = IF(@art_exists IS NULL, LAST_INSERT_ID(), @art_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @art_id, 34, 'en' FROM DUAL WHERE @art_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @art_id, 'en', 'Artwork' FROM DUAL WHERE @art_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@art_id, 'level-artwork');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@art_id, 'museum', 30);
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@art_id, 'gallery', 10);

-- Artifact
SET @artf_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Artifact' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @artf_exists IS NULL;
SET @artf_id = IF(@artf_exists IS NULL, LAST_INSERT_ID(), @artf_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @artf_id, 34, 'en' FROM DUAL WHERE @artf_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @artf_id, 'en', 'Artifact' FROM DUAL WHERE @artf_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@artf_id, 'level-artifact');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@artf_id, 'museum', 20);

-- Specimen
SET @spec_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Specimen' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @spec_exists IS NULL;
SET @spec_id = IF(@spec_exists IS NULL, LAST_INSERT_ID(), @spec_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @spec_id, 34, 'en' FROM DUAL WHERE @spec_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @spec_id, 'en', 'Specimen' FROM DUAL WHERE @spec_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@spec_id, 'level-specimen');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@spec_id, 'museum', 60);

-- Installation
SET @inst_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='Installation' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @inst_exists IS NULL;
SET @inst_id = IF(@inst_exists IS NULL, LAST_INSERT_ID(), @inst_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @inst_id, 34, 'en' FROM DUAL WHERE @inst_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @inst_id, 'en', 'Installation' FROM DUAL WHERE @inst_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@inst_id, 'level-installation');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@inst_id, 'museum', 40);
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@inst_id, 'gallery', 40);

-- 3D Model
SET @model_exists = (SELECT t.id FROM term t JOIN term_i18n ti ON t.id=ti.id WHERE t.taxonomy_id=34 AND ti.name='3D Model' LIMIT 1);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @model_exists IS NULL;
SET @model_id = IF(@model_exists IS NULL, LAST_INSERT_ID(), @model_exists);
INSERT IGNORE INTO term (id, taxonomy_id, source_culture)
SELECT @model_id, 34, 'en' FROM DUAL WHERE @model_exists IS NULL;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @model_id, 'en', '3D Model' FROM DUAL WHERE @model_exists IS NULL;
INSERT IGNORE INTO slug (object_id, slug) VALUES (@model_id, 'level-3d-model');
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@model_id, 'museum', 10);
INSERT IGNORE INTO level_of_description_sector (term_id, sector, display_order) VALUES (@model_id, 'dam', 60);

-- =====================================================
-- Museum Metadata Table
-- =====================================================

CREATE TABLE IF NOT EXISTS `museum_metadata` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `work_type` varchar(50) DEFAULT NULL,
  `object_type` varchar(255) DEFAULT NULL,
  `classification` varchar(255) DEFAULT NULL,
  `materials` text,
  `techniques` text,
  `measurements` varchar(255) DEFAULT NULL,
  `dimensions` varchar(255) DEFAULT NULL,
  `creation_date_earliest` date DEFAULT NULL,
  `creation_date_latest` date DEFAULT NULL,
  `inscription` text,
  `inscriptions` text,
  `condition_notes` text,
  `provenance` text,
  `style_period` varchar(255) DEFAULT NULL,
  `cultural_context` varchar(255) DEFAULT NULL,
  `current_location` text,
  `edition_description` text,
  `state_description` varchar(512) DEFAULT NULL,
  `state_identification` varchar(100) DEFAULT NULL,
  `facture_description` text,
  `technique_cco` varchar(512) DEFAULT NULL,
  `technique_qualifier` varchar(255) DEFAULT NULL,
  `orientation` varchar(100) DEFAULT NULL,
  `physical_appearance` text,
  `color` varchar(255) DEFAULT NULL,
  `shape` varchar(255) DEFAULT NULL,
  `condition_term` varchar(100) DEFAULT NULL,
  `condition_date` date DEFAULT NULL,
  `condition_description` text,
  `condition_agent` varchar(255) DEFAULT NULL,
  `treatment_type` varchar(255) DEFAULT NULL,
  `treatment_date` date DEFAULT NULL,
  `treatment_agent` varchar(255) DEFAULT NULL,
  `treatment_description` text,
  `inscription_transcription` text,
  `inscription_type` varchar(100) DEFAULT NULL,
  `inscription_location` varchar(255) DEFAULT NULL,
  `inscription_language` varchar(100) DEFAULT NULL,
  `inscription_translation` text,
  `mark_type` varchar(100) DEFAULT NULL,
  `mark_description` text,
  `mark_location` varchar(255) DEFAULT NULL,
  `related_work_type` varchar(100) DEFAULT NULL,
  `related_work_relationship` varchar(255) DEFAULT NULL,
  `related_work_label` varchar(512) DEFAULT NULL,
  `related_work_id` varchar(255) DEFAULT NULL,
  `current_location_repository` varchar(512) DEFAULT NULL,
  `current_location_geography` varchar(512) DEFAULT NULL,
  `current_location_coordinates` varchar(100) DEFAULT NULL,
  `current_location_ref_number` varchar(255) DEFAULT NULL,
  `creation_place` varchar(512) DEFAULT NULL,
  `creation_place_type` varchar(100) DEFAULT NULL,
  `discovery_place` varchar(512) DEFAULT NULL,
  `discovery_place_type` varchar(100) DEFAULT NULL,
  `provenance_text` text,
  `ownership_history` text,
  `legal_status` varchar(255) DEFAULT NULL,
  `rights_type` varchar(100) DEFAULT NULL,
  `rights_holder` varchar(512) DEFAULT NULL,
  `rights_date` varchar(100) DEFAULT NULL,
  `rights_remarks` text,
  `cataloger_name` varchar(255) DEFAULT NULL,
  `cataloging_date` date DEFAULT NULL,
  `cataloging_institution` varchar(512) DEFAULT NULL,
  `cataloging_remarks` text,
  `record_type` varchar(100) DEFAULT NULL,
  `record_level` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `creator_identity` varchar(512) DEFAULT NULL,
  `creator_role` varchar(255) DEFAULT NULL,
  `creator_extent` varchar(255) DEFAULT NULL,
  `creator_qualifier` varchar(255) DEFAULT NULL,
  `creator_attribution` varchar(255) DEFAULT NULL,
  `creation_date_display` varchar(255) DEFAULT NULL,
  `creation_date_qualifier` varchar(100) DEFAULT NULL,
  `style` varchar(255) DEFAULT NULL,
  `period` varchar(255) DEFAULT NULL,
  `cultural_group` varchar(255) DEFAULT NULL,
  `movement` varchar(255) DEFAULT NULL,
  `school` varchar(255) DEFAULT NULL,
  `dynasty` varchar(255) DEFAULT NULL,
  `subject_indexing_type` varchar(100) DEFAULT NULL,
  `subject_display` text,
  `subject_extent` varchar(255) DEFAULT NULL,
  `historical_context` text,
  `architectural_context` text,
  `archaeological_context` text,
  `object_class` varchar(255) DEFAULT NULL,
  `object_category` varchar(255) DEFAULT NULL,
  `object_sub_category` varchar(255) DEFAULT NULL,
  `edition_number` varchar(100) DEFAULT NULL,
  `edition_size` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_object` (`object_id`),
  CONSTRAINT `museum_metadata_ibfk_1` FOREIGN KEY (`object_id`) REFERENCES `information_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- ============================================================================
-- museum_metadata_i18n - per-culture overrides for the translatable CCO fields
-- (issue #56). The parent museum_metadata row is the source-culture cache (en);
-- non-en cultures override per-field in this table. Read path COALESCEs
-- (current culture, en fallback, parent value).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `museum_metadata_i18n` (
  `id` INT NOT NULL,
  `culture` VARCHAR(16) NOT NULL,
  `work_type` VARCHAR(50),
  `object_type` VARCHAR(255),
  `classification` VARCHAR(255),
  `materials` TEXT,
  `techniques` TEXT,
  `measurements` VARCHAR(255),
  `dimensions` VARCHAR(255),
  `inscription` TEXT,
  `inscriptions` TEXT,
  `condition_notes` TEXT,
  `provenance` TEXT,
  `style_period` VARCHAR(255),
  `cultural_context` VARCHAR(255),
  `current_location` TEXT,
  `edition_description` TEXT,
  `state_description` VARCHAR(512),
  `state_identification` VARCHAR(100),
  `facture_description` TEXT,
  `technique_cco` VARCHAR(512),
  `technique_qualifier` VARCHAR(255),
  `orientation` VARCHAR(100),
  `physical_appearance` TEXT,
  `color` VARCHAR(255),
  `shape` VARCHAR(255),
  `condition_term` VARCHAR(100),
  `condition_description` TEXT,
  `condition_agent` VARCHAR(255),
  `treatment_type` VARCHAR(255),
  `treatment_agent` VARCHAR(255),
  `treatment_description` TEXT,
  `inscription_transcription` TEXT,
  `inscription_type` VARCHAR(100),
  `inscription_location` VARCHAR(255),
  `inscription_language` VARCHAR(100),
  `inscription_translation` TEXT,
  `mark_type` VARCHAR(100),
  `mark_description` TEXT,
  `mark_location` VARCHAR(255),
  `related_work_type` VARCHAR(100),
  `related_work_relationship` VARCHAR(255),
  `related_work_label` VARCHAR(512),
  `current_location_repository` VARCHAR(512),
  `current_location_geography` VARCHAR(512),
  `current_location_ref_number` VARCHAR(255),
  `creation_place` VARCHAR(512),
  `creation_place_type` VARCHAR(100),
  `discovery_place` VARCHAR(512),
  `discovery_place_type` VARCHAR(100),
  `provenance_text` TEXT,
  `ownership_history` TEXT,
  `legal_status` VARCHAR(255),
  `rights_type` VARCHAR(100),
  `rights_holder` VARCHAR(512),
  `rights_date` VARCHAR(100),
  `rights_remarks` TEXT,
  `cataloger_name` VARCHAR(255),
  `cataloging_institution` VARCHAR(512),
  `cataloging_remarks` TEXT,
  `record_type` VARCHAR(100),
  `record_level` VARCHAR(100),
  `creator_identity` VARCHAR(512),
  `creator_role` VARCHAR(255),
  `creator_extent` VARCHAR(255),
  `creator_qualifier` VARCHAR(255),
  `creator_attribution` VARCHAR(255),
  `creation_date_display` VARCHAR(255),
  `creation_date_qualifier` VARCHAR(100),
  `style` VARCHAR(255),
  `period` VARCHAR(255),
  `cultural_group` VARCHAR(255),
  `movement` VARCHAR(255),
  `school` VARCHAR(255),
  `dynasty` VARCHAR(255),
  `subject_indexing_type` VARCHAR(100),
  `subject_display` TEXT,
  `subject_extent` VARCHAR(255),
  `historical_context` TEXT,
  `architectural_context` TEXT,
  `archaeological_context` TEXT,
  `object_class` VARCHAR(255),
  `object_category` VARCHAR(255),
  `object_sub_category` VARCHAR(255),
  `edition_number` VARCHAR(100),
  `edition_size` VARCHAR(100),
  PRIMARY KEY (`id`, `culture`),
  CONSTRAINT `museum_metadata_i18n_FK_1` FOREIGN KEY (`id`) REFERENCES `museum_metadata` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

SET FOREIGN_KEY_CHECKS = 1;
