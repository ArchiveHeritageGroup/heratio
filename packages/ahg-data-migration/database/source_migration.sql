-- heratio#1495: vendor source migration (hierarchy engine, preflight register).
-- Additive and idempotent. Applied by the 2026_11_22_100000 migration, because
-- the package install.sql is sentinel-gated and never re-runs on an existing install.

CREATE TABLE IF NOT EXISTS ahg_source_migration_run (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    source_file VARCHAR(1024) NOT NULL,
    source_sha256 CHAR(64) NOT NULL,
    mapping_sha256 CHAR(64) NOT NULL,
    register_sha256 CHAR(64) NOT NULL,
    register_json LONGTEXT NOT NULL,
    options_json TEXT NULL,
    decisions_json TEXT NULL,
    status VARCHAR(30) NOT NULL,
    blocker_count INT NOT NULL DEFAULT 0,
    acknowledged_by INT NULL,
    acknowledged_at DATETIME NULL,
    committed_at DATETIME NULL,
    records_created INT NOT NULL DEFAULT 0,
    error TEXT NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    INDEX idx_source_status (source, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ahg_source_migration_record (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT UNSIGNED NOT NULL,
    ref_hash CHAR(64) NOT NULL,
    ref_no VARCHAR(1024) NOT NULL,
    information_object_id INT NOT NULL,
    synthesised TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uk_run_ref (run_id, ref_hash),
    INDEX idx_ref_hash (ref_hash),
    INDEX idx_io (information_object_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, sort_order, is_active, metadata, created_at) VALUES
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'fonds',     'Fonds',      10, 1, '{"level":"Fonds"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'subfonds',  'Sub-fonds',  20, 1, '{"level":"Subfonds"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'collection','Collection', 30, 1, '{"level":"Collection"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'group',     'Group',      40, 1, '{"level":"Record group"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'subgroup',  'Sub-group',  50, 1, '{"level":"Subfonds","folds":true}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'series',    'Series',     60, 1, '{"level":"Series"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'subseries', 'Sub-series', 70, 1, '{"level":"Subseries"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'file',      'File',       80, 1, '{"level":"File"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'item',      'Item',       90, 1, '{"level":"Item"}', NOW()),
  ('source_level_calm', 'Calm level mapping', 'data-migration', 'piece',     'Piece',     100, 1, '{"decision":"level_piece"}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'archive',      'Archive',      10, 1, '{"import":true}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'persons',      'Persons',      20, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'corporate',    'Corporate',    30, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'places',       'Places',       40, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'subjects',     'Subjects',     50, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'accessions',   'Accessions',   60, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'location',     'Location',     70, 1, '{"import":false}', NOW()),
  ('source_record_type_calm', 'Calm record types', 'data-migration', 'conservation', 'Conservation', 80, 1, '{"import":false}', NOW()),
  ('source_migration_piece_policy', 'Source level with no equivalent', 'data-migration', 'fold_to_item',      'Fold into Item and keep the source level as a property', 10, 1, NULL, NOW()),
  ('source_migration_piece_policy', 'Source level with no equivalent', 'data-migration', 'create_piece_term', 'Add a Piece term to the levels of description',          20, 1, NULL, NOW()),
  ('source_migration_date_policy', 'Source date handling', 'data-migration', 'keep_both',    'Keep the display expression and normalised start and end', 10, 1, NULL, NOW()),
  ('source_migration_date_policy', 'Source date handling', 'data-migration', 'display_only', 'Keep the display expression only',                         20, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'preflighted',  'Preflighted',  10, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'rejected',     'Rejected',     20, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'acknowledged', 'Acknowledged', 30, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'running',      'Running',      40, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'committed',    'Committed',    50, 1, NULL, NOW()),
  ('source_migration_status', 'Source migration status', 'data-migration', 'failed',       'Failed',       60, 1, NULL, NOW());
