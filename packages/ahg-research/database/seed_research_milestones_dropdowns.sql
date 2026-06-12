-- heratio#1222 - Research OS: Research Milestones & Deliverables tracker - dropdown seed.
--
-- INSERT IGNORE keyed on (taxonomy, code) is idempotent; a site that hand-edits
-- a value keeps its edits on re-run. Two taxonomies, both surfaced in the
-- Dropdown Manager and both VARCHAR-backed (never ENUM, never a hardcoded
-- <option> list in a view):
--
--   milestone_type   - the kind of plan item. A milestone is a planned point in
--                      the work; a deliverable is a tangible output the plan
--                      commits to; decision_point, review and dissemination are
--                      common project-plan event types. An administrator can
--                      extend the list without a code change.
--   milestone_status - the current state of the milestone or deliverable.
--
-- International and jurisdiction-neutral: no value here assumes any one country,
-- institution or funding regime.

-- ---------------------------------------------------------------------------
-- Milestone type
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('milestone_type', 'Milestone Type', 'research', 'milestone',      'Milestone',      'primary',   'flag',          10, 1, 1, NOW()),
('milestone_type', 'Milestone Type', 'research', 'deliverable',    'Deliverable',    'info',      'box',           20, 0, 1, NOW()),
('milestone_type', 'Milestone Type', 'research', 'decision_point', 'Decision point', 'warning',   'code-branch',   30, 0, 1, NOW()),
('milestone_type', 'Milestone Type', 'research', 'review',         'Review',         'secondary', 'clipboard-check', 40, 0, 1, NOW()),
('milestone_type', 'Milestone Type', 'research', 'dissemination',  'Dissemination',  'success',   'bullhorn',      50, 0, 1, NOW()),
('milestone_type', 'Milestone Type', 'research', 'other',          'Other',          'secondary', 'asterisk',      60, 0, 1, NOW());

-- ---------------------------------------------------------------------------
-- Milestone status
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ahg_dropdown`
  (`taxonomy`, `taxonomy_label`, `taxonomy_section`, `code`, `label`, `color`, `icon`, `sort_order`, `is_default`, `is_active`, `created_at`)
VALUES
('milestone_status', 'Milestone Status', 'research', 'planned',     'Planned',     'secondary', 'calendar',      10, 1, 1, NOW()),
('milestone_status', 'Milestone Status', 'research', 'in_progress', 'In progress', 'primary',   'spinner',       20, 0, 1, NOW()),
('milestone_status', 'Milestone Status', 'research', 'completed',   'Completed',   'success',   'check-circle',  30, 0, 1, NOW()),
('milestone_status', 'Milestone Status', 'research', 'delayed',     'Delayed',     'warning',   'exclamation-triangle', 40, 0, 1, NOW()),
('milestone_status', 'Milestone Status', 'research', 'cancelled',   'Cancelled',   'dark',      'ban',           50, 0, 1, NOW());
