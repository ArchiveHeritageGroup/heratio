-- ============================================================================
-- ahg-exhibition - Dropdown seed
-- Idempotent: INSERT IGNORE. Run: mysql -u root heratio < packages/ahg-exhibition/database/seed_dropdowns.sql
--
-- heratio#1219 - "reconstruction assembly montage": the per-reconstruction
-- montage style. Sourced from ahg_dropdown so the admin select reads the
-- Dropdown Manager (no hardcoded <option> list).
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- Licensed under the GNU Affero General Public License v3.0 or later.
-- ============================================================================

-- reconstruction_montage_style
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_default, is_active, created_at) VALUES ('reconstruction_montage_style', 'Reconstruction Montage Style', 'exhibition', 'assembly', 'Assembly (fragments accrete into the whole)', '#0d6efd', 'layer-group', 10, 1, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_default, is_active, created_at) VALUES ('reconstruction_montage_style', 'Reconstruction Montage Style', 'exhibition', 'timelapse', 'Time-lapse (dated states cross-fade)', '#198754', 'clock-history', 20, 0, 1, NOW());
