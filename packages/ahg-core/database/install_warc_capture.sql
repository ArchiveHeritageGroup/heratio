-- heratio#1244 (WARC web-archiving slice) - record-page web-archive captures.
--
-- A bounded, verifiable first slice of web archiving for the catalogue: snapshot a
-- PUBLISHED archival record's OWN public web page into a valid WARC 1.1 (ISO 28500)
-- file, so the catalogue can web-archive its own record pages. This table is the
-- register of those captures - one row per capture attempt.
--
-- The actual WARC bytes live on disk under the configured storage path
-- (config('heratio.storage_path') . '/web-archive'), NEVER a hardcoded path; this
-- table records the metadata: which record was captured, the exact target URI, where
-- the .warc file was written, its byte size, the SHA-256 of the file, when and by
-- whom it was captured, and the outcome status.
--
-- This is a NEW side table, soft-referenced only (no FK into the AtoM/Qubit base
-- schema), so it installs safely on any mid-migration database. No ENUM column: the
-- `status` value comes from the Dropdown Manager (ahg_dropdown group
-- warc_capture_status) and is validated in PHP. No ALTER on any existing table;
-- CREATE TABLE IF NOT EXISTS only.
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- Licensed under the GNU Affero General Public License v3 or later.

CREATE TABLE IF NOT EXISTS `warc_capture` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `information_object_id` INT NULL COMMENT 'soft reference to the captured published record (information_object.id) - no FK',
  `slug` VARCHAR(255) NULL COMMENT 'the record slug at capture time (for display / re-resolution)',
  `target_uri` VARCHAR(2048) NOT NULL COMMENT 'the exact same-host public record URL that was fetched (the WARC-Target-URI)',
  `file_path` VARCHAR(1024) NULL COMMENT 'absolute path to the stored .warc on disk (under the configured storage web-archive dir)',
  `file_name` VARCHAR(255) NULL COMMENT 'the .warc file name (download name)',
  `byte_size` BIGINT UNSIGNED NULL COMMENT 'size of the stored .warc file in bytes',
  `sha256` CHAR(64) NULL COMMENT 'lowercase hex SHA-256 of the stored .warc file (fixity)',
  `http_status` SMALLINT NULL COMMENT 'the HTTP status code returned by the captured page (e.g. 200)',
  `status` VARCHAR(32) NOT NULL DEFAULT 'captured' COMMENT 'outcome status from ahg_dropdown group warc_capture_status (captured | failed); never an ENUM',
  `error_message` VARCHAR(1024) NULL COMMENT 'short human-readable reason when status = failed',
  `captured_by` INT NULL COMMENT 'soft reference to the signed-in user who ran the capture - no FK',
  `captured_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'when the capture was performed',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `warc_capture_io_idx` (`information_object_id`),
  KEY `warc_capture_status_idx` (`status`),
  KEY `warc_capture_captured_at_idx` (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
