-- heratio#1244 (maturity self-assessment slice) - Dropdown Manager seeds for the
-- digital-preservation maturity SELF-ASSESSMENT.
--
-- Two enumerated taxonomies, both served from ahg_dropdown so an operator can rename
-- or extend them in /admin/dropdowns. Neither is ever an ENUM column, and the views
-- never hardcode an <option> list - they read these rows.
--
--   assessment_model  the recognised international maturity models the institution
--                     can assess itself against (NDSA Levels + DPC RAM).
--   maturity_level    the shared 0..4 maturity scale labels (DPC RAM wording, which
--                     also reads correctly for the NDSA "Level 1..4" framing; level 0
--                     means "Not started / Minimal awareness").
--
-- Idempotent: INSERT IGNORE. Run:
--   mysql -u root heratio < packages/ahg-core/database/seed_preservation_self_assessment_dropdowns.sql
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- Licensed under the GNU Affero General Public License v3 or later.

-- Recognised international maturity models.
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('assessment_model', 'Preservation Maturity Model', 'preservation', 'dpc_ram', 'DPC Rapid Assessment Model (DPC RAM)', '#0d6efd', 'list-check', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('assessment_model', 'Preservation Maturity Model', 'preservation', 'ndsa', 'NDSA Levels of Digital Preservation', '#6610f2', 'layer-group', 20, 1, NOW());

-- Shared 0..4 maturity scale labels (international, jurisdiction-neutral).
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('maturity_level', 'Maturity Level', 'preservation', '0', 'Minimal awareness', '#dc3545', 'circle', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('maturity_level', 'Maturity Level', 'preservation', '1', 'Awareness', '#fd7e14', 'circle', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('maturity_level', 'Maturity Level', 'preservation', '2', 'Basic', '#ffc107', 'circle', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('maturity_level', 'Maturity Level', 'preservation', '3', 'Managed', '#20c997', 'circle', 40, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('maturity_level', 'Maturity Level', 'preservation', '4', 'Optimised', '#198754', 'circle', 50, 1, NOW());
