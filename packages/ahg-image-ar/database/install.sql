-- ahg-image-ar — schema install
-- One row per IO holding both the .mind tracker target and the MP4 overlay.

CREATE TABLE IF NOT EXISTS `object_image_ar` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `object_id` INT NOT NULL,
    `digital_object_id` INT DEFAULT NULL COMMENT 'source image used for both target compile and overlay',
    `mp4_filename` VARCHAR(255) DEFAULT NULL,
    `mp4_path` VARCHAR(500) DEFAULT NULL COMMENT 'web-relative path to overlay MP4',
    `mp4_size` BIGINT DEFAULT NULL,
    `mp4_motion` VARCHAR(32) DEFAULT 'zoom_in',
    `mp4_duration_secs` DECIMAL(4,2) DEFAULT '5.00',
    `mind_filename` VARCHAR(255) DEFAULT NULL,
    `mind_path` VARCHAR(500) DEFAULT NULL COMMENT 'web-relative path to .mind tracker target',
    `mind_size` BIGINT DEFAULT NULL,
    `mind_compile_secs` DECIMAL(6,2) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_object_id` (`object_id`),
    KEY `idx_digital_object_id` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `image_ar_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` VARCHAR(32) DEFAULT 'string',
    `description` VARCHAR(500) DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
