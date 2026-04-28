-- ahg-display — install SQL
--
-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
-- License: AGPL-3.0-or-later
--
-- AHG sidecar table — denormalised IO ↔ term relations.
-- Owns: subject (taxonomy_id=35), place (42), genre (78) and any other
-- facet taxonomy a deployment chooses to populate.
--
-- This table is an AHG sidecar. It does NOT modify any AtoM/Qubit base
-- table. See docs/adr/0001-atom-base-schema-readonly-sidecar-pattern.md
-- (Pattern C) for the design rationale.
--
-- Read path: when ahg_settings.ahg_display_use_facet_denorm = 1, the
-- GLAM browse facet code reads from this table instead of joining
-- object_term_relation → term. When 0, the original join path is used.

CREATE TABLE IF NOT EXISTS `ahg_io_facet_denorm` (
    `io_id`         INT UNSIGNED NOT NULL,
    `term_id`       INT UNSIGNED NOT NULL,
    `taxonomy_id`   INT UNSIGNED NOT NULL,
    `repository_id` INT UNSIGNED NULL,
    `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`io_id`, `term_id`),
    KEY `idx_taxonomy_term` (`taxonomy_id`, `term_id`),
    KEY `idx_taxonomy_repo` (`taxonomy_id`, `repository_id`),
    KEY `idx_term_io` (`term_id`, `io_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
