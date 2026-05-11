<?php

/**
 * ResolveTenantMiddleware - sets the active tenant for the request.
 *
 * Resolution order:
 *   1. Request host matches ahg_tenant.domain or ahg_tenant.subdomain
 *   2. session('current_tenant_id') (set by the navbar switcher)
 *   3. Authenticated user's is_primary tenant assignment
 *   4. ahg_tenant.is_default
 *   5. First active tenant
 *
 * The resolved tenant is bound in the container as 'tenant.current'
 * and via app()->instance('tenant.current', $tenant). Views and services
 * read it through TenantService::getCurrentTenant() (which also covers
 * non-HTTP contexts like artisan).
 *
 * When tenant_enabled is false in ahg_settings, the middleware short-circuits
 * to a no-op so existing single-tenant installs keep behaving identically.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Http\Middleware;

use AhgCore\Services\AhgSettingsService;
use AhgMultiTenant\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ResolveTenantMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->multiTenancyEnabled()) {
            return $next($request);
        }

        $service = new TenantService();

        $tenant = $service->getTenantByDomain($request->getHost());

        if (!$tenant) {
            $tenant = $service->getCurrentTenant();
        }

        if ($tenant) {
            session(['current_tenant_id' => (int) $tenant->id]);
            app()->instance('tenant.current', $tenant);
            view()->share('currentTenant', $tenant);
        }

        return $next($request);
    }

    private function multiTenancyEnabled(): bool
    {
        try {
            if (!Schema::hasTable('ahg_tenant') || !Schema::hasTable('ahg_settings')) {
                return false;
            }

            $value = AhgSettingsService::get('tenant_enabled', 'false');

            return $value === 'true' || $value === '1' || $value === 1 || $value === true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
