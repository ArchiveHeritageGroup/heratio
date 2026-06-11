-- heratio#1233 - Research OS Stage 16: Research Memory.
--
-- Retains the researcher's intellectual memory AFTER a project so the next one
-- starts smarter. A research project leaves behind more than its findings: the
-- questions it never resolved, the article ideas it spun off, the sources it
-- gathered but never used, the hypotheses it abandoned, the datasets worth
-- reusing, the collaborations / conferences / grants worth chasing. Today that
-- knowledge evaporates when the project closes. Research Memory captures it as
-- curated items, both per-project and as a cross-project "carry forward" pool a
-- new project can start from.
--
-- This is the ONLY table this slice writes to. Suggestions are read read-only
-- from existing artefacts (e.g. research_decision_log unresolved/rejected
-- entries); the researcher accepts a suggestion, and that accept is the only
-- write. project_id is nullable so an item can be carried forward / detached
-- from any single project.
--
-- kind and status are VARCHAR (NOT MySQL ENUM) per project rules. The service
-- owns the canonical option lists; an ahg_dropdown taxonomy can override them
-- later without a schema change.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider. No ALTER of any existing table.

CREATE TABLE IF NOT EXISTS `research_memory_item` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `researcher_id` INT NOT NULL,
  `project_id` INT NULL,
  `kind` VARCHAR(64) NOT NULL DEFAULT 'other',
  `title` VARCHAR(500) NOT NULL,
  `body` TEXT NULL,
  `source_ref` VARCHAR(500) NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'open',
  `created_by` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rmi_researcher_idx` (`researcher_id`),
  KEY `rmi_project_idx` (`project_id`),
  KEY `rmi_kind_idx` (`kind`),
  KEY `rmi_status_idx` (`status`),
  KEY `rmi_researcher_status_idx` (`researcher_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
