-- ---------------------------------------------------------------------------
-- displaced_heritage_claim - structured repatriation-claim / virtual-return
-- workflow on top of the displaced-heritage register (north-star heratio#1207).
--
-- This is the next slice of the repatriation engine. The detection slice
-- (DisplacedHeritageService) traces, read-only, which catalogue items have a
-- recorded origin that differs from where they are held. This table layers a
-- structured, human-curated CLAIM record on top of any such item: who is
-- claiming it, the place / community of origin, the current holder, where the
-- claim stands, and a factual evidence summary.
--
-- Sensitive subject matter. A claim row is a DOCUMENTED REQUEST AND ITS STATUS,
-- recorded for transparency and dialogue. It is NOT a legal determination, NOT
-- an assertion of wrongful removal, and NOT advice. claim_status records where a
-- conversation stands; it never asserts a legal outcome.
--
-- Design notes:
--   - item_ref is the displaced-heritage item / information_object id the claim
--     concerns. It is a soft reference (NO foreign key) so this additive table
--     never constrains, locks, or ALTERs the existing catalogue tables, and a
--     claim survives independently of catalogue churn.
--   - claim_status is VARCHAR, NOT ENUM (Heratio Dropdown-Manager idiom). The
--     known values are registered|under_review|acknowledged|returned|
--     virtual_return|disputed but the column accepts any dropdown value.
--   - Created idempotently (CREATE TABLE IF NOT EXISTS) and auto-installed on
--     first boot by the service provider behind a Schema::hasTable probe in one
--     outer try/catch, the canonical package idiom.
--
-- @author     Johan Pieterse
-- @copyright  Plain Sailing Information Systems
-- @license    AGPL-3.0-or-later
-- This file is part of Heratio.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `displaced_heritage_claim` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_ref`          BIGINT UNSIGNED NOT NULL COMMENT 'displaced-heritage item / information_object id (soft reference, no FK)',
    `claimant_community` VARCHAR(512)  NULL COMMENT 'community / institution / nation making the claim',
    `origin_place`      VARCHAR(512)   NULL COMMENT 'recorded place / region of origin',
    `current_holder`    VARCHAR(512)   NULL COMMENT 'current holding institution / location',
    `claim_status`      VARCHAR(64)    NOT NULL DEFAULT 'registered' COMMENT 'registered|under_review|acknowledged|returned|virtual_return|disputed (dropdown VARCHAR, never ENUM)',
    `evidence_summary`  TEXT           NULL COMMENT 'factual summary of the documented evidence the claim rests on',
    `contact`           VARCHAR(512)   NULL COMMENT 'point of contact for the claim (free text)',
    `notes`             TEXT           NULL COMMENT 'curatorial / dialogue notes',
    `created_by`        BIGINT UNSIGNED NULL COMMENT 'user id that registered the claim',
    `created_at`        TIMESTAMP      NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP      NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_dhc_item_ref` (`item_ref`),
    KEY `ix_dhc_status` (`claim_status`),
    KEY `ix_dhc_item_status` (`item_ref`, `claim_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
