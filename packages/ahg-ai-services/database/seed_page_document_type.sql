-- heratio#1584: document types for per-page classification (Dropdown Manager
-- taxonomy page_document_type). Idempotent; edit the list in /admin/dropdowns.
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
  ('page_document_type', 'Page document type', 'ai', 'letter',            'Letter',            10, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'form',              'Form',              20, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'invoice',           'Invoice',           30, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'purchase_order',    'Purchase order',    40, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'delivery_note',     'Delivery note',     50, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'medical_record',    'Medical record',    60, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'identity_document', 'Identity document', 70, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'report',            'Report',            80, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'minutes',           'Minutes',           90, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'newspaper_cutting', 'Newspaper cutting', 100, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'photograph',        'Photograph',        110, 0, 1, NOW(), NOW()),
  ('page_document_type', 'Page document type', 'ai', 'other',             'Other',             120, 1, 1, NOW(), NOW());
