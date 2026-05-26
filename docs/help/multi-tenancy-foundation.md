> Heratio Help Center article. Category: Admin and Settings.

# Multi-Tenancy Foundation (Phase 1)

Phase 1 of issue #651 lays the groundwork that the rest of the multi-tenant hard-isolation work hangs off. There is no new UI in this phase. If you are a content editor or researcher, nothing visible changes. This article is for administrators and developers who need to understand how the resolver and migration tooling work before Phase 2 lands.

## What "tenant context" means

A tenant context is just the answer to one question: which tenant is the current user (or background job) acting on behalf of right now? Everything downstream - file paths, audit rows, future API authorisation checks - keys off that single answer.

Heratio resolves the tenant in this order, highest priority first:

1. An explicit scope set in code by a background job or migration script.
2. A static pin in `.env` (`AHG_TENANT_ID`).
3. The tenant the user picked in the navbar switcher this session.
4. The tenant attached to the authenticated user's profile.
5. An `X-Tenant-Id` HTTP header with a matching key (API integrations only).
6. A match against the request host (domain or subdomain).
7. A match against the first URL path segment (when path-based tenancy is enabled).
8. The `default` tenant row.
9. The first active tenant, or no tenant at all on installs that have not configured any.

For most installations that means "the user's session" wins for browser traffic, and "the static pin" wins for cron and queue workers. The order is deliberately stable so a tenant switch always takes effect immediately on the next request without requiring a logout.

## The default tenant

A brand-new install auto-seeds a single tenant row with code `default`, active, marked as the default. Existing single-tenant deployments keep working without operator action - every existing user implicitly belongs to this tenant. Operators can rename it from the Tenants admin page (`/admin/tenants`) at any time.

## Storage paths

When tenants are added later, file uploads land under `{storage_path}/tenant-{id}/` instead of the flat `{storage_path}/` layout. Phase 1 ships the `TenantFileService` path helper that file-handling code will call from Phase 2 onwards; no existing files move yet.

## The assign-rows migration command

When an existing install splits into multiple tenants, the historical rows have no tenant id. The new artisan command `multi-tenant:assign-rows` bulk-assigns a tenant id to all matching rows on a given table.

Typical usage:

```
php artisan multi-tenant:assign-rows information_object default
php artisan multi-tenant:assign-rows digital_object default --where "created_at < '2026-01-01'"
php artisan multi-tenant:assign-rows audit_log default --dry-run
```

The command refuses to run against a table that has no `tenant_id` column unless you pass `--force` (which is almost always a typo). Every successful run writes one row to the audit trail describing the table, tenant, filter, and rows-affected count, so the back-fill always leaves a trace.

## Configuration

Three new keys live in `config/ahg.php`, all driven by environment variables:

- `tenant_id` (`AHG_TENANT_ID`) - explicit pin. Leave empty for normal multi-tenant operation.
- `tenant_resolution_strategy` (`AHG_TENANT_RESOLUTION_STRATEGY`) - one of `domain`, `subdomain`, `header`, `path`, `none`. Defaults to `domain` so behaviour matches what installs are already running.
- `tenant_api_key` (`AHG_TENANT_API_KEY`) - shared secret consumed by header-based resolution. Empty disables header-based resolution entirely.

## What is NOT yet in Phase 1

Hard data isolation across every API surface, queue, and cache landed only in this foundation; the actual enforcement middleware on every API call, plus the per-table `tenant_id` schema migrations on `digital_object`, the queue tables, and the cache, are tracked under Phase 2+ follow-ups. Until those land, tenant scoping is advisory for new code paths only. Existing single-tenant deployments are unaffected.
