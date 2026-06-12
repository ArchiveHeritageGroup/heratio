-- heratio#1222 - Research OS: Research Milestones & Deliverables tracker slice.
--
-- The PLAN register for a research project: the milestones and deliverables a
-- project intends to reach, each with a due date, a status and a progress
-- percentage, so a project plan is documented in one place alongside its DMP,
-- outputs, ethics, funding and team. A milestone is a planned point in the work
-- (a decision point, a review, a dissemination event) and a deliverable is a
-- tangible output the plan commits to producing; both are tracked here with a
-- single type taxonomy. This documents the intended schedule of the work; it is
-- distinct from the Research Outputs register, which records outputs that have
-- actually been produced.
--
-- International and jurisdiction-neutral: NOTHING here defaults to one country,
-- institution or funding regime. Dates are plain calendar dates and the
-- deliverable / title text is free-text DATA, not a fixed list.
--
-- Additive only: one NEW table. No ALTER of any existing table. VARCHAR for the
-- dropdown-backed columns (milestone_type, status), never ENUM and never a
-- hardcoded <option> list in a view.

CREATE TABLE IF NOT EXISTS `research_milestone` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `title` VARCHAR(512) NOT NULL COMMENT 'the milestone or deliverable name - free-text DATA, no jurisdiction or institution assumed',
  `milestone_type` VARCHAR(32) NOT NULL DEFAULT 'milestone' COMMENT 'from ahg_dropdown milestone_type: milestone, deliverable, decision_point, review, dissemination, other',
  `description` MEDIUMTEXT NULL COMMENT 'free-text description of the milestone or deliverable',
  `due_date` DATE NULL COMMENT 'the planned date the milestone or deliverable is due',
  `completed_date` DATE NULL COMMENT 'the date the milestone or deliverable was actually completed, if any',
  `status` VARCHAR(32) NOT NULL DEFAULT 'planned' COMMENT 'from ahg_dropdown milestone_status: planned, in_progress, completed, delayed, cancelled',
  `progress_pct` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'progress towards completion, 0-100',
  `deliverable` VARCHAR(512) NULL COMMENT 'the concrete deliverable expected at this milestone - free-text DATA',
  `owner_id` INT NULL COMMENT 'research_researcher.id - the record owner',
  `created_by` INT NULL COMMENT 'research_researcher.id',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rmilestone_project_idx` (`project_id`),
  KEY `rmilestone_type_idx` (`milestone_type`),
  KEY `rmilestone_status_idx` (`status`),
  KEY `rmilestone_due_idx` (`due_date`),
  KEY `rmilestone_owner_idx` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
