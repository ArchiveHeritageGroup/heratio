-- ahgRecordsManagePlugin — dropdown seed
--
-- Adds the controlled-vocabulary entries the RM module reads from ahg_dropdown.
-- Idempotent (INSERT IGNORE on the unique (taxonomy, code) pair).
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later

-- Disposal action codes — what happens at end of retention.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_disposal_action', 'Disposal Action', 'records', 'destroy',          'Destroy',                       10, 1),
  ('rm_disposal_action', 'Disposal Action', 'records', 'transfer_archives','Transfer to archives',          20, 1),
  ('rm_disposal_action', 'Disposal Action', 'records', 'permanent',        'Retain permanently',            30, 1),
  ('rm_disposal_action', 'Disposal Action', 'records', 'review',           'Review at end of retention',    40, 1),
  ('rm_disposal_action', 'Disposal Action', 'records', 'transfer_external','Transfer to external custodian',50, 1);

-- Retention trigger codes — when the retention clock starts.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'creation_date',     'Date of creation',                10, 1),
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'closure_date',      'Date the file is closed',         20, 1),
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'last_action_date',  'Date of last action on the file', 30, 1),
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'project_end_date',  'Project end date',                40, 1),
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'employment_end',    'End of employment',               50, 1),
  ('rm_retention_trigger', 'Retention Trigger', 'records', 'event_specific',    'Specific event (manual)',         60, 1);

-- Retention schedule status codes.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_schedule_status', 'Retention Schedule Status', 'records', 'draft',     'Draft',     10, 1),
  ('rm_schedule_status', 'Retention Schedule Status', 'records', 'approved',  'Approved',  20, 1),
  ('rm_schedule_status', 'Retention Schedule Status', 'records', 'effective', 'In force',  30, 1),
  ('rm_schedule_status', 'Retention Schedule Status', 'records', 'superseded','Superseded',40, 1),
  ('rm_schedule_status', 'Retention Schedule Status', 'records', 'withdrawn', 'Withdrawn', 50, 1);

-- Disposal action workflow status codes.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_disposal_status', 'Disposal Status', 'records', 'pending',     'Pending review',           10, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'recommended', 'Recommended',              20, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'approved',    'Approved',                 30, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'legal_hold',  'On legal hold',            40, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'rejected',    'Rejected',                 50, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'executed',    'Executed',                 60, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'verified',    'Verified',                 70, 1),
  ('rm_disposal_status', 'Disposal Status', 'records', 'cancelled',   'Cancelled',                80, 1);

-- File plan node types.
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_node_type', 'File Plan Node Type', 'records', 'function',   'Function',   10, 1),
  ('rm_node_type', 'File Plan Node Type', 'records', 'activity',   'Activity',   20, 1),
  ('rm_node_type', 'File Plan Node Type', 'records', 'series',     'Series',     30, 1),
  ('rm_node_type', 'File Plan Node Type', 'records', 'subseries',  'Sub-series', 40, 1),
  ('rm_node_type', 'File Plan Node Type', 'records', 'class',      'Class',      50, 1);

-- Review schedule decision codes (P2.4).
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_review_decision', 'Review Decision', 'records', 'retain_extend', 'Retain — extend retention', 10, 1),
  ('rm_review_decision', 'Review Decision', 'records', 'retain_review', 'Retain — schedule next review', 20, 1),
  ('rm_review_decision', 'Review Decision', 'records', 'dispose',       'Trigger disposal action',    30, 1),
  ('rm_review_decision', 'Review Decision', 'records', 'transfer',      'Transfer to archives',       40, 1),
  ('rm_review_decision', 'Review Decision', 'records', 'no_change',     'No change',                  50, 1);

-- Compliance frameworks (P2.8).
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'iso_15489',      'ISO 15489 (Records management)',   10, 1),
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'iso_16175',      'ISO 16175 (ERM principles)',       20, 1),
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'moreq2010',      'MoReq2010',                        30, 1),
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'dod_5015_2',     'DoD 5015.2',                       40, 1),
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'iso_30300',      'ISO 30300 (MSR)',                  50, 1),
  ('rm_compliance_framework', 'Compliance Framework', 'records', 'iso_23081',      'ISO 23081 (Metadata for records)', 60, 1);

-- Classification rule types (P4.2).
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'folder_path', 'Folder path match',  10, 1),
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'workspace',   'Workspace match',    20, 1),
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'tag',         'Tag match',          30, 1),
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'mime_type',   'MIME type match',    40, 1),
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'metadata',    'Metadata match',     50, 1),
  ('rm_classification_rule_type', 'Classification Rule Type', 'records', 'department',  'Department fallback',60, 1);

-- Email capture sources (P2.6).
INSERT IGNORE INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `sort_order`, `is_active`) VALUES
  ('rm_email_capture_source', 'Email Capture Source', 'records', 'imap',     'IMAP polling',     10, 1),
  ('rm_email_capture_source', 'Email Capture Source', 'records', 'smtp_drop','SMTP dropbox',     20, 1),
  ('rm_email_capture_source', 'Email Capture Source', 'records', 'eml_upload','EML file upload', 30, 1),
  ('rm_email_capture_source', 'Email Capture Source', 'records', 'msg_upload','MSG file upload', 40, 1);
