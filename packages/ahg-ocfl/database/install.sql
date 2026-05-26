-- ahg-ocfl: install schema (idempotent).
--
-- Maps Heratio information_object ids to OCFL object ids in the storage
-- root. One row per IO that has been ingested; head_version tracks the
-- newest version written.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later

CREATE TABLE IF NOT EXISTS `ahg_ocfl_object_map` (
    `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `information_object_id` BIGINT UNSIGNED NOT NULL,
    `ocfl_object_id`        VARCHAR(255)    NOT NULL,
    `storage_root`          VARCHAR(64)     NOT NULL DEFAULT 'ocfl',
    `head_version`          VARCHAR(16)     NOT NULL DEFAULT 'v1',
    `created_at`            TIMESTAMP NULL DEFAULT NULL,
    `updated_at`            TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_ocfl_io`        (`information_object_id`),
    UNIQUE KEY `uniq_ocfl_object_id` (`ocfl_object_id`),
    KEY        `idx_storage_root`    (`storage_root`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
