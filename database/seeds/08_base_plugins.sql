-- ============================================================================
-- 08_base_plugins.sql - register the base (is_core=1) plugin set
-- ============================================================================
-- These are the foundation plugins that are ALWAYS installed and ENABLED from
-- the start of a fresh install. Everything else in atom_plugin is registered
-- and enabled one at a time by the administrator via /admin/ahgSettings/plugins.
--
-- Three of these (sfPropelPlugin, qbAclPlugin, sfPluginAdminPlugin) are legacy
-- registry names with no self-registering Laravel package, so without this seed
-- a fresh install has an almost-empty plugin registry, an empty Browse menu, and
-- a bare Plugin Management page.
--
-- atom_plugin.name is UNIQUE: INSERT IGNORE registers any missing rows, and the
-- trailing UPDATE force-enables the locked base set so the seed is self-healing
-- and idempotent (safe to re-run).
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later
-- ============================================================================

INSERT IGNORE INTO `atom_plugin`
    (`name`, `class_name`, `version`, `description`, `category`, `is_enabled`, `is_core`, `is_locked`, `admin_only`, `status`, `load_order`, `created_at`, `updated_at`)
VALUES
    ('ahgCorePlugin',              'ahgCorePluginConfiguration',              '1.0.0',  'Core utilities and shared services for AHG plugins',                                                  'core',  1, 1, 1, 0, 'enabled', 1,   NOW(), NOW()),
    ('sfPropelPlugin',             'sfPropelPlugin',                          NULL,     'Propel ORM integration',                                                                              'core',  1, 1, 1, 0, 'enabled', 10,  NOW(), NOW()),
    ('qbAclPlugin',                'qbAclPlugin',                             NULL,     'Access Control List management',                                                                      'core',  1, 1, 1, 0, 'enabled', 20,  NOW(), NOW()),
    ('sfPluginAdminPlugin',        'sfPluginAdminPlugin',                     NULL,     'Legacy plugin-admin UI. Heratio uses /admin/ahgSettings/plugins instead.',                            'core',  1, 1, 1, 0, 'enabled', 40,  NOW(), NOW()),
    ('ahgSettingsPlugin',          'ahgSettingsPluginConfiguration',          '1.0.1',  'AHG Settings - central configuration UI for theme, branding, plugins, audit, integrity and per-plugin tunables.', 'admin', 1, 1, 0, 0, 'enabled', 50,  NOW(), NOW()),
    ('ahgSecurityClearancePlugin', 'ahgSecurityClearancePluginConfiguration', '1.2.9',  'Security classification, user clearance, embargo, watermarking and extended rights management',       'ahg',   1, 1, 1, 0, 'enabled', 100, NOW(), NOW()),
    ('ahgThemeB5Plugin',           'arAHGThemeB5Plugin',                      '1.14.21','Modern Bootstrap 5 theme for Heratio',                                                                'ahg',   1, 1, 1, 0, 'enabled', 100, NOW(), NOW());

-- Force-enable the locked base set (self-heals an install where a base plugin
-- was registered disabled by some other path).
UPDATE `atom_plugin`
SET `is_enabled` = 1, `is_core` = 1, `status` = 'enabled'
WHERE `name` IN (
    'ahgCorePlugin', 'sfPropelPlugin', 'qbAclPlugin', 'sfPluginAdminPlugin',
    'ahgSettingsPlugin', 'ahgSecurityClearancePlugin', 'ahgThemeB5Plugin'
);
