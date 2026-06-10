-- heratio#1193 - Gaussian-splat photoreal captures (.ply/.splat/.ksplat) for the web viewer.
CREATE TABLE IF NOT EXISTS `ahg_gaussian_splat` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(96) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `source_filename` VARCHAR(255) NULL,
  `file_name` VARCHAR(160) NULL COMMENT 'stored filename under heratio.splats_path',
  `information_object_id` INT NULL COMMENT 'museum object this capture belongs to (#1193 link)',
  `format` VARCHAR(12) NULL COMMENT 'ply | splat | ksplat',
  `size_bytes` BIGINT UNSIGNED NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'ready' COMMENT 'ready | failed',
  `error` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ahg_gaussian_splat_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
