<?php

/**
 * HeritageAdminService - regions, accounting standards and compliance rules
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgHeritageManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Administration of the multi-standard accounting set-up. Each region
 * (heritage_regional_config) ships one accounting standard plus its
 * compliance rules as an SQL pack in database/regions/<region_code>.sql;
 * one region is the institution's active one (heritage_institution_config,
 * repository_id NULL = global).
 */
class HeritageAdminService
{
    // ------------------------------------------------------------------
    // Regions
    // ------------------------------------------------------------------

    public function regions()
    {
        if (! Schema::hasTable('heritage_regional_config')) {
            return collect();
        }

        return DB::table('heritage_regional_config')->orderBy('region_name')->get()
            ->map(function ($row) {
                $row->countries = json_decode((string) $row->countries, true) ?: [];

                return $row;
            });
    }

    public function region(string $code): ?object
    {
        if (! Schema::hasTable('heritage_regional_config')) {
            return null;
        }
        $row = DB::table('heritage_regional_config')->where('region_code', $code)->first();
        if ($row) {
            $row->countries = json_decode((string) $row->countries, true) ?: [];
        }

        return $row;
    }

    public function activeConfig(): ?object
    {
        if (! Schema::hasTable('heritage_institution_config')) {
            return null;
        }

        return DB::table('heritage_institution_config')->whereNull('repository_id')->first();
    }

    /** region_code => number of standards. */
    public function standardsByRegion(): array
    {
        return DB::table('heritage_accounting_standard')->whereNotNull('region_code')
            ->groupBy('region_code')->get(['region_code', DB::raw('COUNT(*) as n')])
            ->pluck('n', 'region_code')->map(fn ($v) => (int) $v)->all();
    }

    /** region_code => number of compliance rules. */
    public function rulesByRegion(): array
    {
        return DB::table('heritage_compliance_rule as r')
            ->join('heritage_accounting_standard as s', 'r.standard_id', '=', 's.id')
            ->whereNotNull('s.region_code')
            ->groupBy('s.region_code')
            ->get(['s.region_code', DB::raw('COUNT(*) as n')])
            ->pluck('n', 'region_code')->map(fn ($v) => (int) $v)->all();
    }

    public function regionStandard(string $code): ?object
    {
        return DB::table('heritage_accounting_standard')->where('region_code', $code)->first();
    }

    public function regionPackPath(string $code): string
    {
        return __DIR__ . '/../../database/regions/' . basename($code) . '.sql';
    }

    /** Run the region's SQL pack: standard + compliance rules. */
    public function installRegion(string $code): array
    {
        $region = $this->region($code);
        if (! $region) {
            return ['success' => false, 'error' => __('Unknown region: :code', ['code' => $code])];
        }
        if ($region->is_installed) {
            return ['success' => true, 'already_installed' => true, 'message' => __('Region :code is already installed', ['code' => $code])];
        }
        $file = $this->regionPackPath($code);
        if (! is_file($file)) {
            return ['success' => false, 'error' => __('SQL file not found for region: :code', ['code' => $code])];
        }

        try {
            DB::unprepared((string) file_get_contents($file));
            DB::table('heritage_regional_config')->where('region_code', $code)
                ->update(['is_installed' => 1, 'installed_at' => now()]);

            $standard = $this->regionStandard($code);
            $rules = $standard ? DB::table('heritage_compliance_rule')->where('standard_id', $standard->id)->count() : 0;

            return [
                'success' => true,
                'standard_code' => $standard->code ?? null,
                'compliance_rules_installed' => $rules,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /** Remove the region's standard and rules (refused while assets use it). */
    public function uninstallRegion(string $code): array
    {
        $region = $this->region($code);
        if (! $region || ! $region->is_installed) {
            return ['success' => false, 'error' => __('Region :code is not installed', ['code' => $code])];
        }

        $standard = $this->regionStandard($code);
        if ($standard) {
            $inUse = DB::table('heritage_asset')->where('accounting_standard_id', $standard->id)->count();
            if ($inUse > 0) {
                return ['success' => false, 'error' => __('Cannot uninstall: :n assets are using this standard', ['n' => $inUse])];
            }
        }

        DB::transaction(function () use ($code, $standard) {
            if ($standard) {
                DB::table('heritage_compliance_rule')->where('standard_id', $standard->id)->delete();
                DB::table('heritage_accounting_standard')->where('id', $standard->id)->delete();
            }
            DB::table('heritage_regional_config')->where('region_code', $code)
                ->update(['is_installed' => 0, 'installed_at' => null]);
        });

        return ['success' => true, 'message' => __('Region :code uninstalled successfully', ['code' => $code])];
    }

    /** Make an installed region the institution-wide (global) active region. */
    public function setActiveRegion(string $code): array
    {
        $region = $this->region($code);
        if (! $region || ! $region->is_installed) {
            return ['success' => false, 'error' => __('Region :code is not installed', ['code' => $code])];
        }
        $standard = $this->regionStandard($code);

        $data = [
            'region_code' => $code,
            'accounting_standard_id' => $standard->id ?? null,
            'currency' => $region->default_currency,
            'financial_year_start' => $region->financial_year_start,
            'updated_at' => now(),
        ];

        $existing = $this->activeConfig();
        if ($existing) {
            DB::table('heritage_institution_config')->where('id', $existing->id)->update($data);
        } else {
            DB::table('heritage_institution_config')->insert($data + ['repository_id' => null, 'created_at' => now()]);
        }

        return ['success' => true, 'standard_code' => $standard->code ?? null];
    }

    // ------------------------------------------------------------------
    // Standards
    // ------------------------------------------------------------------

    public function standards(bool $activeOnly = false)
    {
        return DB::table('heritage_accounting_standard')
            ->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    public function standard(int $id): ?object
    {
        return DB::table('heritage_accounting_standard')->where('id', $id)->first();
    }

    /** Asset count per standard (only standards in use). */
    public function assetsByStandard()
    {
        return DB::table('heritage_asset as ha')
            ->join('heritage_accounting_standard as hs', 'ha.accounting_standard_id', '=', 'hs.id')
            ->groupBy('hs.id', 'hs.code', 'hs.name')
            ->orderBy('hs.sort_order')
            ->get(['hs.code', 'hs.name', DB::raw('COUNT(*) as count')]);
    }

    public function saveStandard(array $v, ?int $id = null): void
    {
        $data = [
            'code' => strtoupper(trim($v['code'])),
            'name' => $v['name'],
            'country' => $v['country'],
            'description' => $v['description'] ?? null,
            'capitalisation_required' => ! empty($v['capitalisation_required']) ? 1 : 0,
            'valuation_methods' => json_encode(array_values($v['valuation_methods'] ?? [])),
            'disclosure_requirements' => json_encode(array_values(array_filter(array_map('trim',
                preg_split('/\r\n|\r|\n/', (string) ($v['disclosure_requirements'] ?? '')))))),
            'is_active' => ! empty($v['is_active']) ? 1 : 0,
            'sort_order' => (int) ($v['sort_order'] ?? 99),
        ];

        if ($id) {
            DB::table('heritage_accounting_standard')->where('id', $id)->update($data);
        } else {
            DB::table('heritage_accounting_standard')->insert($data + ['created_at' => now()]);
        }
    }

    public function toggleStandard(int $id): void
    {
        DB::table('heritage_accounting_standard')->where('id', $id)
            ->update(['is_active' => DB::raw('1 - COALESCE(is_active, 0)')]);
    }

    /** Refused (returns false) while any heritage asset uses the standard. */
    public function deleteStandard(int $id): bool
    {
        if (DB::table('heritage_asset')->where('accounting_standard_id', $id)->exists()) {
            return false;
        }
        DB::transaction(function () use ($id) {
            DB::table('heritage_compliance_rule')->where('standard_id', $id)->delete();
            DB::table('heritage_accounting_standard')->where('id', $id)->delete();
        });

        return true;
    }

    // ------------------------------------------------------------------
    // Compliance rules
    // ------------------------------------------------------------------

    public function rules(?int $standardId = null)
    {
        return DB::table('heritage_compliance_rule as r')
            ->join('heritage_accounting_standard as s', 'r.standard_id', '=', 's.id')
            ->when($standardId, fn ($q) => $q->where('r.standard_id', $standardId))
            ->orderBy('s.sort_order')->orderBy('r.category')->orderBy('r.sort_order')
            ->get(['r.*', 's.code as standard_code', 's.name as standard_name']);
    }

    public function rulesForStandard(int $standardId)
    {
        return DB::table('heritage_compliance_rule')->where('standard_id', $standardId)
            ->orderBy('category')->orderBy('sort_order')->get();
    }

    public function rule(int $id): ?object
    {
        return DB::table('heritage_compliance_rule')->where('id', $id)->first();
    }

    public function saveRule(array $v, ?int $id = null): void
    {
        $data = [
            'standard_id' => (int) $v['standard_id'],
            'category' => $v['category'],
            'code' => strtoupper(trim($v['code'])),
            'name' => $v['name'],
            'description' => $v['description'] ?? null,
            'check_type' => $v['check_type'],
            'field_name' => $v['field_name'] ?? null,
            'condition' => $v['condition'] ?? null,
            'error_message' => $v['error_message'],
            'reference' => $v['reference'] ?? null,
            'severity' => $v['severity'] ?? 'error',
            'is_active' => ! empty($v['is_active']) ? 1 : 0,
            'sort_order' => (int) ($v['sort_order'] ?? 0),
        ];

        if ($id) {
            DB::table('heritage_compliance_rule')->where('id', $id)->update($data);
        } else {
            DB::table('heritage_compliance_rule')->insert($data + ['created_at' => now()]);
        }
    }

    public function toggleRule(int $id): ?object
    {
        $rule = $this->rule($id);
        if ($rule) {
            DB::table('heritage_compliance_rule')->where('id', $id)->update(['is_active' => $rule->is_active ? 0 : 1]);
        }

        return $rule;
    }

    public function deleteRule(int $id): ?object
    {
        $rule = $this->rule($id);
        if ($rule) {
            DB::table('heritage_compliance_rule')->where('id', $id)->delete();
        }

        return $rule;
    }
}
