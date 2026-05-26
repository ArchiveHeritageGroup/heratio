<?php

/*
|--------------------------------------------------------------------------
| AHG / Multi-tenancy / cross-cutting service config
|--------------------------------------------------------------------------
|
| Cross-package knobs that several AHG packages read (multi-tenant
| resolver, audit logger, observability sidecars). Kept in one file so
| operators only have to learn one set of env vars.
|
| Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
| License: AGPL-3.0-or-later
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | tenant_id - explicit override consumed by services that need to pin a
    | tenant regardless of request / auth context (background jobs, cron,
    | install scripts). AuditLogger reads this; TenantContext honours it as
    | the lowest-precedence fallback after request + auth resolution.
    |
    | Set via AHG_TENANT_ID in .env. Empty / null means "no static pin".
    |--------------------------------------------------------------------------
    */
    'tenant_id' => env('AHG_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | tenant_resolution_strategy - which host-derived signal TenantContext
    | should consult when no explicit override or auth tenant_id is present.
    |
    |   'domain'    - full host matches ahg_tenant.domain
    |   'subdomain' - first label of host matches ahg_tenant.subdomain
    |   'header'    - X-Tenant-Id request header (API contexts only, must
    |                 carry a matching tenant_api_key)
    |   'path'      - first path segment matches ahg_tenant.code
    |   'none'      - skip host-derived resolution entirely
    |
    | Default 'domain' preserves the behaviour of ResolveTenantMiddleware
    | (which already does domain + subdomain matching).
    |--------------------------------------------------------------------------
    */
    'tenant_resolution_strategy' => env('AHG_TENANT_RESOLUTION_STRATEGY', 'domain'),

    /*
    |--------------------------------------------------------------------------
    | tenant_api_key - shared secret used by request-header resolution. When
    | strategy='header' the request must carry both X-Tenant-Id and a
    | matching X-Tenant-Key (or Authorization: Bearer) for the resolver to
    | trust the header. Empty disables header-based resolution.
    |--------------------------------------------------------------------------
    */
    'tenant_api_key' => env('AHG_TENANT_API_KEY', ''),

];
