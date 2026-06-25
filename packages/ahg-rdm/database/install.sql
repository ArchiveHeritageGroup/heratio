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
