-- ============================================================================
-- ahg-storage-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgStorageManagePlugin/database/install.sql
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

-- ============================================================================
-- heratio#144 — Strongroom space allocation (2026-05-23, rebuild).
-- ============================================================================
-- Models a strongroom as its own entity (own table, own slug, own CRUD), not a
-- physical_object subtype. Physical objects link to a strongroom via the join
-- table below, which also records how much of the room's capacity each
-- physical object consumes. One physical object lives in at most one
-- strongroom (UNIQUE on physical_object_id).
-- ============================================================================

CREATE TABLE IF NOT EXISTS ahg_strongroom (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug                 VARCHAR(255) NOT NULL,
    name                 VARCHAR(255) NOT NULL,
    location_description TEXT,
    capacity_value       DECIMAL(12,2),
    capacity_unit        VARCHAR(20) NOT NULL DEFAULT 'linear_meters'
                         COMMENT 'linear_meters, shelves, boxes, cubic_meters',
    notes                TEXT,
    created_at           TIMESTAMP NULL,
    updated_at           TIMESTAMP NULL,
    UNIQUE KEY uq_strongroom_slug (slug),
    INDEX ix_strongroom_name (name)
);

CREATE TABLE IF NOT EXISTS ahg_physical_object_storage (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    physical_object_id INT NOT NULL,
    strongroom_id      BIGINT UNSIGNED NOT NULL,
    size_units_used    DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at         TIMESTAMP NULL,
    updated_at         TIMESTAMP NULL,
    UNIQUE KEY uq_physical_object (physical_object_id),
    INDEX ix_strongroom (strongroom_id),
    CONSTRAINT fk_phyo FOREIGN KEY (physical_object_id) REFERENCES physical_object(id) ON DELETE CASCADE,
    CONSTRAINT fk_strr FOREIGN KEY (strongroom_id)      REFERENCES ahg_strongroom(id)  ON DELETE RESTRICT
);

SET FOREIGN_KEY_CHECKS = 1;
