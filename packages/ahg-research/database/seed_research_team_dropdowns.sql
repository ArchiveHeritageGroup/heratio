-- heratio#1222 - Research OS: Research Team & Collaborators register - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Two taxonomies, both surfaced in the
-- Dropdown Manager and both VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   research_team_role   - the contributor's role on the project. The codes are
--                          informed by the international CRediT contributor-roles
--                          taxonomy and common project-team roles; an administrator
--                          can extend them without a code change.
--   research_team_status - the contributor's current involvement status.
--
-- International and jurisdiction-neutral: no value here assumes any one country,
-- institution or funding regime.

-- ---------------------------------------------------------------------------
-- Team role
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_team_role', 'Research Team Role', 'research', 'principal_investigator', 'Principal investigator', 'primary',   'user-tie',       10, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'co_investigator',        'Co-investigator',        'info',      'user-friends',   20, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'researcher',             'Researcher',             'success',   'user',           30, 1, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'student',                'Student',                'warning',   'user-graduate',  40, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'advisor',                'Advisor',                'secondary', 'chalkboard-teacher', 50, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'partner',                'Partner',                'dark',      'handshake',      60, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'technician',             'Technician',             'secondary', 'tools',          70, 0, 1, NOW()),
('research_team_role', 'Research Team Role', 'research', 'other',                  'Other',                  'secondary', 'asterisk',       80, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Team status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('research_team_status', 'Research Team Status', 'research', 'active',   'Active',   'success',   'play-circle',  10, 1, 1, NOW()),
('research_team_status', 'Research Team Status', 'research', 'inactive', 'Inactive', 'warning',   'pause-circle', 20, 0, 1, NOW()),
('research_team_status', 'Research Team Status', 'research', 'former',   'Former',   'secondary', 'sign-out-alt', 30, 0, 1, NOW());
