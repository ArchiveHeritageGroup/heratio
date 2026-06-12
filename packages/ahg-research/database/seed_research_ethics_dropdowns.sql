-- heratio#1222 - Research OS: Research Ethics & Consent register - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Four taxonomies, all surfaced in the
-- Dropdown Manager and all VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   research_ethics_approval_type - the kind of ethics / governance approval.
--   research_ethics_status        - the lifecycle status of the approval.
--   research_consent_basis        - the GENERIC governance basis on which the
--                                   data is held. These are jurisdiction-neutral
--                                   governance concepts, NOT the lawful-basis
--                                   terms of any single country's regime.
--   research_data_sensitivity     - the sensitivity classification of the data.
--
-- International and jurisdiction-neutral: no value here assumes any one country,
-- funder, regulator or legal regime.

-- ---------------------------------------------------------------------------
-- Approval type
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_ethics_approval_type', 'Research Ethics Approval Type', 'research', 'human_subjects',  'Human subjects',  'primary',   'user-friends',  10, 1, 1, NOW()),
('research_ethics_approval_type', 'Research Ethics Approval Type', 'research', 'animal',          'Animal',          'info',      'paw',           20, 0, 1, NOW()),
('research_ethics_approval_type', 'Research Ethics Approval Type', 'research', 'data_protection', 'Data protection', 'warning',   'shield-alt',    30, 0, 1, NOW()),
('research_ethics_approval_type', 'Research Ethics Approval Type', 'research', 'biosafety',       'Biosafety',       'danger',    'biohazard',     40, 0, 1, NOW()),
('research_ethics_approval_type', 'Research Ethics Approval Type', 'research', 'other',           'Other',           'secondary', 'asterisk',      50, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Approval status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_ethics_status', 'Research Ethics Status', 'research', 'not_required', 'Not required', 'secondary', 'minus-circle',  10, 0, 1, NOW()),
('research_ethics_status', 'Research Ethics Status', 'research', 'pending',      'Pending',      'warning',   'hourglass-half',20, 1, 1, NOW()),
('research_ethics_status', 'Research Ethics Status', 'research', 'approved',     'Approved',     'success',   'check-circle',  30, 0, 1, NOW()),
('research_ethics_status', 'Research Ethics Status', 'research', 'conditions',   'Approved with conditions', 'info', 'clipboard-check', 40, 0, 1, NOW()),
('research_ethics_status', 'Research Ethics Status', 'research', 'expired',      'Expired',      'dark',      'calendar-times',50, 0, 1, NOW()),
('research_ethics_status', 'Research Ethics Status', 'research', 'rejected',     'Rejected',     'danger',    'times-circle',  60, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Consent basis (GENERIC governance concepts - jurisdiction-neutral)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_consent_basis', 'Research Consent Basis', 'research', 'informed_consent',    'Informed consent',    'primary',   'file-signature', 10, 1, 1, NOW()),
('research_consent_basis', 'Research Consent Basis', 'research', 'legitimate_interest', 'Legitimate interest', 'info',      'balance-scale',  20, 0, 1, NOW()),
('research_consent_basis', 'Research Consent Basis', 'research', 'public_task',         'Public task',         'secondary', 'landmark',       30, 0, 1, NOW()),
('research_consent_basis', 'Research Consent Basis', 'research', 'anonymised',          'Anonymised data',     'success',   'user-secret',    40, 0, 1, NOW()),
('research_consent_basis', 'Research Consent Basis', 'research', 'not_applicable',      'Not applicable',      'secondary', 'ban',            50, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Data sensitivity
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_data_sensitivity', 'Research Data Sensitivity', 'research', 'none',             'None',             'secondary', 'unlock',     10, 1, 1, NOW()),
('research_data_sensitivity', 'Research Data Sensitivity', 'research', 'personal',         'Personal',         'warning',   'user',       20, 0, 1, NOW()),
('research_data_sensitivity', 'Research Data Sensitivity', 'research', 'special_category', 'Special category', 'danger',    'user-shield',30, 0, 1, NOW()),
('research_data_sensitivity', 'Research Data Sensitivity', 'research', 'restricted',       'Restricted',       'dark',      'lock',       40, 0, 1, NOW());
