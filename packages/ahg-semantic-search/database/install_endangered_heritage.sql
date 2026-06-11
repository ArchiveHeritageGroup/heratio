-- ---------------------------------------------------------------------------
-- endangered_heritage_item - endangered-heritage register + capture-priority
-- list (north-star heratio#1205: race against loss).
--
-- One row per catalogue item flagged as at-risk. item_ref is a SOFT reference
-- to the information_object id (NO foreign key) so this additive table never
-- constrains or ALTERs the existing catalogue. risk_category, urgency and
-- capture_status are VARCHAR (Dropdown-Manager idiom) - never MySQL ENUM - so
-- the Dropdown Manager can extend the value sets without a schema change.
--
-- Read-only over every existing table; this is the only table the slice writes
-- to. CREATE TABLE IF NOT EXISTS so the boot installer is idempotent.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- AGPL-3.0-or-later
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `endangered_heritage_item` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_ref`       BIGINT UNSIGNED NOT NULL,
    `risk_category`  VARCHAR(64)  NOT NULL DEFAULT 'other',
    `urgency`        VARCHAR(32)  NOT NULL DEFAULT 'medium',
    `reason`         TEXT NULL,
    `capture_status` VARCHAR(32)  NOT NULL DEFAULT 'flagged',
    `flagged_by`     BIGINT UNSIGNED NULL,
    `created_at`     TIMESTAMP NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ix_ehi_item_ref` (`item_ref`),
    KEY `ix_ehi_urgency` (`urgency`),
    KEY `ix_ehi_capture_status` (`capture_status`),
    KEY `ix_ehi_item_urgency` (`item_ref`, `urgency`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
