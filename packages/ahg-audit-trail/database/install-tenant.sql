-- ============================================================================
-- ahg-audit-trail - tenant_id propagation (issue #676 Phase 6)
-- ============================================================================
-- Adds a `tenant_id` column + composite index on (tenant_id, action, created_at)
-- so multi-tenant Heratio deployments can produce tenant-scoped audit reports
-- without table scans.
--
-- Existing rows keep NULL `tenant_id` - they are treated as belonging to the
-- "no tenant" / single-tenant case for backward compatibility. New writes via
-- AuditLogger / ChainedAuditWriter resolve the tenant from:
--   1. explicit constructor arg / per-call override
--   2. config('ahg.tenant_id') / env('AHG_TENANT_ID')
--   3. Auth::user()->tenant_id when authenticated
--   4. NULL otherwise
--
-- Idempotent: schema-probe in AhgAuditTrailServiceProvider::boot() runs this
-- file only if the column is absent. MySQL 8 has no IF NOT EXISTS for
-- ADD COLUMN so we guard from PHP instead.
-- ============================================================================

ALTER TABLE `ahg_audit_log`
    ADD COLUMN `tenant_id` INT UNSIGNED NULL COMMENT 'tenant id for multi-tenant deployments, NULL = single-tenant / legacy' AFTER `user_id`,
    ADD KEY `idx_audit_tenant_action_created` (`tenant_id`, `action`, `created_at`);
