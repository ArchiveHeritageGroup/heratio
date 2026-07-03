-- ahg-articles dropdown seed: the attachment "Type" taxonomy, managed in the
-- Dropdown Manager (/admin/dropdowns) so institutions add their own types.
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'guide',        'Guide',                 10, 1, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'template',     'Template',              20, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'checklist',    'Checklist / tick-sheet',30, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'worksheet',    'Worksheet',             40, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'report',       'Report',                50, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'dataset',      'Dataset',               60, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'presentation', 'Presentation',          70, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'policy',       'Policy',                80, 0, 1, NOW(), NOW()),
  ('blog_attachment_kind', 'Article Attachment Type', 'content', 'conference_paper', 'Conference Paper',  90, 0, 1, NOW(), NOW());
