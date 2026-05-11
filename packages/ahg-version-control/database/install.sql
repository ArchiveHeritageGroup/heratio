-- ahgVersionControlPlugin / ahg-version-control — schema (Phase A)
--
-- Two version tables, one per entity type in the v1 scope (information_object, actor).
-- Snapshot is the full deterministic JSON state including all i18n cultures,
-- access points, taxonomy relations and custom field values.
--
-- BASE ATOM IS NOT MODIFIED. These tables FK to base AtoM tables for read-only
-- referential integrity; the base schema is untouched per project lock.
--
-- Idempotent: safe to re-run. CREATE TABLE IF NOT EXISTS guards both tables.
--
-- This file is byte-equivalent to:
--   /usr/share/nginx/archive/atom-ahg-plugins/ahgVersionControlPlugin/database/install.sql

-- -----------------------------------------------------
-- 1. information_object_version
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS information_object_version (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    information_object_id INT NOT NULL,
    version_number INT NOT NULL COMMENT 'Monotonic per information_object',
    snapshot JSON NOT NULL COMMENT 'Full deterministic state: schema_version + base + i18n + access_points + custom_fields',
    change_summary VARCHAR(500) DEFAULT NULL COMMENT 'Auto-generated summary or user-supplied note',
    changed_fields JSON DEFAULT NULL COMMENT 'List of field names that differ from prior version',
    created_by INT DEFAULT NULL COMMENT 'FK user.id; nullable for system-created versions',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_restore TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 if this version was created by a restore action',
    restored_from_version INT DEFAULT NULL COMMENT 'When is_restore=1, the version_number that was restored',
    PRIMARY KEY (id),
    UNIQUE KEY uq_io_version (information_object_id, version_number),
    KEY idx_io (information_object_id),
    KEY idx_created (created_at),
    KEY idx_created_by (created_by),
    CONSTRAINT fk_iov_io FOREIGN KEY (information_object_id)
        REFERENCES information_object(id) ON DELETE CASCADE,
    CONSTRAINT fk_iov_user FOREIGN KEY (created_by)
        REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. actor_version
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS actor_version (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_id INT NOT NULL,
    version_number INT NOT NULL COMMENT 'Monotonic per actor',
    snapshot JSON NOT NULL,
    change_summary VARCHAR(500) DEFAULT NULL,
    changed_fields JSON DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_restore TINYINT(1) NOT NULL DEFAULT 0,
    restored_from_version INT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_actor_version (actor_id, version_number),
    KEY idx_actor (actor_id),
    KEY idx_created (created_at),
    KEY idx_created_by (created_by),
    CONSTRAINT fk_av_actor FOREIGN KEY (actor_id)
        REFERENCES actor(id) ON DELETE CASCADE,
    CONSTRAINT fk_av_user FOREIGN KEY (created_by)
        REFERENCES user(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
