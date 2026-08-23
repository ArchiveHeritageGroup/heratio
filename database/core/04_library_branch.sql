-- =============================================================================
-- 04_library_branch.sql - the circulation branch axis (#1473)
-- =============================================================================
-- Mirrors the columns added by
--   packages/ahg-library/database/migrations/2026_08_23_000100_add_branch_to_library_circulation.php
-- into the core schema, because a fresh install and the CI test database are
-- built by loading database/core/0*.sql and never run package migrations. A
-- column that exists only in a migration is therefore absent from every test
-- run, and any query naming it fails there while passing on a real instance.
--
-- Kept as its own file rather than folded into 00_core_schema.sql so that this
-- change can be staged, reviewed and reverted on its own. The loader globs
-- 0*.sql in name order, so this runs after 00 has created the tables.
--
-- Every statement is guarded and idempotent: MySQL 8 has no
-- ADD COLUMN IF NOT EXISTS, so each is wrapped in a prepared statement that
-- becomes a no-op when the column or index is already present. That matters
-- because an instance can reach this file in either order - core schema first,
-- or the package migration first.
--
-- Branch identity is a `repository` row. See the migration for why
-- library_loan_rule uses 0 as an "all branches" sentinel while every other
-- table uses NULL.
-- =============================================================================

-- library_loan_rule.branch_id
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_loan_rule' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `library_loan_rule` ADD COLUMN `branch_id` INT NOT NULL DEFAULT 0 COMMENT ''repository.id, or 0 = applies to all branches'' AFTER `id`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- library_copy.branch_id
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_copy' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `library_copy` ADD COLUMN `branch_id` INT NULL COMMENT ''repository.id - the branch this row belongs to'' AFTER `branch`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- library_patron.branch_id
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_patron' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `library_patron` ADD COLUMN `branch_id` INT NULL COMMENT ''repository.id - the branch this row belongs to'' AFTER `patron_type`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- library_hold.branch_id
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_hold' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `library_hold` ADD COLUMN `branch_id` INT NULL COMMENT ''repository.id - the branch this row belongs to'' AFTER `pickup_branch`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- library_checkout.branch_id
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_checkout' AND COLUMN_NAME = 'branch_id') = 0,
    'ALTER TABLE `library_checkout` ADD COLUMN `branch_id` INT NULL COMMENT ''repository.id - the branch this row belongs to'' AFTER `patron_id`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_loan_rule' AND INDEX_NAME = 'idx_loan_rule_branch') = 0,
    'ALTER TABLE `library_loan_rule` ADD INDEX `idx_loan_rule_branch` (`branch_id`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_copy' AND INDEX_NAME = 'idx_copy_branch_id') = 0,
    'ALTER TABLE `library_copy` ADD INDEX `idx_copy_branch_id` (`branch_id`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_patron' AND INDEX_NAME = 'idx_patron_branch_id') = 0,
    'ALTER TABLE `library_patron` ADD INDEX `idx_patron_branch_id` (`branch_id`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_hold' AND INDEX_NAME = 'idx_hold_branch_id') = 0,
    'ALTER TABLE `library_hold` ADD INDEX `idx_hold_branch_id` (`branch_id`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_checkout' AND INDEX_NAME = 'idx_checkout_branch_id') = 0,
    'ALTER TABLE `library_checkout` ADD INDEX `idx_checkout_branch_id` (`branch_id`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

-- The loan-rule uniqueness widens to include the branch. Without this the old
-- key permits only one rule per (material_type, patron_type) for the entire
-- installation, which is the constraint that made circulation single-branch.
SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_loan_rule' AND INDEX_NAME = 'uk_type_patron') > 0,
    'ALTER TABLE `library_loan_rule` DROP INDEX `uk_type_patron`',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_loan_rule' AND INDEX_NAME = 'uk_branch_type_patron') = 0,
    'ALTER TABLE `library_loan_rule` ADD UNIQUE KEY `uk_branch_type_patron` (`branch_id`, `material_type`, `patron_type`)',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
