-- heratio#1211 ("every museum for everyone"), alt-text curation slice.
--
-- A dedicated, human-authored alternative-text store for image digital objects.
-- The accessibility coverage report surfaced that published image surrogates have
-- essentially no genuine alt text (the catalogue has no dedicated alt-text column;
-- the report could only proxy from digital_object_metadata.description). This table
-- gives cataloguers and contributors a real place to write and curate a text
-- alternative for every published image, separately from the embedded IPTC/XMP
-- caption, so screen-reader users get a true non-text alternative (WCAG 2.1 - 1.1.1
-- Non-text Content).
--
-- Lang-aware from the start (international; Afrikaans is a first-class language, not
-- an afterthought): one row per (digital_object_id, lang), so the same image can
-- carry alt text in en, af, and any other language. digital_object_id is a SOFT
-- reference (no foreign key) to keep the table decoupled from the AtoM base schema
-- and safe to install on any mid-migration database. No ENUM columns (lang is a
-- VARCHAR), no ALTER on any existing table.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- Licensed under the GNU Affero General Public License v3 or later.

CREATE TABLE IF NOT EXISTS `image_alt_text` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `digital_object_id` INT NOT NULL COMMENT 'soft reference to digital_object.id - no FK',
  `lang` VARCHAR(16) NOT NULL DEFAULT 'en' COMMENT 'BCP-47-ish language code, e.g. en, af, fr',
  `alt_text` TEXT NULL COMMENT 'human-authored text alternative for the image (WCAG 1.1.1)',
  `contributed_by` INT NULL COMMENT 'soft reference to the user who first authored this entry',
  `updated_by` INT NULL COMMENT 'soft reference to the user who last edited this entry',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `image_alt_text_object_lang_unique` (`digital_object_id`, `lang`),
  KEY `image_alt_text_object_idx` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
