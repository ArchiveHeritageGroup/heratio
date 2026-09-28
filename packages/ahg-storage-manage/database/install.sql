-- ============================================================================
-- ahg-storage-manage - install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgStorageManagePlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install - Phase 1 #3.
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
-- heratio#144 - Strongroom space allocation (2026-05-23, rebuild).
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

-- ============================================================================
-- heratio#1514 / atom-ahg-plugins#193 - Hierarchical storage locations.
-- ============================================================================
-- Same tables as the AtoM ahgStorageManagePlugin (kept in step on purpose, so
-- both platforms read one schema). parent_id is the source of truth; the
-- closure table is a maintained index (option 2 on #193), same shape as the
-- information_object / term / menu closures (heratio#1333).
-- ============================================================================

CREATE TABLE IF NOT EXISTS ahg_storage_location (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    slug            VARCHAR(255) NOT NULL,
    description     TEXT,
    location_type   VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown taxonomy storage_location_type',
    parent_id       BIGINT UNSIGNED DEFAULT NULL,
    capacity_value  DECIMAL(10,2) DEFAULT NULL,
    capacity_unit   VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown taxonomy capacity_unit',
    notes           TEXT,
    level           INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'depth in the tree; 0 is a root location',
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    UNIQUE KEY uq_storage_location_slug (slug),
    INDEX ix_storage_location_parent (parent_id),
    INDEX ix_storage_location_type (location_type),
    CONSTRAINT fk_storage_location_parent FOREIGN KEY (parent_id)
        REFERENCES ahg_storage_location(id) ON DELETE SET NULL
);

-- Closure table for ahg_storage_location (atom-ahg-plugins#193, option 2).
-- parent_id stays the source of truth; this is a maintained index of every
-- ancestor/descendant pair, so "everything under Strongroom B", a location's
-- path and capacity roll-ups are single joins instead of parent walks.
-- Same shape as Heratio's closure tables (heratio#1333): ancestor, descendant,
-- depth, with a (X, X, 0) self row per location. StorageLocationService keeps
-- it in step on create, move and delete; `php artisan ahg:build-closure
-- --table=ahg_storage_location` re-derives it (AtoM: rebuildClosure()).
CREATE TABLE IF NOT EXISTS ahg_storage_location_closure (
    ancestor    BIGINT UNSIGNED NOT NULL,
    descendant  BIGINT UNSIGNED NOT NULL,
    depth       INT UNSIGNED NOT NULL,
    PRIMARY KEY (ancestor, descendant),
    INDEX ix_slc_anc_depth_desc (ancestor, depth, descendant),
    INDEX ix_slc_desc_depth (descendant, depth),
    CONSTRAINT fk_slc_ancestor   FOREIGN KEY (ancestor)   REFERENCES ahg_storage_location(id) ON DELETE CASCADE,
    CONSTRAINT fk_slc_descendant FOREIGN KEY (descendant) REFERENCES ahg_storage_location(id) ON DELETE CASCADE
);

-- Backfill from parent_id. Idempotent: INSERT IGNORE on the primary key, so a
-- re-run of this file adds only missing pairs. The depth cap stops a cycle in
-- hand-edited data from recursing without end.
INSERT IGNORE INTO ahg_storage_location_closure (ancestor, descendant, depth)
WITH RECURSIVE paths (ancestor, descendant, depth) AS (
    SELECT id, id, 0 FROM ahg_storage_location
    UNION ALL
    SELECT p.ancestor, c.id, p.depth + 1
    FROM paths p
    JOIN ahg_storage_location c ON c.parent_id = p.descendant
    WHERE p.depth < 100
)
SELECT ancestor, descendant, depth FROM paths;

SET FOREIGN_KEY_CHECKS = 1;
