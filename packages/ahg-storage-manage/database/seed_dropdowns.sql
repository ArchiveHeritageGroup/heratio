-- ahg-storage-manage dropdown seed: storage location types, managed in the
-- Dropdown Manager (/admin/dropdowns) so institutions add their own levels.
-- Capacity units reuse the existing capacity_unit taxonomy.
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
  ('storage_location_type', 'Storage Location Type', 'core', 'building',     'Building',     10, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'floor',        'Floor',        20, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'room',         'Room',         30, 1, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'aisle',        'Aisle',        40, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'bay',          'Bay',          50, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'rack',         'Rack',         60, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'shelf',        'Shelf',        70, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'container',    'Container',    80, 0, 1, NOW(), NOW()),
  ('storage_location_type', 'Storage Location Type', 'core', 'storage_unit', 'Storage Unit', 90, 0, 1, NOW(), NOW());
