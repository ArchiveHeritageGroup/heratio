-- Heratio C2PA - digitisation provenance / content-credentials records.
--
-- Issue #1201 (Provenance & authenticity layer). This table is the
-- record-of-capture for a digitised heritage asset: who digitised it, when,
-- on what device, with what software, plus any AI-inference steps that have
-- touched it (chained to #61 inference provenance). Each record can be bound
-- to a signed C2PA manifest (ahg_c2pa_manifest) so the capture chain carries
-- a verifiable Ed25519 claim signature.
--
-- Manifest-level signing (Ed25519 over the JCS-canonical claim) works on any
-- install - it needs only ext-sodium. Embedding the manifest into the media
-- file itself (JUMBF/C2PA-in-JPEG) additionally needs the native c2patool
-- binary; when c2patool is absent we still produce a signed sidecar and a
-- durable DB record, which is enough to verify authenticity inside Heratio.

CREATE TABLE IF NOT EXISTS `ahg_c2pa_provenance` (
    `id`                       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `information_object_id`    INT UNSIGNED NOT NULL,

    -- Optional link to the specific digital object (master image / 3D scan)
    -- this provenance record describes. NULL = record applies to the IO as a
    -- whole (e.g. a born-digital accession with no single master file).
    `digital_object_id`        INT UNSIGNED DEFAULT NULL,

    -- Capture provenance. Free-text dropdown-friendly values; nothing here is
    -- an ENUM (see Dropdown Manager rule).
    `captured_by`              VARCHAR(255) DEFAULT NULL,
    `captured_at`              DATETIME DEFAULT NULL,
    `capture_device`           VARCHAR(255) DEFAULT NULL,
    `capture_software`         VARCHAR(255) DEFAULT NULL,
    `notes`                    TEXT DEFAULT NULL,

    -- SHA-256 of the asset file at the moment of record creation, hex-encoded.
    -- This is the binding between the record and the bytes on disk.
    `asset_sha256`             CHAR(64) DEFAULT NULL,

    -- AI-inference steps that have touched the asset, as a JSON array of
    -- objects (step, model_id, model_version, when, output_sha256, ...).
    -- Chains to #61 inference provenance / ADR-0002.
    `inference_steps`          LONGTEXT DEFAULT NULL,

    -- The signed C2PA manifest row this record is bound to, when one was
    -- produced. NULL = record exists but no manifest was signed yet.
    `manifest_id`              BIGINT UNSIGNED DEFAULT NULL,

    -- Cached signing status string for quick display:
    --   'signed'    - a signed manifest is bound (manifest_id set)
    --   'unsigned'  - record only, no manifest signed yet
    `sign_status`              VARCHAR(16) NOT NULL DEFAULT 'unsigned',

    `created_at`               DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updated_at`               DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`),
    KEY `idx_io` (`information_object_id`),
    KEY `idx_do` (`digital_object_id`),
    KEY `idx_manifest` (`manifest_id`),
    KEY `idx_status` (`sign_status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
