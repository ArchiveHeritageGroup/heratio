-- heratio#1222 - Research OS: Research Funding tracker slice.
--
-- The AWARDED-FUNDING ledger for a research project: a record of the funding
-- sources, amounts, currencies and award periods that support the work, so a
-- project's financial backing is documented alongside its DMP, outputs, grants
-- and ethics. This is the actual awarded-funding ledger and is DISTINCT from the
-- sibling grant-DRAFTING slice (research_grant_draft), which is about composing a
-- proposal - this records what a project actually has or has applied for, with
-- amounts and dates.
--
-- International and jurisdiction-neutral: NOTHING here defaults to one currency,
-- funder country or legal regime. The currency is an ISO 4217 code chosen from
-- the Dropdown Manager (a seed of common codes - USD, EUR, GBP, ZAR, AUD, CAD,
-- JPY, CHF and others), never assumed. Amounts are summarised PER CURRENCY and
-- never cross-summed.
--
-- Additive only: one NEW table. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (funder_type, status, currency), never ENUM and never
-- a hardcoded <option> list in a view. Money is DECIMAL(14,2) - never a float.
-- The dmp_id is an FK-by-convention to research_dmp.id (the sibling DMP-builder
-- slice) - no hard foreign-key constraint, so the slices install independently
-- in any order.

CREATE TABLE IF NOT EXISTS `research_funding` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `title` VARCHAR(512) NOT NULL COMMENT 'a short label for this funding line, e.g. the award or programme name',
  `funder_name` VARCHAR(512) NOT NULL COMMENT 'the funding body - free-text DATA, no jurisdiction or country assumed',
  `funder_type` VARCHAR(32) NOT NULL DEFAULT 'other' COMMENT 'from ahg_dropdown research_funder_type: government, research_council, foundation, charity, industry, internal, other',
  `award_reference` VARCHAR(128) NULL COMMENT 'the funder grant / award reference number',
  `amount` DECIMAL(14,2) NULL COMMENT 'the awarded / requested amount, stored as exact decimal (never a float)',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'USD' COMMENT 'ISO 4217 code from ahg_dropdown research_currency - NO single currency is canonical; the schema default is a neutral placeholder only',
  `status` VARCHAR(32) NOT NULL DEFAULT 'applied' COMMENT 'from ahg_dropdown research_funding_status: applied, awarded, active, completed, declined',
  `start_date` DATE NULL COMMENT 'start of the award period; drives the active-now indicator',
  `end_date` DATE NULL COMMENT 'end of the award period; drives the active-now indicator',
  `notes` MEDIUMTEXT NULL COMMENT 'free-text notes about the funding line, conditions or reporting obligations',
  `dmp_id` BIGINT UNSIGNED NULL COMMENT 'FK-by-convention to research_dmp.id (sibling slice) - the plan whose data this funding supports',
  `owner_id` INT NULL COMMENT 'research_researcher.id - the record owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rfund_project_idx` (`project_id`),
  KEY `rfund_type_idx` (`funder_type`),
  KEY `rfund_status_idx` (`status`),
  KEY `rfund_currency_idx` (`currency`),
  KEY `rfund_dates_idx` (`start_date`, `end_date`),
  KEY `rfund_dmp_idx` (`dmp_id`),
  KEY `rfund_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
