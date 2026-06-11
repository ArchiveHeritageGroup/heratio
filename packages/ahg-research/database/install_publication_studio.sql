-- heratio#1232 - Research OS #10: Publication Studio (ROS Stage 15, epic #1222)
--
-- Per-project publication workflow built ON the existing target-journal
-- directory (research_target_journal, #1107). A project can record one or more
-- SUBMISSIONS against a matched venue; each submission carries a compliance
-- REQUIREMENT checklist and a response-to-reviewers / revision history thread.
--
-- Jurisdiction-neutral: no market-specific columns. DHET is just one of many
-- accreditation markets in the target-journal directory; nothing here assumes
-- a South-African (or any other) regime. No MySQL ENUM anywhere - statuses are
-- VARCHAR backed by the Dropdown Manager taxonomy `submission_status`.
--
-- These tables are NEW. No ALTER of any existing table.

CREATE TABLE IF NOT EXISTS `research_submission` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`       INT NOT NULL,
  `venue_ref`        INT NULL,                       -- research_target_journal.id when matched from the directory (nullable: free-text venue allowed)
  `venue_name`       VARCHAR(300) NOT NULL,
  `status`           VARCHAR(40) NOT NULL DEFAULT 'drafting', -- drafting|submitted|reviewed|revised|accepted|published|rejected (Dropdown Manager: submission_status)
  `manuscript_title` VARCHAR(500) NULL,
  `submitted_at`     DATE NULL,
  `decision_at`      DATE NULL,
  `doi`              VARCHAR(255) NULL,
  `repository_url`   VARCHAR(1000) NULL,
  `notes`            TEXT NULL,
  `created_by`       INT NULL,
  `created_at`       TIMESTAMP NULL DEFAULT NULL,
  `updated_at`       TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rsub_project_idx` (`project_id`),
  KEY `rsub_venue_idx` (`venue_ref`),
  KEY `rsub_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_submission_requirement` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id` BIGINT UNSIGNED NOT NULL,
  `label`         VARCHAR(255) NOT NULL,
  `met`           TINYINT(1) NOT NULL DEFAULT 0,
  `note`          VARCHAR(1000) NULL,
  `sort_order`    INT NOT NULL DEFAULT 0,
  `created_at`    TIMESTAMP NULL DEFAULT NULL,
  `updated_at`    TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rsreq_submission_idx` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_submission_response` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `submission_id`  BIGINT UNSIGNED NOT NULL,
  `reviewer_label` VARCHAR(120) NULL,              -- e.g. "Reviewer 1", "Editor"
  `point`          TEXT NULL,                      -- the reviewer's comment / requested change
  `response`       TEXT NULL,                      -- the author's reply
  `revision_note`  TEXT NULL,                      -- what was changed in the manuscript
  `created_by`     INT NULL,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rsresp_submission_idx` (`submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
