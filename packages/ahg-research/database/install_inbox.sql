-- ============================================================================
-- heratio#1228 - Research OS #6: Quick Capture Inbox (ROS Stage 0, epic #1222).
--
-- Frictionless capture - the front door of the research mind. Ideas arrive from
-- anywhere (web quick-note, voice transcription slot, email-in, web clipper,
-- mobile, file upload) and land in an Inbox with a timestamp + origin so nothing
-- is lost. Triage into a project happens later via mark-triaged / archive /
-- move-to-project.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider (Schema::hasTable guard + one outer try/catch,
-- per reference_ci_schema_hastable). VARCHAR + Dropdown Manager values, never
-- MySQL ENUM.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `research_inbox_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `researcher_id` INT NOT NULL,
  `project_id` INT NULL,
  `kind` VARCHAR(32) NOT NULL DEFAULT 'note',          -- note|voice|email|clip|photo|file (research_inbox_kind dropdown)
  `title` VARCHAR(500) NULL,
  `body` TEXT NULL,
  `origin` VARCHAR(32) NOT NULL DEFAULT 'web',          -- web|email-in|clipper|mobile (research_inbox_origin dropdown)
  `source_url` VARCHAR(1000) NULL,
  `attachment_path` VARCHAR(1000) NULL,                 -- relative to config('heratio.storage_path'); never an absolute host path
  `status` VARCHAR(32) NOT NULL DEFAULT 'inbox',        -- inbox|triaged|archived (research_inbox_status dropdown)
  `captured_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rii_researcher_idx` (`researcher_id`),
  KEY `rii_project_idx` (`project_id`),
  KEY `rii_status_idx` (`status`),
  KEY `rii_kind_idx` (`kind`),
  KEY `rii_captured_idx` (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
