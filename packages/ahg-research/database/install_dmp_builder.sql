-- heratio#1222 - Research OS: Data Management Plan (DMP) Builder slice.
--
-- A DMP is the standard FAIR research artifact that funders require (Horizon
-- Europe, NSF, Wellcome, NRF and others). This slice models a researcher-facing
-- DMP builder scoped to a research project, structured on the RDA / Science
-- Europe machine-actionable DMP (maDMP) common standard. The recognised maDMP
-- sections are surfaced as normalised section rows so a site can add, reorder or
-- relabel sections from a template without a schema change.
--
-- International and funder-neutral: the funder is captured as DATA on the plan
-- (a free-text field plus an optional dropdown), never assumed and never
-- defaulted to any one jurisdiction. The example funders surfaced elsewhere
-- (Horizon Europe, NSF, Wellcome, NRF) are selectable examples, not assumptions.
--
-- Additive only: two NEW tables. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (status, funder_template), never ENUM. The ordered
-- maDMP section template lives in code (DmpService::MADMP_SECTIONS); these tables
-- hold the per-plan answers.

CREATE TABLE IF NOT EXISTS `research_dmp` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'from ahg_dropdown dmp_status: draft, in_review, approved, published, superseded',
  `funder` VARCHAR(255) NULL COMMENT 'free-text funder name as DATA - jurisdiction-neutral, never assumed',
  `funder_template` VARCHAR(64) NULL COMMENT 'optional ahg_dropdown dmp_funder_template.code, e.g. generic, horizon_europe, nsf, wellcome, nrf',
  `language` VARCHAR(12) NOT NULL DEFAULT 'en' COMMENT 'BCP-47 language tag for the maDMP export',
  `contact_name` VARCHAR(255) NULL COMMENT 'maDMP contact - the person responsible for the plan',
  `contact_email` VARCHAR(255) NULL,
  `owner_id` INT NULL COMMENT 'research_researcher.id - the plan owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rdmp_project_idx` (`project_id`),
  KEY `rdmp_status_idx` (`status`),
  KEY `rdmp_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per maDMP section per plan. The section_key is a stable key from the
-- maDMP common-standard section list (DmpService::MADMP_SECTIONS); the body is
-- the researcher's answer. Normalised so sections can be added/reordered from a
-- template without altering the plan table.
CREATE TABLE IF NOT EXISTS `research_dmp_section` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `dmp_id` BIGINT UNSIGNED NOT NULL,
  `section_key` VARCHAR(64) NOT NULL COMMENT 'stable maDMP section key, e.g. data_description, findable, accessible',
  `label` VARCHAR(190) NOT NULL,
  `body` MEDIUMTEXT NULL COMMENT 'the researcher answer for this section',
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rdmps_dmp_idx` (`dmp_id`, `sort_order`),
  KEY `rdmps_key_idx` (`dmp_id`, `section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
