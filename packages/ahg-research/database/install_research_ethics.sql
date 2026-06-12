-- heratio#1222 - Research OS: Research Ethics & Consent register slice.
--
-- A research project that touches human subjects, animals, personal data or
-- other sensitive material must record its ethics approvals and the consent
-- basis on which that data is held and used. This is a standard research-
-- governance artifact: a register of a project's ethics approvals (committee,
-- reference number, decision and expiry dates, status) and the consent basis
-- and data-sensitivity classification for each.
--
-- International and jurisdiction-neutral: the consent_basis values are generic
-- governance concepts (informed consent, legitimate interest, public task,
-- anonymised, not applicable) - NOT the lawful-basis terms of any one country's
-- regime. Nothing here is defaulted to GDPR, POPIA, HIPAA or any single law.
--
-- Additive only: one NEW table. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (approval_type, status, consent_basis,
-- data_sensitivity), never ENUM and never a hardcoded <option> list in a view.
-- The dmp_id is an FK-by-convention to research_dmp.id (the sibling DMP-builder
-- slice) - no hard foreign-key constraint, so the slices install independently
-- in any order.

CREATE TABLE IF NOT EXISTS `research_ethics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `title` VARCHAR(512) NOT NULL,
  `approval_type` VARCHAR(32) NOT NULL DEFAULT 'human_subjects' COMMENT 'from ahg_dropdown research_ethics_approval_type: human_subjects, animal, data_protection, biosafety, other',
  `reference_number` VARCHAR(128) NULL COMMENT 'the committee or body reference / protocol number',
  `committee_name` VARCHAR(512) NULL COMMENT 'name of the ethics committee, review board or governance body - free-text DATA, no jurisdiction assumed',
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending' COMMENT 'from ahg_dropdown research_ethics_status: not_required, pending, approved, conditions, expired, rejected',
  `decision_date` DATE NULL COMMENT 'date the decision was issued',
  `expiry_date` DATE NULL COMMENT 'date the approval lapses; drives the expiring-soon flag',
  `consent_basis` VARCHAR(32) NOT NULL DEFAULT 'informed_consent' COMMENT 'from ahg_dropdown research_consent_basis: informed_consent, legitimate_interest, public_task, anonymised, not_applicable - GENERIC governance concepts, never one law''s terms',
  `data_sensitivity` VARCHAR(32) NOT NULL DEFAULT 'none' COMMENT 'from ahg_dropdown research_data_sensitivity: none, personal, special_category, restricted',
  `notes` MEDIUMTEXT NULL COMMENT 'free-text notes about the approval, conditions or consent arrangements',
  `dmp_id` BIGINT UNSIGNED NULL COMMENT 'FK-by-convention to research_dmp.id (sibling slice) - the plan that governs this data',
  `owner_id` INT NULL COMMENT 'research_researcher.id - the record owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reth_project_idx` (`project_id`),
  KEY `reth_type_idx` (`approval_type`),
  KEY `reth_status_idx` (`status`),
  KEY `reth_expiry_idx` (`expiry_date`),
  KEY `reth_dmp_idx` (`dmp_id`),
  KEY `reth_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
