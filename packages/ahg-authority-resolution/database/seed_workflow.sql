-- ==========================================================================
-- AHG Authority Resolution Engine - workflow definition seed
-- Package: ahg/authority-resolution
--
-- Seeds a minimal "Authority Resolution Review" workflow into the
-- ahg-workflow plugin's tables so the Assign / Workflow feature has a
-- workflow definition to route ahg_mention tasks through.
--
--   ahg_workflow       : one global, applies_to='ahg_mention' row
--   ahg_workflow_step  : one "Review" step (review / approve_reject)
--
-- Idempotent: keyed on the workflow name. Re-running is a no-op once the
-- workflow + step exist. Auto-applied by AhgAuthorityResolutionServiceProvider
-- boot() if missing; also safe to load by hand on a fresh install.
-- ==========================================================================

-- ahg_workflow: insert only if a workflow with this name does not exist.
INSERT INTO `ahg_workflow`
    (`name`, `description`, `scope_type`, `scope_id`, `trigger_event`,
     `applies_to`, `is_active`, `is_default`, `require_all_steps`,
     `allow_parallel`, `notification_enabled`, `created_at`, `updated_at`)
SELECT
    'Authority Resolution Review',
    'Routes promoted NER mentions assigned by an archivist through a single review step. Created by the AHG Authority Resolution Engine (Assign / Workflow feature).',
    'global', NULL, 'submit',
    'ahg_mention', 1, 0, 1,
    0, 1, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `ahg_workflow` WHERE `name` = 'Authority Resolution Review'
);

-- ahg_workflow_step: one "Review" step for that workflow, only if absent.
INSERT INTO `ahg_workflow_step`
    (`workflow_id`, `name`, `description`, `step_order`, `step_type`,
     `action_required`, `pool_enabled`, `notification_template`,
     `instructions`, `is_optional`, `is_active`, `created_at`, `updated_at`)
SELECT
    w.`id`,
    'Review',
    'Review the assigned mention and resolve it to an authority record (link / create / reject).',
    1, 'review',
    'approve_reject', 1, 'default',
    'Open the mention in the Authority Resolution review screen, weigh the ranked candidates against the evidence packet, and record a link / create-new / reject decision.',
    0, 1, NOW(), NOW()
FROM `ahg_workflow` w
WHERE w.`name` = 'Authority Resolution Review'
  AND NOT EXISTS (
      SELECT 1 FROM `ahg_workflow_step` s
      WHERE s.`workflow_id` = w.`id` AND s.`name` = 'Review'
  );
