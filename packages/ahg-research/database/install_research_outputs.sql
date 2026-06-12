-- heratio#1222 - Research OS: Research Outputs register slice (CRIS / RIM).
--
-- A research project produces scholarly outputs - journal articles, datasets,
-- software, presentations, theses, reports, book chapters and more. This is the
-- core entity of a Current Research Information System (CRIS) / Research
-- Information Management (RIM) platform: a register of what a project produced,
-- each carrying a persistent identifier (DOI, handle, ISBN, URL) so the output
-- is citable and resolvable, and each optionally linked back to the project's
-- Data Management Plan (the sibling DMP-builder slice, research_dmp).
--
-- International and jurisdiction-neutral: an output is recorded as data. Nothing
-- here is defaulted to any one country, funder or repository.
--
-- Additive only: one NEW table. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (output_type, identifier_type, status), never ENUM and
-- never a hardcoded <option> list in a view. The dmp_id is an FK-by-convention
-- to research_dmp.id (the sibling slice) - no hard foreign-key constraint, so the
-- two slices install independently in any order.

CREATE TABLE IF NOT EXISTS `research_output` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `output_type` VARCHAR(32) NOT NULL DEFAULT 'journal_article' COMMENT 'from ahg_dropdown research_output_type: journal_article, dataset, software, presentation, thesis, report, chapter, other',
  `title` VARCHAR(512) NOT NULL,
  `authors` VARCHAR(1024) NULL COMMENT 'free-text author list as DATA - never parsed into a jurisdiction-specific format',
  `venue` VARCHAR(512) NULL COMMENT 'journal, conference, repository or publisher name',
  `identifier_type` VARCHAR(32) NULL COMMENT 'from ahg_dropdown research_output_identifier_type: doi, handle, isbn, url, other',
  `identifier` VARCHAR(512) NULL COMMENT 'the bare identifier value, e.g. 10.1234/abcd for a DOI',
  `identifier_url` VARCHAR(1024) NULL COMMENT 'optional explicit resolvable URL; if blank the type+identifier resolves one (e.g. doi -> https://doi.org/...)',
  `output_date` DATE NULL COMMENT 'date the output was published / released',
  `status` VARCHAR(32) NOT NULL DEFAULT 'planned' COMMENT 'from ahg_dropdown research_output_status: planned, in_progress, published',
  `notes` MEDIUMTEXT NULL COMMENT 'abstract or free-text notes about the output',
  `dmp_id` BIGINT UNSIGNED NULL COMMENT 'FK-by-convention to research_dmp.id (sibling slice) - the plan that governs this output',
  `owner_id` INT NULL COMMENT 'research_researcher.id - the output owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rout_project_idx` (`project_id`),
  KEY `rout_type_idx` (`output_type`),
  KEY `rout_status_idx` (`status`),
  KEY `rout_dmp_idx` (`dmp_id`),
  KEY `rout_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
