-- ============================================================================
-- heratio#1228 - Quick Capture Inbox dropdown seed.
-- Run on fresh install: mysql -u root heratio < packages/ahg-research/database/seed_inbox_dropdowns.sql
-- Or via artisan: php artisan ahg:seed-research-dropdowns (loads all research seeds)
--
-- Uses INSERT IGNORE - safe to run multiple times. VARCHAR + Dropdown Manager,
-- never MySQL ENUM. Jurisdiction-neutral / international.
-- ============================================================================

-- Capture kind ---------------------------------------------------------------
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'note', 'Note', '#0d6efd', 'sticky-note', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'voice', 'Voice', '#6f42c1', 'microphone', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'email', 'Email', '#fd7e14', 'envelope', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'clip', 'Web Clip', '#20c997', 'link', 40, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'photo', 'Photo', '#d63384', 'image', 50, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_kind', 'Inbox Kind', 'research', 'file', 'File', '#6c757d', 'paperclip', 60, 1, NOW());

-- Capture origin -------------------------------------------------------------
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_origin', 'Inbox Origin', 'research', 'web', 'Web', '#0d6efd', 'globe', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_origin', 'Inbox Origin', 'research', 'email-in', 'Email-In', '#fd7e14', 'inbox', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_origin', 'Inbox Origin', 'research', 'clipper', 'Web Clipper', '#20c997', 'cut', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_origin', 'Inbox Origin', 'research', 'mobile', 'Mobile', '#6610f2', 'mobile-alt', 40, 1, NOW());

-- Triage status --------------------------------------------------------------
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_status', 'Inbox Status', 'research', 'inbox', 'Inbox', '#0dcaf0', 'inbox', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_status', 'Inbox Status', 'research', 'triaged', 'Triaged', '#198754', 'check-circle', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('research_inbox_status', 'Inbox Status', 'research', 'archived', 'Archived', '#6c757d', 'archive', 30, 1, NOW());
