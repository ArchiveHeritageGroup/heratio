-- ahg-image-ar — schema install
-- One row per IO holding the AI-generated MP4 + the inputs that produced it.

CREATE TABLE IF NOT EXISTS `object_image_ar` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `object_id` INT NOT NULL,
    `digital_object_id` INT DEFAULT NULL,
    `mp4_filename` VARCHAR(255) DEFAULT NULL,
    `mp4_path` VARCHAR(500) DEFAULT NULL,
    `mp4_size` BIGINT DEFAULT NULL,
    `mp4_duration_secs` DECIMAL(4,2) DEFAULT NULL,
    `mp4_width` INT DEFAULT NULL,
    `mp4_height` INT DEFAULT NULL,
    `mp4_fps` INT DEFAULT NULL,
    `mp4_motion` VARCHAR(64) DEFAULT NULL COMMENT 'free-text label, e.g. ai-svd or ai-cogvideox-prompt',
    `ai_model` VARCHAR(64) DEFAULT NULL COMMENT 'svd, svd-xt, cogvideox-2b, wan-2.1, etc.',
    `ai_prompt` TEXT DEFAULT NULL COMMENT 'text prompt sent to the model (NULL for SVD which is image-only)',
    `ai_seed` BIGINT DEFAULT NULL,
    `ai_motion_bucket_id` INT DEFAULT NULL COMMENT 'SVD-specific motion strength (1..255)',
    `generation_secs` DECIMAL(7,2) DEFAULT NULL COMMENT 'wall time on the AI server',
    -- Legacy MindAR-AR columns retained nullable so existing rows survive a redeploy.
    `mind_filename` VARCHAR(255) DEFAULT NULL,
    `mind_path` VARCHAR(500) DEFAULT NULL,
    `mind_size` BIGINT DEFAULT NULL,
    `mind_compile_secs` DECIMAL(6,2) DEFAULT NULL,
    `created_by` INT DEFAULT NULL,
    `created_at` DATETIME DEFAULT NULL,
    `updated_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_object_id` (`object_id`),
    KEY `idx_digital_object_id` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Per-column upgrades for older installs are handled in
-- AhgImageArServiceProvider::ensureInstalled() via Schema::hasColumn(),
-- since MySQL 8.0 does not support ADD COLUMN IF NOT EXISTS.

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
