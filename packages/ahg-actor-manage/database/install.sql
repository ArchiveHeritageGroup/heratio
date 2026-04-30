-- ============================================================================
-- ahg-actor-manage — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgActorManagePlugin/database/install.sql
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

-- ahgActorBrowsePlugin: No custom tables needed
-- This plugin overrides actor browse/autocomplete only

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Ported from AtoM ahgAuthorityPlugin on 2026-04-30
-- ============================================================================
-- ============================================================================
-- ahgAuthorityPlugin Database Tables
-- Version: 1.0.0
-- Issues: #201-#210 (Authority Records Enhancement)
-- ============================================================================

-- Table 1: ahg_actor_identifier (#202 External Authority Linking)
-- Links actors to external authority sources (Wikidata, VIAF, ULAN, LCNAF)
CREATE TABLE IF NOT EXISTS ahg_actor_identifier (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    identifier_type VARCHAR(30) NOT NULL COMMENT 'wikidata, viaf, ulan, lcnaf, isni, orcid, gnd, uri',
    identifier_value VARCHAR(500) NOT NULL,
    uri VARCHAR(1000) NULL,
    label VARCHAR(500) NULL COMMENT 'Display label fetched from authority source',
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    verified_by INT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'manual' COMMENT 'manual, reconciliation, import',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_actor_type (actor_id, identifier_type),
    INDEX idx_type_value (identifier_type, identifier_value(255)),
    INDEX idx_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 2: ahg_actor_completeness (#206 Completeness Dashboard)
-- Tracks completeness scores and quality levels for authority records
CREATE TABLE IF NOT EXISTS ahg_actor_completeness (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    completeness_level VARCHAR(20) NOT NULL DEFAULT 'stub' COMMENT 'stub, minimal, partial, full',
    completeness_score TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0-100',
    field_scores JSON NULL COMMENT '{"name":1,"dates":0,"history":1,...}',
    has_external_ids TINYINT(1) NOT NULL DEFAULT 0,
    has_relations TINYINT(1) NOT NULL DEFAULT 0,
    has_resources TINYINT(1) NOT NULL DEFAULT 0,
    has_contacts TINYINT(1) NOT NULL DEFAULT 0,
    manual_override TINYINT(1) NOT NULL DEFAULT 0,
    assigned_to INT NULL,
    assigned_at DATETIME NULL,
    scored_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_actor (actor_id),
    INDEX idx_level (completeness_level),
    INDEX idx_score (completeness_score),
    INDEX idx_assigned (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 3: ahg_actor_occupation (#205 Structured Occupations)
-- Structured occupation data with taxonomy term linking
CREATE TABLE IF NOT EXISTS ahg_actor_occupation (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    term_id INT NULL COMMENT 'FK to occupation taxonomy term',
    occupation_text VARCHAR(500) NULL COMMENT 'Free text if no term match',
    date_from VARCHAR(20) NULL,
    date_to VARCHAR(20) NULL,
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id),
    INDEX idx_term (term_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 4: ahg_actor_merge (#207/#208 Merge/Split/Dedup)
-- Tracks merge/split operations with approval workflow
CREATE TABLE IF NOT EXISTS ahg_actor_merge (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    merge_type VARCHAR(20) NOT NULL DEFAULT 'merge' COMMENT 'merge, split, absorb',
    primary_actor_id INT NOT NULL,
    secondary_actor_ids JSON NOT NULL COMMENT '[123, 456]',
    field_choices JSON NULL COMMENT '{"history":"primary","dates":"secondary_123"}',
    relations_transferred INT NOT NULL DEFAULT 0,
    resources_transferred INT NOT NULL DEFAULT 0,
    contacts_transferred INT NOT NULL DEFAULT 0,
    identifiers_transferred INT NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, completed, rejected, reversed',
    workflow_task_id INT NULL COMMENT 'FK to ahg_workflow_task if approval required',
    notes TEXT NULL,
    performed_by INT NOT NULL,
    performed_at DATETIME NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_primary (primary_actor_id),
    INDEX idx_status (status),
    INDEX idx_type (merge_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 5: ahg_ner_authority_stub (#204 NER-to-Authority Pipeline)
-- Links NER entities to stub authority records
CREATE TABLE IF NOT EXISTS ahg_ner_authority_stub (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ner_entity_id BIGINT UNSIGNED NOT NULL,
    actor_id INT NOT NULL COMMENT 'Created stub actor',
    source_object_id INT NOT NULL COMMENT 'IO where NER found the entity',
    entity_type VARCHAR(50) NOT NULL COMMENT 'PERSON, ORG, GPE',
    entity_value VARCHAR(500) NOT NULL,
    confidence DECIMAL(5,4) NOT NULL DEFAULT 1.0000,
    status VARCHAR(20) NOT NULL DEFAULT 'stub' COMMENT 'stub, promoted, rejected',
    promoted_by INT NULL,
    promoted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ner_entity (ner_entity_id),
    INDEX idx_actor (actor_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 6: ahg_actor_function_link (#201 ISDF Functions)
-- Links actors to ISDF function entities
CREATE TABLE IF NOT EXISTS ahg_actor_function_link (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_id INT NOT NULL,
    function_id INT NOT NULL COMMENT 'FK to function entity (object.id)',
    relation_type VARCHAR(30) NOT NULL DEFAULT 'responsible' COMMENT 'responsible, participates, authorizes',
    date_from VARCHAR(20) NULL,
    date_to VARCHAR(20) NULL,
    notes TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_id),
    INDEX idx_function (function_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table 7: ahg_authority_config (Plugin settings)
-- Key-value configuration for the authority plugin
CREATE TABLE IF NOT EXISTS ahg_authority_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Data: Default configuration values
INSERT IGNORE INTO ahg_authority_config (config_key, config_value) VALUES
('wikidata_enabled', '1'),
('viaf_enabled', '1'),
('ulan_enabled', '0'),
('lcnaf_enabled', '0'),
('isni_enabled', '0'),
('auto_verify_wikidata', '0'),
('completeness_auto_recalc', '1'),
('ner_auto_stub_enabled', '0'),
('ner_auto_stub_threshold', '0.85'),
('merge_require_approval', '0'),
('dedup_threshold', '0.80'),
('hide_stubs_from_public', '1'),
('function_linking_enabled', '1');

SET FOREIGN_KEY_CHECKS = 1;
