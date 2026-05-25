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
-- Idempotent: each ALTER ... ADD COLUMN is wrapped so re-running the install
-- does not error if the column already exists. We do that with information_schema
-- guards in the service-provider PHP, not in raw SQL (MySQL has no
-- IF NOT EXISTS for ADD COLUMN in 8.0).
-- ============================================================================

ALTER TABLE `ahg_audit_log`
    ADD COLUMN `seq` BIGINT UNSIGNED NULL COMMENT 'monotonic chain sequence, NULL for legacy rows',
    ADD COLUMN `prev_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'entry_hash of previous row, genesis = 64 zero hex chars',
    ADD COLUMN `entry_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NULL COMMENT 'SHA-256 over JCS-canonicalised signing view',
    ADD COLUMN `signature` VARCHAR(128) COLLATE utf8mb4_unicode_ci NULL COMMENT 'Ed25519 detached signature, base64',
    ADD COLUMN `kid` VARCHAR(32) COLLATE utf8mb4_unicode_ci NULL COMMENT 'signing key id, matches ai_inference_key.kid',
    ADD UNIQUE KEY `idx_audit_seq` (`seq`),
    ADD UNIQUE KEY `idx_audit_entry_hash` (`entry_hash`),
    ADD KEY `idx_audit_kid` (`kid`);
