-- heratio#1227 - Research OS Stage 5: Source Triage + honest read-status.
--
-- SIDECAR table only. This never ALTERs research_bibliography / research_bibliography_entry
-- / research_collection / research_collection_item - it sits alongside them and keys back to
-- a source by (source_type, source_id). One triage row per (project, source).
--
-- triage_category and read_status are plain VARCHAR (no MySQL ENUM, per project rules); the
-- accepted values live in the service as a dropdown-style allow-list, never hardcoded ENUM.
-- The system NEVER auto-marks read_status = 'read'; only an explicit human action sets it.
CREATE TABLE IF NOT EXISTS `research_source_triage` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `source_type` VARCHAR(40) NOT NULL,        -- 'bibliography_entry' | 'collection_item'
  `source_id` INT NOT NULL,
  `triage_category` VARCHAR(40) NULL,         -- essential | useful | background | ...
  `read_status` VARCHAR(40) NOT NULL DEFAULT 'unread', -- unread | previewed | skimmed | read | deeply-read
  `ai_preview` TEXT NULL,                      -- optional AI structured preview, not human verified
  `ai_preview_at` DATETIME NULL,
  `notes` TEXT NULL,
  `updated_by` INT NULL,                       -- researcher id of the last editor
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rst_unique` (`project_id`, `source_type`, `source_id`),
  KEY `rst_project_idx` (`project_id`),
  KEY `rst_category_idx` (`triage_category`),
  KEY `rst_read_idx` (`read_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
