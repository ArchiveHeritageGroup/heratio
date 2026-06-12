-- heratio#1222 - Research OS: Research Outputs register - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Three taxonomies, all surfaced in the
-- Dropdown Manager and all VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   research_output_type            - the kind of scholarly output.
--   research_output_identifier_type - the persistent-identifier scheme. The
--                                     resolver (ResearchOutputService) turns a
--                                     bare value into a resolvable URL, e.g. a
--                                     doi -> https://doi.org/{value}. That is a
--                                     link, not an external API call.
--   research_output_status          - the lifecycle status of an output.
--
-- International and jurisdiction-neutral: no value here assumes any one country,
-- funder or publisher.

-- ---------------------------------------------------------------------------
-- Output type
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_output_type', 'Research Output Type', 'research', 'journal_article', 'Journal article', 'primary',   'file-alt',      10, 1, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'dataset',         'Dataset',         'info',      'database',      20, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'software',        'Software',        'success',   'code',          30, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'presentation',    'Presentation',    'warning',   'chalkboard',    40, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'thesis',          'Thesis',          'secondary', 'graduation-cap',50, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'report',          'Report',          'secondary', 'file-contract', 60, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'chapter',         'Book chapter',    'dark',      'book',          70, 0, 1, NOW()),
('research_output_type', 'Research Output Type', 'research', 'other',           'Other',           'secondary', 'asterisk',      80, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Persistent-identifier type
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_output_identifier_type', 'Research Output Identifier Type', 'research', 'doi',    'DOI',    'primary',   'fingerprint', 10, 1, 1, NOW()),
('research_output_identifier_type', 'Research Output Identifier Type', 'research', 'handle', 'Handle', 'info',      'link',        20, 0, 1, NOW()),
('research_output_identifier_type', 'Research Output Identifier Type', 'research', 'isbn',   'ISBN',   'secondary', 'barcode',     30, 0, 1, NOW()),
('research_output_identifier_type', 'Research Output Identifier Type', 'research', 'url',    'URL',     'info',      'globe',       40, 0, 1, NOW()),
('research_output_identifier_type', 'Research Output Identifier Type', 'research', 'other',  'Other',   'secondary', 'asterisk',    50, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Output lifecycle status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_output_status', 'Research Output Status', 'research', 'planned',     'Planned',     'secondary', 'pencil-alt', 10, 1, 1, NOW()),
('research_output_status', 'Research Output Status', 'research', 'in_progress', 'In progress', 'warning',   'spinner',    20, 0, 1, NOW()),
('research_output_status', 'Research Output Status', 'research', 'published',   'Published',   'success',   'globe',      30, 0, 1, NOW());
