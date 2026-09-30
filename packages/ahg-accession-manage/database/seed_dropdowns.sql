-- ahg-accession-manage dropdown seed: CAAIS profile vocabularies (heratio#1514).
-- CAAIS asks each repository to maintain its own controlled vocabulary for
-- these elements; the values below are the examples CAAIS 1.0 gives, managed
-- in the Dropdown Manager (/admin/dropdowns) so an institution adds, renames or
-- retires terms without a code change. INSERT IGNORE on (taxonomy, code).
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
  -- 3.2.1 Extent type
  ('caais_extent_type', 'CAAIS Extent Type', 'accession', 'extent_received', 'Extent received', 10, 1, 1, NOW(), NOW()),
  ('caais_extent_type', 'CAAIS Extent Type', 'accession', 'extent_retained', 'Extent retained', 20, 0, 1, NOW(), NOW()),
  ('caais_extent_type', 'CAAIS Extent Type', 'accession', 'extent_removed',  'Extent removed',  30, 0, 1, NOW(), NOW()),
  -- 3.2.2 Unit of measure
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'linear_metres', 'Linear metres', 10, 1, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'centimetres',   'Centimetres',   20, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'boxes',         'Boxes',         30, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'folders',       'Folders',       40, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'items',         'Items',         50, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'volumes',       'Volumes',       60, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'carriers',      'Carriers',      70, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'files',         'Digital files', 80, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'megabytes',     'Megabytes',     90, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'gigabytes',     'Gigabytes',    100, 0, 1, NOW(), NOW()),
  ('caais_extent_unit', 'CAAIS Extent Unit', 'accession', 'terabytes',     'Terabytes',    110, 0, 1, NOW(), NOW()),
  -- 3.2.3 Content type
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'architectural_technical_drawings', 'Architectural and technical drawings', 10, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'artefacts',            'Artefacts',            20, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'cartographic',         'Cartographic materials', 30, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'datasets',             'Datasets',             40, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'graphic',              'Graphic materials',    50, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'interactive',          'Interactive resources', 60, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'moving_images',        'Moving images',        70, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'natural_objects',      'Natural objects',      80, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'philatelic',           'Philatelic records',   90, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'photographs',          'Photographs',         100, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'sound_recordings',     'Sound recordings',    110, 0, 1, NOW(), NOW()),
  ('caais_content_type', 'CAAIS Content Type', 'accession', 'textual',              'Textual records',     120, 1, 1, NOW(), NOW()),
  -- 3.2.4 Carrier type
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'paper',          'Paper',          10, 1, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'audio_cassette', 'Audio cassette', 20, 0, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'videotape',      'Videotape',      30, 0, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'film_reel',      'Film reel',      40, 0, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'disc',           'Disc',           50, 0, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'external_drive', 'External drive', 60, 0, 1, NOW(), NOW()),
  ('caais_carrier_type', 'CAAIS Carrier Type', 'accession', 'online_transfer','Online transfer (no carrier)', 70, 0, 1, NOW(), NOW()),
  -- 2.1.6 Source confidentiality
  ('caais_source_confidentiality', 'CAAIS Source Confidentiality', 'accession', 'internal_use_only', 'Internal use only',                10, 0, 1, NOW(), NOW()),
  ('caais_source_confidentiality', 'CAAIS Source Confidentiality', 'accession', 'not_public',        'Not for public access',            20, 0, 1, NOW(), NOW()),
  ('caais_source_confidentiality', 'CAAIS Source Confidentiality', 'accession', 'anonymous',         'Donor wishes to remain anonymous', 30, 0, 1, NOW(), NOW()),
  -- 3.4 Language of material (ISO 639 codes; add more in the Dropdown Manager)
  ('caais_language', 'CAAIS Language of Material', 'accession', 'en',  'English',              10, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'fr',  'French',               20, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'es',  'Spanish',              30, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'pt',  'Portuguese',           40, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'de',  'German',               50, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'nl',  'Dutch',                60, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'it',  'Italian',              70, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'ar',  'Arabic',               80, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'zh',  'Chinese',              90, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'ja',  'Japanese',            100, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'hi',  'Hindi',               110, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'sw',  'Swahili',             120, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'af',  'Afrikaans',           130, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'zu',  'Zulu',                140, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'cr',  'Cree',                150, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'iu',  'Inuktitut',           160, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'la',  'Latin',               170, 0, 1, NOW(), NOW()),
  ('caais_language', 'CAAIS Language of Material', 'accession', 'zxx', 'No linguistic content', 900, 0, 1, NOW(), NOW()),
  -- 4.3.1 Preservation requirement type
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'physical_condition',  'Physical condition',  10, 0, 1, NOW(), NOW()),
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'conservation',        'Conservation',        20, 0, 1, NOW(), NOW()),
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'supplies',            'Supplies',            30, 0, 1, NOW(), NOW()),
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'labour',              'Labour',              40, 0, 1, NOW(), NOW()),
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'software_dependency', 'Software dependency', 50, 0, 1, NOW(), NOW()),
  ('caais_preservation_type', 'CAAIS Preservation Requirement Type', 'accession', 'technical_access',    'Technical access',    60, 0, 1, NOW(), NOW()),
  -- 5.1.1 Event type. physical_transfer and legal_transfer are the two CAAIS
  -- makes mandatory; the profile's completeness check looks for these codes.
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'physical_transfer',          'Physical transfer',                        10, 1, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'legal_transfer',             'Legal transfer',                           20, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'deed_of_gift_signed',        'Deed of gift signed',                      30, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'transfer_agreement_signed',  'Transfer agreement signed',                40, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'ceremonial_protocol',        'Ceremonial protocol acknowledging value',  50, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'reboxing_started',           'Reboxing started',                         60, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'reboxing_completed',         'Reboxing completed',                       70, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'archival_appraisal',         'Archival appraisal',                       80, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'disk_image_created',         'Disk image created',                       90, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'checksums_created',          'Checksums created',                       100, 0, 1, NOW(), NOW()),
  ('caais_event_type', 'CAAIS Event Type', 'accession', 'deaccessioned',              'Deaccessioned',                           110, 0, 1, NOW(), NOW()),
  -- 7.2.1 Creation or revision type
  ('caais_revision_type', 'CAAIS Creation or Revision Type', 'accession', 'created', 'Record created', 10, 0, 1, NOW(), NOW()),
  ('caais_revision_type', 'CAAIS Creation or Revision Type', 'accession', 'revised', 'Record revised', 20, 1, 1, NOW(), NOW());
