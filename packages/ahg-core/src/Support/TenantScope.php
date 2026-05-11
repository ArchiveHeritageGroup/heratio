<?php

/**
 * TenantScope - apply the active tenant's repository filter to a query.
 *
 * Bound at runtime via app('tenant.current'), which ResolveTenantMiddleware
 * (ahg-multi-tenant) sets on every web request. Reading the container key
 * does not create a compile-time dependency on ahg-multi-tenant - so ahg-core
 * stays a leaf package.
 *
 * No-op when:
 *   - tenant.current is not bound (artisan / API / multi-tenant disabled);
 *   - ahg_settings.tenant_enforce_filter is false;
 *   - the active user is a Heratio admin (cross-tenant support visibility);
 *   - the tenant has no repository_id binding (open scope).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgCore\Support;

use AhgCore\Services\AhgSettingsService;
use Illuminate\Support\Facades\DB;

class TenantScope
{
    /**
     * Apply the active tenant's repository_id filter to an Eloquent /
     * query-builder $query. Reuses getActiveRepoId() so DB-side and
     * ES-side callers share the same admin / enforce / setting gate.
     */
    public static function apply($query, string $repoIdColumn = 'repository_id'): void
    {
        $repoId = self::getActiveRepoId();
        if ($repoId === null) {
            return;
        }
        try {
            $query->where($repoIdColumn, $repoId);
        } catch (\Throwable $e) {
            // Never block a query on tenant resolution failure.
        }
    }

    /**
     * The active tenant's repository_id (or null when scoping should NOT
     * be applied). Encapsulates the four short-circuit conditions:
     * tenant unresolved, feature disabled, admin user, no repo binding.
     */
    public static function getActiveRepoId(): ?int
    {
        try {
            if (!app()->bound('tenant.current')) {
                return null;
            }

            $enforce = AhgSettingsService::get('tenant_enforce_filter', 'false');
            if (!($enforce === 'true' || $enforce === '1' || $enforce === 1 || $enforce === true)) {
                return null;
            }

            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                $isAdmin = (int) (DB::table('user')->where('id', $userId)->value('is_admin') ?? 0) === 1;
                if ($isAdmin) {
                    return null;
                }
            }

            $tenant = app('tenant.current');
            $repoId = (int) ($tenant->repository_id ?? 0);

            return $repoId > 0 ? $repoId : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
