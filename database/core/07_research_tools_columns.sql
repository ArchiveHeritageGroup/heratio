-- =============================================================================
-- 07_research_tools_columns.sql - Source Assessment fields (#1481)
-- =============================================================================
-- Mirrors database/migrations/2026_08_24_000100_add_research_tools_columns.php
-- into the core schema, because a fresh install and the CI test database load
-- database/core/0*.sql and never run migrations.
--
-- The Source Assessment view, its form and research_source_assessment all
-- existed; there was no route and no controller action, so the Research Tools
-- sidebar link returned 404. Of the seven fields the form collects, only
-- source_type and completeness had a column.
--
-- `bias` is deliberately NOT folded into the existing `bias_context`: bias is a
-- grade and bias_context is prose explaining it. Collapsing the grade into text
-- would lose it and make it unqueryable.
--
-- Guarded and idempotent - MySQL 8 has no ADD COLUMN IF NOT EXISTS.
-- =============================================================================

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_source_assessment' AND COLUMN_NAME = 'provenance') = 0,
    'ALTER TABLE `research_source_assessment` ADD COLUMN `provenance` TEXT NULL COMMENT ''where the source came from''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_source_assessment' AND COLUMN_NAME = 'authenticity_notes') = 0,
    'ALTER TABLE `research_source_assessment` ADD COLUMN `authenticity_notes` TEXT NULL COMMENT ''doubts about the source being what it claims''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_source_assessment' AND COLUMN_NAME = 'reliability') = 0,
    'ALTER TABLE `research_source_assessment` ADD COLUMN `reliability` TINYINT UNSIGNED NULL COMMENT ''1-5''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;

SET @stmt := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'research_source_assessment' AND COLUMN_NAME = 'bias') = 0,
    'ALTER TABLE `research_source_assessment` ADD COLUMN `bias` VARCHAR(20) NULL COMMENT ''none|low|moderate|high|extreme - a grade, distinct from the free-text bias_context''',
    'DO 0'));
PREPARE s FROM @stmt; EXECUTE s; DEALLOCATE PREPARE s;
