-- ahg-rdm dropdown seed (#1338): dataset lifecycle statuses.
-- INSERT IGNORE so re-runs never duplicate. Views read these from ahg_dropdown
-- (taxonomy 'dataset_status') - no hardcoded <option> lists, no ENUM column.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
 ('dataset_status', 'Dataset Status', 'rdm', 'draft',      'Draft',                 '#6c757d', NULL, 10, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'scanning',   'POPIA scanning',        '#0dcaf0', NULL, 20, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'review',     'Awaiting human review', '#ffc107', NULL, 30, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'restricted', 'Restricted / embargoed','#dc3545', NULL, 40, 1, NOW()),
 ('dataset_status', 'Dataset Status', 'rdm', 'published',  'Published (open)',      '#198754', NULL, 50, 1, NOW());

-- Human-gate disposition (#1340): the access decision a reviewer applies after
-- confirming/dismissing findings. 'release' = open; blocked while PII unresolved.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
 ('rdm_disposition', 'Dataset Disposition', 'rdm', 'restrict',     'Restrict access',     '#dc3545', NULL, 10, 1, NOW()),
 ('rdm_disposition', 'Dataset Disposition', 'rdm', 'embargo',      'Embargo (time-limited)','#fd7e14', NULL, 20, 1, NOW()),
 ('rdm_disposition', 'Dataset Disposition', 'rdm', 'de-identify',  'De-identify then release','#0dcaf0', NULL, 30, 1, NOW()),
 ('rdm_disposition', 'Dataset Disposition', 'rdm', 'release',      'Release (open access)','#198754', NULL, 40, 1, NOW());
