-- heratio#1242 - Research OS Part IV (AI Containment): AI Disclosure Statement
-- + interaction log.
--
-- Per-project manual log of AI assistance that the system cannot detect on its
-- own (for example a model used outside Heratio, or an ad-hoc gateway call). The
-- automatic disclosure lines come from existing AI-result columns in already
-- landed slices and are READ-ONLY; those tables are never altered. This is the
-- ONLY table this slice owns, and the ONLY place this slice ever writes.
--
-- All enumerated columns are VARCHAR (Dropdown Manager pattern) - never ENUM.
CREATE TABLE IF NOT EXISTS `research_ai_disclosure_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `tool` VARCHAR(160) NOT NULL DEFAULT '',
    -- the assistive tool or surface used, e.g. "AHG AI gateway", "Source Triage", "external transcription"
  `model` VARCHAR(160) NULL DEFAULT NULL,
    -- the model identifier where known, e.g. a gateway model name; nullable
  `purpose` TEXT NULL DEFAULT NULL,
    -- what the AI assistance was used for, in the researcher's own words
  `output_ref` VARCHAR(500) NULL DEFAULT NULL,
    -- optional pointer to the output the assistance touched (a section, a figure, a DOI)
  `logged_by` INT NULL DEFAULT NULL,
    -- user id of the researcher who recorded the entry
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `radl_project` (`project_id`),
  KEY `radl_project_created` (`project_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
