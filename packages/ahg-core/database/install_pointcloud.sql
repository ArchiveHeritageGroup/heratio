-- heratio#1183 - point-cloud (.las/.laz) -> Potree octree for the web viewer.
CREATE TABLE IF NOT EXISTS `ahg_point_cloud` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `source_filename` VARCHAR(255) NULL,
  `octree_dir` VARCHAR(96) NULL COMMENT 'subdir under heratio.pointclouds_path holding the octree',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, ready, failed',
  `point_count` BIGINT UNSIGNED NULL,
  `error` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ahg_point_cloud_slug_unique` (`slug`),
  KEY `ahg_point_cloud_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
