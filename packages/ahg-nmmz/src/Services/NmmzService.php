<?php

/**
 * NmmzService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgNmmz\Services;

use Illuminate\Support\Facades\DB;

class NmmzService
{
    public function getDashboardStats(): array
    {
        return [
            'monuments' => DB::table('nmmz_monument')->count(),
            'antiquities' => DB::table('nmmz_antiquity')->count(),
            'permits' => DB::table('nmmz_export_permit')->count(),
            'permits_pending' => DB::table('nmmz_export_permit')->where('status', 'pending')->count(),
            'sites' => DB::table('nmmz_archaeological_site')->count(),
            'hias' => DB::table('nmmz_hia')->count(),
        ];
    }

    public function getComplianceStatus(): array
    {
        $issues = [];
        $warnings = [];

        $pendingPermits = DB::table('nmmz_export_permit')->where('status', 'pending')->count();
        if ($pendingPermits > 0) {
            $warnings[] = "{$pendingPermits} export permit(s) awaiting review";
        }

        return ['issues' => $issues, 'warnings' => $warnings];
    }

    public function getAllConfig(): array
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'nmmz')
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
            ['setting_group' => 'nmmz', 'setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now()]
        );
    }

    // Monuments
    public function getMonuments(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('nmmz_monument')
            ->leftJoin('nmmz_monument_category', 'nmmz_monument.category_id', '=', 'nmmz_monument_category.id')
            ->select('nmmz_monument.*', 'nmmz_monument_category.name as category_name');

        if (!empty($filters['category_id'])) {
            $query->where('nmmz_monument.category_id', $filters['category_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('nmmz_monument.legal_status', $filters['status']);
        }
        if (!empty($filters['province'])) {
            $query->where('nmmz_monument.province', $filters['province']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('nmmz_monument.name', 'LIKE', '%' . $filters['search'] . '%')
                    ->orWhere('nmmz_monument.description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('nmmz_monument.name')->get();
    }

    public function getMonument(int $id): ?object
    {
        return DB::table('nmmz_monument')
            ->leftJoin('nmmz_monument_category', 'nmmz_monument.category_id', '=', 'nmmz_monument_category.id')
            ->select('nmmz_monument.*', 'nmmz_monument_category.name as category_name')
            ->where('nmmz_monument.id', $id)
            ->first();
    }

    public function createMonument(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('nmmz_monument')->insertGetId($data);
    }

    public function getMonumentInspections(int $monumentId): \Illuminate\Support\Collection
    {
        return DB::table('nmmz_monument_inspection')
            ->where('monument_id', $monumentId)
            ->orderByDesc('inspection_date')
            ->get();
    }

    public function getCategories(): \Illuminate\Support\Collection
    {
        return DB::table('nmmz_monument_category')->orderBy('name')->get();
    }

    // Antiquities
    public function getAntiquities(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('nmmz_antiquity');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['object_type'])) {
            $query->where('object_type', $filters['object_type']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                    ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        return $query->orderBy('name')->get();
    }

    public function getAntiquity(int $id): ?object
    {
        return DB::table('nmmz_antiquity')->where('id', $id)->first();
    }

    public function createAntiquity(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data['status'] = 'registered';

        return DB::table('nmmz_antiquity')->insertGetId($data);
    }

    // Export Permits
    public function getPermits(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('nmmz_export_permit');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function getPermit(int $id): ?object
    {
        return DB::table('nmmz_export_permit')
            ->leftJoin('nmmz_antiquity', 'nmmz_export_permit.antiquity_id', '=', 'nmmz_antiquity.id')
            ->select('nmmz_export_permit.*', 'nmmz_antiquity.name as antiquity_name')
            ->where('nmmz_export_permit.id', $id)
            ->first();
    }

    public function createPermit(array $data): int
    {
        $data['status'] = 'pending';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('nmmz_export_permit')->insertGetId($data);
    }

    public function approvePermit(int $id, int $userId, ?string $conditions): void
    {
        $permitNumber = 'EP-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);

        DB::table('nmmz_export_permit')->where('id', $id)->update([
            'status' => 'approved',
            'permit_number' => $permitNumber,
            'conditions' => $conditions,
            'reviewed_by' => $userId,
            'review_date' => now()->toDateString(),
            'updated_at' => now(),
        ]);
    }

    public function rejectPermit(int $id, int $userId, ?string $reason): void
    {
        DB::table('nmmz_export_permit')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'reviewed_by' => $userId,
            'review_date' => now()->toDateString(),
            'updated_at' => now(),
        ]);
    }

    // Archaeological Sites
    public function getSites(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('nmmz_archaeological_site');

        if (!empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }
        if (!empty($filters['protection_status'])) {
            $query->where('protection_status', $filters['protection_status']);
        }

        return $query->orderBy('name')->get();
    }

    public function getSite(int $id): ?object
    {
        return DB::table('nmmz_archaeological_site')->where('id', $id)->first();
    }

    public function createSite(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('nmmz_archaeological_site')->insertGetId($data);
    }

    // Heritage Impact Assessments
    public function getHIAs(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('nmmz_hia');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['province'])) {
            $query->where('province', $filters['province']);
        }

        return $query->orderByDesc('created_at')->get();
    }

    public function createHIA(array $data): int
    {
        $data['status'] = 'submitted';
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('nmmz_hia')->insertGetId($data);
    }
}
