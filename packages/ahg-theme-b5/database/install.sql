-- ============================================================================
-- ahg-theme-b5 — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgThemeB5Plugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- AHG Theme B5 Plugin - Data Only
-- Version: 1.0.0
-- Tables are created by atom-framework/database/install.sql

-- Register theme plugin

-- Default AHG Settings
INSERT IGNORE INTO ahg_settings (setting_key, setting_value, setting_type, setting_group) VALUES
('default_sector', 'archive', 'string', 'general'),
('enable_glam_browse', '1', 'boolean', 'general'),
('enable_3d_viewer', '1', 'boolean', 'features'),
('enable_iiif', '1', 'boolean', 'features'),
('research_booking_enabled', '1', 'boolean', 'features'),
('audit_retention_days', '365', 'integer', 'compliance');

SET FOREIGN_KEY_CHECKS = 1;
