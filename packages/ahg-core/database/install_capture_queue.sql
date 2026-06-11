-- heratio#1205 - capture queue: the actionable workflow on top of the at-risk
-- register. One row per information object that an operator has queued for
-- capture/digitisation, with a workflow status, a priority snapshot, an optional
-- note and assignee, and queued/captured timestamps. Status values are sourced
-- from the Dropdown Manager group `capture_queue_status` (no ENUM, no hardcoded
-- option list). Writes are confined to this table; no AtoM base tables are touched.
CREATE TABLE IF NOT EXISTS `ahg_capture_queue` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `information_object_id` INT NOT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'queued',
  `priority_score` INT NOT NULL DEFAULT 0,
  `note` TEXT NULL,
  `assigned_to` VARCHAR(190) NULL,
  `queued_at` TIMESTAMP NULL DEFAULT NULL,
  `captured_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ahg_capture_queue_io_unique` (`information_object_id`),
  KEY `ahg_capture_queue_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
