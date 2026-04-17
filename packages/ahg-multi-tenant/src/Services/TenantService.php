<?php

/**
 * TenantService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgMultiTenant\Services;

use Illuminate\Support\Facades\DB;

class TenantService
{
    public function getTenants(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_tenant')->orderBy('name')->get();
    }

    public function getTenant(int $id): ?object
    {
        return DB::table('ahg_tenant')->where('id', $id)->first();
    }

    /**
     * Resolve the current tenant from the session (or return the default if
     * the user hasn't switched). Heratio-specific — no PSIS equivalent.
     *
     * Returns null if the `ahg_tenant` table hasn't been provisioned yet
     * (deferred to X.7 DB table verification).
     */
    public function getCurrentTenant(): ?object
    {
        try {
            $currentId = (int) session('current_tenant_id', 0);
            if ($currentId > 0) {
                $tenant = $this->getTenant($currentId);
                if ($tenant) {
                    return $tenant;
                }
            }
            return DB::table('ahg_tenant')
                ->where('is_default', 1)
                ->orderBy('id')
                ->first()
                ?? DB::table('ahg_tenant')->orderBy('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function createTenant(array $data): int
    {
        if (empty($data['code'])) {
            $data['code'] = \Illuminate\Support\Str::slug($data['name']);
        }

        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('ahg_tenant')->insertGetId($data);
    }

    public function updateTenant(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::table('ahg_tenant')->where('id', $id)->update($data);
    }

    public function deleteTenant(int $id): void
    {
        DB::table('ahg_tenant_user')->where('tenant_id', $id)->delete();
        DB::table('ahg_tenant')->where('id', $id)->delete();
    }

    public function getSuperUsers(): \Illuminate\Support\Collection
    {
        return DB::table('user as u')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('u.id', '=', 'ai.id')
                    ->where('ai.culture', '=', app()->getLocale());
            })
            ->leftJoin('ahg_tenant_user as tu', 'u.id', '=', 'tu.user_id')
            ->select('u.id', 'u.username', 'u.email', 'ai.authorized_form_of_name as name', 'tu.is_super_user')
            ->orderBy('ai.authorized_form_of_name')
            ->get();
    }

    public function getTenantUsers(int $tenantId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_tenant_user as tu')
            ->leftJoin('user as u', 'tu.user_id', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('u.id', '=', 'ai.id')
                    ->where('ai.culture', '=', app()->getLocale());
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
        DB::table('ahg_tenant_branding')->updateOrInsert(
            ['tenant_id' => $tenantId],
            array_merge($data, ['updated_at' => now()])
        );
    }
}
