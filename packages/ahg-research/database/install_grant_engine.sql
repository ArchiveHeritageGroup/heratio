-- heratio#1239 - Research OS #17 (moonshot 24): Grant Engine (epic #1222)
--
-- Assemble funder-specific grant DRAFTS from material that already exists on a
-- project: the project mission/description, the Method Protocol (#1231), the
-- Question Brief (#1226) and the project's claims/assertions (#1223). A draft
-- is started from a chosen FUNDER TEMPLATE (a dropdown taxonomy carrying an
-- ordered section list), pre-filled read-only from that source material, and
-- then edited section by section by the researcher. Optional AI drafting per
-- section runs through the AI gateway abstraction only, is clearly labelled,
-- and is never auto-submitted - the researcher approves every word.
--
-- Funder templates (generic, NRF, ERC, NIH, Wellcome) are selectable EXAMPLES,
-- not assumptions. Defaults are jurisdiction-neutral.
--
-- Additive only: three NEW tables. No ALTER of existing tables. VARCHAR for the
-- dropdown-backed columns (funder_template, status), never ENUM.

CREATE TABLE IF NOT EXISTS `research_grant_draft` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `funder_template` VARCHAR(64) NOT NULL COMMENT 'ahg_dropdown grant_funder_template.code, e.g. generic, nrf, erc, nih, wellcome',
  `title` VARCHAR(255) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'from ahg_dropdown grant_draft_status: draft, in_review, ready, submitted',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rgd_project_idx` (`project_id`),
  KEY `rgd_template_idx` (`funder_template`),
  KEY `rgd_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_grant_section` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `draft_id` BIGINT UNSIGNED NOT NULL,
  `section_key` VARCHAR(64) NOT NULL COMMENT 'stable key within the funder template, e.g. summary, aims, methodology',
  `label` VARCHAR(190) NOT NULL,
  `body` MEDIUMTEXT NULL COMMENT 'the section draft text the researcher edits',
  `sort_order` INT NOT NULL DEFAULT 100,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rgs_draft_idx` (`draft_id`, `sort_order`),
  KEY `rgs_key_idx` (`draft_id`, `section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracked funder calls / opportunities a researcher is watching. Scoped to a
-- researcher and optionally a project (both nullable so a call can be a general
-- watch). Pure tracking - no money movement, no submission.
CREATE TABLE IF NOT EXISTS `research_grant_call` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `researcher_id` INT NULL COMMENT 'research_researcher.id - owner of the watch',
  `project_id` INT NULL COMMENT 'optional project this call is being tracked against',
  `funder` VARCHAR(255) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `url` VARCHAR(500) NULL,
  `deadline` DATE NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'watching' COMMENT 'from ahg_dropdown grant_call_status: watching, preparing, submitted, awarded, declined, closed',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rgc_researcher_idx` (`researcher_id`),
  KEY `rgc_project_idx` (`project_id`),
  KEY `rgc_status_idx` (`status`),
  KEY `rgc_deadline_idx` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
