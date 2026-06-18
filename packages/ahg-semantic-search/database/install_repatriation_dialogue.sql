-- ---------------------------------------------------------------------------
-- Repatriation dialogue + audit + shared-access tables (north-star heratio#1207).
--
-- This slice layers a TWO-WAY, threaded DIALOGUE, a STATUS AUDIT TRAIL, and a
-- token-permissioned SHARED RECORD on top of the existing displaced_heritage_claim
-- workflow. It is additive and fail-soft: it adds NEW tables only, never ALTERs or
-- constrains any existing table, and every reference into the catalogue / claim
-- tables is a SOFT reference (no foreign key) so a row survives independently of
-- claim / catalogue churn.
--
-- Three tables:
--
--   repatriation_claim_message   - one row per dialogue message on a claim. A
--                                   threaded conversation between the HOLDING
--                                   institution (staff) and the CLAIMANT / origin
--                                   community (token-gated guest). author_role
--                                   records which side spoke; visibility lets a
--                                   note be kept internal to staff or shared on the
--                                   joint record. NOT moderated (it is a direct
--                                   dialogue between named parties), in contrast to
--                                   the moderated community-knowledge feed.
--
--   repatriation_claim_status_log - append-only audit trail of every status
--                                   change on a claim: who changed it, when, the
--                                   old and new value, and an optional note. The
--                                   claim row keeps the CURRENT status; this table
--                                   keeps the HISTORY.
--
--   repatriation_claim_access     - a shared-record ACCESS GRANT: a per-claim
--                                   capability token that lets a named origin-
--                                   community representative open the shared record
--                                   and post dialogue WITHOUT a full staff account.
--                                   Staff mint, revoke and (optionally) expire a
--                                   grant. The token is a random opaque string; the
--                                   shared surface is keyed off it.
--
-- All status / role / visibility columns are VARCHAR (the Heratio Dropdown-Manager
-- idiom) - never MySQL ENUM - so the value sets can grow without a schema change.
--
-- Sensitive subject matter, handled with care. A dialogue message and a claim's
-- status describe where a conversation stands; neither asserts a legal outcome.
--
-- Created idempotently (CREATE TABLE IF NOT EXISTS) and auto-installed on first
-- boot by the service provider behind a Schema::hasTable probe in one outer
-- try/catch (the canonical Heratio package idiom; see
-- memory/reference_ci_schema_hastable.md).
--
-- @author     Johan Pieterse
-- @copyright  Plain Sailing Information Systems
-- @license    AGPL-3.0-or-later
-- This file is part of Heratio.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `repatriation_claim_message` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_id`      BIGINT UNSIGNED NOT NULL COMMENT 'displaced_heritage_claim.id (soft reference, no FK)',
    `author_role`   VARCHAR(32)  NOT NULL DEFAULT 'institution' COMMENT 'institution|claimant|mediator (dropdown VARCHAR, never ENUM)',
    `author_name`   VARCHAR(255) NULL COMMENT 'display name of the speaker (the staff member, or the claimant representative on a shared grant)',
    `author_user`   BIGINT UNSIGNED NULL COMMENT 'user id when the author is an authenticated staff member (soft reference, no FK)',
    `access_id`     BIGINT UNSIGNED NULL COMMENT 'repatriation_claim_access.id when the author posted through a shared-record grant (soft reference, no FK)',
    `body`          MEDIUMTEXT   NOT NULL COMMENT 'the message text',
    `visibility`    VARCHAR(16)  NOT NULL DEFAULT 'shared' COMMENT 'shared (on the joint record) | internal (staff-only) (dropdown VARCHAR, never ENUM)',
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_rcm_claim` (`claim_id`),
    KEY `ix_rcm_claim_vis` (`claim_id`, `visibility`),
    KEY `ix_rcm_access` (`access_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repatriation_claim_status_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_id`      BIGINT UNSIGNED NOT NULL COMMENT 'displaced_heritage_claim.id (soft reference, no FK)',
    `from_status`   VARCHAR(64)  NULL COMMENT 'the status before the change (null for the first record)',
    `to_status`     VARCHAR(64)  NOT NULL COMMENT 'the status after the change',
    `note`          VARCHAR(1024) NULL COMMENT 'optional reason / note recorded with the change',
    `changed_by`    BIGINT UNSIGNED NULL COMMENT 'user id that made the change (soft reference, no FK)',
    `changed_by_name` VARCHAR(255) NULL COMMENT 'display name of who made the change (staff name, or claimant representative)',
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_rcsl_claim` (`claim_id`),
    KEY `ix_rcsl_claim_created` (`claim_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `repatriation_claim_access` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `claim_id`      BIGINT UNSIGNED NOT NULL COMMENT 'displaced_heritage_claim.id (soft reference, no FK)',
    `token`         VARCHAR(64)  NOT NULL COMMENT 'opaque random capability token; the shared record is keyed off it',
    `grantee_name`  VARCHAR(255) NULL COMMENT 'name of the origin-community representative the grant is for',
    `grantee_role`  VARCHAR(32)  NOT NULL DEFAULT 'claimant' COMMENT 'claimant|mediator|observer (dropdown VARCHAR, never ENUM)',
    `can_message`   TINYINT(1)   NOT NULL DEFAULT 1 COMMENT 'whether this grant may post dialogue messages (vs read-only)',
    `status`        VARCHAR(16)  NOT NULL DEFAULT 'active' COMMENT 'active|revoked (dropdown VARCHAR, never ENUM)',
    `expires_at`    TIMESTAMP    NULL DEFAULT NULL COMMENT 'optional expiry; null = no expiry',
    `created_by`    BIGINT UNSIGNED NULL COMMENT 'staff user id that minted the grant (soft reference, no FK)',
    `last_seen_at`  TIMESTAMP    NULL DEFAULT NULL COMMENT 'last time the grant opened the shared record',
    `created_at`    TIMESTAMP    NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP    NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rca_token` (`token`),
    KEY `ix_rca_claim` (`claim_id`),
    KEY `ix_rca_claim_status` (`claim_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
