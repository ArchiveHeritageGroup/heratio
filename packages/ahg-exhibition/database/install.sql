-- ============================================================================
-- ahg-exhibition — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgExhibitionPlugin/database/install.sql
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
-- ahgExhibitionPlugin - Database Schema
-- Unified Exhibition Management for all GLAM/DAM sectors
-- DO NOT include INSERT INTO atom_plugin
-- =====================================================












-- =====================================================
-- VENUES & GALLERIES
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_venue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_type` VARCHAR(47) COLLATE utf8mb4_unicode_ci DEFAULT 'internal' COMMENT 'internal, partner, external, online',
  `address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `province_state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'South Africa',
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `website` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_square_meters` decimal(10,2) DEFAULT NULL,
  `has_climate_control` tinyint(1) DEFAULT '0',
  `has_security_system` tinyint(1) DEFAULT '0',
  `has_loading_dock` tinyint(1) DEFAULT '0',
  `accessibility_rating` VARCHAR(31) COLLATE utf8mb4_unicode_ci DEFAULT 'partial' COMMENT 'none, partial, full',
  `has_facility_insurance` tinyint(1) DEFAULT '0',
  `insurance_policy_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exhibition_gallery` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `venue_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gallery_type` VARCHAR(68) COLLATE utf8mb4_unicode_ci DEFAULT 'gallery' COMMENT 'gallery, hall, room, corridor, outdoor, foyer, stairwell',
  `floor_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `square_meters` decimal(8,2) DEFAULT NULL,
  `ceiling_height_meters` decimal(4,2) DEFAULT NULL,
  `wall_linear_meters` decimal(8,2) DEFAULT NULL,
  `has_climate_control` tinyint(1) DEFAULT '0',
  `target_temperature` decimal(4,1) DEFAULT NULL,
  `target_humidity` decimal(4,1) DEFAULT NULL,
  `natural_light` tinyint(1) DEFAULT '0',
  `max_lux_level` int DEFAULT NULL,
  `max_visitors` int DEFAULT NULL,
  `max_objects` int DEFAULT NULL,
  `has_display_cases` tinyint(1) DEFAULT '0',
  `number_of_cases` int DEFAULT '0',
  `has_pedestals` tinyint(1) DEFAULT '0',
  `number_of_pedestals` int DEFAULT '0',
  `has_track_lighting` tinyint(1) DEFAULT '0',
  `has_av_equipment` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_gallery_venue` (`venue_id`),
  CONSTRAINT `exhibition_gallery_ibfk_1` FOREIGN KEY (`venue_id`) REFERENCES `exhibition_venue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EXHIBITIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `theme` text COLLATE utf8mb4_unicode_ci,
  `exhibition_type` VARCHAR(59) COLLATE utf8mb4_unicode_ci DEFAULT 'temporary' COMMENT 'permanent, temporary, traveling, online, pop_up',
  `status` VARCHAR(99) COLLATE utf8mb4_unicode_ci DEFAULT 'concept' COMMENT 'concept, planning, preparation, installation, open, closing, closed, archived, canceled',
  `planning_start_date` date DEFAULT NULL,
  `preparation_start_date` date DEFAULT NULL,
  `installation_start_date` date DEFAULT NULL,
  `opening_date` date DEFAULT NULL,
  `closing_date` date DEFAULT NULL,
  `actual_closing_date` date DEFAULT NULL,
  `venue_id` bigint unsigned DEFAULT NULL,
  `venue_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_address` text COLLATE utf8mb4_unicode_ci,
  `venue_city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_external_venue` tinyint(1) DEFAULT '0',
  `gallery_ids` json DEFAULT NULL,
  `total_square_meters` decimal(10,2) DEFAULT NULL,
  `admission_fee` decimal(10,2) DEFAULT NULL,
  `admission_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `is_free_admission` tinyint(1) DEFAULT '0',
  `expected_visitors` int DEFAULT NULL,
  `actual_visitors` int DEFAULT NULL,
  `wheelchair_accessible` tinyint(1) DEFAULT '1',
  `audio_guide_available` tinyint(1) DEFAULT '0',
  `braille_available` tinyint(1) DEFAULT '0',
  `sign_language_tours` tinyint(1) DEFAULT '0',
  `budget_amount` decimal(12,2) DEFAULT NULL,
  `budget_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `actual_cost` decimal(12,2) DEFAULT NULL,
  `total_insurance_value` decimal(15,2) DEFAULT NULL,
  `insurance_policy_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `insurance_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_catalog` tinyint(1) DEFAULT '0',
  `catalog_isbn` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_publication_date` date DEFAULT NULL,
  `has_virtual_tour` tinyint(1) DEFAULT '0',
  `virtual_tour_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `online_exhibition_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curator_id` int DEFAULT NULL,
  `curator_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `designer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organized_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponsored_by` text COLLATE utf8mb4_unicode_ci,
  `project_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `internal_notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_exhibition_status` (`status`),
  KEY `idx_exhibition_type` (`exhibition_type`),
  KEY `idx_exhibition_dates` (`opening_date`,`closing_date`),
  KEY `idx_exhibition_venue` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exhibition_status_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `from_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int DEFAULT NULL,
  `change_reason` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_exhibition` (`exhibition_id`),
  CONSTRAINT `exhibition_status_history_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SECTIONS
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_section` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `narrative` text COLLATE utf8mb4_unicode_ci,
  `section_type` VARCHAR(61) COLLATE utf8mb4_unicode_ci DEFAULT 'gallery' COMMENT 'gallery, room, alcove, corridor, outdoor, virtual',
  `sequence_order` int DEFAULT '0',
  `gallery_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `floor_level` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `square_meters` decimal(8,2) DEFAULT NULL,
  `target_temperature_min` decimal(4,1) DEFAULT NULL,
  `target_temperature_max` decimal(4,1) DEFAULT NULL,
  `target_humidity_min` decimal(4,1) DEFAULT NULL,
  `target_humidity_max` decimal(4,1) DEFAULT NULL,
  `max_lux_level` int DEFAULT NULL,
  `theme` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_scheme` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_audio_guide` tinyint(1) DEFAULT '0',
  `audio_guide_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_video` tinyint(1) DEFAULT '0',
  `has_interactive` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_section_exhibition` (`exhibition_id`),
  KEY `idx_section_order` (`exhibition_id`,`sequence_order`),
  CONSTRAINT `exhibition_section_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- OBJECTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_object` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `section_id` bigint unsigned DEFAULT NULL,
  `information_object_id` int NOT NULL,
  `sequence_order` int DEFAULT '0',
  `display_position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(78) COLLATE utf8mb4_unicode_ci DEFAULT 'proposed' COMMENT 'proposed, confirmed, on_loan_request, installed, removed, returned',
  `requires_loan` tinyint(1) DEFAULT '0',
  `loan_id` bigint unsigned DEFAULT NULL,
  `lender_institution` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_case_required` tinyint(1) DEFAULT '0',
  `mount_required` tinyint(1) DEFAULT '0',
  `mount_description` text COLLATE utf8mb4_unicode_ci,
  `special_lighting` tinyint(1) DEFAULT '0',
  `lighting_notes` text COLLATE utf8mb4_unicode_ci,
  `security_level` VARCHAR(39) COLLATE utf8mb4_unicode_ci DEFAULT 'standard' COMMENT 'standard, enhanced, maximum',
  `climate_controlled` tinyint(1) DEFAULT '0',
  `max_lux_level` int DEFAULT NULL,
  `uv_filtering_required` tinyint(1) DEFAULT '0',
  `rotation_required` tinyint(1) DEFAULT '0',
  `max_display_days` int DEFAULT NULL,
  `display_start_date` date DEFAULT NULL,
  `display_end_date` date DEFAULT NULL,
  `pre_installation_condition_report_id` bigint unsigned DEFAULT NULL,
  `post_exhibition_condition_report_id` bigint unsigned DEFAULT NULL,
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `label_text` text COLLATE utf8mb4_unicode_ci,
  `label_credits` text COLLATE utf8mb4_unicode_ci,
  `extended_label` text COLLATE utf8mb4_unicode_ci,
  `audio_stop_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_notes` text COLLATE utf8mb4_unicode_ci,
  `handling_notes` text COLLATE utf8mb4_unicode_ci,
  `installed_by` int DEFAULT NULL,
  `installed_at` timestamp NULL DEFAULT NULL,
  `removed_by` int DEFAULT NULL,
  `removed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_exobj_exhibition` (`exhibition_id`),
  KEY `idx_exobj_section` (`section_id`),
  KEY `idx_exobj_object` (`information_object_id`),
  KEY `idx_exobj_status` (`status`),
  CONSTRAINT `exhibition_object_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exhibition_object_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `exhibition_section` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- EVENTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` VARCHAR(101) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'opening, closing, tour, lecture, workshop, performance, family, school, vip, press, other',
  `description` text COLLATE utf8mb4_unicode_ci,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `is_recurring` tinyint(1) DEFAULT '0',
  `recurrence_pattern` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_id` bigint unsigned DEFAULT NULL,
  `gallery_id` bigint unsigned DEFAULT NULL,
  `location_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_attendees` int DEFAULT NULL,
  `registered_attendees` int DEFAULT '0',
  `actual_attendees` int DEFAULT NULL,
  `requires_registration` tinyint(1) DEFAULT '0',
  `registration_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `is_free` tinyint(1) DEFAULT '1',
  `ticket_price` decimal(10,2) DEFAULT NULL,
  `ticket_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `presenter_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `presenter_bio` text COLLATE utf8mb4_unicode_ci,
  `status` VARCHAR(53) COLLATE utf8mb4_unicode_ci DEFAULT 'scheduled' COMMENT 'scheduled, confirmed, canceled, completed',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_exhibition` (`exhibition_id`),
  KEY `idx_event_date` (`event_date`),
  CONSTRAINT `exhibition_event_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- CHECKLISTS
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_checklist_template` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `checklist_type` VARCHAR(89) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'planning, preparation, installation, opening, during, closing, deinstallation',
  `items` json DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exhibition_checklist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `template_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `checklist_type` VARCHAR(89) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'planning, preparation, installation, opening, during, closing, deinstallation',
  `due_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `status` VARCHAR(56) COLLATE utf8mb4_unicode_ci DEFAULT 'not_started' COMMENT 'not_started, in_progress, completed, overdue',
  `assigned_to` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_checklist_exhibition` (`exhibition_id`),
  CONSTRAINT `exhibition_checklist_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exhibition_checklist_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `checklist_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `is_completed` tinyint(1) DEFAULT '0',
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `sequence_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_item_checklist` (`checklist_id`),
  CONSTRAINT `exhibition_checklist_item_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `exhibition_checklist` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- STORYLINES
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_storyline` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `narrative_type` VARCHAR(154) COLLATE utf8mb4_unicode_ci DEFAULT 'general' COMMENT 'thematic, chronological, biographical, geographical, technique, custom, general, guided_tour, self_guided, educational, accessible, highlights',
  `introduction` text COLLATE utf8mb4_unicode_ci,
  `body_text` text COLLATE utf8mb4_unicode_ci,
  `conclusion` text COLLATE utf8mb4_unicode_ci,
  `sequence_order` int DEFAULT '0',
  `is_primary` tinyint(1) DEFAULT '0',
  `target_audience` VARCHAR(57) COLLATE utf8mb4_unicode_ci DEFAULT 'all' COMMENT 'general, children, students, specialists, all',
  `reading_level` VARCHAR(41) COLLATE utf8mb4_unicode_ci DEFAULT 'intermediate' COMMENT 'basic, intermediate, advanced',
  `estimated_duration_minutes` int DEFAULT NULL,
  `has_audio` tinyint(1) DEFAULT '0',
  `audio_file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_video` tinyint(1) DEFAULT '0',
  `video_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_storyline_exhibition` (`exhibition_id`),
  CONSTRAINT `exhibition_storyline_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exhibition_storyline_stop` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `storyline_id` bigint unsigned NOT NULL,
  `exhibition_object_id` bigint unsigned NOT NULL,
  `sequence_order` int DEFAULT '0',
  `stop_number` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `narrative_text` text COLLATE utf8mb4_unicode_ci,
  `key_points` text COLLATE utf8mb4_unicode_ci,
  `discussion_questions` text COLLATE utf8mb4_unicode_ci,
  `connection_to_next` text COLLATE utf8mb4_unicode_ci,
  `connection_to_theme` text COLLATE utf8mb4_unicode_ci,
  `audio_transcript` text COLLATE utf8mb4_unicode_ci,
  `audio_duration_seconds` int DEFAULT NULL,
  `suggested_viewing_minutes` int DEFAULT '2',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `exhibition_object_id` (`exhibition_object_id`),
  KEY `idx_stop_storyline` (`storyline_id`),
  KEY `idx_stop_order` (`storyline_id`,`sequence_order`),
  CONSTRAINT `exhibition_storyline_stop_ibfk_1` FOREIGN KEY (`storyline_id`) REFERENCES `exhibition_storyline` (`id`) ON DELETE CASCADE,
  CONSTRAINT `exhibition_storyline_stop_ibfk_2` FOREIGN KEY (`exhibition_object_id`) REFERENCES `exhibition_object` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- MEDIA
-- =====================================================

CREATE TABLE IF NOT EXISTS `exhibition_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exhibition_id` bigint unsigned NOT NULL,
  `section_id` bigint unsigned DEFAULT NULL,
  `media_type` VARCHAR(67) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'image, video, audio, document, floorplan, poster, press',
  `usage_type` VARCHAR(78) COLLATE utf8mb4_unicode_ci DEFAULT 'documentation' COMMENT 'promotional, installation, documentation, press, catalog, internal',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` text COLLATE utf8mb4_unicode_ci,
  `credits` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '1',
  `sequence_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_media_exhibition` (`exhibition_id`),
  CONSTRAINT `exhibition_media_ibfk_1` FOREIGN KEY (`exhibition_id`) REFERENCES `exhibition` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;










SET FOREIGN_KEY_CHECKS = 1;
