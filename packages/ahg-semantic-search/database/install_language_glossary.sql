-- ---------------------------------------------------------------------------
-- language_revival_glossary - community-contributed glossary for the
-- language-revival corpus surface (north-star heratio#1208: a culture you can
-- talk to - corpus-grounded history + language revival).
--
-- One row per contributed term in a heritage / endangered language: the term
-- itself, its meaning, an optional usage example, a source/attribution, and the
-- culture code it belongs to (matches information_object_i18n.culture /
-- term_i18n.culture, e.g. 'af', 'zu', 'xh', 'nso'). Entries are community
-- contributions and are ADMIN-MODERATED: a new entry lands as 'pending' and is
-- only shown publicly once an admin sets it to 'approved' (or hidden as
-- 'rejected'). moderation_status is a VARCHAR (Dropdown-Manager idiom) - never a
-- MySQL ENUM - so the moderation value set can grow without a schema change.
--
-- This is the ONLY table the slice writes to. Everything else it shows about a
-- language (records described in it, terms / place-names, transcriptions) is read
-- READ-ONLY from the existing catalogue. There are NO foreign keys: culture is a
-- soft code and contributed_by is a soft reference to the user id, so this
-- additive table never constrains or ALTERs the existing catalogue.
--
-- CREATE TABLE IF NOT EXISTS so the boot installer is idempotent.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- AGPL-3.0-or-later
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `language_revival_glossary` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `culture`           VARCHAR(16)  NOT NULL,
    `term`              VARCHAR(512) NOT NULL,
    `meaning`           TEXT NOT NULL,
    `usage_example`     TEXT NULL,
    `source`            VARCHAR(512) NULL,
    `moderation_status` VARCHAR(32)  NOT NULL DEFAULT 'pending',
    `contributed_by`    BIGINT UNSIGNED NULL,
    `contributor_name`  VARCHAR(255) NULL,
    `moderated_by`      BIGINT UNSIGNED NULL,
    `moderated_at`      TIMESTAMP NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_lrg_culture` (`culture`),
    KEY `ix_lrg_status` (`moderation_status`),
    KEY `ix_lrg_culture_status` (`culture`, `moderation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
