-- ahg-image-animate — schema install
-- Adds two tables:
--   object_image_animation     — one row per generated MP4 clip per IO
--   image_animate_settings     — admin-tunable defaults + feature toggle
-- Mirrors the object_3d_model / viewer_3d_settings layout used by ahg-3d-model.

CREATE TABLE IF NOT EXISTS `object_image_animation` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `object_id` INT NOT NULL,
    `digital_object_id` INT DEFAULT NULL COMMENT 'source digital_object row',
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL COMMENT 'web-relative path under uploads',
    `file_size` BIGINT DEFAULT NULL,
    `mime_type` VARCHAR(50) DEFAULT 'video/mp4',
    `mode` VARCHAR(32) DEFAULT 'kenburns' COMMENT 'kenburns, parallax (future)',
    `motion` VARCHAR(32) DEFAULT 'zoom_in' COMMENT 'zoom_in, zoom_out, pan_lr, pan_rl, ken_burns_diagonal',
    `duration_secs` DECIMAL(4,2) DEFAULT '5.00',
    `fps` INT DEFAULT 25,
    `width` INT DEFAULT 1280,
    `height` INT DEFAULT 720,
    `poster_path` VARCHAR(500) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_object_id` (`object_id`),
    KEY `idx_digital_object_id` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `image_animate_settings` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `setting_type` VARCHAR(32) DEFAULT 'string' COMMENT 'string, integer, decimal, boolean',
    `description` VARCHAR(500) DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
