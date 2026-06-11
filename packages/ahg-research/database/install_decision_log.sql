-- heratio#1224 - Research OS Stage 9: per-project Decision Log.
--
-- The Decision Log is the audit trail of THINKING for a research project: the
-- recorded memory of every loop. It is distinct from research_activity_log
-- (system audit of WHAT happened). The Decision Log records WHY: every scope
-- change, every excluded source/case/dataset, every revised or rejected
-- hypothesis, every methodological pivot, every question reformulation, and
-- every supervisor instruction acted on. It answers an examiner's "why did you
-- exclude X" with receipts, feeds the limitations section, and makes going
-- backwards safe and recorded rather than silently erased.
--
-- decision_type is a VARCHAR (NOT a MySQL ENUM) per project rules; its values
-- come from the ahg_dropdown taxonomy 'decision_type' (seeded in
-- seed_decision_log_dropdowns.sql), with a clearly-seeded fallback list in the
-- service if the dropdown is absent.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider.

CREATE TABLE IF NOT EXISTS `research_decision_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `decision_type` VARCHAR(64) NOT NULL DEFAULT 'other',
  `summary` VARCHAR(500) NOT NULL,
  `reason` TEXT NULL,
  `related_ref` VARCHAR(500) NULL,
  `decided_by` VARCHAR(255) NULL,
  `decided_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rdl_project_idx` (`project_id`),
  KEY `rdl_type_idx` (`decision_type`),
  KEY `rdl_project_decided_idx` (`project_id`, `decided_at`),
  CONSTRAINT `rdl_project_fk` FOREIGN KEY (`project_id`)
    REFERENCES `research_project` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
