-- heratio#1237 - Research OS #15: Open-format project export.
--
-- Founding principle: "no lock-in / the exit door is always open." A researcher
-- must be able to walk out with a faithful, full-fidelity copy of their work in
-- open, non-proprietary formats at any time.
--
-- This OPTIONAL log table records each export the system produced (which project,
-- which format, by whom, when) purely as an audit convenience. The export itself
-- is read-only over every existing research table - this is the ONLY table the
-- slice ever writes to, and it never alters any existing table.
--
-- Idempotent: CREATE TABLE IF NOT EXISTS. Auto-installed on boot from the
-- AhgResearchServiceProvider.

CREATE TABLE IF NOT EXISTS `research_export_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `format` VARCHAR(32) NOT NULL DEFAULT 'zip' COMMENT 'zip, markdown, json, bibtex, ris, csl',
  `exported_by` VARCHAR(255) NULL COMMENT 'human-readable name or email of the exporter',
  `exported_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rel_project_idx` (`project_id`),
  KEY `rel_format_idx` (`format`),
  KEY `rel_project_at_idx` (`project_id`, `exported_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
