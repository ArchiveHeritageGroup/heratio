-- ============================================================================
-- ahg-core — user_plugin_preference  (issue #40 "Plugin per user")
-- ============================================================================
-- Lets users hide globally-enabled plugins from THEIR own nav. Default:
-- show every globally-enabled plugin. A row in this table marks a plugin as
-- "hidden for this user". Re-enabled by deleting the row.
--
-- This is per-USER (visibility / clutter), not per-ROLE (capability — that's
-- ACL's job in qb_acl_permission).
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_plugin_preference` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT NOT NULL,
    `plugin_name`  VARCHAR(255) NOT NULL COMMENT 'matches atom_plugin.name',
    `is_hidden`    TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1=hidden in nav, 0=visible',
    `created_at`   DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_plugin` (`user_id`, `plugin_name`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_plugin_name` (`plugin_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
