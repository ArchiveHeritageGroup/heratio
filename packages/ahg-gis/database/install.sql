-- ============================================================================
-- ahg-gis — install schema
-- ============================================================================
-- Phase 1 #3 — Heratio standalone install
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Inline idempotent index helper (the framework's add_index_if_not_exists is
-- dropped after 03_framework.sql so plugins recreate locally).
DROP PROCEDURE IF EXISTS ahg_gis_add_index;
DELIMITER //
CREATE PROCEDURE ahg_gis_add_index(IN p_table VARCHAR(64), IN p_index VARCHAR(64), IN p_column VARCHAR(64))
proc: BEGIN
    DECLARE n INT DEFAULT 0;
    SELECT COUNT(*) INTO n FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table;
    IF n = 0 THEN LEAVE proc; END IF;
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND COLUMN_NAME = p_column;
    IF n = 0 THEN LEAVE proc; END IF;
    SELECT COUNT(*) INTO n FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = p_table AND INDEX_NAME = p_index;
    IF n = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD INDEX `', p_index, '` (`', p_column, '`)');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END proc //
DELIMITER ;

-- Spatial indexes for bounding-box queries on coordinate columns.
-- (MySQL 8 has no spatial index on FLOAT and no `CREATE INDEX IF NOT EXISTS`,
-- so use the helper procedure above.)
CALL ahg_gis_add_index('contact_information', 'idx_contact_latitude',  'latitude');
CALL ahg_gis_add_index('contact_information', 'idx_contact_longitude', 'longitude');

DROP PROCEDURE IF EXISTS ahg_gis_add_index;

SET FOREIGN_KEY_CHECKS = 1;
