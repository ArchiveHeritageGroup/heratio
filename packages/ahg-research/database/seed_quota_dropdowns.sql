-- heratio#1325 - Researcher quotas: Dropdown Manager seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent. Two VARCHAR-backed
-- taxonomies (never ENUM, never a hardcoded <option> list in a view):
--   quota_scope   - the scope a quota policy applies to.
--   quota_period  - the window a download quota is counted over.

INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
  ('quota_scope', 'Quota Scope', 'Research', 'global',  'Global default',              NULL, NULL, 10, 1, 1, NOW()),
  ('quota_scope', 'Quota Scope', 'Research', 'role',    'Per researcher type (role)',  NULL, NULL, 20, 0, 1, NOW()),
  ('quota_scope', 'Quota Scope', 'Research', 'user',    'Per researcher',              NULL, NULL, 30, 0, 1, NOW()),
  ('quota_scope', 'Quota Scope', 'Research', 'project', 'Per project',                 NULL, NULL, 40, 0, 1, NOW()),
  ('quota_period', 'Quota Period', 'Research', 'monthly', 'Per calendar month',        NULL, NULL, 10, 1, 1, NOW()),
  ('quota_period', 'Quota Period', 'Research', 'total',   'All-time total',            NULL, NULL, 20, 0, 1, NOW());
