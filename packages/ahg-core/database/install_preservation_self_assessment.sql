-- heratio#1244 (maturity self-assessment slice) - human-entered digital-preservation
-- MATURITY SELF-ASSESSMENT.
--
-- This is the human, organisational counterpart to the read-only, evidence-COMPUTED
-- /admin/preservation-maturity dashboard (PreservationMaturityService). That surface
-- scores the running instance from concrete records; THIS one records what the
-- institution says about itself when it rates its own practice against a recognised
-- international maturity model:
--
--   * the NDSA Levels of Digital Preservation (five functional areas, levels 1..4), and
--   * the DPC Rapid Assessment Model (DPC RAM - eleven sections, each rated 0..4 from
--     "Minimal awareness" through to "Optimised").
--
-- Two tables:
--   preservation_self_assessment         one assessment RUN (date, assessor, model, notes)
--   preservation_self_assessment_rating  one rating row per section within a run
--
-- Both are NEW side tables, soft-referenced only (no FK into the AtoM/Qubit base
-- schema), so they install safely on any mid-migration database. No ENUM columns:
-- the assessment model and the maturity-level labels come from the Dropdown Manager
-- (ahg_dropdown groups assessment_model + maturity_level), and the per-section level
-- is a plain TINYINT validated 0..4 in PHP. No ALTER on any existing table; CREATE
-- TABLE IF NOT EXISTS only.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- Licensed under the GNU Affero General Public License v3 or later.

CREATE TABLE IF NOT EXISTS `preservation_self_assessment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `model` VARCHAR(32) NOT NULL DEFAULT 'dpc_ram' COMMENT 'assessment model code from ahg_dropdown group assessment_model (ndsa | dpc_ram); never an ENUM',
  `title` VARCHAR(255) NULL COMMENT 'optional human label for this run, e.g. "Annual review 2026"',
  `assessor` VARCHAR(255) NULL COMMENT 'who carried out the assessment (free text / name)',
  `assessor_user_id` INT NULL COMMENT 'soft reference to the signed-in user who created the run - no FK',
  `assessment_date` DATE NULL COMMENT 'date the institution dates this self-assessment to',
  `status` VARCHAR(32) NOT NULL DEFAULT 'draft' COMMENT 'draft | complete - workflow state of the run',
  `notes` TEXT NULL COMMENT 'overall narrative / scope notes for the run',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `preservation_self_assessment_model_idx` (`model`),
  KEY `preservation_self_assessment_date_idx` (`assessment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `preservation_self_assessment_rating` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assessment_id` BIGINT UNSIGNED NOT NULL COMMENT 'owning run - preservation_self_assessment.id (soft reference, no FK)',
  `section_key` VARCHAR(64) NOT NULL COMMENT 'stable section identifier within the model (e.g. dpc_ram section or ndsa functional area)',
  `level` TINYINT NOT NULL DEFAULT 0 COMMENT 'self-rated maturity level for this section, validated 0..4 in PHP',
  `evidence` TEXT NULL COMMENT 'evidence / justification notes for the rated level',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pres_self_rating_assessment_section_unique` (`assessment_id`, `section_key`),
  KEY `pres_self_rating_assessment_idx` (`assessment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
