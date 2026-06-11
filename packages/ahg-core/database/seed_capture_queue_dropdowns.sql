-- heratio#1205 - capture queue: status dropdown group.
-- Idempotent: INSERT IGNORE. The capture-queue workflow status comes from the
-- Dropdown Manager group `capture_queue_status` (never an ENUM, never a hardcoded
-- option list). An operator can rename/add/disable values in /admin/dropdowns and
-- the queue UI follows. Run:
--   mysql -u root heratio < packages/ahg-core/database/seed_capture_queue_dropdowns.sql
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('capture_queue_status', 'Capture Queue Status', 'capture', 'queued', 'Queued', '#0d6efd', 'list-check', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('capture_queue_status', 'Capture Queue Status', 'capture', 'in_progress', 'In progress', '#fd7e14', 'spinner', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('capture_queue_status', 'Capture Queue Status', 'capture', 'captured', 'Captured', '#198754', 'check-circle', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('capture_queue_status', 'Capture Queue Status', 'capture', 'deferred', 'Deferred', '#6c757d', 'clock', 40, 1, NOW());
