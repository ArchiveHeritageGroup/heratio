-- heratio#1238 - Research OS #16 (moonshot 22): Replication Pack.
--
-- One click assembles everything needed to replicate a study, READ-ONLY from
-- existing slices: the Method Protocol (#1231), the Analysis Results + their
-- provenance and linked claims (#1234), the Decision Log (#1224), and the
-- Claims + Evidence (#1223). The pack is built as a ZIP with a README/manifest
-- that lists what was included and what was omitted (e.g. restricted data) for
-- ethics reasons. NOTHING in this slice alters an existing table.
--
-- This table is OPTIONAL: it records a lightweight audit line each time a pack
-- is built (who / when), so the project keeps a trace of replication exports.
-- The feature works fully even if this table is absent (it is read-only by
-- nature); every read/write here is Schema::hasTable-guarded in the service.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider. No FK is declared so a partial install never
-- blocks an insert.

CREATE TABLE IF NOT EXISTS `research_replication_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `built_by` INT NULL COMMENT 'research_researcher.id of who built the pack',
  `built_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rrl_project_idx` (`project_id`),
  KEY `rrl_project_built_idx` (`project_id`, `built_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
