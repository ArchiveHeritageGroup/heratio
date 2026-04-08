
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_access_decision` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `action_requested` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `decision` varchar(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'permitted, denied',
  `rationale` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evaluated_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_policy` (`policy_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_decision` (`decision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `activity_type` varchar(86) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'class, tour, exhibit, loan, conservation, photography, filming, event, meeting, other',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `organizer_id` int DEFAULT NULL COMMENT 'Researcher ID if registered',
  `organizer_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organizer_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organizer_phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_attendees` int DEFAULT NULL,
  `reading_room_id` int DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `recurring` tinyint(1) DEFAULT '0',
  `recurrence_pattern` json DEFAULT NULL,
  `setup_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `av_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `catering_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `special_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(71) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'requested' COMMENT 'requested, tentative, confirmed, in_progress, completed, cancelled',
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_by` int DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`activity_type`),
  KEY `idx_organizer` (`organizer_id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_date` (`start_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_activity_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `activity_type` varchar(348) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'view, search, download, cite, annotate, collect, book, request, export, share, login, logout, snapshot_created, snapshot_compared, assertion_created, assertion_verified, assertion_disputed, extraction_queued, extraction_completed, validation_accepted, validation_rejected, hypothesis_created, hypothesis_updated, policy_evaluated, doi_minted, create, clipboard_add',
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `entity_title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `session_id` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_type` (`activity_type`),
  KEY `idx_date` (`created_at`),
  KEY `idx_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_activity_material` (
  `id` int NOT NULL AUTO_INCREMENT,
  `activity_id` int NOT NULL,
  `object_id` int NOT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `handling_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `display_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'For exhibits',
  `insurance_value` decimal(15,2) DEFAULT NULL,
  `loan_agreement_signed` tinyint(1) DEFAULT '0',
  `condition_before` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `condition_after` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(71) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'requested' COMMENT 'requested, approved, rejected, retrieved, in_use, returned, damaged',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `retrieved_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_activity_participant` (
  `id` int NOT NULL AUTO_INCREMENT,
  `activity_id` int NOT NULL,
  `researcher_id` int DEFAULT NULL COMMENT 'If registered researcher',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` varchar(78) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'visitor' COMMENT 'organizer, instructor, presenter, student, visitor, assistant, staff, other',
  `dietary_requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `accessibility_needs` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `registration_status` varchar(63) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, confirmed, waitlist, cancelled, attended, no_show',
  `registered_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` datetime DEFAULT NULL,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_activity` (`activity_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_role` (`role`),
  KEY `idx_status` (`registration_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_annotation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT 'information_object',
  `collection_id` int DEFAULT NULL,
  `digital_object_id` int DEFAULT NULL,
  `annotation_type` varchar(51) DEFAULT 'note' COMMENT 'note, highlight, bookmark, tag, transcription',
  `title` varchar(255) DEFAULT NULL,
  `content` text,
  `content_format` varchar(20) DEFAULT 'text' COMMENT 'text, html',
  `target_selector` text,
  `canvas_id` varchar(500) DEFAULT NULL,
  `iiif_annotation_id` varchar(255) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT '1',
  `visibility` varchar(31) DEFAULT 'private' COMMENT 'private, shared, public',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_collection` (`collection_id`),
  FULLTEXT KEY `idx_annotation_fulltext` (`title`,`content`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_annotation_target` (
  `id` int NOT NULL AUTO_INCREMENT,
  `annotation_id` int NOT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int DEFAULT NULL,
  `selector_type` varchar(97) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'TextQuoteSelector, FragmentSelector, SvgSelector, PointSelector, RangeSelector, TimeSelector',
  `selector_json` json DEFAULT NULL,
  `source_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_annotation` (`annotation_id`),
  KEY `idx_source` (`source_type`,`source_id`),
  KEY `idx_selector` (`selector_type`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_annotation_v2` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `motivation` varchar(84) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'commenting' COMMENT 'commenting, describing, classifying, linking, questioning, tagging, highlighting',
  `body_json` json DEFAULT NULL,
  `creator_json` json DEFAULT NULL,
  `generated_json` json DEFAULT NULL,
  `status` varchar(33) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, archived, deleted',
  `visibility` varchar(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'private' COMMENT 'private, shared, public',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_motivation` (`motivation`),
  KEY `idx_status` (`status`),
  KEY `idx_visibility` (`visibility`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_api_key` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permissions` json DEFAULT NULL,
  `rate_limit` int DEFAULT '1000',
  `last_used_at` datetime DEFAULT NULL,
  `request_count` int DEFAULT '0',
  `expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_key` (`api_key`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_api_log` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `api_key_id` int DEFAULT NULL,
  `researcher_id` int DEFAULT NULL,
  `endpoint` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `method` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_params` json DEFAULT NULL,
  `response_code` int DEFAULT NULL,
  `response_time_ms` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_key` (`api_key_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_assertion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `subject_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` int NOT NULL,
  `subject_label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `predicate` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `object_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `object_label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assertion_type` varchar(67) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'biographical, chronological, spatial, relational, attributive',
  `status` varchar(46) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'proposed' COMMENT 'proposed, verified, disputed, retracted',
  `confidence` decimal(5,2) DEFAULT NULL,
  `version` int DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_subject` (`subject_type`,`subject_id`),
  KEY `idx_predicate` (`predicate`),
  KEY `idx_assertion_type` (`assertion_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_assertion_evidence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `assertion_id` int NOT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int NOT NULL,
  `selector_json` json DEFAULT NULL,
  `relationship` varchar(26) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'supports, refutes',
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `added_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assertion` (`assertion_id`),
  KEY `idx_source` (`source_type`,`source_id`),
  KEY `idx_relationship` (`relationship`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_bibliography` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `citation_style` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'chicago',
  `is_public` tinyint(1) DEFAULT '0',
  `share_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_share` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_bibliography_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bibliography_id` int NOT NULL,
  `object_id` int DEFAULT NULL,
  `entry_type` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'archival' COMMENT 'archival, book, article, chapter, thesis, website, other',
  `csl_data` json DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authors` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publisher` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `container_title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issue` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pages` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doi` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accessed_date` date DEFAULT NULL,
  `archive_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archive_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `box` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `folder` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bibliography` (`bibliography_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`entry_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_booking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `reading_room_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text,
  `status` varchar(55) DEFAULT 'pending' COMMENT 'pending, confirmed, cancelled, completed, no_show',
  `confirmed_by` int DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text,
  `checked_in_at` datetime DEFAULT NULL,
  `checked_out_at` datetime DEFAULT NULL,
  `notes` text,
  `is_walk_in` tinyint(1) DEFAULT '0',
  `rules_acknowledged` tinyint(1) DEFAULT '0',
  `rules_acknowledged_at` datetime DEFAULT NULL,
  `seat_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_status` (`status`),
  KEY `idx_booking_seat` (`seat_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_citation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int DEFAULT NULL,
  `object_id` int NOT NULL,
  `citation_style` varchar(50) NOT NULL,
  `citation_text` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14231 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_clipboard_project` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int NOT NULL,
  `object_id` int NOT NULL,
  `is_pinned` tinyint(1) DEFAULT '0',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_object` (`project_id`,`object_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `is_public` tinyint(1) DEFAULT '0',
  `share_token` varchar(64) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_share_token` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_collection_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `collection_id` int NOT NULL,
  `object_id` int NOT NULL,
  `object_type` varchar(50) DEFAULT 'information_object',
  `culture` varchar(10) DEFAULT NULL,
  `external_uri` varchar(1000) DEFAULT NULL,
  `tags` varchar(500) DEFAULT NULL,
  `reference_code` varchar(255) DEFAULT NULL,
  `notes` text,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item` (`collection_id`,`object_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_comment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `entity_type` varchar(67) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'report, report_section, annotation, journal_entry, collection',
  `entity_id` int NOT NULL,
  `parent_id` int DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_resolved` tinyint(1) DEFAULT '0',
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_custody_handoff` (
  `id` int NOT NULL AUTO_INCREMENT,
  `material_request_id` int NOT NULL,
  `handoff_type` varchar(50) NOT NULL COMMENT 'checkout|checkin|transfer|return_to_storage|condition_check',
  `from_handler_id` int DEFAULT NULL COMMENT 'Staff/researcher releasing',
  `to_handler_id` int DEFAULT NULL COMMENT 'Staff/researcher receiving',
  `from_location` varchar(255) DEFAULT NULL,
  `to_location` varchar(255) DEFAULT NULL,
  `condition_at_handoff` varchar(50) DEFAULT NULL COMMENT 'excellent|good|fair|poor|critical',
  `condition_notes` text,
  `signature_confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed_at` datetime DEFAULT NULL,
  `confirmed_by` int DEFAULT NULL,
  `barcode_scanned` varchar(100) DEFAULT NULL,
  `spectrum_movement_id` int DEFAULT NULL COMMENT 'FK to spectrum_movement if created',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`material_request_id`),
  KEY `idx_type` (`handoff_type`),
  KEY `idx_spectrum` (`spectrum_movement_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_discussion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `workspace_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  `researcher_id` int NOT NULL,
  `subject` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_resolved` tinyint(1) DEFAULT '0',
  `resolved_by` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_researcher` (`researcher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_document_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `fields_json` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`document_type`),
  KEY `idx_creator` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_entity_resolution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_a_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_a_id` int NOT NULL,
  `entity_b_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_b_id` int NOT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `match_method` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'proposed' COMMENT 'proposed, accepted, rejected',
  `resolver_id` int DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `evidence_json` json DEFAULT NULL,
  `relationship_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sameAs',
  `proposer_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity_a` (`entity_a_type`,`entity_a_id`),
  KEY `idx_entity_b` (`entity_b_type`,`entity_b_id`),
  KEY `idx_status` (`status`),
  KEY `idx_confidence` (`confidence`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_equipment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reading_room_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equipment_type` varchar(127) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'microfilm_reader, microfiche_reader, scanner, computer, magnifier, book_cradle, light_box, camera_stand, gloves, weights, other',
  `brand` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Where in the reading room',
  `requires_training` tinyint(1) DEFAULT '0',
  `max_booking_hours` int DEFAULT '4',
  `booking_increment_minutes` int DEFAULT '30',
  `condition_status` varchar(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'good' COMMENT 'excellent, good, fair, needs_repair, out_of_service',
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_type` (`equipment_type`),
  KEY `idx_available` (`is_available`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_equipment_booking` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int DEFAULT NULL COMMENT 'Linked reading room booking',
  `researcher_id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(52) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'reserved' COMMENT 'reserved, in_use, returned, cancelled, no_show',
  `checked_out_at` datetime DEFAULT NULL,
  `checked_out_by` int DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `returned_by` int DEFAULT NULL,
  `condition_on_return` varchar(37) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'excellent, good, fair, damaged',
  `return_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_equipment` (`equipment_id`),
  KEY `idx_date` (`booking_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_equipment_maintenance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `equipment_id` int NOT NULL,
  `description` text NOT NULL,
  `condition_before` varchar(57) DEFAULT NULL,
  `condition_after` varchar(57) NOT NULL DEFAULT 'good',
  `next_maintenance_date` date DEFAULT NULL,
  `performed_by` int DEFAULT NULL,
  `performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_equipment` (`equipment_id`),
  KEY `idx_performed_at` (`performed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_external_collaborator` (
  `id` int NOT NULL AUTO_INCREMENT,
  `share_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `institution` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orcid_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `access_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'viewer' COMMENT 'viewer, contributor',
  `last_accessed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_token` (`access_token`),
  KEY `idx_share` (`share_id`),
  KEY `idx_email` (`email`),
  KEY `idx_token` (`access_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_extraction_job` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `collection_id` int DEFAULT NULL,
  `researcher_id` int NOT NULL,
  `extraction_type` varchar(79) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ocr, ner, summarize, translate, spellcheck, face_detection, form_extraction',
  `parameters_json` json DEFAULT NULL,
  `status` varchar(41) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'queued' COMMENT 'queued, running, completed, failed',
  `progress` int DEFAULT '0',
  `total_items` int DEFAULT '0',
  `processed_items` int DEFAULT '0',
  `error_log` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_collection` (`collection_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_type` (`extraction_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_extraction_result` (
  `id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `object_id` int NOT NULL,
  `result_type` varchar(66) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'entity, summary, translation, transcription, form_field, face',
  `data_json` json DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `model_version` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_hash` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job` (`job_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`result_type`),
  KEY `idx_confidence` (`confidence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_hypothesis` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `statement` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'proposed' COMMENT 'proposed, testing, supported, refuted',
  `evidence_count` int DEFAULT '0',
  `tags` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_hypothesis_evidence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hypothesis_id` int NOT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_id` int NOT NULL,
  `relationship` varchar(34) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'supports, refutes, neutral',
  `confidence` decimal(5,2) DEFAULT NULL,
  `note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `added_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hypothesis` (`hypothesis_id`),
  KEY `idx_source` (`source_type`,`source_id`),
  KEY `idx_relationship` (`relationship`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_institution` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_institutional_share` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `institution_id` int DEFAULT NULL,
  `share_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'view' COMMENT 'view, contribute, full',
  `shared_by` int NOT NULL,
  `accepted_by` int DEFAULT NULL,
  `status` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, active, revoked, expired',
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `permissions` json DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_token` (`share_token`),
  KEY `idx_project` (`project_id`),
  KEY `idx_institution` (`institution_id`),
  KEY `idx_token` (`share_token`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_journal_entry` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `entry_date` date NOT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'html' COMMENT 'text, html',
  `entry_type` varchar(108) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'manual' COMMENT 'manual, auto_booking, auto_material, auto_annotation, auto_search, auto_collection, reflection, milestone',
  `time_spent_minutes` int DEFAULT NULL,
  `tags` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_private` tinyint(1) DEFAULT '1',
  `related_entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_entity_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_date` (`entry_date`),
  FULLTEXT KEY `idx_journal_fulltext` (`title`,`content`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_map_point` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `place_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_valid_from` date DEFAULT NULL,
  `date_valid_to` date DEFAULT NULL,
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_coords` (`latitude`,`longitude`),
  KEY `idx_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_material_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `object_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `notes` text,
  `request_type` varchar(54) DEFAULT 'reading_room' COMMENT 'reading_room, reproduction, loan, remote_access',
  `priority` varchar(26) DEFAULT 'normal' COMMENT 'normal, high, rush',
  `handling_instructions` text,
  `location_code` varchar(100) DEFAULT NULL,
  `shelf_location` varchar(255) DEFAULT NULL,
  `location_current` varchar(255) DEFAULT NULL COMMENT 'Current location: storage/transit/reading_room/returned',
  `retrieval_scheduled_for` datetime DEFAULT NULL,
  `queue_id` int DEFAULT NULL,
  `box_number` varchar(50) DEFAULT NULL,
  `folder_number` varchar(50) DEFAULT NULL,
  `curatorial_approval_required` tinyint(1) DEFAULT '0',
  `curatorial_approved_by` int DEFAULT NULL,
  `curatorial_approved_at` datetime DEFAULT NULL,
  `paging_slip_printed` tinyint(1) DEFAULT '0',
  `call_slip_printed_at` datetime DEFAULT NULL,
  `call_slip_printed_by` int DEFAULT NULL,
  `status` varchar(67) DEFAULT 'requested' COMMENT 'requested, retrieved, delivered, in_use, returned, unavailable',
  `retrieved_by` int DEFAULT NULL,
  `retrieved_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  `condition_notes` text,
  `sla_due_date` date DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `triage_status` varchar(50) DEFAULT NULL,
  `triage_by` int DEFAULT NULL,
  `checkout_confirmed_at` datetime DEFAULT NULL,
  `checkout_confirmed_by` int DEFAULT NULL,
  `return_condition` varchar(50) DEFAULT NULL,
  `return_verified_by` int DEFAULT NULL,
  `return_verified_at` datetime DEFAULT NULL,
  `triage_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_request_queue` (`queue_id`),
  KEY `idx_request_retrieval` (`retrieval_scheduled_for`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `type` varchar(70) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'alert, invitation, comment, reply, system, reminder, collaboration',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `related_entity_id` int DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_read` (`is_read`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_notification_preference` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `notification_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_enabled` tinyint(1) DEFAULT '1',
  `in_app_enabled` tinyint(1) DEFAULT '1',
  `digest_frequency` varchar(37) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'immediate' COMMENT 'immediate, daily, weekly, none',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_researcher_type` (`researcher_id`,`notification_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_password_reset` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_peer_review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `requested_by` int NOT NULL,
  `reviewer_id` int NOT NULL,
  `status` varchar(48) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, in_progress, completed, declined',
  `feedback` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rating` int DEFAULT NULL,
  `requested_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_print_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_type` varchar(65) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'call_slip, paging_slip, receipt, badge, label, report, letter',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `template_html` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `css_styles` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `page_size` varchar(55) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'a4' COMMENT 'a4, a5, letter, label_4x6, label_2x4, badge, custom',
  `orientation` varchar(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'portrait' COMMENT 'portrait, landscape',
  `margin_top` int DEFAULT '10',
  `margin_right` int DEFAULT '10',
  `margin_bottom` int DEFAULT '10',
  `margin_left` int DEFAULT '10',
  `copies_default` int DEFAULT '1',
  `variables` json DEFAULT NULL COMMENT 'Available template variables',
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_type` (`template_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_project` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `project_type` varchar(103) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'personal' COMMENT 'thesis, dissertation, publication, exhibition, documentary, genealogy, institutional, personal, other',
  `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supervisor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `funding_source` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grant_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ethics_approval` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expected_end_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `status` varchar(52) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planning' COMMENT 'planning, active, on_hold, completed, archived',
  `visibility` varchar(38) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'private' COMMENT 'private, collaborators, public',
  `share_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`project_type`),
  KEY `idx_share` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_project_collaborator` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `role` varchar(41) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'contributor' COMMENT 'owner, editor, contributor, viewer',
  `permissions` json DEFAULT NULL,
  `invited_by` int DEFAULT NULL,
  `invited_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` datetime DEFAULT NULL,
  `status` varchar(43) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, accepted, declined, removed',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_researcher` (`project_id`,`researcher_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_project_milestone` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `due_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int DEFAULT NULL,
  `status` varchar(49) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`),
  KEY `idx_due` (`due_date`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_project_resource` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `resource_type` varchar(92) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'collection, saved_search, annotation, bibliography, object, external_link, document, note',
  `resource_id` int DEFAULT NULL,
  `object_id` int DEFAULT NULL,
  `external_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link_type` varchar(73) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'academic, archive, database, government, website, social_media, other',
  `link_metadata` json DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tags` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `added_by` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_type` (`resource_type`),
  KEY `idx_object` (`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_quality_metric` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `metric_type` varchar(78) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ocr_confidence, image_quality, digitisation_completeness, fixity_status',
  `metric_value` decimal(10,4) NOT NULL,
  `source_service` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_data_json` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_type` (`metric_type`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_reading_room` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(20) DEFAULT NULL,
  `description` text,
  `amenities` text,
  `capacity` int DEFAULT '10',
  `has_seat_management` tinyint(1) DEFAULT '0' COMMENT 'Enable individual seat tracking',
  `walk_ins_allowed` tinyint(1) DEFAULT '1',
  `walk_in_capacity` int DEFAULT '5',
  `floor_plan_path` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `operating_hours` text,
  `rules` text,
  `advance_booking_days` int DEFAULT '14',
  `max_booking_hours` int DEFAULT '4',
  `cancellation_hours` int DEFAULT '24',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `opening_time` time DEFAULT '09:00:00',
  `closing_time` time DEFAULT '17:00:00',
  `days_open` varchar(50) DEFAULT 'Mon,Tue,Wed,Thu,Fri',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_reading_room_seat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reading_room_id` int NOT NULL,
  `seat_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `seat_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Display name like "Table A - Seat 1"',
  `seat_type` varchar(69) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'standard' COMMENT 'standard, accessible, computer, microfilm, oversize, quiet, group',
  `has_power` tinyint(1) DEFAULT '1',
  `has_lamp` tinyint(1) DEFAULT '1',
  `has_computer` tinyint(1) DEFAULT '0',
  `has_magnifier` tinyint(1) DEFAULT '0',
  `position_x` int DEFAULT NULL COMMENT 'For visual seat map',
  `position_y` int DEFAULT NULL COMMENT 'For visual seat map',
  `zone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Reading room zone/section',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_room_seat` (`reading_room_id`,`seat_number`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_type` (`seat_type`),
  KEY `idx_active` (`is_active`),
  KEY `idx_zone` (`zone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_report` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_type` varchar(85) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'custom' COMMENT 'research_summary, genealogical, historical, source_analysis, finding_aid, custom',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(53) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT 'draft, in_progress, review, completed, archived',
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_report_section` (
  `id` int NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `section_type` varchar(98) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text' COMMENT 'title_page, toc, heading, text, bibliography, collection_list, annotation_list, timeline, custom',
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `content_format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'html' COMMENT 'text, html',
  `bibliography_id` int DEFAULT NULL,
  `collection_id` int DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report` (`report_id`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_report_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sections_config` json NOT NULL,
  `is_system` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_reproduction_file` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint DEFAULT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `download_count` int DEFAULT '0',
  `download_expires_at` datetime DEFAULT NULL,
  `download_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_token` (`download_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_reproduction_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `object_id` int NOT NULL,
  `digital_object_id` int DEFAULT NULL,
  `reproduction_type` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'scan' COMMENT 'photocopy, scan, photograph, digital_copy, digital_scan, transcription, certification, certified_copy, microfilm, other',
  `format` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'PDF',
  `resolution` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color_mode` varchar(28) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'grayscale' COMMENT 'color, grayscale, bw',
  `quantity` int DEFAULT '1',
  `page_range` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `special_instructions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` varchar(49) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled',
  `completed_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_reproduction_request` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `reference_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `intended_use` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'personal',
  `publication_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(87) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT 'draft, submitted, processing, awaiting_payment, in_production, completed, cancelled',
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `final_cost` decimal(10,2) DEFAULT NULL,
  `currency` varchar(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ZAR',
  `payment_reference` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `delivery_method` varchar(69) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'email' COMMENT 'email, download, post, collect, digital, pickup, courier, physical',
  `delivery_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `delivery_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `processed_by` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `admin_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `triage_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `triage_by` int DEFAULT NULL,
  `triage_at` datetime DEFAULT NULL,
  `triage_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sla_due_date` date DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `closed_by` int DEFAULT NULL,
  `closure_reason` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reference` (`reference_number`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_request_correspondence` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `request_type` varchar(50) NOT NULL DEFAULT 'material' COMMENT 'material|reproduction',
  `sender_type` varchar(50) NOT NULL DEFAULT 'staff' COMMENT 'staff|researcher',
  `sender_id` int DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `is_internal` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Staff-only note, hidden from researcher',
  `attachment_path` varchar(500) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`,`request_type`),
  KEY `idx_sender` (`sender_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_request_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `queue_type` varchar(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'retrieval' COMMENT 'retrieval, paging, return, curatorial, reproduction',
  `filter_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Comma-separated statuses to include',
  `filter_room_id` int DEFAULT NULL,
  `filter_priority` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_field` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'created_at',
  `sort_direction` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ASC' COMMENT 'ASC, DESC',
  `auto_assign` tinyint(1) DEFAULT '0',
  `assigned_staff_id` int DEFAULT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3498db' COMMENT 'Queue display color',
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'box',
  `is_default` tinyint(1) DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_type` (`queue_type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_request_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `request_id` int NOT NULL,
  `request_type` varchar(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'material' COMMENT 'material, reproduction',
  `old_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`,`request_type`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_researcher` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `affiliation_type` varchar(63) DEFAULT 'independent' COMMENT 'academic, government, private, independent, student, other',
  `institution` varchar(255) DEFAULT NULL,
  `institution_id` int DEFAULT NULL,
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `research_interests` text,
  `current_project` text,
  `orcid_id` varchar(50) DEFAULT NULL,
  `orcid_verified` tinyint(1) DEFAULT '0',
  `orcid_access_token` text,
  `orcid_refresh_token` text,
  `orcid_token_expires_at` datetime DEFAULT NULL,
  `researcher_id_wos` varchar(50) DEFAULT NULL,
  `scopus_id` varchar(50) DEFAULT NULL,
  `isni` varchar(50) DEFAULT NULL,
  `researcher_type_id` int DEFAULT NULL,
  `timezone` varchar(50) DEFAULT 'Africa/Johannesburg',
  `preferred_language` varchar(10) DEFAULT 'en',
  `api_key` varchar(64) DEFAULT NULL,
  `api_key_expires_at` datetime DEFAULT NULL,
  `id_type` varchar(65) DEFAULT NULL COMMENT 'passport, national_id, drivers_license, student_card, other',
  `id_number` varchar(100) DEFAULT NULL,
  `id_verified` tinyint(1) DEFAULT '0',
  `id_verified_by` int DEFAULT NULL,
  `id_verified_at` datetime DEFAULT NULL,
  `status` varchar(53) DEFAULT 'pending' COMMENT 'pending, approved, suspended, expired, rejected',
  `rejection_reason` text,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `renewal_reminder_sent` tinyint(1) DEFAULT '0',
  `notes` text,
  `photo_path` varchar(500) DEFAULT NULL,
  `card_number` varchar(50) DEFAULT NULL,
  `card_barcode` varchar(100) DEFAULT NULL,
  `card_issued_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_researcher_audit` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_id` int NOT NULL,
  `user_id` int NOT NULL,
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `affiliation_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `research_interests` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `current_project` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `orcid_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Status at time of archival',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `archived_by` int DEFAULT NULL,
  `archived_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `original_created_at` datetime DEFAULT NULL,
  `original_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_researcher_type` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_booking_days_advance` int DEFAULT '14',
  `max_booking_hours_per_day` int DEFAULT '4',
  `max_materials_per_booking` int DEFAULT '10',
  `can_remote_access` tinyint(1) DEFAULT '0',
  `can_request_reproductions` tinyint(1) DEFAULT '1',
  `can_export_data` tinyint(1) DEFAULT '1',
  `requires_id_verification` tinyint(1) DEFAULT '1',
  `auto_approve` tinyint(1) DEFAULT '0',
  `expiry_months` int DEFAULT '12',
  `priority_level` int DEFAULT '5',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '100',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_researcher_type_i18n` (
  `id` int NOT NULL,
  `culture` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`,`culture`),
  CONSTRAINT `fk_researcher_type_i18n` FOREIGN KEY (`id`) REFERENCES `research_researcher_type` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_retrieval_schedule` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reading_room_id` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g., "Morning Retrieval", "Afternoon Retrieval"',
  `day_of_week` tinyint DEFAULT NULL COMMENT '0=Sunday, 6=Saturday, NULL=all days',
  `retrieval_time` time NOT NULL,
  `cutoff_minutes_before` int DEFAULT '30' COMMENT 'Minutes before retrieval time to stop accepting requests',
  `max_items_per_run` int DEFAULT '50',
  `storage_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Which storage area this schedule serves',
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_day` (`day_of_week`),
  KEY `idx_time` (`retrieval_time`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_rights_policy` (
  `id` int NOT NULL AUTO_INCREMENT,
  `target_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` int NOT NULL,
  `policy_type` varchar(43) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'permission, prohibition, obligation',
  `action_type` varchar(57) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'use, reproduce, distribute, modify, archive, display',
  `constraints_json` json DEFAULT NULL,
  `policy_json` json DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target` (`target_type`,`target_id`),
  KEY `idx_policy_type` (`policy_type`),
  KEY `idx_action` (`action_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_room` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `status` varchar(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft' COMMENT 'draft, active, archived',
  `created_by` int NOT NULL,
  `max_participants` int DEFAULT '10',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_room_manifest` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `object_id` int NOT NULL,
  `manifest_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `derivative_type` varchar(31) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'full' COMMENT 'full, subset, annotated',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`room_id`),
  CONSTRAINT `research_room_manifest_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `research_room` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_room_participant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint unsigned NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(29) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'viewer' COMMENT 'owner, editor, viewer',
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_room_user` (`room_id`,`user_id`),
  CONSTRAINT `research_room_participant_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `research_room` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_saved_search` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `search_query` text NOT NULL,
  `search_filters` text,
  `query_ast_json` json DEFAULT NULL,
  `result_snapshot_json` json DEFAULT NULL,
  `citation_id` varchar(100) DEFAULT NULL,
  `last_result_count` int DEFAULT NULL,
  `search_type` varchar(50) DEFAULT 'informationobject',
  `total_results_at_save` int DEFAULT NULL,
  `facets` json DEFAULT NULL,
  `alert_enabled` tinyint(1) DEFAULT '0',
  `alert_frequency` varchar(30) DEFAULT 'weekly' COMMENT 'daily, weekly, monthly',
  `last_alert_at` datetime DEFAULT NULL,
  `new_results_count` int DEFAULT '0',
  `is_public` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_search_alert_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `saved_search_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `previous_count` int DEFAULT NULL,
  `new_count` int DEFAULT NULL,
  `new_items_count` int DEFAULT NULL,
  `notification_sent` tinyint(1) DEFAULT '0',
  `notification_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search` (`saved_search_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_seat_assignment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `booking_id` int NOT NULL,
  `seat_id` int NOT NULL,
  `assigned_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `released_by` int DEFAULT NULL,
  `status` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'assigned' COMMENT 'assigned, occupied, released, no_show',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_booking_seat` (`booking_id`,`seat_id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_seat` (`seat_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date` (`assigned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_snapshot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `hash_sha256` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `query_state_json` json DEFAULT NULL,
  `rights_state_json` json DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `item_count` int DEFAULT '0',
  `status` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT 'active, frozen, archived',
  `frozen_at` datetime DEFAULT NULL,
  `citation_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`),
  KEY `idx_hash` (`hash_sha256`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_snapshot_item` (
  `id` int NOT NULL AUTO_INCREMENT,
  `snapshot_id` int NOT NULL,
  `object_id` int NOT NULL,
  `object_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'information_object',
  `culture` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata_version_json` json DEFAULT NULL,
  `rights_snapshot_json` json DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_object` (`object_id`,`object_type`),
  KEY `idx_sort` (`snapshot_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_source_assessment` (
  `id` int NOT NULL AUTO_INCREMENT,
  `object_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `source_type` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'primary, secondary, tertiary',
  `source_form` varchar(62) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'original' COMMENT 'born_digital, scan, original, transcription, translation',
  `completeness` varchar(58) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'complete' COMMENT 'complete, partial, fragment, missing_pages, redacted',
  `trust_score` int DEFAULT NULL,
  `rationale` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `bias_context` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `assessed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_object` (`object_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_source_type` (`source_type`),
  KEY `idx_trust` (`trust_score`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_statistics_daily` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stat_date` date NOT NULL,
  `stat_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `dimension` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimension_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_value` int DEFAULT '0',
  `sum_value` decimal(15,2) DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_stat` (`stat_date`,`stat_type`,`dimension`,`dimension_value`),
  KEY `idx_date` (`stat_date`),
  KEY `idx_type` (`stat_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_timeline_event` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `label` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `date_start` date NOT NULL,
  `date_end` date DEFAULT NULL,
  `date_type` varchar(46) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'event' COMMENT 'event, creation, accession, publication',
  `source_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` int DEFAULT NULL,
  `position` int DEFAULT '0',
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_dates` (`date_start`,`date_end`),
  KEY `idx_source` (`source_type`,`source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_validation_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `result_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `status` varchar(44) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, accepted, rejected, modified',
  `modified_data_json` json DEFAULT NULL,
  `reviewer_id` int DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_result` (`result_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`),
  KEY `idx_reviewer` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_verification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `researcher_id` int NOT NULL,
  `verification_type` varchar(113) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'id_document, institutional_letter, institutional_email, orcid, staff_approval, professional_membership, other',
  `document_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_reference` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `document_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_data` json DEFAULT NULL,
  `status` varchar(43) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, verified, rejected, expired',
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`),
  KEY `idx_type` (`verification_type`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_walk_in_visitor` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reading_room_id` int NOT NULL,
  `visit_date` date NOT NULL,
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_type` varchar(65) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'passport, national_id, drivers_license, student_card, other',
  `id_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `organization` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `research_topic` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `rules_acknowledged` tinyint(1) DEFAULT '0',
  `rules_acknowledged_at` datetime DEFAULT NULL,
  `photo_permission` tinyint(1) DEFAULT '0',
  `converted_to_researcher_id` int DEFAULT NULL COMMENT 'If they registered',
  `seat_id` int DEFAULT NULL,
  `check_in_time` time NOT NULL,
  `check_out_time` time DEFAULT NULL,
  `checked_in_by` int DEFAULT NULL,
  `checked_out_by` int DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_room` (`reading_room_id`),
  KEY `idx_date` (`visit_date`),
  KEY `idx_email` (`email`),
  KEY `idx_converted` (`converted_to_researcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_workspace` (
  `id` int NOT NULL AUTO_INCREMENT,
  `owner_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `visibility` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'private' COMMENT 'private, members, public',
  `share_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_owner` (`owner_id`),
  KEY `idx_visibility` (`visibility`),
  KEY `idx_share` (`share_token`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_workspace_member` (
  `id` int NOT NULL AUTO_INCREMENT,
  `workspace_id` int NOT NULL,
  `researcher_id` int NOT NULL,
  `role` varchar(54) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'viewer' COMMENT 'owner, admin, editor, viewer, member, contributor',
  `invited_by` int DEFAULT NULL,
  `invited_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` datetime DEFAULT NULL,
  `status` varchar(43) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT 'pending, accepted, declined, removed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_workspace_researcher` (`workspace_id`,`researcher_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `research_workspace_resource` (
  `id` int NOT NULL AUTO_INCREMENT,
  `workspace_id` int NOT NULL,
  `resource_type` varchar(68) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'collection, project, bibliography, saved_search, document, link',
  `resource_id` int DEFAULT NULL,
  `external_url` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `added_by` int DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `added_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_type` (`resource_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `researcher_submission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `researcher_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `repository_id` int DEFAULT NULL,
  `parent_object_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `source_type` varchar(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online' COMMENT 'online, offline',
  `source_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `include_images` tinyint(1) DEFAULT '1',
  `status` varchar(75) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT 'draft, submitted, under_review, approved, published, returned, rejected',
  `workflow_task_id` int DEFAULT NULL,
  `total_items` int DEFAULT '0',
  `total_files` int DEFAULT '0',
  `total_file_size` bigint unsigned DEFAULT '0',
  `return_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reject_comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `published_at` datetime DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `researcher_submission_file` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint unsigned DEFAULT '0',
  `checksum` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caption` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sort_order` int DEFAULT '0',
  `published_do_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_item` (`item_id`),
  CONSTRAINT `researcher_submission_file_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `researcher_submission_item` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `researcher_submission_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` bigint unsigned NOT NULL,
  `parent_item_id` bigint unsigned DEFAULT NULL,
  `item_type` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'description' COMMENT 'description, note, repository, creator',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_of_description` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'item',
  `scope_and_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `extent_and_medium` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_display` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` date DEFAULT NULL,
  `creators` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subjects` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `places` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `genres` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `access_conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `reproduction_conditions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `repository_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repository_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `repository_contact` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_object_id` int DEFAULT NULL,
  `reference_slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `published_object_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`submission_id`),
  KEY `idx_parent` (`parent_item_id`),
  KEY `idx_type` (`item_type`),
  CONSTRAINT `researcher_submission_item_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `researcher_submission` (`id`) ON DELETE CASCADE,
  CONSTRAINT `researcher_submission_item_ibfk_2` FOREIGN KEY (`parent_item_id`) REFERENCES `researcher_submission_item` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE IF NOT EXISTS `researcher_submission_review` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `submission_id` bigint unsigned NOT NULL,
  `reviewer_id` int NOT NULL,
  `action` varchar(47) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'comment, return, approve, reject, publish',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_submission` (`submission_id`),
  CONSTRAINT `researcher_submission_review_ibfk_1` FOREIGN KEY (`submission_id`) REFERENCES `researcher_submission` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

