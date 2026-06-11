-- heratio#1235 - Research OS Stage 3: per-project Living Field Alerts.
--
-- Watches the works a project cites (by DOI) and surfaces alerts when one of
-- them is RETRACTED, has been UPDATED (correction/erratum/new version), or has
-- a NEW RELATED work the researcher should know about. The cited DOIs are read
-- from the project's bibliography entries (research_bibliography_entry.doi via
-- research_bibliography.project_id) read-only; researchers may also add a watch
-- by hand. A daily console command (ahg:research-field-alerts) polls the PUBLIC
-- scholarly APIs Crossref (https://api.crossref.org) and OpenAlex
-- (https://api.openalex.org) over Laravel's Http client - these are public
-- bibliographic services, NOT AI services, so they are called directly (never
-- through the AHG AI gateway).
--
-- alert_type is a VARCHAR (NOT a MySQL ENUM) per project rules; it holds one of
-- the codes retraction | update | new_related, with the canonical list living in
-- the FieldAlertService.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider. Existing tables are NEVER altered.

CREATE TABLE IF NOT EXISTS `research_field_watch` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `doi`        VARCHAR(255) NULL,
  `title`      VARCHAR(500) NULL,
  `source_ref` VARCHAR(255) NULL,
  `added_by`   VARCHAR(255) NULL,
  `last_checked_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rfw_project_idx` (`project_id`),
  KEY `rfw_project_doi_idx` (`project_id`, `doi`),
  CONSTRAINT `rfw_project_fk` FOREIGN KEY (`project_id`)
    REFERENCES `research_project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_field_alert` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`  INT NOT NULL,
  `watch_id`    BIGINT UNSIGNED NULL,
  `alert_type`  VARCHAR(40) NOT NULL DEFAULT 'update',
  `title`       VARCHAR(500) NULL,
  `detail`      TEXT NULL,
  `url`         VARCHAR(1000) NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `detected_at` DATETIME NULL,
  `created_at`  TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rfa_project_idx` (`project_id`),
  KEY `rfa_type_idx` (`alert_type`),
  KEY `rfa_project_read_idx` (`project_id`, `is_read`),
  KEY `rfa_watch_idx` (`watch_id`),
  CONSTRAINT `rfa_project_fk` FOREIGN KEY (`project_id`)
    REFERENCES `research_project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
