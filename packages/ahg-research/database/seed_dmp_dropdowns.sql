-- heratio#1222 - Research OS: Data Management Plan (DMP) Builder - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Two taxonomies, both surfaced in the
-- Dropdown Manager and both VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   dmp_status            - the lifecycle status of a plan.
--   dmp_funder_template   - an OPTIONAL funder hint chosen on a plan. The funder
--                           is recorded as DATA, jurisdiction-neutral. The
--                           entries below (generic, Horizon Europe, NSF,
--                           Wellcome, NRF) are selectable EXAMPLES, not
--                           assumptions about where a researcher applies. The
--                           DEFAULT is the generic, jurisdiction-neutral entry.

-- ---------------------------------------------------------------------------
-- DMP lifecycle status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('dmp_status', 'DMP Status', 'research', 'draft',      'Draft',      'secondary', 'pencil-alt',   10, 1, 1, NOW()),
('dmp_status', 'DMP Status', 'research', 'in_review',  'In Review',  'warning',   'eye',          20, 0, 1, NOW()),
('dmp_status', 'DMP Status', 'research', 'approved',   'Approved',   'info',      'check',        30, 0, 1, NOW()),
('dmp_status', 'DMP Status', 'research', 'published',  'Published',  'success',   'globe',        40, 0, 1, NOW()),
('dmp_status', 'DMP Status', 'research', 'superseded', 'Superseded', 'dark',      'archive',      50, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Optional funder template hint (examples, not assumptions)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('dmp_funder_template', 'DMP Funder Template', 'research', 'generic',        'Generic (jurisdiction-neutral)',  'secondary', 'file-alt', 10, 1, 1, NOW()),
('dmp_funder_template', 'DMP Funder Template', 'research', 'horizon_europe', 'Horizon Europe (example)',        'info',      'file-alt', 20, 0, 1, NOW()),
('dmp_funder_template', 'DMP Funder Template', 'research', 'nsf',            'NSF (example)',                   'info',      'file-alt', 30, 0, 1, NOW()),
('dmp_funder_template', 'DMP Funder Template', 'research', 'wellcome',       'Wellcome (example)',              'info',      'file-alt', 40, 0, 1, NOW()),
('dmp_funder_template', 'DMP Funder Template', 'research', 'nrf',            'NRF (example)',                   'info',      'file-alt', 50, 0, 1, NOW());
