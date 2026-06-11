-- heratio#1234 - Research OS Stage 11: Analysis Bridge.
--
-- The Analysis Bridge does NOT perform analysis. It registers the PROVENANCE of
-- results produced elsewhere (Jupyter, R, QDA software, statistics packages) and
-- links each result to the project claim(s) it supports, weakens or contextualises.
--
-- These are NEW tables only. Existing tables (research_assertion,
-- research_assertion_evidence, research_project, ...) are NEVER altered.
-- All statements are CREATE TABLE IF NOT EXISTS and safe to re-run.

-- A registered external analysis result with its full provenance metadata.
CREATE TABLE IF NOT EXISTS `research_analysis_result` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `result_type` VARCHAR(40) NOT NULL DEFAULT 'other' COMMENT 'chart, table, theme, statistic, other',
  `title` VARCHAR(500) NOT NULL,
  `source_data_ref` VARCHAR(1000) NULL COMMENT 'what data this result was produced from (dataset name, query, collection, file)',
  `source_data_version` VARCHAR(120) NULL COMMENT 'version / snapshot / date of the source data',
  `method` TEXT NULL COMMENT 'the analytical method / technique applied',
  `code_ref` VARCHAR(1000) NULL COMMENT 'where the code/notebook/script lives (repo URL, notebook name, file path)',
  `generated_at` DATETIME NULL COMMENT 'when the external result was produced',
  `researcher_decision` TEXT NULL COMMENT 'the human decision/interpretation drawn from this result',
  `artifact_path` VARCHAR(1000) NULL COMMENT 'path RELATIVE to config(heratio.storage_path); never an absolute path',
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rar_project` (`project_id`),
  KEY `idx_rar_type` (`result_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Links a registered result to the project claim(s) it bears on.
-- assertion_id references research_assertion.id (the Claim Ledger), but no FK is
-- declared so a missing/partial install never blocks an insert.
CREATE TABLE IF NOT EXISTS `research_analysis_result_claim` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `result_id` BIGINT UNSIGNED NOT NULL,
  `assertion_id` INT NOT NULL,
  `relationship` VARCHAR(40) NOT NULL DEFAULT 'supports' COMMENT 'supports, weakens, contextualises',
  `note` TEXT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rarc` (`result_id`, `assertion_id`),
  KEY `idx_rarc_result` (`result_id`),
  KEY `idx_rarc_assertion` (`assertion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Light built-in thematic-coding tags and analytic memos, kept per project so a
-- researcher has somewhere to record codes/memos without leaving the portal.
CREATE TABLE IF NOT EXISTS `research_analysis_code` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `kind` VARCHAR(40) NOT NULL DEFAULT 'theme_tag' COMMENT 'theme_tag, memo',
  `label` VARCHAR(255) NOT NULL,
  `body` MEDIUMTEXT NULL,
  `created_by` INT NULL,
  `created_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rac_project` (`project_id`),
  KEY `idx_rac_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
