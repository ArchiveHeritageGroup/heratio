-- heratio#1222 - Research OS: Research Team & Collaborators register slice.
--
-- The PEOPLE register for a research project: who is on the team and in what
-- capacity, so a project's contributors are documented alongside its DMP,
-- outputs, ethics and funding. This is the broader CONTRIBUTOR list -
-- co-investigators, students, partners, technicians and external collaborators -
-- and is DISTINCT from the project's single owner/researcher concept that the
-- portal already carries: it does not replace the owner, it documents everyone
-- else who contributes.
--
-- International and jurisdiction-neutral: NOTHING here defaults to one country or
-- institution. Affiliation is free-text DATA, not a fixed list. The ORCID is the
-- bare iD (16 digits, the last allowed to be X) - an international, registry-
-- neutral persistent identifier for a researcher - stored without the URL prefix
-- and rendered as a link to https://orcid.org/{orcid}; no external fetch is ever
-- made. The role taxonomy is informed by the international CRediT contributor-
-- roles taxonomy, but the detailed contribution is kept as free text.
--
-- Additive only: one NEW table. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (role, status), never ENUM and never a hardcoded
-- <option> list in a view.

CREATE TABLE IF NOT EXISTS `research_team_member` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `person_name` VARCHAR(512) NOT NULL COMMENT 'the contributor name - free-text DATA, no institution or jurisdiction assumed',
  `role` VARCHAR(32) NOT NULL DEFAULT 'researcher' COMMENT 'from ahg_dropdown research_team_role: principal_investigator, co_investigator, researcher, student, advisor, partner, technician, other',
  `affiliation` VARCHAR(512) NULL COMMENT 'institution / organisation - free-text DATA, no country or institution defaulted',
  `email` VARCHAR(255) NULL COMMENT 'contact email for the contributor',
  `orcid` VARCHAR(19) NULL COMMENT 'the bare ORCID iD (####-####-####-###X) - an international persistent identifier; rendered as a link to https://orcid.org/{orcid}, never fetched',
  `is_lead` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'flags a project lead (e.g. principal investigator) for highlighting in the summary',
  `contribution_note` MEDIUMTEXT NULL COMMENT 'free-text description of the contribution; the international CRediT taxonomy is a recognised reference, but this field is free text',
  `start_date` DATE NULL COMMENT 'date the contributor joined the project',
  `end_date` DATE NULL COMMENT 'date the contributor left the project, if any',
  `status` VARCHAR(32) NOT NULL DEFAULT 'active' COMMENT 'from ahg_dropdown research_team_status: active, inactive, former',
  `owner_id` INT NULL COMMENT 'research_researcher.id - the record owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rteam_project_idx` (`project_id`),
  KEY `rteam_role_idx` (`role`),
  KEY `rteam_status_idx` (`status`),
  KEY `rteam_lead_idx` (`is_lead`),
  KEY `rteam_dates_idx` (`start_date`, `end_date`),
  KEY `rteam_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
