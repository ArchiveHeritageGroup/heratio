-- heratio#1231 - Research OS #9: Method Design Studio (ROS Stage 10, epic #1222)
--
-- Method support as DISCIPLINE TEMPLATES. A template carries structured guidance
-- prompts for each design area (design, sampling, data-sources, instruments,
-- coding-framework, variables, validity, reliability, ethics, consent,
-- bias-control, reproducibility, data-management). A researcher starts a
-- per-project Method Protocol from a chosen template; the protocol's `fields`
-- JSON holds their answers per guidance area. The protocol is written once and
-- can be referenced by other features (thesis methodology chapter, grant,
-- ethics application) via the Studio's reuse read model.
--
-- Additive only: two NEW tables, no ALTER of existing tables.

CREATE TABLE IF NOT EXISTS `research_method_template` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL COMMENT 'stable template key, e.g. case-study',
  `name` VARCHAR(190) NOT NULL,
  `discipline` VARCHAR(120) NULL COMMENT 'broad discipline grouping, jurisdiction-neutral',
  `description` TEXT NULL,
  `guidance` MEDIUMTEXT NULL COMMENT 'JSON: ordered map of area-key => {label, prompt, placeholder}',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rmt_code_uq` (`code`),
  KEY `rmt_active_idx` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_method_protocol` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `template_code` VARCHAR(64) NOT NULL COMMENT 'references research_method_template.code',
  `title` VARCHAR(255) NOT NULL,
  `fields` MEDIUMTEXT NULL COMMENT 'JSON: area-key => researcher answer text',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'draft, in_review, final (from ahg_dropdown method_protocol_status)',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rmp_project_idx` (`project_id`),
  KEY `rmp_template_idx` (`template_code`),
  KEY `rmp_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
