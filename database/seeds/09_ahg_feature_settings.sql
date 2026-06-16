-- AHG feature-toggle defaults (ahg_settings).
--
-- These are app feature switches read via the settings layer, separate from the
-- atom_plugin registry seeded in 08_base_plugins.sql. They are NOT auto-created
-- by package boot, so without seeding them the feature reads its hard-coded
-- default (off) on a fresh install.
--
-- spectrum_enabled: the Spectrum collections-management module. The reference
-- install runs with it ON, and every /admin/spectrum/* route is gated by
-- EnsureSpectrumEnabled (404 when off) while the nav hides Spectrum entries when
-- off — so a fresh install should default it ON. INSERT IGNORE (UNIQUE
-- setting_key) makes this a no-op when an operator has already set the value,
-- so re-running the seed never overrides a deliberate "off".

INSERT IGNORE INTO `ahg_settings`
    (`setting_key`, `setting_value`, `setting_type`, `setting_group`, `description`, `is_sensitive`, `is_locked`, `created_at`, `updated_at`)
VALUES
    ('spectrum_enabled', 'true', 'boolean', 'spectrum', 'Enable Spectrum collections management', 0, 0, NOW(), NOW());
