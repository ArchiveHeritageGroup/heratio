-- heratio#1244 (WARC web-archiving slice) - Dropdown Manager seed for the WARC
-- capture outcome status.
--
-- One enumerated taxonomy, served from ahg_dropdown so an operator can rename or
-- extend it in /admin/dropdowns. It is never an ENUM column, and the views never
-- hardcode an <option> list - they read these rows.
--
--   warc_capture_status  the outcome of a record-page WARC capture attempt:
--                        captured (a valid WARC 1.1 file was written) or
--                        failed (the page was unreachable / oversize / not our own).
--
-- Idempotent: INSERT IGNORE. Run:
--   mysql -u root heratio < packages/ahg-core/database/seed_warc_capture_dropdowns.sql
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- Licensed under the GNU Affero General Public License v3 or later.

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('warc_capture_status', 'WARC Capture Status', 'preservation', 'captured', 'Captured', '#198754', 'circle-check', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('warc_capture_status', 'WARC Capture Status', 'preservation', 'failed', 'Failed', '#dc3545', 'circle-xmark', 20, 1, NOW());
