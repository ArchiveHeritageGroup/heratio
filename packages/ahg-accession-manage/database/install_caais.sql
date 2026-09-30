-- ============================================================================
-- ahg-accession-manage - CAAIS profile schema (heratio#1514)
-- ============================================================================
-- Canadian Archival Accession Information Standard (CAAIS) Final 1.0, Canadian
-- Council of Archives, 15 May 2019. A pluggable profile over the core
-- accession model: every table here is a side table keyed on accession.id, so
-- the core `accession` / `accession_i18n` tables are not altered and an
-- institution that never enables the profile carries nothing but empty tables.
--
-- Enumerated values (extent type, units, content and carrier type, source
-- confidentiality, language, preservation requirement type, event type,
-- revision type) are codes from ahg_dropdown - see seed_dropdowns.sql. They are
-- VARCHAR, never ENUM, so an institution extends them in the Dropdown Manager.
--
-- Swept by heratio:install-bootstrap (install*.sql) and self-installed on
-- first boot by AhgAccessionManageServiceProvider. Safe to re-run.
-- ============================================================================

-- 1:1 - elements with one value per accession.
--   1.1 Repository (also the 2020 "accession to repository" request: a
--       multi-repository instance can now say which repository accepted it)
--   7.1 Rules or conventions
CREATE TABLE IF NOT EXISTS accession_caais (
    accession_id INT NOT NULL PRIMARY KEY,
    repository_id INT NULL COMMENT 'CAAIS 1.1 - repository.id that accepts legal responsibility',
    rules_or_conventions VARCHAR(1024) NULL COMMENT 'CAAIS 7.1',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_accession_caais_repository (repository_id),
    CONSTRAINT fk_accession_caais_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE,
    CONSTRAINT fk_accession_caais_repository FOREIGN KEY (repository_id) REFERENCES repository (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2.1.6 Source confidentiality, per source (donor) linked to the accession.
-- The source itself stays in the core donor relation; this only records the
-- handling instruction for that source on this accession.
CREATE TABLE IF NOT EXISTS accession_caais_source (
    accession_id INT NOT NULL,
    actor_id INT NOT NULL COMMENT 'donor / source actor.id',
    confidentiality VARCHAR(100) NULL COMMENT 'CAAIS 2.1.6 - ahg_dropdown caais_source_confidentiality',
    PRIMARY KEY (accession_id, actor_id),
    CONSTRAINT fk_accession_caais_source_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE,
    CONSTRAINT fk_accession_caais_source_actor FOREIGN KEY (actor_id) REFERENCES actor (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.2 Extent statement (repeatable): 3.2.1 type, 3.2.2 quantity and unit,
-- 3.2.3 content type, 3.2.4 carrier type, digital file formats, extent note.
-- The free-text accession_i18n.received_extent_units is left alone and is
-- exported as an extent note when no structured statement exists.
CREATE TABLE IF NOT EXISTS accession_caais_extent (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    extent_type VARCHAR(100) NOT NULL COMMENT 'CAAIS 3.2.1 - caais_extent_type',
    quantity DECIMAL(14,3) NULL COMMENT 'CAAIS 3.2.2',
    is_estimate TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'CAAIS 3.2.2 "ca." convention',
    unit VARCHAR(100) NULL COMMENT 'CAAIS 3.2.2 - caais_extent_unit',
    content_type VARCHAR(100) NULL COMMENT 'CAAIS 3.2.3 - caais_content_type',
    carrier_type VARCHAR(100) NULL COMMENT 'CAAIS 3.2.4 - caais_carrier_type',
    digital_file_formats VARCHAR(1024) NULL COMMENT 'CAAIS 3.2.5 digital file formats',
    note TEXT NULL COMMENT 'CAAIS extent note',
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_accession_caais_extent_accession (accession_id),
    CONSTRAINT fk_accession_caais_extent_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3.4 Language of material (repeatable). A code from caais_language plus an
-- optional statement for what a code cannot say ("In Dakota, with partial
-- English translation").
CREATE TABLE IF NOT EXISTS accession_caais_language (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    language VARCHAR(100) NULL COMMENT 'CAAIS 3.4 - caais_language',
    note VARCHAR(1024) NULL COMMENT 'CAAIS 3.4 free statement',
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_accession_caais_language_accession (accession_id),
    CONSTRAINT fk_accession_caais_language_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4.3 Preservation requirements (repeatable): 4.3.1 type, 4.3.2 value,
-- 4.3.3 note. accession_i18n.physical_characteristics stays as the core
-- free-text field and is exported as a fallback when nothing is structured.
CREATE TABLE IF NOT EXISTS accession_caais_preservation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    requirement_type VARCHAR(100) NOT NULL COMMENT 'CAAIS 4.3.1 - caais_preservation_type',
    requirement_value TEXT NOT NULL COMMENT 'CAAIS 4.3.2',
    note TEXT NULL COMMENT 'CAAIS 4.3.3',
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_accession_caais_preservation_accession (accession_id),
    CONSTRAINT fk_accession_caais_preservation_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5.1 Events (repeatable): 5.1.1 type, 5.1.2 date, 5.1.3 agent, 5.1.4 note.
-- CAAIS requires at least the physical transfer and (if different) the legal
-- transfer. Core accession_event rows are exported alongside these.
CREATE TABLE IF NOT EXISTS accession_caais_event (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL COMMENT 'CAAIS 5.1.1 - caais_event_type',
    event_date DATE NULL COMMENT 'CAAIS 5.1.2',
    agent VARCHAR(255) NULL COMMENT 'CAAIS 5.1.3',
    note TEXT NULL COMMENT 'CAAIS 5.1.4',
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_accession_caais_event_accession (accession_id),
    INDEX idx_accession_caais_event_type (event_type),
    CONSTRAINT fk_accession_caais_event_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7.2 Date of creation or revision (repeatable, append-only): 7.2.1 type,
-- 7.2.2 date, 7.2.3 agent, 7.2.4 note. Written by the application on every
-- create and save. user_id has no foreign key and the agent name is a
-- snapshot, so the record outlives a renamed or deleted account.
CREATE TABLE IF NOT EXISTS accession_caais_revision (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    accession_id INT NOT NULL,
    revision_type VARCHAR(100) NOT NULL COMMENT 'CAAIS 7.2.1 - caais_revision_type',
    revision_date DATETIME NOT NULL COMMENT 'CAAIS 7.2.2',
    agent VARCHAR(255) NULL COMMENT 'CAAIS 7.2.3 - name snapshot',
    user_id INT NULL,
    note TEXT NULL COMMENT 'CAAIS 7.2.4',
    INDEX idx_accession_caais_revision_accession (accession_id, revision_date),
    CONSTRAINT fk_accession_caais_revision_accession FOREIGN KEY (accession_id) REFERENCES accession (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
