-- ============================================================================
-- ahg-privacy Phase 5 (#1199) - Compliance autopilot: auto-drafted retention
-- schedule proposals.
--
-- One row per data category surfaced by the PII catalogue scan. The autopilot
-- asks the gateway LLM to suggest a defensible retention period + legal/policy
-- basis per category (grounded ONLY in the scanned categories, never invented
-- data). Each proposal is held for a data-protection officer to accept; on
-- accept the row is flagged and feeds back into the Article 30 register.
--
-- Jurisdiction-neutral: the period and basis text are generic ("per the
-- applicable retention regime / appraisal policy"); the per-market module
-- (POPIA / GDPR / IPSAS / etc.) is responsible for the concrete law. No
-- single country is hardcoded.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-seeded by
-- AhgPrivacyServiceProvider on first boot.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems.
-- Licensed AGPL-3.0-or-later.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ahg_retention_proposal` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category`         VARCHAR(64) NOT NULL,
  `category_label`   VARCHAR(191) NOT NULL,
  `records_affected` INT UNSIGNED NOT NULL DEFAULT 0,
  `retention_period` VARCHAR(191) NOT NULL,
  `legal_basis`      TEXT NULL,
  `disposal_action`  VARCHAR(191) NULL,
  `rationale`        TEXT NULL,
  `source`           VARCHAR(32) NOT NULL DEFAULT 'autopilot',
  `status`           VARCHAR(32) NOT NULL DEFAULT 'proposed',
  `accepted_at`      TIMESTAMP NULL,
  `accepted_by`      BIGINT UNSIGNED NULL,
  `created_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_retention_category` (`category`),
  KEY `idx_retention_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
