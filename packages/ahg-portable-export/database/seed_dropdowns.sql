-- ahg-portable-export dropdown seed (#1586): versioned releases.
-- INSERT IGNORE so re-runs never duplicate or overwrite an edited label.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
 ('portable_release_status', 'Portable Release Status', 'portable_export', 'frozen', 'Frozen - awaiting approval', '#6c757d', 'lock', 10, 1, NOW()),
 ('portable_release_status', 'Portable Release Status', 'portable_export', 'approved', 'Approved - publishing', '#0d6efd', 'check', 20, 1, NOW()),
 ('portable_release_status', 'Portable Release Status', 'portable_export', 'released', 'Released', '#198754', 'check-circle', 30, 1, NOW()),
 ('portable_release_status', 'Portable Release Status', 'portable_export', 'abandoned', 'Abandoned - never published', '#adb5bd', 'ban', 40, 1, NOW()),
 ('portable_release_freeze_status', 'Portable Release Freeze Status', 'portable_export', 'queued', 'Waiting to freeze', '#6c757d', 'clock', 10, 1, NOW()),
 ('portable_release_freeze_status', 'Portable Release Freeze Status', 'portable_export', 'running', 'Freezing', '#0d6efd', 'spinner', 20, 1, NOW()),
 ('portable_release_freeze_status', 'Portable Release Freeze Status', 'portable_export', 'done', 'Frozen', '#198754', 'check-circle', 30, 1, NOW()),
 ('portable_release_freeze_status', 'Portable Release Freeze Status', 'portable_export', 'failed', 'Failed', '#dc3545', 'times-circle', 40, 1, NOW()),
 ('portable_release_deposit_status', 'Portable Release Deposit Status', 'portable_export', 'approved', 'Approved', '#0d6efd', 'check', 10, 1, NOW()),
 ('portable_release_deposit_status', 'Portable Release Deposit Status', 'portable_export', 'deposited', 'Deposited', '#198754', 'check-circle', 20, 1, NOW()),
 ('portable_release_deposit_status', 'Portable Release Deposit Status', 'portable_export', 'failed', 'Failed', '#dc3545', 'times-circle', 30, 1, NOW()),
 ('portable_release_target_type', 'Portable Release Deposit Target', 'portable_export', 'institution', 'This institution''s repository', '#10373E', 'university', 10, 1, NOW()),
 ('portable_release_target_type', 'Portable Release Deposit Target', 'portable_export', 'zenodo', 'Zenodo', '#0d6efd', 'cloud-upload-alt', 20, 1, NOW()),
 ('portable_release_correction_type', 'Portable Release Correction Type', 'portable_export', 'corrected_figure', 'Corrected figure', '#fd7e14', 'calculator', 10, 1, NOW()),
 ('portable_release_correction_type', 'Portable Release Correction Type', 'portable_export', 'corrected_metadata', 'Corrected description', '#0d6efd', 'edit', 20, 1, NOW()),
 ('portable_release_correction_type', 'Portable Release Correction Type', 'portable_export', 'withdrawn_record', 'Withdrawn record', '#dc3545', 'ban', 30, 1, NOW()),
 ('portable_release_correction_type', 'Portable Release Correction Type', 'portable_export', 'other', 'Other', '#6c757d', 'info-circle', 40, 1, NOW());
