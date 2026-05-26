-- ============================================================================
-- ahg-search — install schema
-- ============================================================================
-- Ported from /usr/share/nginx/archive/atom-ahg-plugins/ahgSearchPlugin/database/install.sql
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

-- ahgSearchPlugin: No legacy plugin tables required.
-- All baseline search functionality uses Elasticsearch and existing AtoM tables.

-- ============================================================================
-- Issue #650 Phase 3 - search analytics
-- ============================================================================
-- Per-query log used to surface top queries, zero-result queries, and CTR in
-- the admin search analytics dashboard. Schema is intentionally lightweight:
--   - user_id is NULL for anonymous searches
--   - anonymized_id is sha256(ip) when not logged in; used so we can count
--     unique searchers without storing the raw IP (POPIA / GDPR friendlier)
--   - filters_json holds the active filter set so we can correlate top
--     queries to common refinements
--   - click_position is NULL until the click-tracking POST endpoint flips
--     it; that gives us CTR per query without joining a separate table
-- ============================================================================
CREATE TABLE IF NOT EXISTS ahg_search_query_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    anonymized_id VARCHAR(64) NULL,
    query VARCHAR(512) NOT NULL,
    filters_json JSON NULL,
    result_count INT NOT NULL DEFAULT 0,
    click_position INT NULL,
    executed_at DATETIME NOT NULL,
    response_time_ms INT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_search_log_executed (executed_at),
    KEY idx_search_log_query (query(64)),
    KEY idx_search_log_user (user_id),
    KEY idx_search_log_zero (result_count, executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Issue #650 Phase 3 - per-query search analytics log';

SET FOREIGN_KEY_CHECKS = 1;
