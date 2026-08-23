-- =============================================================================
-- 05_museum_cco_fields.sql - CCO fields the sector forms were discarding (#1478)
-- =============================================================================
-- Mirrors database/migrations/2026_08_23_000300_add_cco_fields_to_museum_metadata.php
-- into the core schema, because a fresh install and the CI test database are
-- built by loading database/core/0*.sql and never run migrations.
--
-- The gallery and museum edit forms are built to Cataloguing Cultural Objects.
-- Thirty of their fields existed in no column, no validator and neither
-- service's whitelist, so a cataloguer's input was discarded on save with no
-- error - including dimensions_display, which the form marks REQUIRED (CCO 6.1).
--
-- ALL TEXT, NEVER VARCHAR. museum_metadata's VARCHAR definitions alone already
-- account for ~62,500 of InnoDB's 65,535-byte row limit. A VARCHAR counts
-- against that in full; a TEXT costs only a ~20-byte pointer. The first attempt
-- at the migration used VARCHAR and failed partway with "Row size too large".
-- Anyone adding to this table should assume the next VARCHAR will not fit.
--
-- Guarded and idempotent: MySQL 8 has no ADD COLUMN IF NOT EXISTS, so each is
-- wrapped in a prepared statement that becomes a no-op when already present.
-- =============================================================================

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'work_type_qualifier') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `work_type_qualifier` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'components_count') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `components_count` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'title_language') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `title_language` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'creator_display') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `creator_display` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'attribution_qualifier') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `attribution_qualifier` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'school_group') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `school_group` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'dimensions_display') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `dimensions_display` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'height_value') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `height_value` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'width_value') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `width_value` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'depth_value') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `depth_value` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'weight_value') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `weight_value` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'dimension_notes') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `dimension_notes` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'materials_display') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `materials_display` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'subjects_depicted') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `subjects_depicted` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'iconography') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `iconography` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'named_subjects') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `named_subjects` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'impression_quality') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `impression_quality` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'condition_summary') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `condition_summary` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'museum_metadata' AND COLUMN_NAME = 'location_within_repository') = 0,
    'ALTER TABLE `museum_metadata` ADD COLUMN `location_within_repository` TEXT NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
