-- =============================================================================
-- 08_research_custody_columns.sql - chain-of-custody checkout field (#1478)
-- =============================================================================
-- Mirrors database/migrations/2026_08_24_000200_add_expected_return_to_material_request.php
-- into the core schema, because a fresh install and the CI test database load
-- database/core/0*.sql and never run migrations.
--
-- research_custody_handoff and research_material_request both already exist in
-- 00_core_schema.sql; only this one column is new. The custody checkout form
-- collects an expected return date and the return-verification screen displays
-- it, and there was nowhere to store it.
--
-- Guarded and idempotent - MySQL 8 has no ADD COLUMN IF NOT EXISTS.
-- =============================================================================

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_material_request' AND COLUMN_NAME = 'expected_return') = 0,
    'ALTER TABLE `research_material_request` ADD COLUMN `expected_return` DATE NULL COMMENT ''date the checked-out item is due back''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
