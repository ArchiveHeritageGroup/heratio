-- ============================================================================
-- ahg-audit-trail - append-only triggers (issue #676 Phase 5)
-- ============================================================================
-- Once a row in ahg_audit_log has a non-NULL seq it is part of the chain and
-- MUST NOT be mutated or deleted: any change would invalidate every entry_hash
-- downstream. The PHP layer never updates chained rows, but a trigger makes
-- the constraint enforceable end-to-end - even against direct mysql access
-- or a future contributor who forgets.
--
-- Legacy rows (seq IS NULL) remain mutable so existing prune / archive paths
-- still work on the pre-chain backlog.
--
-- Statements are issued one-at-a-time via DB::unprepared() from the service
-- provider (PDO cannot parse DELIMITER directives - see the same notes in
-- packages/ahg-condition/database/install.sql and
-- packages/ahg-ric/database/seed_ric_from_existing.sql).
-- ============================================================================

DROP TRIGGER IF EXISTS `ahg_audit_log_no_update_chained`;

CREATE TRIGGER `ahg_audit_log_no_update_chained`
    BEFORE UPDATE ON `ahg_audit_log`
    FOR EACH ROW
    BEGIN
        IF OLD.seq IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ahg_audit_log chained rows are append-only (issue #676)';
        END IF;
    END;

DROP TRIGGER IF EXISTS `ahg_audit_log_no_delete_chained`;

CREATE TRIGGER `ahg_audit_log_no_delete_chained`
    BEFORE DELETE ON `ahg_audit_log`
    FOR EACH ROW
    BEGIN
        IF OLD.seq IS NOT NULL THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'ahg_audit_log chained rows are append-only (issue #676)';
        END IF;
    END;
