<?php

/**
 * SemanticSearchService - Service for Heratio
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



namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;

class SemanticSearchService
{
    public function getDashboardStats(): array
    {
        return [
            'total_terms' => DB::table('ahg_semantic_term')->count(),
            'active_terms' => DB::table('ahg_semantic_term')->where('is_active', 1)->count(),
            'search_logs' => DB::table('ahg_search_log')->count(),
            'sync_logs' => DB::table('ahg_semantic_sync_log')->count(),
        ];
    }

    public function getConfig(string $key, $default = null)
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'semantic_search')
            ->where('setting_key', $key)
            ->first();

        return $row ? $row->setting_value : $default;
    }

    public function setConfig(string $key, $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_group' => 'semantic_search', 'setting_key' => $key],
            ['setting_value' => $value, 'updated_at' => now()]
        );
    }

    public function getAllConfig(): array
    {
        $rows = DB::table('ahg_settings')
            ->where('setting_group', 'semantic_search')
            ->get(['setting_key', 'setting_value']);

        $config = [];
        foreach ($rows as $row) {
            $config[$row->setting_key] = $row->setting_value;
        }

        return $config;
    }

    // Terms
    public function getTerms(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_semantic_term');

        if (!empty($filters['search'])) {
            $query->where('term', 'LIKE', '%' . $filters['search'] . '%');
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('term')->get();
    }

    public function getTerm(int $id): ?object
    {
        return DB::table('ahg_semantic_term')->where('id', $id)->first();
    }

    public function createTerm(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('ahg_semantic_term')->insertGetId($data);
    }

    public function updateTerm(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::table('ahg_semantic_term')->where('id', $id)->update($data);
    }

    // Search Logs
    public function getSearchLogs(array $filters = []): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_search_log');

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        return $query->orderByDesc('created_at')->limit(500)->get();
    }

    // Sync Logs
    public function getSyncLogs(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_semantic_sync_log')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    // Templates
    public function getTemplates(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_search_template')->orderBy('name')->get();
    }

    public function getTemplate(int $id): ?object
    {
        return DB::table('ahg_search_template')->where('id', $id)->first();
    }

    public function createTemplate(array $data): int
    {
        $data['created_at'] = now();
        $data['updated_at'] = now();

        return DB::table('ahg_search_template')->insertGetId($data);
    }

    public function updateTemplate(int $id, array $data): void
    {
        $data['updated_at'] = now();
        DB::table('ahg_search_template')->where('id', $id)->update($data);
    }

    public function deleteTemplate(int $id): void
    {
        DB::table('ahg_search_template')->where('id', $id)->delete();
    }

    // Saved Searches
    public function getSavedSearches(?int $userId = null): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_saved_search');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('created_at')->get();
    }

    // History
    public function getSearchHistory(?int $userId = null): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_search_log');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderByDesc('created_at')->limit(100)->get();
    }
}
