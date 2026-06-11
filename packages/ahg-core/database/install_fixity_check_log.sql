-- heratio#1244 (fixity slice) - fixity / integrity verification log.
--
-- One row per fixity check performed by the bounded fixity sweep
-- (AhgCore\Services\FixityService). A check re-computes the checksum of a
-- digital object's file on disk and compares it to the stored baseline
-- (digital_object.checksum + checksum_type). The result is recorded here as a
-- transparent, append-only audit trail - the actionable "Integrity" functional
-- area of the NDSA Levels of Digital Preservation.
--
-- This is a NEW, additive table. The sweep is read-only over digital_object;
-- the only writes it performs anywhere are INSERTs into this log. No existing
-- table is ALTERed.
--
-- result is a VARCHAR (never an ENUM, per the Dropdown-Manager rule). The set of
-- values the service writes is: match | mismatch | missing_file | no_baseline |
-- skipped_oversize | error.
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- Licensed under the GNU Affero General Public License v3 or later.
CREATE TABLE IF NOT EXISTS `core_fixity_check_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `digital_object_id` INT NOT NULL,
  `expected_checksum` VARCHAR(255) NULL,
  `expected_algo` VARCHAR(50) NULL,
  `computed_checksum` VARCHAR(255) NULL,
  `result` VARCHAR(40) NOT NULL DEFAULT 'error',
  `byte_size` BIGINT UNSIGNED NULL,
  `detail` VARCHAR(255) NULL,
  `checked_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `core_fixity_check_log_do_idx` (`digital_object_id`),
  KEY `core_fixity_check_log_result_idx` (`result`),
  KEY `core_fixity_check_log_checked_idx` (`checked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
