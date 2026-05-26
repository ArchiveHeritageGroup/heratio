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

-- ============================================================================
-- Phase 3 (#654) - DataCite Events API client (eventdata-guide).
-- One row per (subj-id, relation-type-id, obj-id) submitted to the Events API.
-- The dedupe_hash is sha256 over those three plus source_id so a repeat
-- view/download/citation does not double-register with DataCite.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ahg_datacite_event` (
    `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `dedupe_hash`         CHAR(64) NOT NULL COMMENT 'sha256(subj|rel|obj|src)',
    `subj_id`             VARCHAR(500) NOT NULL COMMENT 'subject DOI (Heratio side)',
    `relation_type_id`    VARCHAR(64)  NOT NULL COMMENT 'e.g. references / is-referenced-by / unique-dataset-investigations-regular',
    `obj_id`              VARCHAR(500) NOT NULL COMMENT 'object id (other DOI, URL, etc)',
    `obj_id_type`         VARCHAR(32)  NOT NULL DEFAULT 'doi' COMMENT 'doi | url | uri | other',
    `source_id`           VARCHAR(64)  NOT NULL DEFAULT 'heratio-archive' COMMENT 'reported source-id',
    `source_token`        VARCHAR(64)  NULL COMMENT 'configured DataCite source-token if known',
    `payload_json`        JSON NULL COMMENT 'final JSON-API body submitted',
    `response_status`     SMALLINT UNSIGNED NULL,
    `response_body`       TEXT NULL,
    `state`               VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending | sent | failed',
    `attempts`            INT UNSIGNED NOT NULL DEFAULT 0,
    `last_error`          TEXT NULL,
    `submitted_at`        TIMESTAMP NULL,
    `created_at`          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_event_dedupe` (`dedupe_hash`),
    KEY `idx_event_subj`  (`subj_id`),
    KEY `idx_event_obj`   (`obj_id`),
    KEY `idx_event_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
