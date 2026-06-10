-- heratio#1202 - storytelling engine: saved/published "stories of the collection".
-- One row per story. status drives public visibility (draft = staff-only, published = public).
CREATE TABLE IF NOT EXISTS `ahg_story` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `theme` VARCHAR(200) NULL,
  `body` MEDIUMTEXT NOT NULL,
  `object_ids` TEXT NULL,
  `sources_json` TEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ahg_story_slug_unique` (`slug`),
  KEY `ahg_story_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
