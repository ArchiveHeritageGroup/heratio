<?php

/**
 * ResearchAnalyticsService - Service for Heratio
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

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;

/**
 * ResearchAnalyticsService
 *
 * Aggregates the existing research_statistics_daily + research_activity_log
 * tables into the metrics a research institution needs to back funding
 * applications: collection usage, search patterns, researcher activity,
 * popular collections, date-range analysis.
 *
 * All read-only. No writes - the source tables are populated by the existing
 * audit + activity-log hooks elsewhere in ahg-research.
 */
class ResearchAnalyticsService
{
    public function dashboard(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: date('Y-m-d', strtotime('-30 days'));
        $to   = $to   ?: date('Y-m-d');

        return [
            'period'                 => ['from' => $from, 'to' => $to],
            'usage_totals'           => $this->usageTotals($from, $to),
            'daily_series'           => $this->dailySeries($from, $to),
            'top_activity_types'     => $this->topActivityTypes($from, $to, 10),
            'top_researchers'        => $this->topResearchers($from, $to, 10),
            'popular_collections'    => $this->popularCollections($from, $to, 10),
            'popular_descriptions'   => $this->popularDescriptions($from, $to, 10),
            'search_terms'           => $this->topSearchTerms($from, $to, 15),
            'citations_by_style'     => $this->citationsByStyle($from, $to),
            'date_range_distribution'=> $this->dateRangeDistribution($from, $to),
        ];
    }

    private function usageTotals(string $from, string $to): array
    {
        $base = DB::table('research_activity_log')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']);

        return [
            'total_events'      => (clone $base)->count(),
            'unique_researchers'=> (clone $base)->distinct()->count('researcher_id'),
            'unique_objects'    => (clone $base)->whereNotNull('entity_id')->distinct()->count(DB::raw("CONCAT(entity_type, ':', entity_id)")),
            'view_events'       => (clone $base)->where('activity_type', 'view')->count(),
            'search_events'     => (clone $base)->where('activity_type', 'search')->count(),
            'cite_events'       => (clone $base)->where('activity_type', 'cite')->count(),
            'download_events'   => (clone $base)->where('activity_type', 'download')->count(),
            'annotation_events' => (clone $base)->where('activity_type', 'annotate')->count(),
        ];
    }

    private function dailySeries(string $from, string $to): array
    {
        return DB::table('research_activity_log')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('DATE(created_at) as day, activity_type, COUNT(*) as n')
            ->groupBy('day', 'activity_type')
            ->orderBy('day')
            ->get()
            ->toArray();
    }

    private function topActivityTypes(string $from, string $to, int $limit): array
    {
        return DB::table('research_activity_log')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('activity_type, COUNT(*) as n')
            ->groupBy('activity_type')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function topResearchers(string $from, string $to, int $limit): array
    {
        return DB::table('research_activity_log as al')
            ->leftJoin('research_researcher as r', 'al.researcher_id', '=', 'r.id')
            ->whereBetween('al.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereNotNull('al.researcher_id')
            ->selectRaw('al.researcher_id, r.first_name, r.last_name, r.email, COUNT(*) as n')
            ->groupBy('al.researcher_id', 'r.first_name', 'r.last_name', 'r.email')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function popularCollections(string $from, string $to, int $limit): array
    {
        return DB::table('research_activity_log as al')
            ->leftJoin('research_collection as c', 'al.entity_id', '=', 'c.id')
            ->whereBetween('al.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('al.entity_type', 'collection')
            ->selectRaw('al.entity_id as collection_id, c.name, COUNT(*) as n')
            ->groupBy('al.entity_id', 'c.name')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function popularDescriptions(string $from, string $to, int $limit): array
    {
        return DB::table('research_activity_log as al')
            ->whereBetween('al.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->whereIn('al.activity_type', ['view', 'cite', 'annotate'])
            ->where('al.entity_type', 'information_object')
            ->selectRaw('al.entity_id, al.entity_title, COUNT(*) as n')
            ->groupBy('al.entity_id', 'al.entity_title')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function topSearchTerms(string $from, string $to, int $limit): array
    {
        return DB::table('research_activity_log')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->where('activity_type', 'search')
            ->whereNotNull('entity_title')
            ->selectRaw('entity_title as term, COUNT(*) as n')
            ->groupBy('entity_title')
            ->orderByDesc('n')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    private function citationsByStyle(string $from, string $to): array
    {
        try {
            return DB::table('research_citation_log')
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                ->selectRaw('citation_style, COUNT(*) as n')
                ->groupBy('citation_style')
                ->orderByDesc('n')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * For each calendar week in the period, count events. Used by the
     * dashboard's date-range distribution chart.
     */
    private function dateRangeDistribution(string $from, string $to): array
    {
        return DB::table('research_activity_log')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw('YEARWEEK(created_at, 3) as yw, MIN(DATE(created_at)) as week_start, COUNT(*) as n')
            ->groupBy('yw')
            ->orderBy('yw')
            ->get()
            ->toArray();
    }
}
