-- ============================================================================
-- ahg-forms - install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgFormsPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install - Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- ahgFormsPlugin Database Schema
-- Configurable metadata entry forms
-- ============================================

-- Form Templates
CREATE TABLE IF NOT EXISTS ahg_form_template (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    form_type VARCHAR(68) COMMENT 'information_object, accession, actor, repository, custom' NOT NULL DEFAULT 'information_object',
    config_json JSON COMMENT 'Template-level configuration (sections, tabs, layout)',
    config TEXT NULL COMMENT 'Layout config written by FormsController / FormService (heratio#1537)',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System templates cannot be deleted',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    version INT NOT NULL DEFAULT 1,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_form_type (form_type),
    INDEX idx_is_default (is_default),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Fields
CREATE TABLE IF NOT EXISTS ahg_form_field (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    field_name VARCHAR(255) NOT NULL COMMENT 'Internal field identifier',
    field_type VARCHAR(137) COMMENT 'text, textarea, richtext, date, daterange, select, multiselect, autocomplete, checkbox, radio, file, hidden, heading, divider' NOT NULL DEFAULT 'text',
    label VARCHAR(255) NOT NULL,
    label_i18n JSON COMMENT 'Translated labels {"en": "Title", "af": "Titel"}',
    help_text TEXT,
    help_text_i18n JSON,
    placeholder VARCHAR(255),
    default_value TEXT,
    validation_rules JSON COMMENT '{"required": true, "minLength": 5, "maxLength": 255, "pattern": "regex"}',
    options_json JSON COMMENT 'For select/multiselect/radio: [{"value": "x", "label": "X"}]',
    autocomplete_source VARCHAR(255) COMMENT 'taxonomy:123 or actor:all or custom:endpoint',
    section_name VARCHAR(100) COMMENT 'Group fields into sections',
    tab_name VARCHAR(100) COMMENT 'Group sections into tabs',
    sort_order INT NOT NULL DEFAULT 0,
    is_repeatable TINYINT(1) NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_readonly TINYINT(1) NOT NULL DEFAULT 0,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    conditional_logic JSON COMMENT '{"field": "fieldName", "operator": "equals", "value": "x"}',
    css_class VARCHAR(255),
    width VARCHAR(38) COMMENT 'full, half, third, quarter' DEFAULT 'full',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_sort_order (sort_order),
    INDEX idx_section (section_name),
    INDEX idx_tab (tab_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Assignments (which template to use where)
CREATE TABLE IF NOT EXISTS ahg_form_assignment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    repository_id INT COMMENT 'NULL = all repositories',
    level_of_description_id INT COMMENT 'NULL = all levels (term_id from taxonomy)',
    collection_id INT COMMENT 'Specific collection/fonds to apply to',
    priority INT NOT NULL DEFAULT 100 COMMENT 'Higher priority wins when multiple match',
    inherit_to_children TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Apply to descendant records',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_repository_id (repository_id),
    INDEX idx_level_id (level_of_description_id),
    INDEX idx_collection_id (collection_id),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Field Mappings (connect form fields to AtoM fields)
CREATE TABLE IF NOT EXISTS ahg_form_field_mapping (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_id BIGINT UNSIGNED NOT NULL,
    target_table VARCHAR(100) NOT NULL COMMENT 'information_object, information_object_i18n, property, note, etc.',
    target_column VARCHAR(100) NOT NULL COMMENT 'title, scope_and_content, etc.',
    target_type_id INT COMMENT 'For property/note tables - the type taxonomy term ID',
    transformation VARCHAR(100) COMMENT 'Transformation function: uppercase, lowercase, date_format, etc.',
    transformation_config JSON COMMENT 'Config for transformation',
    is_i18n TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Whether this maps to i18n table',
    culture VARCHAR(10) DEFAULT 'en',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (field_id) REFERENCES ahg_form_field(id) ON DELETE CASCADE,
    INDEX idx_field_id (field_id),
    INDEX idx_target (target_table, target_column)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Draft Auto-save Storage
CREATE TABLE IF NOT EXISTS ahg_form_draft (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL COMMENT 'information_object, accession, etc.',
    object_id INT COMMENT 'NULL for new records',
    user_id INT NOT NULL,
    form_data JSON NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES ahg_form_template(id) ON DELETE CASCADE,
    INDEX idx_template_id (template_id),
    INDEX idx_object (object_type, object_id),
    INDEX idx_user_id (user_id),
    UNIQUE KEY uk_draft (template_id, object_type, object_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Form Submission Log (audit trail)
CREATE TABLE IF NOT EXISTS ahg_form_submission_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_id BIGINT UNSIGNED NOT NULL,
    object_type VARCHAR(50) NOT NULL,
    object_id INT NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(36) COMMENT 'create, update, autosave' NOT NULL,
    form_data JSON,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    INDEX idx_template_id (template_id),
    INDEX idx_object (object_type, object_id),
    INDEX idx_user_id (user_id),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED DATA: Pre-built Template Library
-- ============================================

-- ISAD-G Minimal Template
-- heratio#1556: seed each system template once; the fields below are added
-- only when this run created it, never to a stale LAST_INSERT_ID().
INSERT INTO ahg_form_template (name, description, form_type, is_system, config_json)
SELECT 'ISAD-G Minimal', 'Minimal ISAD(G) compliant form with essential fields only', 'information_object', 1,
'{"layout": "single", "sections": ["identity", "context", "content"], "submitLabel": "Save Record"}' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ahg_form_template WHERE name = 'ISAD-G Minimal' AND is_system = 1);

SET @isadg_minimal_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_minimal_id, 'title', 'text', 'Title', 'identity', 1, 1, 'A word, phrase or character that names the unit of description'),
ROW(@isadg_minimal_id, 'identifier', 'text', 'Reference Code', 'identity', 2, 1, 'Unique identifier for the unit of description'),
ROW(@isadg_minimal_id, 'levelOfDescription', 'select', 'Level of Description', 'identity', 3, 1, 'Position of the unit of description in the hierarchy'),
ROW(@isadg_minimal_id, 'extentAndMedium', 'textarea', 'Extent and Medium', 'identity', 4, 0, 'Physical extent and medium of the unit'),
ROW(@isadg_minimal_id, 'date', 'daterange', 'Dates', 'identity', 5, 0, 'Date(s) of the unit of description'),
ROW(@isadg_minimal_id, 'creators', 'autocomplete', 'Creator(s)', 'context', 6, 0, 'Name of entity responsible for creation'),
ROW(@isadg_minimal_id, 'repository', 'autocomplete', 'Repository', 'context', 7, 0, 'Institution holding the materials'),
ROW(@isadg_minimal_id, 'scopeAndContent', 'richtext', 'Scope and Content', 'content', 8, 0, 'Summary of the content and context')) AS v
WHERE @isadg_minimal_id IS NOT NULL;

-- Field mappings for ISAD-G Minimal
INSERT IGNORE INTO ahg_form_field_mapping (field_id, target_table, target_column, is_i18n)
SELECT id, 'information_object_i18n', 'title', 1 FROM ahg_form_field WHERE template_id = @isadg_minimal_id AND field_name = 'title';
INSERT IGNORE INTO ahg_form_field_mapping (field_id, target_table, target_column, is_i18n)
SELECT id, 'information_object', 'identifier', 0 FROM ahg_form_field WHERE template_id = @isadg_minimal_id AND field_name = 'identifier';
INSERT IGNORE INTO ahg_form_field_mapping (field_id, target_table, target_column, is_i18n)
SELECT id, 'information_object_i18n', 'extent_and_medium', 1 FROM ahg_form_field WHERE template_id = @isadg_minimal_id AND field_name = 'extentAndMedium';
INSERT IGNORE INTO ahg_form_field_mapping (field_id, target_table, target_column, is_i18n)
SELECT id, 'information_object_i18n', 'scope_and_content', 1 FROM ahg_form_field WHERE template_id = @isadg_minimal_id AND field_name = 'scopeAndContent';

-- ISAD-G Full Template
-- heratio#1556: seed each system template once; the fields below are added
-- only when this run created it, never to a stale LAST_INSERT_ID().
INSERT INTO ahg_form_template (name, description, form_type, is_system, config_json)
SELECT 'ISAD-G Full', 'Complete ISAD(G) form with all 26 elements across 7 areas', 'information_object', 1,
'{"layout": "tabs", "tabs": ["Identity", "Context", "Content & Structure", "Access", "Allied Materials", "Notes", "Description Control"]}' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ahg_form_template WHERE name = 'ISAD-G Full' AND is_system = 1);

SET @isadg_full_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

-- Identity Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'identifier', 'text', 'Reference Code (3.1.1)', 'Identity', 'Reference', 1, 1, 'Unique identifier for the unit of description'),
ROW(@isadg_full_id, 'title', 'text', 'Title (3.1.2)', 'Identity', 'Reference', 2, 1, 'A word, phrase or character that names the unit of description'),
ROW(@isadg_full_id, 'date', 'daterange', 'Date(s) (3.1.3)', 'Identity', 'Reference', 3, 1, 'Date(s) of the unit of description'),
ROW(@isadg_full_id, 'levelOfDescription', 'select', 'Level of Description (3.1.4)', 'Identity', 'Reference', 4, 1, 'Position of the unit in the hierarchy'),
ROW(@isadg_full_id, 'extentAndMedium', 'textarea', 'Extent and Medium (3.1.5)', 'Identity', 'Physical', 5, 1, 'Physical extent and medium of the unit')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Context Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'creators', 'autocomplete', 'Name of Creator(s) (3.2.1)', 'Context', 'Creator', 10, 0, 'Name of entity responsible for creation'),
ROW(@isadg_full_id, 'adminHistory', 'richtext', 'Administrative/Biographical History (3.2.2)', 'Context', 'Creator', 11, 0, 'History of the creator'),
ROW(@isadg_full_id, 'archivalHistory', 'richtext', 'Archival History (3.2.3)', 'Context', 'Provenance', 12, 0, 'Successive transfers of ownership'),
ROW(@isadg_full_id, 'acquisition', 'richtext', 'Immediate Source of Acquisition (3.2.4)', 'Context', 'Provenance', 13, 0, 'Source from which the unit was acquired')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Content and Structure Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'scopeAndContent', 'richtext', 'Scope and Content (3.3.1)', 'Content & Structure', 'Content', 20, 0, 'Summary of the content'),
ROW(@isadg_full_id, 'appraisal', 'richtext', 'Appraisal, Destruction and Scheduling (3.3.2)', 'Content & Structure', 'Content', 21, 0, 'Appraisal actions taken'),
ROW(@isadg_full_id, 'accruals', 'textarea', 'Accruals (3.3.3)', 'Content & Structure', 'Structure', 22, 0, 'Expected additions to the unit'),
ROW(@isadg_full_id, 'arrangement', 'richtext', 'System of Arrangement (3.3.4)', 'Content & Structure', 'Structure', 23, 0, 'Internal structure and filing system')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Access Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'accessConditions', 'richtext', 'Conditions Governing Access (3.4.1)', 'Access', 'Access', 30, 0, 'Legal or other access restrictions'),
ROW(@isadg_full_id, 'reproductionConditions', 'richtext', 'Conditions Governing Reproduction (3.4.2)', 'Access', 'Access', 31, 0, 'Restrictions on copying'),
ROW(@isadg_full_id, 'language', 'multiselect', 'Language/Scripts of Material (3.4.3)', 'Access', 'Technical', 32, 0, 'Languages and scripts present'),
ROW(@isadg_full_id, 'physicalCharacteristics', 'textarea', 'Physical Characteristics (3.4.4)', 'Access', 'Technical', 33, 0, 'Physical condition and requirements'),
ROW(@isadg_full_id, 'findingAids', 'textarea', 'Finding Aids (3.4.5)', 'Access', 'Technical', 34, 0, 'Finding aids available')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Allied Materials Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'locationOfOriginals', 'textarea', 'Existence/Location of Originals (3.5.1)', 'Allied Materials', 'Related', 40, 0, 'Location of originals if copies'),
ROW(@isadg_full_id, 'locationOfCopies', 'textarea', 'Existence/Location of Copies (3.5.2)', 'Allied Materials', 'Related', 41, 0, 'Location of any copies'),
ROW(@isadg_full_id, 'relatedUnits', 'textarea', 'Related Units of Description (3.5.3)', 'Allied Materials', 'Related', 42, 0, 'Related materials in same repository'),
ROW(@isadg_full_id, 'publicationNote', 'textarea', 'Publication Note (3.5.4)', 'Allied Materials', 'Related', 43, 0, 'Publications about or based on unit')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Notes Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'generalNote', 'richtext', 'Note (3.6.1)', 'Notes', 'Notes', 50, 0, 'Any additional information')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Description Control Area
INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@isadg_full_id, 'archivistNote', 'textarea', 'Archivists Note (3.7.1)', 'Description Control', 'Control', 60, 0, 'Sources consulted and methods'),
ROW(@isadg_full_id, 'rules', 'text', 'Rules or Conventions (3.7.2)', 'Description Control', 'Control', 61, 0, 'Standards applied'),
ROW(@isadg_full_id, 'descriptionIdentifier', 'text', 'Description Identifier', 'Description Control', 'Control', 62, 0, 'Unique identifier for description'),
ROW(@isadg_full_id, 'sources', 'textarea', 'Sources Used', 'Description Control', 'Control', 63, 0, 'Sources consulted')) AS v
WHERE @isadg_full_id IS NOT NULL;

-- Dublin Core Simple Template
-- heratio#1556: seed each system template once; the fields below are added
-- only when this run created it, never to a stale LAST_INSERT_ID().
INSERT INTO ahg_form_template (name, description, form_type, is_system, config_json)
SELECT 'Dublin Core Simple', 'Dublin Core 15 core elements', 'information_object', 1,
'{"layout": "single", "sections": ["core"]}' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ahg_form_template WHERE name = 'Dublin Core Simple' AND is_system = 1);

SET @dc_simple_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@dc_simple_id, 'title', 'text', 'Title', 'core', 1, 1, 'A name given to the resource'),
ROW(@dc_simple_id, 'creator', 'autocomplete', 'Creator', 'core', 2, 0, 'An entity primarily responsible for making the resource'),
ROW(@dc_simple_id, 'subject', 'autocomplete', 'Subject', 'core', 3, 0, 'Topic of the resource'),
ROW(@dc_simple_id, 'description', 'richtext', 'Description', 'core', 4, 0, 'An account of the resource'),
ROW(@dc_simple_id, 'publisher', 'text', 'Publisher', 'core', 5, 0, 'Entity responsible for making the resource available'),
ROW(@dc_simple_id, 'contributor', 'autocomplete', 'Contributor', 'core', 6, 0, 'Entity responsible for making contributions'),
ROW(@dc_simple_id, 'date', 'date', 'Date', 'core', 7, 0, 'A point or period of time associated with the resource'),
ROW(@dc_simple_id, 'type', 'select', 'Type', 'core', 8, 0, 'Nature or genre of the resource'),
ROW(@dc_simple_id, 'format', 'text', 'Format', 'core', 9, 0, 'File format, physical medium, or dimensions'),
ROW(@dc_simple_id, 'identifier', 'text', 'Identifier', 'core', 10, 0, 'Unambiguous reference to the resource'),
ROW(@dc_simple_id, 'source', 'text', 'Source', 'core', 11, 0, 'Related resource from which this is derived'),
ROW(@dc_simple_id, 'language', 'select', 'Language', 'core', 12, 0, 'Language of the resource'),
ROW(@dc_simple_id, 'relation', 'text', 'Relation', 'core', 13, 0, 'A related resource'),
ROW(@dc_simple_id, 'coverage', 'text', 'Coverage', 'core', 14, 0, 'Spatial or temporal topic of the resource'),
ROW(@dc_simple_id, 'rights', 'textarea', 'Rights', 'core', 15, 0, 'Information about rights held in and over the resource')) AS v
WHERE @dc_simple_id IS NOT NULL;

-- Accession Form Template
-- heratio#1556: seed each system template once; the fields below are added
-- only when this run created it, never to a stale LAST_INSERT_ID().
INSERT INTO ahg_form_template (name, description, form_type, is_system, config_json)
SELECT 'Accession Standard', 'Standard accession registration form', 'accession', 1,
'{"layout": "tabs", "tabs": ["Basic Info", "Donor", "Materials", "Processing"]}' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ahg_form_template WHERE name = 'Accession Standard' AND is_system = 1);

SET @accession_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@accession_id, 'accessionNumber', 'text', 'Accession Number', 'Basic Info', 'Identity', 1, 1, 'Unique accession identifier'),
ROW(@accession_id, 'accessionDate', 'date', 'Accession Date', 'Basic Info', 'Identity', 2, 1, 'Date materials were accessioned'),
ROW(@accession_id, 'title', 'text', 'Title', 'Basic Info', 'Identity', 3, 1, 'Title of the accession'),
ROW(@accession_id, 'acquisitionType', 'select', 'Acquisition Type', 'Basic Info', 'Identity', 4, 0, 'How materials were acquired'),
ROW(@accession_id, 'receivedExtentNumber', 'text', 'Extent (Number)', 'Basic Info', 'Extent', 5, 0, 'Numeric extent'),
ROW(@accession_id, 'receivedExtentUnit', 'select', 'Extent (Unit)', 'Basic Info', 'Extent', 6, 0, 'Unit of extent'),
ROW(@accession_id, 'donorName', 'autocomplete', 'Donor Name', 'Donor', 'Donor Info', 10, 0, 'Name of donor'),
ROW(@accession_id, 'donorAddress', 'textarea', 'Donor Address', 'Donor', 'Donor Info', 11, 0, 'Contact address'),
ROW(@accession_id, 'donorContactInfo', 'text', 'Donor Contact', 'Donor', 'Donor Info', 12, 0, 'Phone or email'),
ROW(@accession_id, 'scopeAndContent', 'richtext', 'Scope and Content', 'Materials', 'Description', 20, 0, 'Description of materials'),
ROW(@accession_id, 'appraisal', 'richtext', 'Appraisal', 'Materials', 'Description', 21, 0, 'Appraisal statement'),
ROW(@accession_id, 'physicalCondition', 'textarea', 'Physical Condition', 'Materials', 'Condition', 22, 0, 'Condition of materials'),
ROW(@accession_id, 'processingStatus', 'select', 'Processing Status', 'Processing', 'Status', 30, 0, 'Current processing status'),
ROW(@accession_id, 'processingPriority', 'select', 'Processing Priority', 'Processing', 'Status', 31, 0, 'Processing priority level'),
ROW(@accession_id, 'processingNotes', 'richtext', 'Processing Notes', 'Processing', 'Notes', 32, 0, 'Notes for processing')) AS v
WHERE @accession_id IS NOT NULL;

-- Photo Collection Template (example of specialized template)
-- heratio#1556: seed each system template once; the fields below are added
-- only when this run created it, never to a stale LAST_INSERT_ID().
INSERT INTO ahg_form_template (name, description, form_type, is_system, config_json)
SELECT 'Photo Collection Item', 'Specialized form for photograph collections', 'information_object', 1,
'{"layout": "tabs", "tabs": ["Basic", "Technical", "Subjects", "Rights"]}' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ahg_form_template WHERE name = 'Photo Collection Item' AND is_system = 1);

SET @photo_id = IF(ROW_COUNT() = 1, LAST_INSERT_ID(), NULL);

INSERT IGNORE INTO ahg_form_field (template_id, field_name, field_type, label, tab_name, section_name, sort_order, is_required, help_text)
SELECT * FROM (VALUES
ROW(@photo_id, 'title', 'text', 'Title/Caption', 'Basic', 'Identity', 1, 1, 'Title or caption of the photograph'),
ROW(@photo_id, 'identifier', 'text', 'Photo Number', 'Basic', 'Identity', 2, 1, 'Unique photo identifier'),
ROW(@photo_id, 'photographer', 'autocomplete', 'Photographer', 'Basic', 'Creator', 3, 0, 'Name of photographer'),
ROW(@photo_id, 'dateCreated', 'date', 'Date Taken', 'Basic', 'Dates', 4, 0, 'Date photograph was taken'),
ROW(@photo_id, 'datePrinted', 'date', 'Date Printed', 'Basic', 'Dates', 5, 0, 'Date of print'),
ROW(@photo_id, 'description', 'richtext', 'Description', 'Basic', 'Content', 6, 0, 'Description of photograph'),
ROW(@photo_id, 'format', 'select', 'Format', 'Technical', 'Physical', 10, 0, 'Physical format (print, negative, slide, digital)'),
ROW(@photo_id, 'dimensions', 'text', 'Dimensions', 'Technical', 'Physical', 11, 0, 'Size of photograph'),
ROW(@photo_id, 'colorMode', 'select', 'Color Mode', 'Technical', 'Physical', 12, 0, 'Color or black and white'),
ROW(@photo_id, 'process', 'select', 'Photographic Process', 'Technical', 'Physical', 13, 0, 'Photographic process used'),
ROW(@photo_id, 'condition', 'textarea', 'Condition', 'Technical', 'Preservation', 14, 0, 'Physical condition'),
ROW(@photo_id, 'subjects', 'autocomplete', 'Subjects', 'Subjects', 'Topics', 20, 0, 'Subject terms'),
ROW(@photo_id, 'personsDepicted', 'autocomplete', 'Persons Depicted', 'Subjects', 'People', 21, 0, 'People shown in photograph'),
ROW(@photo_id, 'location', 'text', 'Location Depicted', 'Subjects', 'Places', 22, 0, 'Location shown'),
ROW(@photo_id, 'geoCoordinates', 'text', 'Coordinates', 'Subjects', 'Places', 23, 0, 'GPS coordinates if known'),
ROW(@photo_id, 'copyright', 'select', 'Copyright Status', 'Rights', 'Rights', 30, 0, 'Copyright status'),
ROW(@photo_id, 'copyrightHolder', 'text', 'Copyright Holder', 'Rights', 'Rights', 31, 0, 'Name of copyright holder'),
ROW(@photo_id, 'accessRestrictions', 'textarea', 'Access Restrictions', 'Rights', 'Access', 32, 0, 'Any access restrictions'),
ROW(@photo_id, 'useRestrictions', 'textarea', 'Use Restrictions', 'Rights', 'Access', 33, 0, 'Restrictions on use')) AS v
WHERE @photo_id IS NOT NULL;

-- Update field options for select fields
UPDATE ahg_form_field SET options_json = '[{"value":"fonds","label":"Fonds"},{"value":"series","label":"Series"},{"value":"file","label":"File"},{"value":"item","label":"Item"},{"value":"collection","label":"Collection"}]'
WHERE field_name = 'levelOfDescription' AND template_id IN (@isadg_minimal_id, @isadg_full_id);

UPDATE ahg_form_field SET autocomplete_source = 'taxonomy:level_of_description'
WHERE field_name = 'levelOfDescription';

UPDATE ahg_form_field SET autocomplete_source = 'actor:creator'
WHERE field_name IN ('creators', 'creator', 'photographer');

UPDATE ahg_form_field SET autocomplete_source = 'repository:all'
WHERE field_name = 'repository';

UPDATE ahg_form_field SET autocomplete_source = 'taxonomy:subject'
WHERE field_name IN ('subject', 'subjects');

UPDATE ahg_form_field SET autocomplete_source = 'actor:all'
WHERE field_name = 'personsDepicted';

UPDATE ahg_form_field SET options_json = '[{"value":"donation","label":"Donation"},{"value":"purchase","label":"Purchase"},{"value":"transfer","label":"Transfer"},{"value":"deposit","label":"Deposit"},{"value":"bequest","label":"Bequest"}]'
WHERE field_name = 'acquisitionType';

UPDATE ahg_form_field SET options_json = '[{"value":"not_started","label":"Not Started"},{"value":"in_progress","label":"In Progress"},{"value":"completed","label":"Completed"},{"value":"on_hold","label":"On Hold"}]'
WHERE field_name = 'processingStatus';

UPDATE ahg_form_field SET options_json = '[{"value":"high","label":"High"},{"value":"medium","label":"Medium"},{"value":"low","label":"Low"}]'
WHERE field_name = 'processingPriority';

UPDATE ahg_form_field SET options_json = '[{"value":"print","label":"Print"},{"value":"negative","label":"Negative"},{"value":"slide","label":"Slide"},{"value":"digital","label":"Digital"},{"value":"daguerreotype","label":"Daguerreotype"},{"value":"tintype","label":"Tintype"},{"value":"glass_plate","label":"Glass Plate Negative"}]'
WHERE field_name = 'format' AND template_id = @photo_id;

UPDATE ahg_form_field SET options_json = '[{"value":"color","label":"Color"},{"value":"bw","label":"Black & White"},{"value":"sepia","label":"Sepia"},{"value":"hand_colored","label":"Hand Colored"}]'
WHERE field_name = 'colorMode';

UPDATE ahg_form_field SET options_json = '[{"value":"silver_gelatin","label":"Silver Gelatin"},{"value":"albumen","label":"Albumen"},{"value":"cyanotype","label":"Cyanotype"},{"value":"platinum","label":"Platinum"},{"value":"chromogenic","label":"Chromogenic (C-Print)"},{"value":"inkjet","label":"Inkjet"},{"value":"unknown","label":"Unknown"}]'
WHERE field_name = 'process';

UPDATE ahg_form_field SET options_json = '[{"value":"copyrighted","label":"Copyrighted"},{"value":"public_domain","label":"Public Domain"},{"value":"unknown","label":"Unknown"},{"value":"orphan_work","label":"Orphan Work"}]'
WHERE field_name = 'copyright';

SET FOREIGN_KEY_CHECKS = 1;
