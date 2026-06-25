-- ahg-rdm dropdown seed (#1338): dataset lifecycle statuses.
-- INSERT IGNORE so re-runs never duplicate. Views read these from ahg_dropdown
-- (taxonomy 'dataset_status') - no hardcoded <option> lists, no ENUM column.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
 ('dataset_status', 'Dataset Status', 'rdm', 'draft',      'Draft',                 '#6c757d', NULL, 10, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'scanning',   'POPIA scanning',        '#0dcaf0', NULL, 20, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'review',     'Awaiting human review', '#ffc107', NULL, 30, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'restricted', 'Restricted / embargoed','#dc3545', NULL, 40, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'published',  'Published (open)',      '#198754', NULL, 50, 1, NOW());
