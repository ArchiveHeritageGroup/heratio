-- ============================================================================
-- ahg-custom-fields — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgCustomFieldsPlugin/database/install.sql
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

-- ============================================================
-- ahgCustomFieldsPlugin Installation SQL
-- Admin-configurable custom metadata fields (EAV pattern)
-- ============================================================

-- Register plugin in atom_plugin table
INSERT IGNORE INTO atom_plugin (name, class_name, version, description, category, is_enabled, is_core, is_locked, load_order, created_at)
VALUES (
    'ahgCustomFieldsPlugin',
    'ahgCustomFieldsPluginConfiguration',
    '1.0.0',
    'Admin-configurable custom metadata fields for any entity type',
    'metadata',
    0,
    0,
    0,
    45,
    NOW()
);

UPDATE atom_plugin SET
    version = '1.0.0',
    description = 'Admin-configurable custom metadata fields for any entity type',
    category = 'metadata',
    load_order = 45,
    updated_at = NOW()
WHERE name = 'ahgCustomFieldsPlugin';

-- ============================================================
-- Field definitions — admin-configurable schema
-- ============================================================
CREATE TABLE IF NOT EXISTS `custom_field_definition` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `field_key` VARCHAR(100) NOT NULL,
    `field_label` VARCHAR(255) NOT NULL,
    `field_type` VARCHAR(30) NOT NULL DEFAULT 'text'
        COMMENT 'text, textarea, date, number, boolean, dropdown, url',
    `entity_type` VARCHAR(50) NOT NULL
        COMMENT 'informationobject, actor, accession, repository, donor, function',
    `field_group` VARCHAR(100) NULL
        COMMENT 'UI section grouping label',
    `dropdown_taxonomy` VARCHAR(100) NULL
        COMMENT 'ahg_dropdown taxonomy key when field_type=dropdown',
    `is_required` TINYINT(1) DEFAULT 0,
    `is_searchable` TINYINT(1) DEFAULT 0,
    `is_visible_public` TINYINT(1) DEFAULT 1,
    `is_visible_edit` TINYINT(1) DEFAULT 1,
    `is_repeatable` TINYINT(1) DEFAULT 0,
    `default_value` VARCHAR(500) NULL,
    `help_text` VARCHAR(500) NULL,
    `validation_rule` VARCHAR(255) NULL
        COMMENT 'e.g. max:255, regex:/^[A-Z]/',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_field_entity` (`field_key`, `entity_type`),
    INDEX `idx_entity_type` (`entity_type`),
    INDEX `idx_active_entity` (`is_active`, `entity_type`),
    INDEX `idx_group` (`field_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Field values — EAV storage
-- ============================================================
CREATE TABLE IF NOT EXISTS `custom_field_value` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `field_definition_id` BIGINT UNSIGNED NOT NULL,
    `object_id` INT NOT NULL
        COMMENT 'FK to the entity (information_object.id, actor.id, etc.)',
    `value_text` TEXT NULL,
    `value_number` DECIMAL(15,4) NULL,
    `value_date` DATE NULL,
    `value_boolean` TINYINT(1) NULL,
    `value_dropdown` VARCHAR(100) NULL
        COMMENT 'ahg_dropdown code reference',
    `sequence` INT DEFAULT 0
        COMMENT 'Ordering for repeatable fields',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_field_object` (`field_definition_id`, `object_id`),
    INDEX `idx_object` (`object_id`),
    INDEX `idx_dropdown` (`value_dropdown`),
    FOREIGN KEY (`field_definition_id`)
        REFERENCES `custom_field_definition`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
