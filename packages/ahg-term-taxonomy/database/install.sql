-- ============================================================================
-- ahg-term-taxonomy - install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgTermTaxonomyPlugin/database/install.sql
-- on 2026-04-30. Heratio standalone install - Phase 1 #3.
--
-- Transforms applied:
--   - DROP TABLE/VIEW statements removed
--   - CREATE TABLE -> CREATE TABLE IF NOT EXISTS (idempotent re-run)
--   - mysqldump /*!NNNNN ... */ blocks stripped (incl. multi-line)
--   - COMMENT clauses moved to end of column definition (MySQL 8 strict)
--   - VIEWs stripped (recreate by hand if needed)
--   - Wrapped in SET FOREIGN_KEY_CHECKS=0 to allow plugins to load before
--     their FK targets in other plugins / seed data
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- #661 Phase 3: SKOS cross-vocabulary mapping links.
-- Stores skos:exactMatch / closeMatch / broadMatch / narrowMatch / relatedMatch
-- pointers from a Heratio term to a concept in another controlled vocabulary
-- (LCSH, Getty AAT, Wikidata, custom). Emitted by the SKOS exporter in all
-- four serialisations (RDF/XML, Turtle, N-Triples, JSON-LD).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ahg_term_cross_match` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `term_id`      INT NOT NULL,
  `match_type`   VARCHAR(16) NOT NULL COMMENT 'exactMatch|closeMatch|broadMatch|narrowMatch|relatedMatch',
  `target_uri`   VARCHAR(512) NOT NULL,
  `target_label` VARCHAR(255) DEFAULT NULL,
  `target_vocab` VARCHAR(255) DEFAULT NULL,
  `confidence`   DECIMAL(3,2) DEFAULT NULL,
  `source`       VARCHAR(32)  NOT NULL DEFAULT 'manual' COMMENT 'manual|getty|loc|wikidata|automated',
  `created_at`   TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atcm_term`       (`term_id`),
  KEY `idx_atcm_match_type` (`match_type`),
  KEY `idx_atcm_target`     (`target_uri`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
