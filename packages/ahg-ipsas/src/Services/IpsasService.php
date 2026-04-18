<?php

/**
 * IpsasService - Service for Heratio
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



namespace AhgIpsas\Services;

use Illuminate\Support\Facades\DB;

class IpsasService
{
    public function getDashboardStats(): array
    {
        $valuationBasis = DB::table('ipsas_heritage_asset')
            ->selectRaw('valuation_basis, COUNT(*) as cnt')
            ->whereNotNull('valuation_basis')
            ->groupBy('valuation_basis')
            ->pluck('cnt', 'valuation_basis')
            ->toArray();

        $categories = DB::table('ipsas_heritage_asset as a')
            ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
            ->selectRaw('c.name as name, COUNT(*) as count, COALESCE(SUM(a.current_value), 0) as value')
            ->whereNotNull('a.category_id')
            ->groupBy('c.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $recentValuations = DB::table('ipsas_valuation')
            ->whereYear('valuation_date', (int) date('Y'))
            ->count();

        return [
            'assets' => [
                'total' => DB::table('ipsas_heritage_asset')->count(),
                'active' => DB::table('ipsas_heritage_asset')->where('status', 'active')->count(),
            ],
            'values' => [
                'total' => DB::table('ipsas_heritage_asset')->sum('current_value') ?? 0,
                'insured' => DB::table('ipsas_insurance')->where('status', 'active')->sum('sum_insured') ?? 0,
            ],
            'insurance' => [
                'active' => DB::table('ipsas_insurance')->where('status', 'active')->count(),
                'expiring_soon' => DB::table('ipsas_insurance')
                    ->where('status', 'active')
                    ->whereRaw('coverage_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
                    ->count(),
            ],
            'valuation_basis' => $valuationBasis,
            'categories' => $categories,
            'recent_valuations' => $recentValuations,
        ];
    }

    public function getComplianceStatus(): array
    {
        $issues = [];
        $warnings = [];

        $unvalued = DB::table('ipsas_heritage_asset')
            ->whereNull('current_value')
            ->where('status', 'active')
            ->count();
        if ($unvalued > 0) {
            $warnings[] = "{$unvalued} active asset(s) have no current valuation";
        }

        $expiringInsurance = DB::table('ipsas_insurance')
            ->where('status', 'active')
            ->whereRaw('coverage_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)')
            ->count();
        if ($expiringInsurance > 0) {
            $warnings[] = "{$expiringInsurance} insurance policy/policies expiring within 30 days";
        }

        return ['issues' => $issues, 'warnings' => $warnings];
    }

    public function getAllConfig(): array
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'ipsas')
            ->get(['setting_key', 'setting_value']);

        $config = [];
        foreach ($rows as $row) {
            $config[$row->setting_key] = $row->setting_value;
        }

        return $config;
    }

    public function setConfig(string $key, ?string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_group' => 'ipsas', 'setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now()]
        );
    }

    public function getAssets(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ipsas_heritage_asset as a')
            ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
            ->select('a.*', 'c.name as category_name');

        if (!empty($filters['category_id'])) {
            $query->where('a.category_id', $filters['category_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('a.status', $filters['status']);
        }
        if (!empty($filters['valuation_basis'])) {
            $query->where('a.valuation_basis', $filters['valuation_basis']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('a.title', 'LIKE', '%' . $filters['search'] . '%')
                    ->orWhere('a.asset_number', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('a.asset_number')->get();
    }

    public function getAsset(int $id): ?object
    {
        return DB::table('ipsas_heritage_asset as a')
            ->leftJoin('ipsas_asset_category as c', 'a.category_id', '=', 'c.id')
            ->select('a.*', 'c.name as category_name')
            ->where('a.id', $id)
            ->first();
    }

    public function createAsset(array $data): int
    {
        $count = DB::table('ipsas_heritage_asset')->count();
        $data['asset_number'] = 'HA-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        $data['status'] = 'active';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('ipsas_heritage_asset')->insertGetId($data);
    }

    public function updateAsset(int $id, array $data, int $userId): void
    {
        $data['updated_at'] = now();
        DB::table('ipsas_heritage_asset')->where('id', $id)->update($data);
    }

    public function deleteAsset(int $id): bool
    {
        DB::table('ipsas_valuation')->where('asset_id', $id)->delete();
        DB::table('ipsas_impairment')->where('asset_id', $id)->delete();
        DB::table('ipsas_insurance')->where('asset_id', $id)->delete();
        return DB::table('ipsas_heritage_asset')->where('id', $id)->delete() > 0;
    }

    public function getCategories(): \Illuminate\Support\Collection
    {
        return DB::table('ipsas_asset_category')->orderBy('name')->get();
    }

    public function getValuations(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ipsas_valuation as v')
            ->leftJoin('ipsas_heritage_asset as a', 'v.asset_id', '=', 'a.id')
            ->select('v.*', 'a.asset_number', 'a.title as asset_title');

        if (!empty($filters['type'])) {
            $query->where('v.valuation_type', $filters['type']);
        }
        if (!empty($filters['year'])) {
            $query->whereYear('v.valuation_date', $filters['year']);
        }

        return $query->orderByDesc('v.valuation_date')->get();
    }

    public function getAssetValuations(int $assetId): \Illuminate\Support\Collection
    {
        return DB::table('ipsas_valuation')
            ->where('asset_id', $assetId)
            ->orderByDesc('valuation_date')
            ->get();
    }

    public function createValuation(array $data): int
    {
        $data['created_at'] = now();

        $id = DB::table('ipsas_valuation')->insertGetId($data);

        if (!empty($data['new_value']) && !empty($data['asset_id'])) {
            DB::table('ipsas_heritage_asset')->where('id', $data['asset_id'])->update([
                'current_value' => $data['new_value'],
                'updated_at' => now(),
            ]);
        }

        return $id;
    }

    public function getImpairments(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ipsas_impairment as i')
            ->leftJoin('ipsas_heritage_asset as a', 'i.asset_id', '=', 'a.id')
            ->select('i.*', 'a.asset_number', 'a.title as asset_title');

        if (!empty($filters['asset_id'])) {
            $query->where('i.asset_id', $filters['asset_id']);
        }
        if (!empty($filters['recognized_only'])) {
            $query->where('i.impairment_recognized', 1);
        }

        return $query->orderByDesc('i.assessment_date')->get();
    }

    public function getInsurancePolicies(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ipsas_insurance as ins')
            ->leftJoin('ipsas_heritage_asset as a', 'ins.asset_id', '=', 'a.id')
            ->select('ins.*', 'a.asset_number', 'a.title as asset_title');

        if (!empty($filters['status'])) {
            $query->where('ins.status', $filters['status']);
        }

        return $query->orderByDesc('ins.coverage_end')->get();
    }

    public function calculateFinancialYearSummary(string $year): array
    {
        $assets = DB::table('ipsas_heritage_asset')->where('status', 'active');

        return [
            'total_assets' => $assets->count(),
            'total_value' => $assets->sum('current_value'),
            'acquisitions' => DB::table('ipsas_heritage_asset')
                ->whereYear('acquisition_date', $year)->count(),
            'valuations' => DB::table('ipsas_valuation')
                ->whereYear('valuation_date', $year)->count(),
            'impairments' => DB::table('ipsas_impairment')
                ->whereYear('assessment_date', $year)
                ->where('impairment_recognized', 1)->count(),
        ];
    }
}
