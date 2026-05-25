-- Heratio AI Compliance - EU AI Act Article 12 record-keeping.
--
-- Append-only tamper-evident chain of every inference call.
-- Each row links to the previous via prev_hash; entry_hash is over the
-- canonical (JCS) form of the row's signing view; signature is Ed25519
-- over the entry_hash. See packages/ahg-inference-receipts/ for the
-- protocol details.

CREATE TABLE IF NOT EXISTS `ai_inference_log` (
    `id`                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `seq`                BIGINT UNSIGNED NOT NULL,
    `ts`                 DATETIME(3) NOT NULL,
    `prev_hash`          CHAR(64) NOT NULL,
    `entry_hash`         CHAR(64) NOT NULL,
    `signature`          VARCHAR(128) NOT NULL,
    `kid`                VARCHAR(32) NOT NULL,
    `v`                  TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `alg`                VARCHAR(16) NOT NULL DEFAULT 'ed25519',

    -- Payload fields (also captured inside payload_json for canonical hashing).
    -- Promoted to columns so we can query / index without parsing JSON.
    `service`            VARCHAR(32) NOT NULL,
    `model_id`           VARCHAR(128) NOT NULL,
    `model_version`      VARCHAR(64) DEFAULT NULL,
    `input_fingerprint`  CHAR(64) DEFAULT NULL,
    `output_fingerprint` CHAR(64) DEFAULT NULL,
    `request_id`         VARCHAR(64) DEFAULT NULL,
    `user_id`            BIGINT UNSIGNED DEFAULT NULL,
    `tenant_id`          INT UNSIGNED DEFAULT NULL,
    `latency_ms`         INT UNSIGNED DEFAULT NULL,
    `tokens_in`          INT UNSIGNED DEFAULT NULL,
    `tokens_out`         INT UNSIGNED DEFAULT NULL,

    -- Full payload as canonical JSON. This is the authoritative source
    -- for re-hashing during chain verification. After retention prune
    -- this may be set to NULL while the row (and its hash + sig) is kept
    -- so the chain remains verifiable.
    `payload_json`       JSON DEFAULT NULL,
    `payload_pruned_at`  DATETIME DEFAULT NULL,

    `created_at`         DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_seq` (`seq`),
    UNIQUE KEY `uniq_entry_hash` (`entry_hash`),
    KEY `idx_ts` (`ts`),
    KEY `idx_service_ts` (`service`, `ts`),
    KEY `idx_user_ts` (`user_id`, `ts`),
    KEY `idx_tenant_ts` (`tenant_id`, `ts`),
    KEY `idx_request_id` (`request_id`),
    KEY `idx_kid` (`kid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-key registry. New rows appended when the signing key is rotated.
-- Public key endpoint reads the active row; verifier resolves historical
-- kids through this table.
CREATE TABLE IF NOT EXISTS `ai_inference_key` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kid`           VARCHAR(32) NOT NULL,
    `public_key`    VARBINARY(64) NOT NULL,
    `alg`           VARCHAR(16) NOT NULL DEFAULT 'ed25519',
    `active`        TINYINT(1) NOT NULL DEFAULT 1,
    `rotated_at`    DATETIME DEFAULT NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_kid` (`kid`),
    KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
