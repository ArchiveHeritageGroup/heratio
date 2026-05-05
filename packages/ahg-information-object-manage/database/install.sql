-- ============================================================================
-- ahg-information-object-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgInformationObjectManagePlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install — Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE → CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ahgInformationObjectManagePlugin
-- Mostly uses existing information_object, event, note, relation,
-- object_term_relation, status, property, and their i18n counterparts.

-- Sidecar table: per-IO security classification + handling instructions.
-- One row per information_object (object_id is PK + FK). Decoupled from the
-- AtoM/Qubit base table so we can iterate on the security model without
-- touching read-only base schema (ADR-0001 Pattern A: ahg_* sidecar).
CREATE TABLE IF NOT EXISTS ahg_io_security (
    object_id                       INT          NOT NULL PRIMARY KEY,
    security_classification_id      INT UNSIGNED NULL,
    security_reason                 TEXT         NULL,
    security_review_date            DATE         NULL,
    security_declassify_date        DATE         NULL,
    security_handling_instructions  TEXT         NULL,
    security_inherit_to_children    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at                      DATETIME     NULL,
    updated_at                      DATETIME     NULL,
    CONSTRAINT fk_ahg_io_security_object
        FOREIGN KEY (object_id) REFERENCES information_object(id) ON DELETE CASCADE,
    CONSTRAINT fk_ahg_io_security_class
        FOREIGN KEY (security_classification_id) REFERENCES security_classification(id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;
