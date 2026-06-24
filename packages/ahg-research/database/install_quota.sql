-- heratio#1325 - Researcher download / storage quotas + workspace files.
--
-- Two tables, both idempotent (CREATE TABLE IF NOT EXISTS):
--   research_quota_policy  - configurable limits, resolved most-specific-first
--                            (user > role > global). Jurisdiction-neutral; all
--                            enumerated values come from the Dropdown Manager.
--   research_workspace_file - researcher-uploaded files in a workspace/project.
--                            file_size is summed for the storage quota.
--
-- The ServiceProvider auto-installs this on first boot keyed on
-- research_quota_policy. A single global default policy row is seeded so the
-- quota engine has a sensible baseline out of the box.

CREATE TABLE IF NOT EXISTS `research_quota_policy` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `scope` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'global' COMMENT 'global, role, user, project (Dropdown Manager: quota_scope)',
  `scope_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'researcher_type code (role), researcher id (user), project id; NULL/* = global',
  `period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT 'monthly, total (Dropdown Manager: quota_period)',
  `max_downloads` int DEFAULT NULL COMMENT 'max download/reproduction events in the period; NULL = unlimited',
  `max_storage_bytes` bigint DEFAULT NULL COMMENT 'max total workspace-file bytes; NULL = unlimited',
  `soft_warn_pct` tinyint unsigned NOT NULL DEFAULT 80 COMMENT 'usage >= this %% of limit raises a soft warning before the hard block',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_scope` (`scope`,`scope_key`,`period`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baseline global default: 100 downloads / calendar month, 5 GiB workspace
-- storage. Operators tune this (or add role/user/project overrides) in the
-- admin quota page; this row just guarantees the engine is never unconfigured.
INSERT IGNORE INTO `research_quota_policy`
  (`scope`, `scope_key`, `period`, `max_downloads`, `max_storage_bytes`, `soft_warn_pct`, `is_active`, `notes`)
VALUES
  ('global', '*', 'monthly', 100, 5368709120, 80, 1, 'Default baseline: 100 downloads/month, 5 GiB workspace storage');

CREATE TABLE IF NOT EXISTS `research_workspace_file` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `workspace_id` int DEFAULT NULL,
  `project_id` int DEFAULT NULL,
  `researcher_id` int NOT NULL,
  `file_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` bigint NOT NULL DEFAULT 0,
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checksum_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'sha256',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_workspace` (`workspace_id`),
  KEY `idx_researcher` (`researcher_id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
