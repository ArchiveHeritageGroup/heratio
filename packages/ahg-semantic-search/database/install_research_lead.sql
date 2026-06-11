-- ---------------------------------------------------------------------------
-- research_lead - public "Research Leads" feed (north-star heratio#1210:
-- generative scholarship - AI finds connections no human spotted).
--
-- One row per promoted research lead: a compelling AI-found cross-collection
-- connection, curated by staff into a browsable scholarly lead. A lead carries
-- the connection's centre record (information_object_id - a SOFT reference, NO
-- foreign key, so this additive table never constrains or ALTERs the existing
-- catalogue), a plain-language "why this might matter" prompt, a headline, the
-- AI lead text, a JSON snapshot of the evidence (the verified links it rests
-- on), and a curation status.
--
-- status is VARCHAR (Dropdown-Manager idiom) - never a MySQL ENUM - so the
-- value set (pending / published / dismissed) can be extended without a schema
-- change. Only PUBLISHED leads are shown on the public feed; pending and
-- dismissed are admin-only.
--
-- source_discovery_id is a SOFT reference back to the ahg_scholarship_discovery
-- row a lead was promoted from (NO foreign key), used to keep one lead per
-- discovery on regeneration. Read-only over every existing table; this is the
-- only table the slice writes to. CREATE TABLE IF NOT EXISTS so the boot
-- installer is idempotent.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- AGPL-3.0-or-later
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `research_lead` (
    `id`                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id` BIGINT UNSIGNED NOT NULL,
    `source_discovery_id`  BIGINT UNSIGNED NULL,
    `headline`             VARCHAR(1024) NULL,
    `lead_text`            TEXT NULL,
    `why_it_matters`       TEXT NULL,
    `connection_count`     INT UNSIGNED NOT NULL DEFAULT 0,
    `confidence`           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `evidence`             JSON NULL,
    `status`               VARCHAR(32) NOT NULL DEFAULT 'pending',
    `ai_labelled`          TINYINT(1) NOT NULL DEFAULT 1,
    `curated_by`           BIGINT UNSIGNED NULL,
    `generated_at`         TIMESTAMP NULL DEFAULT NULL,
    `published_at`         TIMESTAMP NULL DEFAULT NULL,
    `created_at`           TIMESTAMP NULL DEFAULT NULL,
    `updated_at`           TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_lead_io` (`information_object_id`),
    KEY `ix_research_lead_status` (`status`),
    KEY `ix_research_lead_status_conf` (`status`, `confidence`),
    KEY `ix_research_lead_source` (`source_discovery_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
