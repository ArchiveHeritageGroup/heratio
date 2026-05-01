-- ============================================================================
-- ahg-core — user_plugin_grant  (issue #40 c5: admin capability layer)
-- ============================================================================
-- Layered with user_plugin_preference (created in migration 001):
--
--   user_plugin_grant       — "what is this user ALLOWED to use"  (admin sets)
--   user_plugin_preference  — "what does this user CHOOSE to see" (user sets)
--
-- Visibility resolution (in MenuService::isPluginEnabled):
--   1. globally enabled (atom_plugin.is_enabled = 1)              [admin]
--   2. NOT explicitly denied for this user (user_plugin_grant)    [admin]
--   3. NOT user-hidden (user_plugin_preference.is_hidden = 1)     [user]
--
-- Mode column lets admins say:
--   'allow'     — user IS allowed (overrides default-deny if mode=deny)
--   'deny'      — user is NOT allowed regardless of global state
--   'inherit'   — fall back to global (the default; row absence = inherit)
--
-- A row is only stored when mode != 'inherit', so the table stays small.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `user_plugin_grant` (
    `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`      INT NOT NULL,
    `plugin_name`  VARCHAR(255) NOT NULL COMMENT 'matches atom_plugin.name',
    `mode`         VARCHAR(16) NOT NULL DEFAULT 'allow'
                   COMMENT 'allow | deny | inherit',
    `granted_by`   INT NULL COMMENT 'admin user_id who set this',
    `created_at`   DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_plugin` (`user_id`, `plugin_name`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_plugin_name` (`plugin_name`),
    KEY `idx_mode` (`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
