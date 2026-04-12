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

    public function deleteTerm(int $id): bool
    {
        DB::table('ahg_thesaurus_synonym')->where('term_id', $id)->delete();
        return DB::table('ahg_semantic_term')->where('id', $id)->delete() > 0;
    }

    /**
     * Sync terms from AtoM's term table into `ahg_semantic_term` and log the run.
     * Heratio-specific: PSIS has no semantic-search plugin.
     *
     * @return array{success: bool, synced: int, skipped: int, duration_ms: int}
     */
    public function syncTerms(): array
    {
        $start = microtime(true);
        $synced = 0;
        $skipped = 0;

        DB::table('term as t')
            ->leftJoin('term_i18n as i', function ($j) {
                $j->on('i.id', '=', 't.id')->where('i.culture', '=', app()->getLocale());
            })
            ->select(['t.id', 't.taxonomy_id', 'i.name'])
            ->whereNotNull('i.name')
            ->orderBy('t.id')
            ->chunk(500, function ($rows) use (&$synced, &$skipped) {
                foreach ($rows as $row) {
                    $exists = DB::table('ahg_semantic_term')
                        ->where('source_term_id', $row->id)
                        ->exists();
                    if ($exists) {
                        $skipped++;
                        continue;
                    }
                    DB::table('ahg_semantic_term')->insert([
                        'source_term_id' => $row->id,
                        'taxonomy_id'    => $row->taxonomy_id,
                        'name'           => (string) $row->name,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                    $synced++;
                }
            });

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        DB::table('ahg_semantic_sync_log')->insert([
            'synced_count'  => $synced,
            'skipped_count' => $skipped,
            'duration_ms'   => $durationMs,
            'status'        => 'success',
            'created_at'    => now(),
        ]);

        return [
            'success'     => true,
            'synced'      => $synced,
            'skipped'     => $skipped,
            'duration_ms' => $durationMs,
        ];
    }

    public function clearSearchHistory(?int $userId = null): int
    {
        $query = DB::table('ahg_search_log');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }
        return $query->delete();
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

    // ========================================================================
    // Query Expansion (uses ahg_thesaurus_term + ahg_thesaurus_synonym)
    // ========================================================================

    /**
     * Expand a search query with synonyms from the thesaurus.
     * Ported from AtoM ahgSemanticSearchPlugin ThesaurusService::expandQuery()
     */
    public function expandQuery(string $query, string $language = 'en'): array
    {
        $minWeight = (float) $this->getConfig('semantic_min_weight', 0.6);
        $expansionLimit = (int) $this->getConfig('semantic_expansion_limit', 5);

        $words = $this->tokenize($query);
        $expandedTerms = [];

        foreach ($words as $word) {
            if (mb_strlen($word) < 3) {
                continue;
            }

            $synonyms = $this->getThesaurusSynonyms($word, $language, $minWeight, $expansionLimit);

            if (!empty($synonyms)) {
                $expandedTerms[$word] = array_column($synonyms, 'text');
            }
        }

        $allSynonyms = [];
        foreach ($expandedTerms as $syns) {
            $allSynonyms = array_merge($allSynonyms, $syns);
        }
        $allSynonyms = array_unique($allSynonyms);

        $expandedQuery = $query;
        if (!empty($allSynonyms)) {
            $expandedQuery .= ' ' . implode(' ', $allSynonyms);
        }

        return [
            'original_query' => $query,
            'expanded_query' => $expandedQuery,
            'expanded_terms' => $expandedTerms,
            'expansion_count' => count($allSynonyms),
        ];
    }

    protected function getThesaurusSynonyms(string $term, string $language, float $minWeight, int $limit): array
    {
        $normalized = mb_strtolower(trim($term));
        $synonyms = [];

        // Find the thesaurus term
        $termRecord = DB::table('ahg_thesaurus_term')
            ->where('term', $normalized)
            ->where('language', $language)
            ->where('is_active', true)
            ->first();

        if ($termRecord) {
            // Get direct synonyms
            $direct = DB::table('ahg_thesaurus_synonym')
                ->where('term_id', $termRecord->id)
                ->where('is_active', true)
                ->where('weight', '>=', $minWeight)
                ->orderByDesc('weight')
                ->limit($limit)
                ->get();

            foreach ($direct as $syn) {
                $synonyms[$syn->synonym_text] = [
                    'text' => $syn->synonym_text,
                    'weight' => (float) $syn->weight,
                ];
            }
        }

        // Reverse synonyms (where this term is listed as a synonym of another)
        $reverse = DB::table('ahg_thesaurus_synonym as s')
            ->join('ahg_thesaurus_term as t', 's.term_id', '=', 't.id')
            ->where('s.synonym_text', $normalized)
            ->where('s.is_bidirectional', true)
            ->where('s.is_active', true)
            ->where('t.is_active', true)
            ->where('t.language', $language)
            ->where('s.weight', '>=', $minWeight)
            ->select('t.term as text', 's.weight')
            ->limit($limit)
            ->get();

        foreach ($reverse as $rev) {
            if (!isset($synonyms[$rev->text])) {
                $synonyms[$rev->text] = [
                    'text' => $rev->text,
                    'weight' => (float) $rev->weight,
                ];
            }
        }

        uasort($synonyms, fn($a, $b) => $b['weight'] <=> $a['weight']);

        return array_slice(array_values($synonyms), 0, $limit);
    }

    protected function tokenize(string $query): array
    {
        $query = preg_replace('/[^\w\s]/u', ' ', $query);
        $query = preg_replace('/[_-]+/', ' ', $query);
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);

        $stopWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];

        return array_filter($words, fn($w) => !in_array(strtolower($w), $stopWords));
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
