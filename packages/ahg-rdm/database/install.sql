-- ahg-rdm install schema (#1338)
-- A Dataset is a thin wrapper: it links a research project to a container
-- information_object (io_parent_id) under which deposited files live as child
-- IOs (each with a digital_object), created via ahg-ingest IngestService. No
-- bespoke file storage - digital_object remains the single source of truth.

CREATE TABLE IF NOT EXISTS rdm_dataset (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id      INT NULL,                       -- FK research_project.id (nullable: standalone dataset allowed)
    io_parent_id    INT NOT NULL,                   -- container information_object.id; files deposit as its children
    title           VARCHAR(500) NOT NULL,
    description     TEXT NULL,
    -- status drives the deposit -> scan -> review -> publish lifecycle; values
    -- come from ahg_dropdown (taxonomy 'dataset_status'), NEVER a MySQL ENUM.
    status          VARCHAR(40) NOT NULL DEFAULT 'draft',
    -- POPIA scan verdict (CLEAR | PERSONAL | SPECIAL_CATEGORY), set by PopiaScanService.
    verdict         VARCHAR(32) NULL,
    scanned_at      TIMESTAMP NULL,
    doi             VARCHAR(255) NULL,
    created_by      INT NULL,                       -- auth user id of the depositor
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rdm_dataset_project (project_id),
    KEY idx_rdm_dataset_io (io_parent_id),
    KEY idx_rdm_dataset_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rdm_dataset_file (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id      INT UNSIGNED NOT NULL,          -- FK rdm_dataset.id
    io_id           INT NOT NULL,                   -- the child information_object IngestService created for this file
    do_id           INT NULL,                       -- the master digital_object.id
    original_name   VARCHAR(1024) NOT NULL,
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rdm_file_dataset (dataset_id),
    KEY idx_rdm_file_io (io_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- POPIA scan findings (#1339). One row per detected item. The scan NEVER auto-
-- decides: findings land 'pending' and a human confirms/overrides each (#1340).
-- 'method' = deterministic | ner | lexicon; 'sample' is a MASKED snippet (never
-- the full PII value). review_status from ahg_dropdown later; kept simple here.
CREATE TABLE IF NOT EXISTS rdm_scan_finding (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id      INT UNSIGNED NOT NULL,
    dataset_file_id INT UNSIGNED NULL,              -- FK rdm_dataset_file.id
    file_name       VARCHAR(1024) NULL,
    type            VARCHAR(60) NOT NULL,           -- sa_id_number|email|phone|passport|person|location|org|special_category
    category        VARCHAR(40) NOT NULL DEFAULT 'personal', -- personal | special_category
    sample          VARCHAR(255) NULL,              -- MASKED
    confidence      VARCHAR(20) NOT NULL DEFAULT 'high',     -- high | medium | low (AI-suggested = medium/low)
    method          VARCHAR(20) NOT NULL DEFAULT 'deterministic',
    review_status   VARCHAR(20) NOT NULL DEFAULT 'pending',  -- pending|confirmed|dismissed (set by the human gate)
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rdm_finding_dataset (dataset_id),
    KEY idx_rdm_finding_status (review_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Idempotent ADD COLUMN for installs whose rdm_dataset predates the verdict/
-- scanned_at columns (the CREATE above is skipped when the table exists).
SET @c := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'rdm_dataset' AND column_name = 'verdict');
SET @s := IF(@c = 0, 'ALTER TABLE rdm_dataset ADD COLUMN verdict VARCHAR(32) NULL AFTER status, ADD COLUMN scanned_at TIMESTAMP NULL AFTER verdict', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
