-- ============================================================================
-- ahg-data-migration - dropdown seeds
-- ============================================================================
-- Issue #740 - Data-migration exports parity (PSIS twin atom-ahg-plugins#86).
--
-- Three taxonomies are seeded so the exports UI does not hard-code enums:
--   * export_format          - format choices for the export pages
--   * sheet_type             - sheet semantics returned by detectSheets
--   * data_migration_sector  - sector picker for sectorExport
-- ============================================================================

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
  ('export_format', 'Export format', 'data-migration', 'csv',     'CSV (AHG extended)',  NULL, 'fa-file-csv',  10, 1, NOW()),
  ('export_format', 'Export format', 'data-migration', 'ead',     'EAD 2002 XML',        NULL, 'fa-file-code', 20, 1, NOW()),
  ('export_format', 'Export format', 'data-migration', 'sector',  'Sector-specific CSV', NULL, 'fa-file-export', 30, 1, NOW()),
  ('export_format', 'Export format', 'data-migration', 'json',    'JSON',                NULL, 'fa-file-code', 40, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
  ('sheet_type', 'Sheet type', 'data-migration', 'records',    'Records (one row per object)',     NULL, NULL, 10, 1, NOW()),
  ('sheet_type', 'Sheet type', 'data-migration', 'authority',  'Authority list',                   NULL, NULL, 20, 1, NOW()),
  ('sheet_type', 'Sheet type', 'data-migration', 'taxonomy',   'Taxonomy / controlled vocabulary', NULL, NULL, 30, 1, NOW()),
  ('sheet_type', 'Sheet type', 'data-migration', 'relations',  'Relations',                        NULL, NULL, 40, 1, NOW()),
  ('sheet_type', 'Sheet type', 'data-migration', 'unknown',    'Unknown / skip',                   NULL, NULL, 50, 1, NOW());

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
  ('data_migration_sector', 'Sector', 'data-migration', 'archive', 'Archives (ISAD-G)',          NULL, 'fa-folder-open',  10, 1, NOW()),
  ('data_migration_sector', 'Sector', 'data-migration', 'museum',  'Museum (Spectrum)',          NULL, 'fa-landmark',     20, 1, NOW()),
  ('data_migration_sector', 'Sector', 'data-migration', 'library', 'Library (MARC / RDA)',       NULL, 'fa-book',         30, 1, NOW()),
  ('data_migration_sector', 'Sector', 'data-migration', 'gallery', 'Gallery (CCO / VRA)',        NULL, 'fa-palette',      40, 1, NOW()),
  ('data_migration_sector', 'Sector', 'data-migration', 'dam',     'Digital Assets (DC)',        NULL, 'fa-photo-film',   50, 1, NOW());
