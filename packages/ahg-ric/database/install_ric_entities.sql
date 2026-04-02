-- ============================================================
-- RiC-Native Entity Tables for Heratio
-- Creates 9 tables following AtoM's CTI + i18n patterns
-- Seeds ahg_dropdown with RiC taxonomies (~70 rows)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. ric_place — First-class Place entity
-- ============================================================
CREATE TABLE IF NOT EXISTS `ric_place` (
    `id` INT NOT NULL,
    `type_id` VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown code from ric_place_type',
    `latitude` DECIMAL(10, 7) DEFAULT NULL,
    `longitude` DECIMAL(10, 7) DEFAULT NULL,
    `authority_uri` VARCHAR(1024) DEFAULT NULL COMMENT 'GeoNames/Wikidata URI',
    `parent_id` INT DEFAULT NULL COMMENT 'Hierarchy: FK to ric_place.id',
    `source_culture` VARCHAR(16) NOT NULL DEFAULT 'en',
    PRIMARY KEY (`id`),
    CONSTRAINT `ric_place_object_fk` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ric_place_parent_fk` FOREIGN KEY (`parent_id`) REFERENCES `ric_place` (`id`) ON DELETE SET NULL,
    INDEX `idx_ric_place_type` (`type_id`),
    INDEX `idx_ric_place_parent` (`parent_id`),
    INDEX `idx_ric_place_coords` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ric_place_i18n` (
    `id` INT NOT NULL,
    `culture` VARCHAR(16) NOT NULL,
    `name` VARCHAR(1024) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `address` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`, `culture`),
    CONSTRAINT `ric_place_i18n_fk` FOREIGN KEY (`id`) REFERENCES `ric_place` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 2. ric_rule — Mandate/Rule/Regulation entity
-- ============================================================
CREATE TABLE IF NOT EXISTS `ric_rule` (
    `id` INT NOT NULL,
    `type_id` VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown code from ric_rule_type',
    `jurisdiction` VARCHAR(512) DEFAULT NULL,
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `authority_uri` VARCHAR(1024) DEFAULT NULL,
    `source_culture` VARCHAR(16) NOT NULL DEFAULT 'en',
    PRIMARY KEY (`id`),
    CONSTRAINT `ric_rule_object_fk` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
    INDEX `idx_ric_rule_type` (`type_id`),
    INDEX `idx_ric_rule_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ric_rule_i18n` (
    `id` INT NOT NULL,
    `culture` VARCHAR(16) NOT NULL,
    `title` VARCHAR(1024) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `legislation` TEXT DEFAULT NULL,
    `sources` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`, `culture`),
    CONSTRAINT `ric_rule_i18n_fk` FOREIGN KEY (`id`) REFERENCES `ric_rule` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. ric_activity — Activity entity (richer than AtoM event)
-- ============================================================
CREATE TABLE IF NOT EXISTS `ric_activity` (
    `id` INT NOT NULL,
    `type_id` VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown code from ric_activity_type',
    `start_date` DATE DEFAULT NULL,
    `end_date` DATE DEFAULT NULL,
    `place_id` INT DEFAULT NULL COMMENT 'FK to ric_place.id',
    `source_culture` VARCHAR(16) NOT NULL DEFAULT 'en',
    PRIMARY KEY (`id`),
    CONSTRAINT `ric_activity_object_fk` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ric_activity_place_fk` FOREIGN KEY (`place_id`) REFERENCES `ric_place` (`id`) ON DELETE SET NULL,
    INDEX `idx_ric_activity_type` (`type_id`),
    INDEX `idx_ric_activity_dates` (`start_date`, `end_date`),
    INDEX `idx_ric_activity_place` (`place_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ric_activity_i18n` (
    `id` INT NOT NULL,
    `culture` VARCHAR(16) NOT NULL,
    `name` VARCHAR(1024) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `date_display` VARCHAR(512) DEFAULT NULL,
    PRIMARY KEY (`id`, `culture`),
    CONSTRAINT `ric_activity_i18n_fk` FOREIGN KEY (`id`) REFERENCES `ric_activity` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. ric_instantiation — Rich Instantiation metadata
-- ============================================================
CREATE TABLE IF NOT EXISTS `ric_instantiation` (
    `id` INT NOT NULL,
    `record_id` INT DEFAULT NULL COMMENT 'FK to object.id (information_object or any RiC entity)',
    `carrier_type` VARCHAR(50) DEFAULT NULL COMMENT 'ahg_dropdown code from ric_carrier_type',
    `mime_type` VARCHAR(255) DEFAULT NULL,
    `extent_value` DECIMAL(12, 2) DEFAULT NULL,
    `extent_unit` VARCHAR(50) DEFAULT NULL,
    `digital_object_id` INT DEFAULT NULL COMMENT 'Optional link to existing digital_object.id',
    `source_culture` VARCHAR(16) NOT NULL DEFAULT 'en',
    PRIMARY KEY (`id`),
    CONSTRAINT `ric_instantiation_object_fk` FOREIGN KEY (`id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
    CONSTRAINT `ric_instantiation_record_fk` FOREIGN KEY (`record_id`) REFERENCES `object` (`id`) ON DELETE SET NULL,
    INDEX `idx_ric_instantiation_record` (`record_id`),
    INDEX `idx_ric_instantiation_carrier` (`carrier_type`),
    INDEX `idx_ric_instantiation_digital_object` (`digital_object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ric_instantiation_i18n` (
    `id` INT NOT NULL,
    `culture` VARCHAR(16) NOT NULL,
    `title` VARCHAR(1024) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `technical_characteristics` TEXT DEFAULT NULL,
    `production_technical_characteristics` TEXT DEFAULT NULL,
    PRIMARY KEY (`id`, `culture`),
    CONSTRAINT `ric_instantiation_i18n_fk` FOREIGN KEY (`id`) REFERENCES `ric_instantiation` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. ric_relation_meta — RiC predicate metadata on relations
-- ============================================================
CREATE TABLE IF NOT EXISTS `ric_relation_meta` (
    `relation_id` INT NOT NULL COMMENT 'FK to relation.id (which is FK to object.id)',
    `rico_predicate` VARCHAR(255) NOT NULL COMMENT 'e.g. rico:hasCreator',
    `inverse_predicate` VARCHAR(255) DEFAULT NULL,
    `domain_class` VARCHAR(100) DEFAULT NULL COMMENT 'RiC-O class of subject',
    `range_class` VARCHAR(100) DEFAULT NULL COMMENT 'RiC-O class of object',
    `dropdown_code` VARCHAR(100) DEFAULT NULL COMMENT 'ahg_dropdown ric_relation_type code',
    `certainty` VARCHAR(50) DEFAULT NULL COMMENT 'certain, probable, possible',
    `evidence` TEXT DEFAULT NULL COMMENT 'Source/evidence for this relation',
    PRIMARY KEY (`relation_id`),
    CONSTRAINT `ric_relation_meta_fk` FOREIGN KEY (`relation_id`) REFERENCES `object` (`id`) ON DELETE CASCADE,
    INDEX `idx_ric_rel_predicate` (`rico_predicate`),
    INDEX `idx_ric_rel_dropdown` (`dropdown_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DROPDOWN TAXONOMIES (ahg_dropdown)
-- taxonomy_section = 'ric' for all
-- ============================================================

-- ric_entity_type
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_entity_type', 'RiC Entity Type', 'ric', 'place', 'Place', '#28a745', 'fa-map-marker-alt', 1, 0, 1, NOW(), NOW()),
('ric_entity_type', 'RiC Entity Type', 'ric', 'rule', 'Rule', '#fd7e14', 'fa-gavel', 2, 0, 1, NOW(), NOW()),
('ric_entity_type', 'RiC Entity Type', 'ric', 'activity', 'Activity', '#6f42c1', 'fa-running', 3, 0, 1, NOW(), NOW()),
('ric_entity_type', 'RiC Entity Type', 'ric', 'instantiation', 'Instantiation', '#17a2b8', 'fa-file-alt', 4, 0, 1, NOW(), NOW());

-- ric_place_type
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_place_type', 'RiC Place Type', 'ric', 'country', 'Country', '#007bff', 'fa-globe', 1, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'region', 'Region', '#6c757d', 'fa-map', 2, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'province', 'Province/State', '#6c757d', 'fa-map', 3, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'city', 'City', '#28a745', 'fa-city', 4, 1, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'town', 'Town', '#28a745', 'fa-home', 5, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'building', 'Building', '#fd7e14', 'fa-building', 6, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'site', 'Site', '#dc3545', 'fa-landmark', 7, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'room', 'Room', '#ffc107', 'fa-door-open', 8, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'district', 'District', '#6c757d', 'fa-map-marked', 9, 0, 1, NOW(), NOW()),
('ric_place_type', 'RiC Place Type', 'ric', 'address', 'Address', '#17a2b8', 'fa-map-pin', 10, 0, 1, NOW(), NOW());

-- ric_rule_type
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_rule_type', 'RiC Rule Type', 'ric', 'mandate', 'Mandate', '#dc3545', 'fa-gavel', 1, 1, 1, NOW(), NOW()),
('ric_rule_type', 'RiC Rule Type', 'ric', 'regulation', 'Regulation', '#fd7e14', 'fa-balance-scale', 2, 0, 1, NOW(), NOW()),
('ric_rule_type', 'RiC Rule Type', 'ric', 'policy', 'Policy', '#007bff', 'fa-file-contract', 3, 0, 1, NOW(), NOW()),
('ric_rule_type', 'RiC Rule Type', 'ric', 'law', 'Law', '#28a745', 'fa-landmark', 4, 0, 1, NOW(), NOW()),
('ric_rule_type', 'RiC Rule Type', 'ric', 'convention', 'Convention', '#6f42c1', 'fa-handshake', 5, 0, 1, NOW(), NOW()),
('ric_rule_type', 'RiC Rule Type', 'ric', 'standard', 'Standard', '#17a2b8', 'fa-certificate', 6, 0, 1, NOW(), NOW());

-- ric_activity_type
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_activity_type', 'RiC Activity Type', 'ric', 'production', 'Production', '#28a745', 'fa-industry', 1, 1, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'accumulation', 'Accumulation', '#007bff', 'fa-layer-group', 2, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'custody', 'Custody', '#6f42c1', 'fa-archive', 3, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'transfer', 'Transfer', '#fd7e14', 'fa-exchange-alt', 4, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'publication', 'Publication', '#17a2b8', 'fa-book-open', 5, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'reproduction', 'Reproduction', '#6c757d', 'fa-copy', 6, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'modification', 'Modification', '#ffc107', 'fa-edit', 7, 0, 1, NOW(), NOW()),
('ric_activity_type', 'RiC Activity Type', 'ric', 'destruction', 'Destruction', '#dc3545', 'fa-fire', 8, 0, 1, NOW(), NOW());

-- ric_carrier_type
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'physical', 'Physical', '#6c757d', 'fa-box', 1, 1, 1, NOW(), NOW()),
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'digital', 'Digital', '#007bff', 'fa-hdd', 2, 0, 1, NOW(), NOW()),
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'microform', 'Microform', '#fd7e14', 'fa-film', 3, 0, 1, NOW(), NOW()),
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'audio', 'Audio', '#28a745', 'fa-volume-up', 4, 0, 1, NOW(), NOW()),
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'video', 'Video', '#dc3545', 'fa-video', 5, 0, 1, NOW(), NOW()),
('ric_carrier_type', 'RiC Carrier Type', 'ric', 'mixed', 'Mixed', '#6f42c1', 'fa-cubes', 6, 0, 1, NOW(), NOW());

-- ric_relation_category
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`, `updated_at`) VALUES
('ric_relation_category', 'RiC Relation Category', 'ric', 'provenance', 'Provenance', '#28a745', 'fa-project-diagram', 1, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'temporal', 'Temporal', '#007bff', 'fa-clock', 2, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'associative', 'Associative', '#6f42c1', 'fa-link', 3, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'hierarchical', 'Hierarchical', '#fd7e14', 'fa-sitemap', 4, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'functional', 'Functional', '#dc3545', 'fa-cogs', 5, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'whole_part', 'Whole-Part', '#ffc107', 'fa-puzzle-piece', 6, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'sequential', 'Sequential', '#17a2b8', 'fa-sort-numeric-down', 7, 0, 1, NOW(), NOW()),
('ric_relation_category', 'RiC Relation Category', 'ric', 'derivative', 'Derivative', '#6c757d', 'fa-code-branch', 8, 0, 1, NOW(), NOW());

-- ric_relation_type (~30 specific types with RiC-O predicate metadata in JSON)
INSERT INTO `ahg_dropdown` (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `metadata`, `created_at`, `updated_at`) VALUES
-- Provenance relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_creator', 'Has Creator', '#28a745', 'fa-user-edit', 1, 0, 1, '{"predicate":"rico:hasCreator","inverse":"rico:isCreatorOf","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_accumulator', 'Has Accumulator', '#28a745', 'fa-layer-group', 2, 0, 1, '{"predicate":"rico:hasAccumulator","inverse":"rico:isAccumulatorOf","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_collector', 'Has Collector', '#28a745', 'fa-hand-holding', 3, 0, 1, '{"predicate":"rico:hasCollector","inverse":"rico:isCollectorOf","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_provenance', 'Has Provenance', '#28a745', 'fa-project-diagram', 4, 0, 1, '{"predicate":"rico:hasProvenance","inverse":"rico:isProvenanceOf","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'held_by', 'Held By', '#ffc107', 'fa-archive', 5, 0, 1, '{"predicate":"rico:heldBy","inverse":"rico:holds","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'managed_by', 'Managed By', '#ffc107', 'fa-user-tie', 6, 0, 1, '{"predicate":"rico:managedBy","inverse":"rico:manages","category":"provenance","domain":"Record","range":"Agent","symmetric":false}', NOW(), NOW()),
-- Hierarchical relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'includes', 'Includes', '#fd7e14', 'fa-sitemap', 7, 0, 1, '{"predicate":"rico:includes","inverse":"rico:isIncludedIn","category":"hierarchical","domain":"RecordSet","range":"Record","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_part', 'Has Part', '#fd7e14', 'fa-puzzle-piece', 8, 0, 1, '{"predicate":"rico:hasPart","inverse":"rico:isPartOf","category":"whole_part","domain":"*","range":"*","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_superior_of', 'Is Superior Of', '#fd7e14', 'fa-level-up-alt', 9, 0, 1, '{"predicate":"rico:isSuperiorOf","inverse":"rico:isSubordinateTo","category":"hierarchical","domain":"Agent","range":"Agent","symmetric":false}', NOW(), NOW()),
-- Temporal relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_successor_of', 'Is Successor Of', '#007bff', 'fa-arrow-right', 10, 0, 1, '{"predicate":"rico:isSuccessorOf","inverse":"rico:isPredecessorOf","category":"temporal","domain":"Agent","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_predecessor_of', 'Is Predecessor Of', '#007bff', 'fa-arrow-left', 11, 0, 1, '{"predicate":"rico:isPredecessorOf","inverse":"rico:isSuccessorOf","category":"temporal","domain":"Agent","range":"Agent","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'follows', 'Follows', '#007bff', 'fa-fast-forward', 12, 0, 1, '{"predicate":"rico:follows","inverse":"rico:precedes","category":"sequential","domain":"Record","range":"Record","symmetric":false}', NOW(), NOW()),
-- Functional relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'performs_function', 'Performs Function', '#dc3545', 'fa-cogs', 13, 0, 1, '{"predicate":"rico:performsOrPerformed","inverse":"rico:isOrWasPerformedBy","category":"functional","domain":"Agent","range":"Function","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_mandate', 'Has Mandate', '#dc3545', 'fa-gavel', 14, 0, 1, '{"predicate":"rico:isAssociatedWithRule","inverse":"rico:isRuleAssociatedWith","category":"functional","domain":"Agent","range":"Rule","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_regulated_by', 'Is Regulated By', '#dc3545', 'fa-balance-scale-left', 15, 0, 1, '{"predicate":"rico:isOrWasRegulatedBy","inverse":"rico:regulatesOrRegulated","category":"functional","domain":"Activity","range":"Rule","symmetric":false}', NOW(), NOW()),
-- Associative relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_associated_with', 'Is Associated With', '#6f42c1', 'fa-link', 16, 0, 1, '{"predicate":"rico:isAssociatedWith","inverse":"rico:isAssociatedWith","category":"associative","domain":"*","range":"*","symmetric":true}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_equivalent_to', 'Is Equivalent To', '#6f42c1', 'fa-equals', 17, 0, 1, '{"predicate":"rico:isEquivalentTo","inverse":"rico:isEquivalentTo","category":"associative","domain":"*","range":"*","symmetric":true}', NOW(), NOW()),
-- Place relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_or_had_location', 'Has/Had Location', '#17a2b8', 'fa-map-marker-alt', 18, 0, 1, '{"predicate":"rico:hasOrHadLocation","inverse":"rico:isOrWasLocationOf","category":"associative","domain":"*","range":"Place","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_or_had_subject', 'Has/Had Subject', '#17a2b8', 'fa-tags', 19, 0, 1, '{"predicate":"rico:hasOrHadSubject","inverse":"rico:isOrWasSubjectOf","category":"associative","domain":"Record","range":"*","symmetric":false}', NOW(), NOW()),
-- Activity relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'results_from', 'Results From Activity', '#6f42c1', 'fa-random', 20, 0, 1, '{"predicate":"rico:resultsOrResultedFrom","inverse":"rico:resultsOrResultedIn","category":"functional","domain":"Record","range":"Activity","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'performed_by', 'Performed By', '#6f42c1', 'fa-user', 21, 0, 1, '{"predicate":"rico:isOrWasPerformedBy","inverse":"rico:performsOrPerformed","category":"functional","domain":"Activity","range":"Agent","symmetric":false}', NOW(), NOW()),
-- Instantiation relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_instantiation', 'Has Instantiation', '#17a2b8', 'fa-file', 22, 0, 1, '{"predicate":"rico:hasInstantiation","inverse":"rico:isInstantiationOf","category":"whole_part","domain":"Record","range":"Instantiation","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_derived_instantiation', 'Has Derived Instantiation', '#17a2b8', 'fa-code-branch', 23, 0, 1, '{"predicate":"rico:hasDerivedInstantiation","inverse":"rico:isDerivedFromInstantiation","category":"derivative","domain":"Instantiation","range":"Instantiation","symmetric":false}', NOW(), NOW()),
-- Family relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_family_member', 'Has Family Member', '#28a745', 'fa-users', 24, 0, 1, '{"predicate":"rico:hasFamilyMember","inverse":"rico:isFamilyMemberOf","category":"associative","domain":"Family","range":"Person","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_child_of', 'Is Child Of', '#28a745', 'fa-child', 25, 0, 1, '{"predicate":"rico:isChildOf","inverse":"rico:isParentOf","category":"associative","domain":"Person","range":"Person","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_sibling_of', 'Is Sibling Of', '#28a745', 'fa-people-arrows', 26, 0, 1, '{"predicate":"rico:isSiblingOf","inverse":"rico:isSiblingOf","category":"associative","domain":"Person","range":"Person","symmetric":true}', NOW(), NOW()),
-- Record resource relations
('ric_relation_type', 'RiC Relation Type', 'ric', 'documents', 'Documents', '#007bff', 'fa-file-alt', 27, 0, 1, '{"predicate":"rico:documents","inverse":"rico:isDocumentedBy","category":"functional","domain":"Record","range":"Activity","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'has_genetic_link_to', 'Has Genetic Link To', '#6c757d', 'fa-dna', 28, 0, 1, '{"predicate":"rico:hasGeneticLinkToRecordResource","inverse":"rico:hasGeneticLinkToRecordResource","category":"derivative","domain":"Record","range":"Record","symmetric":true}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_copy_of', 'Is Copy Of', '#6c757d', 'fa-copy', 29, 0, 1, '{"predicate":"rico:isCopyOf","inverse":"rico:hasCopy","category":"derivative","domain":"Record","range":"Record","symmetric":false}', NOW(), NOW()),
('ric_relation_type', 'RiC Relation Type', 'ric', 'is_original_of', 'Is Original Of', '#6c757d', 'fa-certificate', 30, 0, 1, '{"predicate":"rico:isOriginalOf","inverse":"rico:hasOriginal","category":"derivative","domain":"Record","range":"Record","symmetric":false}', NOW(), NOW());

-- ============================================================
-- COLUMN MAP ENTRIES (link table columns to dropdown taxonomies)
-- ============================================================
INSERT INTO `ahg_dropdown_column_map` (`table_name`, `column_name`, `taxonomy`, `is_strict`, `created_at`)
SELECT 'ric_place', 'type_id', 'ric_place_type', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ahg_dropdown_column_map` WHERE `table_name`='ric_place' AND `column_name`='type_id');

INSERT INTO `ahg_dropdown_column_map` (`table_name`, `column_name`, `taxonomy`, `is_strict`, `created_at`)
SELECT 'ric_rule', 'type_id', 'ric_rule_type', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ahg_dropdown_column_map` WHERE `table_name`='ric_rule' AND `column_name`='type_id');

INSERT INTO `ahg_dropdown_column_map` (`table_name`, `column_name`, `taxonomy`, `is_strict`, `created_at`)
SELECT 'ric_activity', 'type_id', 'ric_activity_type', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ahg_dropdown_column_map` WHERE `table_name`='ric_activity' AND `column_name`='type_id');

INSERT INTO `ahg_dropdown_column_map` (`table_name`, `column_name`, `taxonomy`, `is_strict`, `created_at`)
SELECT 'ric_instantiation', 'carrier_type', 'ric_carrier_type', 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `ahg_dropdown_column_map` WHERE `table_name`='ric_instantiation' AND `column_name`='carrier_type');

-- ============================================================
-- Done. Verify with:
--   SHOW TABLES LIKE 'ric_%';
--   SELECT DISTINCT taxonomy FROM ahg_dropdown WHERE taxonomy_section = 'ric';
-- ============================================================
