-- ============================================================================
-- ahg-ftp-upload — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgFtpPlugin/database/install.sql
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

-- =====================================================
-- ahgFtpPlugin - FTP/SFTP Upload for CSV Import
-- No tables needed - config stored in ahg_settings
-- =====================================================

-- Register plugin in atom_plugin (idempotent)
INSERT IGNORE INTO atom_plugin (name, class_name, version, description, category, is_enabled, is_core, is_locked, load_order, created_at, updated_at)
VALUES ('ahgFtpPlugin', 'ahgFtpPluginConfiguration', '1.0.0', 'Browser-based FTP/SFTP upload for CSV import digital objects', 'import', 1, 0, 0, 100, NOW(), NOW());

UPDATE atom_plugin SET version = '1.0.0', description = 'Browser-based FTP/SFTP upload for CSV import digital objects', category = 'import', updated_at = NOW() WHERE name = 'ahgFtpPlugin';

-- =====================================================
-- Menu entry: Import > FTP Upload
-- Inserts as last child of the Import menu node (name='import')
-- Uses MPTT: shift rgt values to make room, then insert
-- =====================================================
SET @import_rgt = (SELECT rgt FROM menu WHERE name = 'import' LIMIT 1);

-- Only insert if not already present
SET @exists = (SELECT COUNT(*) FROM menu WHERE name = 'ftpUpload');

-- Make room in the nested set: shift nodes to the right
UPDATE menu SET rgt = rgt + 2 WHERE rgt >= @import_rgt AND @exists = 0;
UPDATE menu SET lft = lft + 2 WHERE lft > @import_rgt AND @exists = 0;

-- Insert the menu node as last child of Import
INSERT IGNORE INTO menu (parent_id, name, path, lft, rgt, created_at, updated_at, source_culture, serial_number)
SELECT id, 'ftpUpload', 'ftpUpload/index', @import_rgt, @import_rgt + 1, NOW(), NOW(), 'en', 0
FROM menu WHERE name = 'import' AND @exists = 0
LIMIT 1;

-- Insert the i18n label
INSERT IGNORE INTO menu_i18n (id, culture, label, description)
SELECT m.id, 'en', 'FTP Upload', 'Upload digital objects via FTP/SFTP for CSV import'
FROM menu m WHERE m.name = 'ftpUpload' AND NOT EXISTS (
    SELECT 1 FROM menu_i18n mi WHERE mi.id = m.id AND mi.culture = 'en'
);

-- =====================================================
-- Default settings (group: ftp)
-- =====================================================
INSERT IGNORE INTO ahg_settings (setting_key, setting_value, setting_group, created_at, updated_at)
VALUES
('ftp_protocol', 'sftp', 'ftp', NOW(), NOW()),
('ftp_host', '', 'ftp', NOW(), NOW()),
('ftp_port', '22', 'ftp', NOW(), NOW()),
('ftp_username', '', 'ftp', NOW(), NOW()),
('ftp_password', '', 'ftp', NOW(), NOW()),
('ftp_remote_path', '/uploads', 'ftp', NOW(), NOW()),
('ftp_passive_mode', 'true', 'ftp', NOW(), NOW());

SET FOREIGN_KEY_CHECKS = 1;
