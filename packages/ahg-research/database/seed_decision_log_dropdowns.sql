-- heratio#1224 - Research OS Stage 9: Decision Log decision_type taxonomy.
--
-- Seeds the 'decision_type' taxonomy into ahg_dropdown so the values render in
-- the Decision Manager / Dropdown Manager and are editable per install. These
-- match the seven canonical decision categories of the research-loop memory.
-- The column research_decision_log.decision_type is a VARCHAR holding one of
-- these codes (never a MySQL ENUM).
--
-- Uses INSERT IGNORE - safe to run multiple times. Auto-seeded on first boot
-- from the AhgResearchServiceProvider if the taxonomy is missing.

INSERT IGNORE INTO ahg_dropdown (taxonomy, taxonomy_label, taxonomy_section, code, label, color, icon, sort_order, is_active, created_at) VALUES
  ('decision_type', 'Decision Type', 'research', 'scope_change',           'Scope change',            '#0d6efd', 'crop',              10, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'exclusion',              'Exclusion',               '#dc3545', 'ban',               20, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'hypothesis_revision',    'Hypothesis revision',     '#fd7e14', 'lightbulb',         30, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'method_pivot',           'Method pivot',            '#6610f2', 'route',             40, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'question_reformulation', 'Question reformulation',  '#20c997', 'question-circle',   50, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'supervisor_instruction', 'Supervisor instruction',  '#198754', 'user-graduate',     60, 1, NOW()),
  ('decision_type', 'Decision Type', 'research', 'other',                  'Other',                   '#6c757d', 'circle-dot',        70, 1, NOW());
