-- ============================================================================
-- ahg-federation - federation trust handshake + key pinning (T1, heratio#1316)
-- ============================================================================
-- Part of epic heratio#1313 "federation backbone hardening". F1 (#1314) shipped
-- the shared FederationClient; F2 (#1315) added peer discovery + governance.
-- T1 (#1316) adds the trust handshake: peers SIGN their federation responses
-- (provider side) and this instance VERIFIES + PINS the peer's key Trust-On-
-- First-Use (consumer side).
--
-- These columns are ADDED to the existing federation_peer table (where the F2
-- governance + discovery-cache columns live), so a peer's pinned key sits on the
-- same governance row an admin manages. Matches F2's guarded-ALTER pattern
-- exactly: every ALTER is guarded against re-run via INFORMATION_SCHEMA so this
-- file is idempotent and safe to re-apply on every boot.
--
-- TOFU pin columns:
--   pinned_key_fingerprint  the peer's Ed25519 key id (kid, 16 hex chars) pinned
--                           on the FIRST successful signature verify. On a later
--                           fetch a DIFFERENT presented kid is a key_mismatch:
--                           the response is marked unverified + flagged and the
--                           changed key is NOT auto-trusted (an admin must
--                           re-pin / clear the pin from the governance surface).
--   key_pinned_at           timestamp the key was pinned (TOFU moment).
--
-- No DROP, no data mutation. Jurisdiction-neutral. International by default.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- --- TOFU pin: pinned_key_fingerprint ------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'pinned_key_fingerprint');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN pinned_key_fingerprint VARCHAR(64) NULL COMMENT 'TOFU-pinned peer Ed25519 key id (kid); a different presented kid later = key_mismatch (not auto-trusted)' AFTER last_probed_at, ADD KEY idx_peer_pinned_key (pinned_key_fingerprint)",
    'SELECT ''federation_peer.pinned_key_fingerprint exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- --- TOFU pin: key_pinned_at ---------------------------------------------
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'federation_peer' AND COLUMN_NAME = 'key_pinned_at');
SET @sql := IF(@col = 0,
    "ALTER TABLE federation_peer ADD COLUMN key_pinned_at DATETIME NULL COMMENT 'timestamp the peer key was pinned Trust-On-First-Use' AFTER pinned_key_fingerprint",
    'SELECT ''federation_peer.key_pinned_at exists''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;
