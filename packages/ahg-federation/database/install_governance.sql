-- ============================================================================
-- ahg-federation - peer discovery + governance (F2, heratio#1315)
-- ============================================================================
-- Part of epic heratio#1313 "federation backbone hardening". F1 (#1314) shipped
-- the shared FederationClient (SSRF-guarded cross-peer fetch). F2 adds peer
-- DISCOVERY (crawl each peer's /open-data/protocol + /open-data/maturity) and
-- per-peer GOVERNANCE, plus a cache of the last discovery probe.
--
-- These are ADDED to the existing federation_peer table rather than a new
-- federation_peer_config table: federation_peer already owns base_url, the
-- connector dispatch (peer_type/config) and the harvest status columns, so the
-- governance + discovery-cache fields belong on the same row. Every ALTER is
-- guarded against re-run via INFORMATION_SCHEMA so this file is idempotent and
-- safe to re-apply on every boot (matches the back-fill pattern already in
-- install.sql for peer_type / config).
--
-- Governance columns:
--   federation_enabled    1 = this peer participates in Federation Query
--                         Protocol discovery + federated queries (distinct from
--                         is_active, which gates OAI harvesting). Default 0
--                         (opt-in) so an existing harvest peer is not silently
--                         federated until an admin enables it.
--   trust_level           free-text governance tier (ahg_dropdown
--                         federation_trust_level: untrusted|basic|trusted|
--                         verified). No ENUM column (Dropdown Manager rule).
--   rate_limit_seconds    per-peer minimum seconds between live fetches; feeds
--                         FederationClient->withRateLimit(). NULL = client default.
--   allowed_entity_types  JSON array of surfaces this peer may be queried for
--                         (subset of graph|endangered|search). NULL = all that
--                         the peer advertises.
--
-- Discovery-cache columns (written by ahg:federation-discover):
--   discovery_status      ok | unreachable | non_compliant | unknown
--   protocol_version      the federation block's protocol_version, if advertised
--   declared_surfaces     JSON array of surfaces the peer advertises
--   maturity_grade        the peer's /open-data/maturity headline grade, if any
--   capabilities_json     the cached federation block (+ maturity summary)
--   last_probed_at        timestamp of the last discovery probe
--
-- No DROP, no data mutation. Jurisdiction-neutral. International by default.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --- governance: federation_enabled --------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'federation_enabled');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN federation_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'opt-in: this peer participates in Federation Query Protocol discovery + queries' AFTER is_active, ADD KEY idx_peer_fed_enabled (federation_enabled)",
    'SELECT ''federation_peer.federation_enabled exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- governance: trust_level ---------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'trust_level');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN trust_level VARCHAR(32) NOT NULL DEFAULT 'basic' COMMENT 'governance tier - uses ahg_dropdown federation_trust_level' AFTER federation_enabled",
    'SELECT ''federation_peer.trust_level exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- governance: rate_limit_seconds --------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'rate_limit_seconds');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN rate_limit_seconds INT NULL COMMENT 'per-peer min seconds between live fetches; NULL = FederationClient default' AFTER trust_level",
    'SELECT ''federation_peer.rate_limit_seconds exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- governance: allowed_entity_types ------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'allowed_entity_types');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN allowed_entity_types JSON NULL COMMENT 'JSON array of surfaces this peer may be queried for (graph|endangered|search); NULL = all advertised' AFTER rate_limit_seconds",
    'SELECT ''federation_peer.allowed_entity_types exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: discovery_status -----------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'discovery_status');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN discovery_status VARCHAR(32) NULL COMMENT 'last probe: ok|unreachable|non_compliant|unknown - uses ahg_dropdown federation_discovery_status' AFTER allowed_entity_types, ADD KEY idx_peer_discovery_status (discovery_status)",
    'SELECT ''federation_peer.discovery_status exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: protocol_version -----------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'protocol_version');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN protocol_version VARCHAR(32) NULL COMMENT 'federation block protocol_version advertised by the peer' AFTER discovery_status",
    'SELECT ''federation_peer.protocol_version exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: declared_surfaces ----------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'declared_surfaces');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN declared_surfaces JSON NULL COMMENT 'JSON array of surfaces the peer advertises (graph|endangered|search)' AFTER protocol_version",
    'SELECT ''federation_peer.declared_surfaces exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: maturity_grade -------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'maturity_grade');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN maturity_grade VARCHAR(64) NULL COMMENT 'peer /open-data/maturity headline grade, if advertised' AFTER declared_surfaces",
    'SELECT ''federation_peer.maturity_grade exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: capabilities_json ----------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'capabilities_json');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN capabilities_json JSON NULL COMMENT 'cached federation block + maturity summary from the last probe' AFTER maturity_grade",
    'SELECT ''federation_peer.capabilities_json exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- discovery cache: last_probed_at -------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'last_probed_at');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN last_probed_at DATETIME NULL COMMENT 'timestamp of the last discovery probe' AFTER capabilities_json",
    'SELECT ''federation_peer.last_probed_at exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
