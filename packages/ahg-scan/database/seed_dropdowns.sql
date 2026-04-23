-- ============================================================================
-- ahg-scan — Dropdown seed
-- Idempotent: INSERT IGNORE. Run: mysql -u root heratio < packages/ahg-scan/database/seed_dropdowns.sql
-- ============================================================================

-- ingest_session_kind
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_session_kind', 'Ingest Session Kind', 'ingest', 'wizard', 'Wizard (interactive)', '#0d6efd', 'hat-wizard', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_session_kind', 'Ingest Session Kind', 'ingest', 'watched_folder', 'Watched folder', '#198754', 'folder-open', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_session_kind', 'Ingest Session Kind', 'ingest', 'scan_api', 'Scan API', '#6f42c1', 'plug', 30, 1, NOW());

-- ingest_file_status
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'pending', 'Pending', '#6c757d', 'clock', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'processing', 'Processing', '#0d6efd', 'spinner', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'done', 'Done', '#198754', 'check-circle', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'failed', 'Failed', '#dc3545', 'exclamation-triangle', 40, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'duplicate', 'Duplicate (already ingested)', '#ffc107', 'clone', 50, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_status', 'Ingest File Status', 'ingest', 'quarantined', 'Quarantined', '#fd7e14', 'shield-virus', 60, 1, NOW());

-- ingest_file_stage
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'virus', 'Virus scan', NULL, NULL, 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'meta', 'Extract metadata', NULL, NULL, 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'io', 'Resolve / create IO', NULL, NULL, 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'do', 'Create digital object', NULL, NULL, 40, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'deriving', 'Generating derivatives', NULL, NULL, 50, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('ingest_file_stage', 'Ingest File Stage', 'ingest', 'indexing', 'Indexing', NULL, NULL, 60, 1, NOW());

-- scan_folder_layout
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_folder_layout', 'Scan Folder Layout', 'scan', 'path', 'Path as destination', NULL, NULL, 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_folder_layout', 'Scan Folder Layout', 'scan', 'flat-sidecar', 'Flat files with XML sidecar', NULL, NULL, 20, 1, NOW());

-- scan_disposition (applies to disposition_success and disposition_failure)
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_disposition', 'Scan Disposition', 'scan', 'move', 'Move to archive folder', NULL, NULL, 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_disposition', 'Scan Disposition', 'scan', 'quarantine', 'Move to quarantine folder', NULL, NULL, 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_disposition', 'Scan Disposition', 'scan', 'leave', 'Leave in place', NULL, NULL, 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('scan_disposition', 'Scan Disposition', 'scan', 'delete', 'Delete (not recommended)', NULL, NULL, 40, 1, NOW());
