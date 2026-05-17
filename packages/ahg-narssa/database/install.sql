-- ahg-narssa: archive-transfer manifest tracking
-- Idempotent CREATE TABLE IF NOT EXISTS on every table.
--
-- narssa_transfer       one row per batch (reference, title, package path,
--                       sha256, status: draft/packaged/transmitted/accepted/rejected,
--                       transmission and acceptance timestamps, receipt reference).
-- narssa_transfer_item  one row per information_object in a batch (FK back to
--                       disposal_action when the transfer originates from the
--                       retention/disposal workflow).

CREATE TABLE IF NOT EXISTS `narssa_transfer` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transfer_reference`       VARCHAR(64) NOT NULL COMMENT 'Auto-generated, e.g. NARSSA-2026-001',
    `title`                    VARCHAR(255) NOT NULL,
    `description`              TEXT NULL,
    `schedule_codes`           VARCHAR(1000) NULL COMMENT 'CSV of retention_schedule.code values represented',
    `initiated_by`             INT NULL COMMENT 'FK user.id',
    `item_count`               INT NOT NULL DEFAULT 0,
    `total_size_bytes`         BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `package_path`             VARCHAR(500) NULL COMMENT 'Path to the generated tar.gz package on disk',
    `package_sha256`           VARCHAR(64) NULL COMMENT 'SHA-256 of the package for verification',
    `status`                   VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, packaged, transmitted, accepted, rejected',
    `transmitted_at`           DATETIME NULL,
    `accepted_at`              DATETIME NULL,
    `narssa_receipt_reference` VARCHAR(255) NULL COMMENT 'Reference number issued by the receiving archive on acceptance',
    `notes`                    TEXT NULL,
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transfer_reference` (`transfer_reference`),
    KEY `idx_status` (`status`),
    KEY `idx_initiated` (`initiated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `narssa_transfer_item` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transfer_id`              BIGINT UNSIGNED NOT NULL,
    `information_object_id`    INT NOT NULL,
    `disposal_action_id`       BIGINT UNSIGNED NULL COMMENT 'FK disposal_action.id when transfer came from disposal workflow',
    `archival_reference`       VARCHAR(255) NULL COMMENT 'IO.identifier captured at packaging time',
    `title_snapshot`           VARCHAR(500) NULL,
    `schedule_code`            VARCHAR(50) NULL,
    `digital_object_count`     INT NOT NULL DEFAULT 0,
    `digital_object_bytes`     BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `sha256`                   VARCHAR(64) NULL COMMENT 'SHA-256 of the per-item folder',
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_transfer_io` (`transfer_id`, `information_object_id`),
    KEY `idx_io` (`information_object_id`),
    KEY `idx_disposal` (`disposal_action_id`),
    CONSTRAINT `fk_nti_transfer` FOREIGN KEY (`transfer_id`)
        REFERENCES `narssa_transfer`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
