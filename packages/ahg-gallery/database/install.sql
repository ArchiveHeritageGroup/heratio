-- ============================================================================
-- ahg-gallery — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgGalleryPlugin/database/install.sql
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
-- ahgGalleryPlugin - Database Schema
-- Gallery-specific: Artists, Loans, Valuations, Spaces
-- Exhibitions are managed by ahgExhibitionPlugin
-- DO NOT include INSERT INTO atom_plugin
-- =====================================================













-- =====================================================
-- ARTISTS
-- =====================================================


CREATE TABLE IF NOT EXISTS `gallery_artist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actor_id` int DEFAULT NULL,
  `display_name` varchar(255) NOT NULL,
  `sort_name` varchar(255) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(255) DEFAULT NULL,
  `death_date` date DEFAULT NULL,
  `death_place` varchar(255) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `artist_type` VARCHAR(53) DEFAULT 'individual' COMMENT 'individual, collective, studio, anonymous',
  `medium_specialty` text,
  `movement_style` text,
  `active_period` varchar(100) DEFAULT NULL,
  `represented` tinyint(1) DEFAULT '0',
  `representation_start` date DEFAULT NULL,
  `representation_end` date DEFAULT NULL,
  `representation_terms` text,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `exclusivity` tinyint(1) DEFAULT '0',
  `biography` text,
  `artist_statement` text,
  `cv` text,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `studio_address` text,
  `instagram` varchar(100) DEFAULT NULL,
  `twitter` varchar(100) DEFAULT NULL,
  `facebook` varchar(255) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_actor` (`actor_id`),
  KEY `idx_name` (`display_name`),
  KEY `idx_represented` (`represented`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE IF NOT EXISTS `gallery_artist_bibliography` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `entry_type` VARCHAR(84) DEFAULT 'article' COMMENT 'book, catalog, article, review, interview, thesis, website, video, other',
  `title` varchar(500) NOT NULL,
  `author` varchar(255) DEFAULT NULL,
  `publication` varchar(255) DEFAULT NULL,
  `publisher` varchar(255) DEFAULT NULL,
  `publication_date` date DEFAULT NULL,
  `volume` varchar(50) DEFAULT NULL,
  `issue` varchar(50) DEFAULT NULL,
  `pages` varchar(50) DEFAULT NULL,
  `url` varchar(500) DEFAULT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_type` (`entry_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE IF NOT EXISTS `gallery_artist_exhibition_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `artist_id` int NOT NULL,
  `exhibition_type` VARCHAR(51) DEFAULT 'group' COMMENT 'solo, group, duo, retrospective, survey',
  `title` varchar(255) NOT NULL,
  `venue` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `curator` varchar(255) DEFAULT NULL,
  `catalog_published` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_artist` (`artist_id`),
  KEY `idx_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================
-- LOANS
-- =====================================================


CREATE TABLE IF NOT EXISTS `gallery_loan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_number` varchar(50) NOT NULL,
  `loan_type` VARCHAR(30) NOT NULL COMMENT 'incoming, outgoing',
  `status` VARCHAR(123) DEFAULT 'inquiry' COMMENT 'inquiry, requested, approved, agreed, in_transit_out, on_loan, in_transit_return, returned, cancelled, declined',
  `purpose` varchar(255) DEFAULT NULL,
  `exhibition_id` int DEFAULT NULL,
  `institution_name` varchar(255) NOT NULL,
  `institution_address` text,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `request_date` date DEFAULT NULL,
  `approval_date` date DEFAULT NULL,
  `loan_start_date` date DEFAULT NULL,
  `loan_end_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `loan_fee` decimal(12,2) DEFAULT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `insurance_provider` varchar(255) DEFAULT NULL,
  `insurance_policy_number` varchar(100) DEFAULT NULL,
  `special_conditions` text,
  `agreement_signed` tinyint(1) DEFAULT '0',
  `agreement_date` date DEFAULT NULL,
  `facility_report_received` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `loan_number` (`loan_number`),
  KEY `idx_type` (`loan_type`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`loan_start_date`,`loan_end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE IF NOT EXISTS `gallery_loan_object` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `object_id` int NOT NULL,
  `insurance_value` decimal(12,2) DEFAULT NULL,
  `condition_out` text,
  `condition_out_date` date DEFAULT NULL,
  `condition_out_by` int DEFAULT NULL,
  `condition_return` text,
  `condition_return_date` date DEFAULT NULL,
  `condition_return_by` int DEFAULT NULL,
  `packing_instructions` text,
  `display_requirements` text,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE IF NOT EXISTS `gallery_facility_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `loan_id` int NOT NULL,
  `report_type` VARCHAR(30) NOT NULL COMMENT 'incoming, outgoing',
  `institution_name` varchar(255) DEFAULT NULL,
  `building_age` int DEFAULT NULL,
  `construction_type` varchar(100) DEFAULT NULL,
  `fire_detection` tinyint(1) DEFAULT '0',
  `fire_suppression` tinyint(1) DEFAULT '0',
  `security_24hr` tinyint(1) DEFAULT '0',
  `security_guards` tinyint(1) DEFAULT '0',
  `cctv` tinyint(1) DEFAULT '0',
  `intrusion_detection` tinyint(1) DEFAULT '0',
  `climate_controlled` tinyint(1) DEFAULT '0',
  `temperature_range` varchar(50) DEFAULT NULL,
  `humidity_range` varchar(50) DEFAULT NULL,
  `light_levels` varchar(100) DEFAULT NULL,
  `uv_filtering` tinyint(1) DEFAULT '0',
  `trained_handlers` tinyint(1) DEFAULT '0',
  `loading_dock` tinyint(1) DEFAULT '0',
  `freight_elevator` tinyint(1) DEFAULT '0',
  `storage_available` tinyint(1) DEFAULT '0',
  `insurance_coverage` varchar(255) DEFAULT NULL,
  `completed_by` varchar(255) DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `approved` tinyint(1) DEFAULT '0',
  `approved_by` int DEFAULT NULL,
  `approved_date` date DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loan` (`loan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================
-- VALUATIONS & INSURANCE
-- =====================================================


CREATE TABLE IF NOT EXISTS `gallery_valuation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `valuation_type` VARCHAR(79) DEFAULT 'insurance' COMMENT 'insurance, market, replacement, auction_estimate, probate, donation',
  `value_amount` decimal(14,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'ZAR',
  `valuation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `appraiser_name` varchar(255) DEFAULT NULL,
  `appraiser_credentials` varchar(255) DEFAULT NULL,
  `appraiser_organization` varchar(255) DEFAULT NULL,
  `methodology` text,
  `comparables` text,
  `notes` text,
  `document_path` varchar(500) DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`valuation_type`),
  KEY `idx_current` (`is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




CREATE TABLE IF NOT EXISTS `gallery_insurance_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_number` varchar(100) NOT NULL,
  `provider` varchar(255) NOT NULL,
  `policy_type` VARCHAR(77) DEFAULT 'all_risk' COMMENT 'all_risk, named_perils, transit, exhibition, permanent_collection',
  `coverage_amount` decimal(14,2) DEFAULT NULL,
  `deductible` decimal(12,2) DEFAULT NULL,
  `premium` decimal(12,2) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `contact_name` varchar(255) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- =====================================================
-- SPACES (references exhibition_venue from ahgExhibitionPlugin)
-- =====================================================


CREATE TABLE IF NOT EXISTS `gallery_space` (
  `id` int NOT NULL AUTO_INCREMENT,
  `venue_id` int NOT NULL COMMENT 'References exhibition_venue.id',
  `name` varchar(255) NOT NULL,
  `description` text,
  `area_sqm` decimal(10,2) DEFAULT NULL,
  `wall_length_m` decimal(10,2) DEFAULT NULL,
  `height_m` decimal(10,2) DEFAULT NULL,
  `lighting_type` varchar(100) DEFAULT NULL,
  `climate_controlled` tinyint(1) DEFAULT '0',
  `max_weight_kg` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venue` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;












-- =====================================================
-- Taxonomy Term for Gallery sector
-- =====================================================
SET @gallery_exists = (SELECT COUNT(*) FROM term WHERE code = 'gallery' AND taxonomy_id = 70);
INSERT IGNORE INTO object (class_name, created_at, updated_at)
SELECT 'QubitTerm', NOW(), NOW() FROM DUAL WHERE @gallery_exists = 0;
SET @gallery_id = LAST_INSERT_ID();
INSERT IGNORE INTO term (id, taxonomy_id, code, source_culture)
SELECT @gallery_id, 70, 'gallery', 'en' FROM DUAL WHERE @gallery_exists = 0 AND @gallery_id > 0;
INSERT IGNORE INTO term_i18n (id, culture, name)
SELECT @gallery_id, 'en', 'Gallery (Spectrum 5.0)' FROM DUAL WHERE @gallery_exists = 0 AND @gallery_id > 0;

SET FOREIGN_KEY_CHECKS = 1;
