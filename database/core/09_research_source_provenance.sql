-- #1492 - provenance for research workspace files fetched from an external URL.
--
-- Mirrored from database/migrations/2026_08_26_000100_add_source_provenance_to_
-- research_workspace_file.php. CI builds its test database from database/core/*.sql
-- and NEVER runs migrations (#1471), so any migration-created column that a query
-- touches must be mirrored here or guarded with hasColumn - otherwise the suite
-- passes locally and 500s in CI.
--
-- Guarded with a stored procedure rather than a bare ALTER: these files are
-- applied to fresh and existing databases alike, and ADD COLUMN on a column that
-- already exists is a hard error that aborts the whole import.

DROP PROCEDURE IF EXISTS ahg_add_research_source_provenance;
DELIMITER $$
CREATE PROCEDURE ahg_add_research_source_provenance()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables
               WHERE table_schema = DATABASE() AND table_name = 'research_workspace_file') THEN

        IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                       WHERE table_schema = DATABASE()
                         AND table_name = 'research_workspace_file'
                         AND column_name = 'source_url') THEN
            ALTER TABLE research_workspace_file
                ADD COLUMN source_url VARCHAR(1024) NULL AFTER checksum_type;
        END IF;

        IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                       WHERE table_schema = DATABASE()
                         AND table_name = 'research_workspace_file'
                         AND column_name = 'fetched_at') THEN
            ALTER TABLE research_workspace_file
                ADD COLUMN fetched_at DATETIME NULL AFTER source_url;
        END IF;
    END IF;
END$$
DELIMITER ;

CALL ahg_add_research_source_provenance();
DROP PROCEDURE IF EXISTS ahg_add_research_source_provenance;
