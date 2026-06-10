-- heratio#1198 - Research Copilot: a cited answer saved into a research workspace.
CREATE TABLE IF NOT EXISTS `research_copilot_answer` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workspace_id` INT NOT NULL,
  `researcher_id` INT NULL,
  `question` VARCHAR(500) NOT NULL,
  `answer` MEDIUMTEXT NOT NULL,
  `sources_json` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rca_workspace_idx` (`workspace_id`),
  KEY `rca_researcher_idx` (`researcher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
