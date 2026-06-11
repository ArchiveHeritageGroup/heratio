-- =============================================================================
-- Review Studio - Research OS Stage 14 (heratio#1230, epic #1222)
--
-- Copyright (C) 2026 Johan Pieterse
-- Plain Sailing Information Systems
-- Email: johan@plainsailingisystems.co.za
--
-- This file is part of Heratio.
--
-- Heratio is free software: you can redistribute it and/or modify it under the
-- terms of the GNU Affero General Public License as published by the Free
-- Software Foundation, either version 3 of the License, or (at your option) any
-- later version. See <https://www.gnu.org/licenses/>.
--
-- Two new tables only. NO ALTER of any existing table.
--
--   1. research_review_comment - supervisor / co-author comment threads. A
--      comment is anchored either to a specific claim (assertion_id, a row in
--      research_assertion) or to the project as a whole (assertion_id NULL).
--      Replies self-reference the root comment via thread_id. resolved is a
--      tinyint toggle. created_at gives the revision history ordering.
--
--   2. research_review_run - one adversarial reviewer-twin simulation run. The
--      persona drives the prompt; model records which gateway model answered;
--      summary + findings (JSON) hold the AI output, ALWAYS labelled in the UI
--      as "AI reviewer - via the AHG gateway, not a human reviewer".
--
-- VARCHAR (not ENUM) is used for persona/status-like columns per the Dropdown
-- Manager rule.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `research_review_comment` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `project_id`    INT NOT NULL,
  `assertion_id`  INT DEFAULT NULL COMMENT 'Anchor to a claim in research_assertion; NULL = project-level comment',
  `thread_id`     INT DEFAULT NULL COMMENT 'Self-ref to the root comment id; NULL = a root comment',
  `author_id`     INT NOT NULL COMMENT 'users.id of the comment author',
  `body`          TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resolved`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rrc_project`   (`project_id`),
  KEY `idx_rrc_assertion` (`assertion_id`),
  KEY `idx_rrc_thread`    (`thread_id`),
  KEY `idx_rrc_author`    (`author_id`),
  KEY `idx_rrc_resolved`  (`resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `research_review_run` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `project_id`  INT NOT NULL,
  `persona`     VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'methodologist',
  `model`       VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Gateway model that answered, if known',
  `summary`     TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `findings`    JSON DEFAULT NULL COMMENT 'Grouped findings: major/minor concerns, objections, required revisions, rejection risks, strongest contribution, weakest section, missing literature',
  `created_by`  INT NOT NULL COMMENT 'users.id who triggered the run',
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rrr_project` (`project_id`),
  KEY `idx_rrr_persona` (`persona`),
  KEY `idx_rrr_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
