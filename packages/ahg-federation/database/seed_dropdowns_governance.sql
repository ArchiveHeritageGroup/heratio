-- ============================================================================
-- ahg-federation - peer discovery + governance dropdown seed (F2, heratio#1315)
-- Idempotent: INSERT IGNORE keyed on (taxonomy, code).
-- Run: mysql -u root heratio < packages/ahg-federation/database/seed_dropdowns_governance.sql
-- (also auto-seeded on first boot by AhgFederationServiceProvider).
-- ============================================================================

-- federation_trust_level: per-peer governance tier used by the F2 governance
-- surface. Stored on federation_peer.trust_level (VARCHAR, never ENUM).
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_trust_level', 'Federation Trust Level', 'federation', 'untrusted', 'Untrusted', '#dc3545', 'shield-x', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_trust_level', 'Federation Trust Level', 'federation', 'basic', 'Basic', '#6c757d', 'shield', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_trust_level', 'Federation Trust Level', 'federation', 'trusted', 'Trusted', '#0d6efd', 'shield-check', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_trust_level', 'Federation Trust Level', 'federation', 'verified', 'Verified', '#198754', 'patch-check', 40, 1, NOW());

-- federation_discovery_status: outcome of the last discovery probe. Stored on
-- federation_peer.discovery_status (VARCHAR, never ENUM).
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_discovery_status', 'Federation Discovery Status', 'federation', 'ok', 'OK (compliant)', '#198754', 'check-circle', 10, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_discovery_status', 'Federation Discovery Status', 'federation', 'unreachable', 'Unreachable', '#dc3545', 'plug', 20, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_discovery_status', 'Federation Discovery Status', 'federation', 'non_compliant', 'Non-compliant (no federation block)', '#fd7e14', 'exclamation-triangle', 30, 1, NOW());
INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES ('federation_discovery_status', 'Federation Discovery Status', 'federation', 'unknown', 'Unknown (not yet probed)', '#6c757d', 'question-circle', 40, 1, NOW());
