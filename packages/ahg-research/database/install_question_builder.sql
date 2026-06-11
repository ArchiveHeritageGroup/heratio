-- heratio#1226 - Research OS #4: Question Builder (ROS Stage 2, epic #1222)
--
-- Refines a project's research question into a structured, VERSIONED Research
-- Design Brief. One brief per project; every save creates a NEW immutable
-- version row that retains the reason for the change, so the evolution of the
-- design is fully auditable before deep source collection begins.
--
-- Jurisdiction-neutral: no market-specific columns. No MySQL ENUM (status is a
-- VARCHAR backed by the Dropdown Manager taxonomy `brief_status`).

CREATE TABLE IF NOT EXISTS `research_question_brief` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id`      INT NOT NULL,
  `current_version` INT NOT NULL DEFAULT 0,
  `status`          VARCHAR(50) NOT NULL DEFAULT 'draft',
  `created_by`      INT NULL,
  `created_at`      TIMESTAMP NULL DEFAULT NULL,
  `updated_at`      TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rqb_project_uq` (`project_id`),
  KEY `rqb_status_idx` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_question_brief_version` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brief_id`            BIGINT UNSIGNED NOT NULL,
  `version_no`          INT NOT NULL,
  `broad_topic`         TEXT NULL,
  `problem_statement`   TEXT NULL,
  `research_gap`        TEXT NULL,
  `primary_question`    TEXT NULL,
  `secondary_questions` TEXT NULL,
  `hypothesis`          TEXT NULL,
  `scope_boundaries`    TEXT NULL,
  `key_definitions`     TEXT NULL,
  `assumptions`         TEXT NULL,
  `bias_risks`          TEXT NULL,
  `change_reason`       VARCHAR(500) NULL,
  `created_by`          INT NULL,
  `created_at`          TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rqbv_brief_version_uq` (`brief_id`, `version_no`),
  KEY `rqbv_brief_idx` (`brief_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
