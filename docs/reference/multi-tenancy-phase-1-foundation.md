# Multi-tenancy Phase 1 - Foundation

Issue #651 (multi-tenancy hard isolation) is large, so it ships in phases. Phase 1 lands the framework that the later, more invasive phases hang off:

- a `TenantContext` resolver that tells any package what the active tenant is,
- a `TenantFileService` path helper that turns a tenant id into a storage prefix,
- a migration command (`multi-tenant:assign-rows`) that backfills `tenant_id` on existing tables,
- a `Tenant` Eloquent model over the existing `ahg_tenant` table,
- auto-seeding of a `default` tenant row so single-tenant installs keep working.

Phase 1 does NOT add per-table `tenant_id` columns to `digital_object`, `audit_log` (already added in #676 Phase 6), the queue / cache subsystems, or change any user-facing flow. Those phases are tracked separately under #651 follow-ups because they touch locked packages and need their own review window.

## Resolution order

`AhgMultiTenant\Services\TenantContext::current()` resolves the active tenant in this precedence:

1. `scope($id, $fn)` stack override (highest - jobs / cron use this).
2. `config('ahg.tenant_id')` or env `AHG_TENANT_ID` (static pin, e.g. for an install hard-bound to one tenant).
3. `session('current_tenant_id')` (set by the navbar switcher).
4. `Auth::user()->tenant_id` when authenticated.
5. `X-Tenant-Id` request header, but only when `tenant_resolution_strategy=header` AND the request carries a matching `X-Tenant-Key` or `Authorization: Bearer` whose value equals `config('ahg.tenant_api_key')`.
6. Host-derived match (`ahg_tenant.domain` or `ahg_tenant.subdomain`), per the configured strategy.
7. First URL path segment matches `ahg_tenant.code` (when strategy is `path`).
8. `ahg_tenant.is_default = 1` row.
9. First active tenant by id (last-ditch single-tenant fallback).
10. `null` (no tenant - single-tenant deployment).

The strategy knob lives in `config/ahg.php` (`tenant_resolution_strategy`), defaulting to `domain` to match the long-standing behaviour of `ResolveTenantMiddleware`.

## TenantContext::scope() - temporary override

Background jobs need to act under a tenant other than whoever happened to enqueue them. `scope($tenantId, $fn)` pushes a frame, runs the closure, and pops the frame again on return or on throw. Nested scopes compose - the top frame wins, popping restores the parent. This is the only correct way for a queue worker or cron job to set the tenant: never poke session or auth directly.

## TenantFileService

A thin path helper around `config/heratio.php` (`storage_path`, `uploads_path`, `backups_path`). Returns `{base}/tenant-{id}/` when a tenant is current, falls back to `{base}` when no tenant context exists. It is pure path arithmetic - callers still talk to Laravel's `Storage` facade for the actual reads and writes.

Pass `0` explicitly to force the unscoped path; pass a positive int to pin a tenant; pass `null` (or omit) to derive from `TenantContext::current()`.

## multi-tenant:assign-rows

A one-shot migration tool. Given a table name and a tenant (id or code), it updates the `tenant_id` column on matching rows, wraps the whole update in a transaction, and writes a single `ahg_audit_log` row using the `AuditLogger` from #676 so the back-fill leaves an audit trail.

Safety rails:

- The table name is validated against `[a-zA-Z0-9_]+` so injection through the argument is impossible.
- A table missing the `tenant_id` column refuses to run unless the operator passes `--force` (which then audits the skip rather than the UPDATE).
- `--dry-run` shows the row count without writing.
- The tenant lookup tries integer id first, then code - so scripted invocations should prefer code (stable across installs).

## What this unblocks

Subsequent phases of #651 can now:

- write `tenant_id` columns to digital_object / queue / cache and look up the resolved id with a single facade call,
- gate every API request behind a `TenantBoundary` middleware that compares the target row's tenant_id against `TenantContext::currentId()`,
- emit tenant-scoped storage paths from any file-handling service via `TenantFileService`,
- back-fill historical data with `multi-tenant:assign-rows` once the schema migration lands.

## Files added

- `packages/ahg-multi-tenant/src/Services/TenantContext.php`
- `packages/ahg-multi-tenant/src/Services/TenantFileService.php`
- `packages/ahg-multi-tenant/src/Facades/TenantContext.php`
- `packages/ahg-multi-tenant/src/Models/Tenant.php`
- `packages/ahg-multi-tenant/src/Console/Commands/AssignRowsCommand.php`
- `config/ahg.php` (new - holds `tenant_id`, `tenant_resolution_strategy`, `tenant_api_key`)
- `packages/ahg-multi-tenant/tests/Unit/TenantContextTest.php`
- `packages/ahg-multi-tenant/tests/Unit/TenantFileServiceTest.php`
