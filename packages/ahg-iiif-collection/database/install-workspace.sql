-- ============================================================================
-- ahg-iiif-collection - workspace persistence schema (issue #699)
-- ============================================================================
-- Per-user Mirador 4 workspace state, saved via exportConfig() and restored
-- via importMiradorState() (importConfig). Multiple named workspaces per
-- user are supported; one may be flagged as the default-on-load.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `ahg_iiif_workspace` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'users.id (Auth::id()); rows are tightly scoped to the owning user',
  `name` VARCHAR(255) NOT NULL COMMENT 'User-supplied label, e.g. Research session 2026-05-25',
  `config_json` JSON NOT NULL COMMENT 'Mirador.exportConfig() output (windows, layout, viewport, etc.)',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'When 1, auto-load this workspace on next viewer open',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ahg_iiif_workspace_user` (`user_id`),
  KEY `idx_ahg_iiif_workspace_user_default` (`user_id`, `is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
