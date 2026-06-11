-- ---------------------------------------------------------------------------
-- language_transcription_contribution - community-contributed transcriptions,
-- corrections and translations for the language-revival corpus surface
-- (north-star heratio#1208: a culture you can talk to - corpus-grounded history
-- + language revival).
--
-- One row per contribution a community member lodges against a PUBLISHED item in
-- a heritage / endangered language. A contribution is one of:
--
--   transcription - the reader has transcribed the text of the item;
--   correction    - the reader is correcting an existing transcription / OCR;
--   translation   - the reader offers a translation of the item's text;
--   note          - a contextual note from someone who knows the language.
--
-- Every contribution is ADMIN-MODERATED: a new row lands as 'pending' and is
-- shown publicly only once an admin sets it to 'approved' (or hidden as
-- 'rejected'). This mirrors the language_revival_glossary moderation flow.
--
-- contribution_type and moderation_status are VARCHAR (the Dropdown-Manager
-- idiom) - never MySQL ENUM - so the value sets can grow without a schema change.
--
-- item_ref is a SOFT reference to the information_object id (NO foreign key) and
-- culture is a soft code, so this additive table never constrains or ALTERs the
-- existing catalogue. This is the ONLY table the transcription slice writes to;
-- everything it shows ABOUT the item is read READ-ONLY from the catalogue.
--
-- Heritage and endangered languages are living languages owned by the
-- communities who speak them. Contributions are credited where the contributor
-- consents to be named (contributor_name + credit_consent); otherwise they are
-- shown anonymously.
--
-- CREATE TABLE IF NOT EXISTS so the boot installer is idempotent.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- AGPL-3.0-or-later
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `language_transcription_contribution` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_ref`          BIGINT UNSIGNED NULL,
    `culture`           VARCHAR(16)  NOT NULL,
    `contribution_type` VARCHAR(32)  NOT NULL DEFAULT 'transcription',
    `body`              MEDIUMTEXT NOT NULL,
    `source`            VARCHAR(512) NULL,
    `contributed_by`    BIGINT UNSIGNED NULL,
    `contributor_name`  VARCHAR(255) NULL,
    `credit_consent`    TINYINT(1) NOT NULL DEFAULT 0,
    `moderation_status` VARCHAR(32)  NOT NULL DEFAULT 'pending',
    `moderated_by`      BIGINT UNSIGNED NULL,
    `moderated_at`      TIMESTAMP NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_ltc_culture` (`culture`),
    KEY `ix_ltc_item_ref` (`item_ref`),
    KEY `ix_ltc_status` (`moderation_status`),
    KEY `ix_ltc_item_status` (`item_ref`, `moderation_status`),
    KEY `ix_ltc_culture_status` (`culture`, `moderation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
