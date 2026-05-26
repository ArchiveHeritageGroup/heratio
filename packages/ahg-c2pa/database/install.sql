-- Heratio C2PA - Coalition for Content Provenance and Authenticity manifests.
--
-- Every AI-touched artefact gets a C2PA 2.1 manifest. Manifests are signed
-- Ed25519 (shared key with ahg-inference-receipts) and either embedded into
-- the host media (JPEG/JUMBF) or written as a sidecar JSON next to the file.
--
-- This table is the durable log of every manifest we have ever emitted. It
-- lets us answer "what AI activity ever touched this IO?" without having to
-- crawl the sidecar files on disk, and it lets us re-issue a manifest if
-- the on-disk copy gets lost.

CREATE TABLE IF NOT EXISTS `ahg_c2pa_manifest` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `information_object_id`    INT UNSIGNED NOT NULL,

    -- C2PA action label. We currently emit one of:
    --   'ai-generated' - the output is wholly produced by an AI model
    --   'ai-assisted'  - a human took the AI output and revised it
    -- See c2pa.actions.v2 in the spec.
    `action`                   VARCHAR(32) NOT NULL,

    `model_id`                 VARCHAR(128) NOT NULL,
    `model_version`            VARCHAR(64) DEFAULT NULL,

    -- Authoritative JSON form (RFC 8785 JCS-canonical). This is what the
    -- claim signature is computed over.
    `manifest_json`            LONGTEXT NOT NULL,

    -- Optional CBOR encoding of the same manifest. Required when embedding
    -- into JUMBF (the C2PA on-wire form in media containers).
    `manifest_cbor`            LONGBLOB DEFAULT NULL,

    -- Absolute path of the sidecar (.c2pa.json) we wrote, when applicable.
    `sidecar_path`             VARCHAR(512) DEFAULT NULL,

    -- Ed25519 signature over SHA-256(JCS(claim)), hex-encoded.
    `claim_signature`          VARCHAR(128) NOT NULL,

    -- Key id (first 16 hex of SHA-256(public_key)). Resolves through
    -- ai_inference_key to find the actual public key for verification.
    `kid`                      VARCHAR(32) NOT NULL,

    `created_at`               DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_io` (`information_object_id`),
    KEY `idx_action` (`action`),
    KEY `idx_kid` (`kid`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
