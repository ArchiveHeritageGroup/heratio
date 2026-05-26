<?php

/**
 * SearchAnalyticsService - records search queries and surfaces top / zero /
 * CTR aggregates for the admin analytics dashboard.
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

namespace AhgSearch\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Search analytics. Issue #650 Phase 3.
 *
 * Lightweight - the goal is "what are people typing? what's getting zero
 * hits? what's getting clicked?" not a full session-replay product. All
 * writes go through {@see recordQuery()} / {@see recordClick()} and all
 * reads come back as plain arrays so the controller can render a vanilla
 * Bootstrap 5 table without an ORM dependency.
 */
class SearchAnalyticsService
{
    /**
     * Truncate a user-supplied query to fit the VARCHAR(512) column without
     * tripping MySQL's strict mode. We index the leading 64 chars so anything
     * past 512 is noise for our aggregates anyway.
     */
    protected const QUERY_MAX = 512;

    /**
     * Persist a query execution. Safe to call from the request hot path - any
     * failure (missing table, DB down) is swallowed so a logging hiccup never
     * breaks the search response.
     *
     * @param  string  $query  Raw user query string.
     * @param  array  $filters  Active filter set (will be JSON-encoded).
     * @param  int  $resultCount  Total hit count returned to the user.
     * @param  int|float  $responseMs  Time taken to produce the response, in ms.
     * @param  string|null  $ip  Caller IP (used for anonymized_id when no auth user).
     * @return int|null Inserted row ID, or null on failure.
     */
    public function recordQuery(string $query, array $filters, int $resultCount, $responseMs, ?string $ip = null): ?int
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        if (! Schema::hasTable('ahg_search_query_log')) {
            return null;
        }

        try {
            $userId = Auth::check() ? (int) Auth::id() : null;
            $anon = $userId === null && $ip !== null
                ? substr(hash('sha256', $ip), 0, 64)
                : null;

            return (int) DB::table('ahg_search_query_log')->insertGetId([
                'user_id' => $userId,
                'anonymized_id' => $anon,
                'query' => mb_substr($query, 0, self::QUERY_MAX),
                'filters_json' => empty($filters) ? null : json_encode($filters, JSON_UNESCAPED_UNICODE),
                'result_count' => max(0, $resultCount),
                'click_position' => null,
                'executed_at' => date('Y-m-d H:i:s'),
                'response_time_ms' => max(0, (int) $responseMs),
            ]);
        } catch (\Throwable $e) {
            Log::debug('SearchAnalyticsService::recordQuery failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Flip a logged query's click_position. Used by the click-tracking POST
     * endpoint so we can compute CTR per query. Idempotent for repeat clicks
     * on the same row - we always store the LATEST position (a user clicking
     * #1 then back-button then #3 records #3, which is the position that
     * actually mattered to them).
     */
    public function recordClick(int $queryLogId, int $position): bool
    {
        if ($queryLogId <= 0 || $position <= 0) {
            return false;
        }

        if (! Schema::hasTable('ahg_search_query_log')) {
            return false;
        }

        try {
            return DB::table('ahg_search_query_log')
                ->where('id', $queryLogId)
                ->update(['click_position' => $position]) > 0;
        } catch (\Throwable $e) {
            Log::debug('SearchAnalyticsService::recordClick failed: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Top queries by execution count since $since. Returns rows of the shape
     *   ['query' => '...', 'count' => 42, 'click_count' => 17, 'ctr' => 0.40,
     *    'avg_results' => 23.5, 'last_seen' => '2026-05-26 12:34:56'].
     */
    public function topQueries(CarbonImmutable $since, int $limit = 20): array
    {
        if (! Schema::hasTable('ahg_search_query_log')) {
            return [];
        }

        $rows = DB::table('ahg_search_query_log')
            ->where('executed_at', '>=', $since->toDateTimeString())
            ->select(
                'query',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN click_position IS NOT NULL THEN 1 ELSE 0 END) as click_count'),
                DB::raw('AVG(result_count) as avg_results'),
                DB::raw('MAX(executed_at) as last_seen'),
            )
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            $count = (int) $r->count;
            $clicks = (int) $r->click_count;

            return [
                'query' => $r->query,
                'count' => $count,
                'click_count' => $clicks,
                'ctr' => $count > 0 ? round($clicks / $count, 4) : 0.0,
                'avg_results' => round((float) $r->avg_results, 1),
                'last_seen' => $r->last_seen,
            ];
        })->toArray();
    }

    /**
     * Queries that returned zero results since $since. Signal for content
     * gaps OR for synonym-dictionary entries we don't yet have. Sorted by
     * frequency desc so the highest-pain misses surface first.
     */
    public function zeroResultQueries(CarbonImmutable $since, int $limit = 20): array
    {
        if (! Schema::hasTable('ahg_search_query_log')) {
            return [];
        }

        $rows = DB::table('ahg_search_query_log')
            ->where('executed_at', '>=', $since->toDateTimeString())
            ->where('result_count', 0)
            ->select(
                'query',
                DB::raw('COUNT(*) as count'),
                DB::raw('MAX(executed_at) as last_seen'),
            )
            ->groupBy('query')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'query' => $r->query,
            'count' => (int) $r->count,
            'last_seen' => $r->last_seen,
        ])->toArray();
    }

    /**
     * High-level totals for the dashboard hero strip.
     *
     * @return array{total:int, zero:int, with_clicks:int, ctr:float, unique_queries:int}
     */
    public function totals(CarbonImmutable $since): array
    {
        if (! Schema::hasTable('ahg_search_query_log')) {
            return ['total' => 0, 'zero' => 0, 'with_clicks' => 0, 'ctr' => 0.0, 'unique_queries' => 0];
        }

        $row = DB::table('ahg_search_query_log')
            ->where('executed_at', '>=', $since->toDateTimeString())
            ->selectRaw(
                'COUNT(*) AS total, '.
                'SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) AS zero, '.
                'SUM(CASE WHEN click_position IS NOT NULL THEN 1 ELSE 0 END) AS with_clicks, '.
                'COUNT(DISTINCT query) AS unique_queries'
            )->first();

        $total = (int) ($row->total ?? 0);
        $clicks = (int) ($row->with_clicks ?? 0);

        return [
            'total' => $total,
            'zero' => (int) ($row->zero ?? 0),
            'with_clicks' => $clicks,
            'ctr' => $total > 0 ? round($clicks / $total, 4) : 0.0,
            'unique_queries' => (int) ($row->unique_queries ?? 0),
        ];
    }
}
