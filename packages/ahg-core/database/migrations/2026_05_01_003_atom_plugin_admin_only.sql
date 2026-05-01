-- ============================================================================
-- Add atom_plugin.admin_only — issue #40 follow-up
-- ============================================================================
-- When 1, non-admin users NEVER see this plugin in their nav, regardless of
-- any user_plugin_grant rows. Effectively "locked to admins".
-- Safe re-run: column-existence check via stored procedure.
-- ============================================================================

DROP PROCEDURE IF EXISTS heratio_add_admin_only;
DELIMITER //
CREATE PROCEDURE heratio_add_admin_only()
proc: BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'atom_plugin'
          AND COLUMN_NAME  = 'admin_only'
    ) THEN
        LEAVE proc;
    END IF;
    ALTER TABLE atom_plugin
        ADD COLUMN admin_only TINYINT(1) NOT NULL DEFAULT 0
            COMMENT 'When 1, non-admin users never see this plugin, regardless of user_plugin_grant'
            AFTER is_locked;
END proc //
DELIMITER ;
CALL heratio_add_admin_only();
DROP PROCEDURE IF EXISTS heratio_add_admin_only;
