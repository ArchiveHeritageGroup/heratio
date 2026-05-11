<?php

/**
 * TenantService - core tenant CRUD and scoping helpers.
 *
 * Heratio is single-DB + repository-scoped: each ahg_tenant row points at
 * a `repository` via repository_id, and data isolation flows through that
 * FK on information_object / digital_object / etc.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMultiTenant\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    /** Editable columns on ahg_tenant. */
    private const FILLABLE = [
        'code', 'name', 'description', 'domain', 'subdomain',
        'repository_id', 'contact_email', 'contact_phone',
        'max_users', 'max_storage_gb', 'is_active', 'is_default',
        'status', 'trial_ends_at', 'suspended_at', 'suspended_reason',
        'settings',
    ];

    public function getTenants(): Collection
    {
        return DB::table('ahg_tenant')->orderBy('name')->get();
    }

    public function getTenant(int $id): ?object
    {
        return DB::table('ahg_tenant')->where('id', $id)->first();
    }

    public function getTenantByCode(string $code): ?object
    {
        return DB::table('ahg_tenant')->where('code', $code)->first();
    }

    public function getTenantByDomain(string $host): ?object
    {
        $host = strtolower($host);

        $tenant = DB::table('ahg_tenant')
            ->where('domain', $host)
            ->where('is_active', 1)
            ->first();

        if ($tenant) {
            return $tenant;
        }

        $subdomain = explode('.', $host)[0] ?? null;
        if (!$subdomain) {
            return null;
        }

        return DB::table('ahg_tenant')
            ->where('subdomain', $subdomain)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Resolve the active tenant for the current request.
     *
     * Resolution order:
     *   1. session('current_tenant_id') if still valid + active
     *   2. authenticated user's primary tenant assignment
     *   3. tenant flagged is_default
     *   4. first active tenant by id (last-ditch fallback)
     */
    public function getCurrentTenant(): ?object
    {
        try {
            $currentId = (int) session('current_tenant_id', 0);
            if ($currentId > 0) {
                $tenant = DB::table('ahg_tenant')
                    ->where('id', $currentId)
                    ->where('is_active', 1)
                    ->first();
                if ($tenant) {
                    return $tenant;
                }
            }

            $userId = (int) (auth()->id() ?? 0);
            if ($userId > 0) {
                $primary = DB::table('ahg_tenant_user as tu')
                    ->join('ahg_tenant as t', 't.id', '=', 'tu.tenant_id')
                    ->where('tu.user_id', $userId)
                    ->where('tu.is_primary', 1)
                    ->where('t.is_active', 1)
                    ->select('t.*')
                    ->first();
                if ($primary) {
                    return $primary;
                }
            }

            return DB::table('ahg_tenant')
                ->where('is_active', 1)
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createTenant(array $data): int
    {
        $data = $this->filterFillable($data);

        if (empty($data['code']) && !empty($data['name'])) {
            $data['code'] = Str::slug($data['name']);
        }

        $data['created_by'] = (int) (auth()->id() ?? 0) ?: null;
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return (int) DB::table('ahg_tenant')->insertGetId($data);
    }

    public function updateTenant(int $id, array $data): void
    {
        $data = $this->filterFillable($data);
        $data['updated_at'] = now();

        DB::table('ahg_tenant')->where('id', $id)->update($data);
    }

    public function deleteTenant(int $id): void
    {
        // FK cascades handle ahg_tenant_user / ahg_tenant_branding /
        // ahg_tenant_settings_override; we still wipe explicitly for the
        // (rare) case where the constraints have been dropped during
        // operator surgery.
        DB::table('ahg_tenant_settings_override')->where('tenant_id', $id)->delete();
        DB::table('ahg_tenant_branding')->where('tenant_id', $id)->delete();
        DB::table('ahg_tenant_user')->where('tenant_id', $id)->delete();
        DB::table('ahg_tenant')->where('id', $id)->delete();
    }

    public function getSuperUsers(): Collection
    {
        $culture = app()->getLocale();

        return DB::table('user as u')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('u.id', '=', 'ai.id')->where('ai.culture', $culture);
            })
            ->leftJoin('ahg_tenant_user as tu', function ($j) {
                $j->on('u.id', '=', 'tu.user_id')->where('tu.is_super_user', 1);
            })
            ->select('u.id', 'u.username', 'u.email', 'ai.authorized_form_of_name as name', 'tu.is_super_user', 'tu.tenant_id')
            ->orderBy('ai.authorized_form_of_name')
            ->get();
    }

    public function getTenantUsers(int $tenantId): Collection
    {
        $culture = app()->getLocale();

        return DB::table('ahg_tenant_user as tu')
            ->leftJoin('user as u', 'tu.user_id', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('u.id', '=', 'ai.id')->where('ai.culture', $culture);
            })
            ->where('tu.tenant_id', $tenantId)
            ->select('tu.*', 'u.username', 'u.email', 'ai.authorized_form_of_name as name')
            ->orderBy('ai.authorized_form_of_name')
            ->get();
    }

    public function getBranding(int $tenantId): ?object
    {
        return DB::table('ahg_tenant_branding')->where('tenant_id', $tenantId)->first();
    }

    public function updateBranding(int $tenantId, array $data): void
    {
        $allowed = [
            'logo_url', 'primary_color', 'secondary_color',
            'header_bg_color', 'header_text_color', 'link_color', 'button_color',
            'header_html', 'footer_html', 'custom_css',
        ];
        $data = array_intersect_key($data, array_flip($allowed));

        DB::table('ahg_tenant_branding')->updateOrInsert(
            ['tenant_id' => $tenantId],
            array_merge($data, ['updated_at' => now()])
        );
    }

    /**
     * Repository IDs in scope for a given tenant. Returns [] when the tenant
     * has no repository binding (open-scope, no filtering).
     */
    public function getTenantRepositoryIds(int $tenantId): array
    {
        $row = DB::table('ahg_tenant')
            ->where('id', $tenantId)
            ->whereNotNull('repository_id')
            ->value('repository_id');

        return $row !== null ? [(int) $row] : [];
    }

    private function filterFillable(array $data): array
    {
        return array_intersect_key($data, array_flip(self::FILLABLE));
    }
}
