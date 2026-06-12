-- ---------------------------------------------------------------------------
-- repatriation_knowledge_contribution - community KNOWLEDGE contributions about
-- a displaced item / repatriation claim (north-star heratio#1207: the
-- repatriation engine).
--
-- The detection slice (DisplacedHeritageService) traces, read-only, which
-- catalogue items have a recorded origin that differs from where they are held.
-- The claim slice (displaced_heritage_claim) layers a curated CLAIM and its
-- virtual-return view on top. This slice adds the COMMUNITY VOICE: a member of a
-- source community, a descendant, a researcher or any knowledgeable person can
-- contribute KNOWLEDGE about a displaced item - oral history, provenance
-- knowledge, a correction to the record, a pointer to the source community, or
-- another kind of note.
--
-- One row per contribution. A contribution is one of:
--
--   provenance       - documented provenance knowledge about the object;
--   oral_history     - oral history, testimony or memory connected to the object;
--   correction       - a correction to what the record currently says;
--   source_community - identification of, or a link to, the source community;
--   other            - any other knowledge about the object.
--
-- Every contribution is ADMIN-MODERATED: a new row lands as 'pending' and is
-- shown publicly only once an admin sets it to 'approved' (or hidden as
-- 'rejected'). This mirrors the language_revival_glossary /
-- language_transcription_contribution moderation flow exactly.
--
-- Sensitive subject matter, handled with care. Knowledge about a displaced
-- object belongs to its communities. A contribution is a documented piece of a
-- DIALOGUE recorded in a spirit of transparency and respect - it is NOT a legal
-- determination of origin, ownership or wrongful removal. The contributor is
-- CREDITED ONLY where they explicitly consent to be named (contributor_name +
-- credit_consent); otherwise the contribution is shown anonymously.
--
-- Design notes:
--   - claim_id is a SOFT reference to displaced_heritage_claim.id (NO foreign
--     key) so this additive table never constrains, locks, or ALTERs the
--     claim table, and a contribution survives independently of claim churn.
--   - item_ref is a SOFT reference to the information_object id (NO foreign key)
--     so a contribution can attach to the underlying catalogue item directly
--     even when no formal claim row exists yet. At least one of the two soft
--     references is expected; neither is enforced at the schema level.
--   - contribution_type and moderation_status are VARCHAR (the Dropdown-Manager
--     idiom) - never MySQL ENUM - so the value sets can grow without a schema
--     change.
--   - Created idempotently (CREATE TABLE IF NOT EXISTS) and auto-installed on
--     first boot by the service provider behind a Schema::hasTable probe in one
--     outer try/catch, the canonical package idiom.
--
-- This is the ONLY table the slice writes to. Everything it shows ABOUT the
-- claim / item is read READ-ONLY from the existing register, claim and catalogue
-- tables.
--
-- @author     Johan Pieterse
-- @copyright  Plain Sailing Information Systems
-- @license    AGPL-3.0-or-later
-- This file is part of Heratio.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `repatriation_knowledge_contribution` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_id`          BIGINT UNSIGNED NULL COMMENT 'displaced_heritage_claim.id (soft reference, no FK)',
    `item_ref`          BIGINT UNSIGNED NULL COMMENT 'information_object id the knowledge concerns (soft reference, no FK)',
    `contribution_type` VARCHAR(32)  NOT NULL DEFAULT 'other' COMMENT 'provenance|oral_history|correction|source_community|other (dropdown VARCHAR, never ENUM)',
    `body`              MEDIUMTEXT NOT NULL COMMENT 'the contributed knowledge',
    `source`            VARCHAR(512) NULL COMMENT 'source / attribution for the knowledge',
    `contributor_name`  VARCHAR(255) NULL COMMENT 'name, surfaced ONLY when credit_consent is set',
    `credit_consent`    TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'contributor explicitly consented to be credited by name',
    `contributed_by`    BIGINT UNSIGNED NULL COMMENT 'user id, when authenticated (soft reference, no FK)',
    `moderation_status` VARCHAR(32)  NOT NULL DEFAULT 'pending' COMMENT 'pending|approved|rejected (dropdown VARCHAR, never ENUM)',
    `moderated_by`      BIGINT UNSIGNED NULL,
    `moderated_at`      TIMESTAMP NULL DEFAULT NULL,
    `created_at`        TIMESTAMP NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_rkc_claim` (`claim_id`),
    KEY `ix_rkc_item_ref` (`item_ref`),
    KEY `ix_rkc_status` (`moderation_status`),
    KEY `ix_rkc_claim_status` (`claim_id`, `moderation_status`),
    KEY `ix_rkc_item_status` (`item_ref`, `moderation_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
