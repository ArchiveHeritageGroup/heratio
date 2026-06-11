-- heratio#1229 - Research OS Stage 12: Argument Builder.
--
-- Per-project argument scaffold. The researcher drags CLAIMS (which already live
-- in `research_assertion`, owned by the Claim Ledger, heratio#1223) into an
-- ordered argument sequence and the system warns about weak spots.
--
-- Two NEW tables only. The existing `research_assertion` +
-- `research_assertion_evidence` tables are NEVER altered; a step references a
-- claim by its assertion id (nullable) so an empty slot can still exist.
--
-- The `slot` column is a plain VARCHAR (never a MySQL ENUM); the nine canonical
-- slots are validated in PHP and surfaced via the service so they can grow
-- without a schema change.

CREATE TABLE IF NOT EXISTS `research_argument` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `title` VARCHAR(255) NULL,
  `central_thesis` MEDIUMTEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ra_project_idx` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_argument_step` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `argument_id` BIGINT UNSIGNED NOT NULL,
  `slot` VARCHAR(40) NOT NULL,
  `assertion_id` INT NULL,
  `note` TEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ras_argument_idx` (`argument_id`),
  KEY `ras_assertion_idx` (`assertion_id`),
  KEY `ras_sort_idx` (`argument_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
