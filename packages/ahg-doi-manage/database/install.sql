-- ============================================================================
-- ahg-doi-manage / install.sql
--
-- Phase 2 (#654) - FundingReference sidecar table.
-- Idempotent. Auto-seeded by AhgDoiManageServiceProvider::boot() when the
-- ahg_io_funding table is missing.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ahg_io_funding` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id`    INT NOT NULL,
    `funder_name`              VARCHAR(500) NOT NULL,
    `funder_identifier`        VARCHAR(500) NULL COMMENT 'e.g. ROR id, ISNI, Crossref Funder ID, GRID',
    `funder_identifier_type`   VARCHAR(30) NULL COMMENT 'ROR, ISNI, Crossref Funder ID, GRID, Other',
    `award_number`             VARCHAR(255) NULL,
    `award_uri`                VARCHAR(1000) NULL,
    `award_title`              VARCHAR(1000) NULL,
    `created_at`               TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_funding_io`        (`information_object_id`),
    KEY `idx_funding_funder`    (`funder_name`),
    KEY `idx_funding_funder_id` (`funder_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
