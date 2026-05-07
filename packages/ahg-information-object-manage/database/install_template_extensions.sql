-- #98 Phase 2 — sidecar tables + MODS element_visibility flags for the
-- DACS / RAD / MODS show templates.
--
-- Heratio's information_object + information_object_i18n base tables come
-- from AtoM and are read-only (no ALTER per ADR-0001 / feedback memory).
-- Per-standard fields that the standard requires but Heratio doesn't
-- store live in these sidecar tables, joined by information_object_id.
--
-- Existing DACS / RAD area-level element_visibility flags (seeded by
-- AtoM and listed in packages/ahg-settings/resources/views/visible-elements.blade.php)
-- are reused unchanged — show-dacs.blade.php and show-rad.blade.php
-- honour `dacs_identity_area` / `dacs_content_area` / `rad_title_responsibility_area`
-- / etc. via SettingHelper::checkFieldVisibility(). MODS has no AtoM-
-- precedent so its flags are added by this script + a matching
-- 'mods_headings' / 'mods_elements' block in visible-elements.blade.php.

-- ============================================================
-- DACS sidecar — three elements not stored in IO i18n schema
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_io_dacs` (
    `information_object_id` INT NOT NULL,
    -- DACS 4.2 — physical access (separate from 4.3 Technical Access);
    -- AtoM/Heratio's physical_characteristics column collapses both.
    `physical_access_note`  TEXT NULL,
    -- DACS 4.3 — technical access (e.g. "requires 16mm projector",
    -- "MARC reader needed", etc.).
    `technical_access_note` TEXT NULL,
    -- DACS 6.4 — formal publication notes (separate from "related
    -- archival materials" 6.3, which is also rendered).
    `publication_note`      TEXT NULL,
    `created_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`information_object_id`),
    CONSTRAINT `fk_ahg_io_dacs_io`
        FOREIGN KEY (`information_object_id`) REFERENCES `information_object`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- RAD sidecar — General Material Designation, Statement of
-- Responsibility, Publisher's Series, Standard Number
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_io_rad` (
    `information_object_id`              INT NOT NULL,
    -- RAD 1.1B — General Material Designation (e.g. "[textual record]",
    -- "[sound recording]", "[moving image]").
    `general_material_designation`       VARCHAR(120) NULL,
    -- RAD 1.1F — Statement of Responsibility (creator/compiler attribution
    -- in formal RAD form, e.g. "/ photographs by Jane Doe").
    `statement_of_responsibility`        TEXT NULL,
    -- RAD 1.5B — Specific Material Designation (refines GMD;
    -- e.g. "85 photographic prints").
    `specific_material_designation`      VARCHAR(255) NULL,
    -- RAD 1.6 — Publisher's Series Area
    `publisher_series_title`             VARCHAR(255) NULL,
    `publisher_series_statement`         TEXT NULL,
    `publisher_series_issn`              VARCHAR(40) NULL,
    `publisher_series_numbering`         VARCHAR(120) NULL,
    -- RAD 1.8 — Standard Number Area (ISBN/ISSN/etc.)
    `standard_number_type`               VARCHAR(20) NULL,
    `standard_number_value`              VARCHAR(60) NULL,
    `created_at`                         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`information_object_id`),
    CONSTRAINT `fk_ahg_io_rad_io`
        FOREIGN KEY (`information_object_id`) REFERENCES `information_object`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODS sidecar — typeOfResource, genre, physicalDescription,
-- abstract, classification, recordInfo
-- ============================================================
CREATE TABLE IF NOT EXISTS `ahg_io_mods` (
    `information_object_id`            INT NOT NULL,
    -- MODS <typeOfResource> — controlled vocab: "text", "cartographic",
    -- "notated music", "sound recording", "still image", "moving image",
    -- "three dimensional object", "software, multimedia", "mixed material".
    `type_of_resource`                 VARCHAR(40) NULL,
    -- MODS <genre> — free-text or AAT/LCGFT vocab (e.g. "manuscripts (documents)",
    -- "field recordings", "oral histories").
    `genre`                            VARCHAR(255) NULL,
    -- MODS <physicalDescription> sub-elements
    `physical_form`                    VARCHAR(255) NULL,
    `physical_extent`                  VARCHAR(255) NULL,
    -- e.g. "image/jpeg", "video/mp4". Heratio's digital_object table has
    -- this for derivatives; cataloguers may also assert the originating
    -- format here.
    `internet_media_type`              VARCHAR(120) NULL,
    -- MODS <digitalOrigin> — "born digital" | "reformatted digital" |
    -- "digitized microfilm" | "digitized other analog"
    `digital_origin`                   VARCHAR(40) NULL,
    -- MODS <abstract> — distinct from $io->scope_and_content; abstracts
    -- are typically shorter "preview" summaries.
    `abstract`                         TEXT NULL,
    -- MODS <tableOfContents>
    `table_of_contents`                TEXT NULL,
    -- MODS <targetAudience>
    `target_audience`                  VARCHAR(255) NULL,
    -- MODS <classification> — LCC / DDC / NLM / SuDocs etc.
    `classification_authority`         VARCHAR(40) NULL,
    `classification_value`             VARCHAR(255) NULL,
    -- MODS <recordInfo>
    `record_content_source`            VARCHAR(255) NULL,
    `record_origin`                    TEXT NULL,
    `language_of_cataloging`           VARCHAR(40) NULL,
    `created_at`                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`                       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`information_object_id`),
    CONSTRAINT `fk_ahg_io_mods_io`
        FOREIGN KEY (`information_object_id`) REFERENCES `information_object`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- MODS element_visibility flags — DACS + RAD already seeded by
-- AtoM (see visible-elements.blade.php). MODS is new to Heratio.
-- ============================================================
INSERT IGNORE INTO `setting` (`scope`, `name`, `editable`, `deleteable`, `source_culture`) VALUES
    ('element_visibility', 'mods_titleinfo_area',           1, 0, 'en'),
    ('element_visibility', 'mods_name_area',                1, 0, 'en'),
    ('element_visibility', 'mods_type_of_resource_area',    1, 0, 'en'),
    ('element_visibility', 'mods_genre_area',               1, 0, 'en'),
    ('element_visibility', 'mods_origininfo_area',          1, 0, 'en'),
    ('element_visibility', 'mods_language_area',            1, 0, 'en'),
    ('element_visibility', 'mods_physicaldescription_area', 1, 0, 'en'),
    ('element_visibility', 'mods_abstract_area',            1, 0, 'en'),
    ('element_visibility', 'mods_table_of_contents_area',   1, 0, 'en'),
    ('element_visibility', 'mods_subject_area',             1, 0, 'en'),
    ('element_visibility', 'mods_classification_area',      1, 0, 'en'),
    ('element_visibility', 'mods_related_item_area',        1, 0, 'en'),
    ('element_visibility', 'mods_identifier_area',          1, 0, 'en'),
    ('element_visibility', 'mods_location_area',            1, 0, 'en'),
    ('element_visibility', 'mods_access_condition_area',    1, 0, 'en'),
    ('element_visibility', 'mods_record_info_area',         1, 0, 'en');

-- Default '1' (visible) per locale. Operators flip individual rows via
-- the element-visibility admin form at /admin/settings/visible-elements.
INSERT IGNORE INTO `setting_i18n` (`id`, `culture`, `value`)
SELECT `id`, 'en', '1' FROM `setting`
WHERE `scope` = 'element_visibility' AND `name` LIKE 'mods_%';
