-- ============================================================================
-- ahg-audit-trail - hash-chain tamper evidence (issue #676 Phase 5)
-- ============================================================================
-- Adds five columns to ahg_audit_log that turn each row into one immutable
-- link in an Ed25519-signed, JCS-canonicalised hash chain (RFC 8785 + SHA-256
-- + Ed25519, identical to the protocol shipped in ahg/inference-receipts for
-- EU AI Act Article 12).
--
-- Existing rows keep NULL chain columns - they remain non-tamper-evident
-- legacy data. Every row written from the moment of upgrade is chained.
--
-- Idempotent + dependency-safe in raw SQL (#1136): when install-bootstrap runs
-- these files via DB::unprepared the PHP guards do not apply, and this file
-- sorts before install.sql (which creates ahg_audit_log). So we (a) no-op if the
-- base table is absent on this pass, and (b) no-op if `seq` already exists, via
-- an information_schema-guarded prepared statement. MySQL 8.0 has no
-- "ADD COLUMN IF NOT EXISTS".
-- ============================================================================

SET @ddl := (
  SELECT IF(
    -- table exists AND the chain columns are not yet present
    EXISTS(
      SELECT 1 FROM information_schema.TABLES
      WHERE table_schema = DATABASE() AND table_name = 'ahg_audit_log'
    )
    AND NOT EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE table_schema = DATABASE() AND table_name = 'ahg_audit_log' AND column_name = 'seq'
    ),
    'ALTER TABLE `ahg_audit_log`
        ADD COLUMN `seq` BIGINT UNSIGNED NULL COMMENT ''monotonic chain sequence, NULL for legacy rows'',
        ADD COLUMN `prev_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT ''entry_hash of previous row, genesis = 64 zero hex chars'',
        ADD COLUMN `entry_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT ''SHA-256 over JCS-canonicalised signing view'',
        ADD COLUMN `signature` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT ''Ed25519 detached signature, base64'',
        ADD COLUMN `kid` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT ''signing key id, matches ai_inference_key.kid'',
        ADD UNIQUE KEY `idx_audit_seq` (`seq`),
        ADD UNIQUE KEY `idx_audit_entry_hash` (`entry_hash`),
        ADD KEY `idx_audit_kid` (`kid`)',
    'SELECT 1'
  )
);
PREPARE _s FROM @ddl; EXECUTE _s; DEALLOCATE PREPARE _s;
