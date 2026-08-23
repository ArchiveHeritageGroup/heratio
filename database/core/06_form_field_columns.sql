-- =============================================================================
-- 06_form_field_columns.sql - columns for form fields that had nowhere to save
-- =============================================================================
-- Mirrors database/migrations/2026_08_23_000400_add_missing_form_field_columns.php
-- into the core schema, because a fresh install and the CI test database load
-- database/core/0*.sql and never run migrations (#1478).
--
-- These are not renames. The concept was never added to the schema, so the
-- forms discarded the input on every save. The security-clearance fields are
-- the reason this was worth doing rather than deleting the inputs: a clearance
-- record that asks who vetted the person, when, and under what reference, and
-- then throws the answers away, is a worse artefact than one that never asked.
--
-- Guarded and idempotent - MySQL 8 has no ADD COLUMN IF NOT EXISTS.
-- =============================================================================

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_security_clearance' AND COLUMN_NAME = 'vetting_authority') = 0,
    'ALTER TABLE `user_security_clearance` ADD COLUMN `vetting_authority` VARCHAR(255) NULL COMMENT ''who carried out the vetting''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_security_clearance' AND COLUMN_NAME = 'vetting_date') = 0,
    'ALTER TABLE `user_security_clearance` ADD COLUMN `vetting_date` DATE NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_security_clearance' AND COLUMN_NAME = 'vetting_reference') = 0,
    'ALTER TABLE `user_security_clearance` ADD COLUMN `vetting_reference` VARCHAR(255) NULL COMMENT ''reference issued by the vetting authority''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'repository' AND COLUMN_NAME = 'repository_type') = 0,
    'ALTER TABLE `repository` ADD COLUMN `repository_type` VARCHAR(100) NULL',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ahg_vendor_transactions' AND COLUMN_NAME = 'completion_date') = 0,
    'ALTER TABLE `ahg_vendor_transactions` ADD COLUMN `completion_date` DATE NULL COMMENT ''work finished; distinct from actual_return_date''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
