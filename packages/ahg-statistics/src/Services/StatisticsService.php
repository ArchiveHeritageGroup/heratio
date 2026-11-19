<?php

/**
 * StatisticsService - Service for Heratio
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

namespace AhgStatistics\Services;

use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /** Usage events by people: bots are recorded (flagged) but not reported. */
    private function people(): \Illuminate\Database\Query\Builder
    {
        return DB::table('ahg_usage_event')->where('is_bot', 0);
    }

    /**
     * heratio#1772: requests, approvals and releases in the period - from the
     * access requests, their step log, the orders that pay quotations and the
     * release links (ahg_cart_downloads).
     *
     * @return array{requests: array, decisions: array, turnaround_hours: ?float, releases: array, by_day: array}
     */
    public function getRequestStats(string $startDate, string $endDate): array
    {
        $end = $endDate.' 23:59:59';
        $has = fn (string $t) => \Illuminate\Support\Facades\Schema::hasTable($t);
        $out = ['requests' => [], 'decisions' => [], 'turnaround_hours' => null, 'releases' => [], 'by_day' => []];
        if (! $has('access_request')) {
            return $out;
        }

        $req = DB::table('access_request')->whereBetween('created_at', [$startDate, $end]);
        $out['requests'] = [
            'submitted' => (clone $req)->count(),
            'basket' => (clone $req)->where('scope_type', 'basket')->count(),
            'records' => $has('access_request_scope') ? DB::table('access_request_scope as s')->join('access_request as r', 'r.id', '=', 's.request_id')->whereBetween('r.created_at', [$startDate, $end])->count() : 0,
            'by_status' => (clone $req)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n)->all(),
        ];

        if ($has('access_request_log')) {
            $out['decisions'] = DB::table('access_request_log')->whereBetween('created_at', [$startDate, $end])
                ->whereIn('action', ['approved', 'released with conditions', 'denied', 'quoted', 'paid', 'cancelled', 'routed'])
                ->select('action', DB::raw('COUNT(*) as n'))->groupBy('action')->pluck('n', 'action')->map(fn ($n) => (int) $n)->all();
        }

        $decided = DB::table('access_request')->whereBetween('reviewed_at', [$startDate, $end])
            ->whereIn('status', ['approved', 'conditional', 'denied'])->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at)) as m')->value('m');
        $out['turnaround_hours'] = $decided !== null ? round(((float) $decided) / 60, 1) : null;

        if ($has('ahg_cart_downloads') && \Illuminate\Support\Facades\Schema::hasColumn('ahg_cart_downloads', 'user_id')) {
            $links = DB::table('ahg_cart_downloads')->whereNotNull('user_id')->whereBetween('created_at', [$startDate, $end]);
            $out['releases'] = [
                'issued' => (clone $links)->count(),
                'by_request' => (clone $links)->whereNotNull('access_request_id')->count(),
                'by_order' => (clone $links)->whereNotNull('order_item_id')->count(),
                'downloaded' => (clone $links)->where('download_count', '>', 0)->count(),
                'downloads' => (int) (clone $links)->sum('download_count'),
                'revoked' => (clone $links)->whereNotNull('revoked_at')->count(),
            ];
        }
        if ($has('ahg_order') && \Illuminate\Support\Facades\Schema::hasColumn('ahg_order', 'access_request_id')) {
            $paid = DB::table('ahg_order')->whereNotNull('access_request_id')->where('status', 'paid')->whereBetween('paid_at', [$startDate, $end]);
            $out['releases']['quotes_paid'] = (clone $paid)->count();
            $out['releases']['quotes_paid_total'] = (float) (clone $paid)->sum('total');
        }

        $out['by_day'] = DB::table('access_request')->whereBetween('created_at', [$startDate, $end])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as n')->groupBy('day')->orderBy('day')
            ->pluck('n', 'day')->map(fn ($n) => (int) $n)->all();

        return $out;
    }

    public function getDashboardStats(string $startDate, string $endDate): array
    {
        $views = $this->people()
            ->where('event_type', 'view')
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->count();

        $downloads = $this->people()
            ->where('event_type', 'download')
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->count();

        $uniqueVisitors = $this->people()
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->distinct()
            ->count(DB::raw('COALESCE(ip_hash, ip_address)'));

        return [
            'views' => $views,
            'downloads' => $downloads,
            'unique_visitors' => $uniqueVisitors,
        ];
    }

    public function getTopItems(string $eventType, int $limit, string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $culture = app()->getLocale();

        return DB::table('ahg_usage_event as e')->where('e.is_bot', 0)
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'e.object_id', '=', 's.object_id')
            ->where('e.event_type', $eventType)
            ->whereBetween('e.created_at', [$startDate, $endDate.' 23:59:59'])
            ->whereNotNull('e.object_id')
            ->select('e.object_id', 'ioi.title', 's.slug', DB::raw('COUNT(*) as count'))
            ->groupBy('e.object_id', 'ioi.title', 's.slug')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    public function getGeographicStats(string $startDate, string $endDate): array
    {
        // Real columns are `country_code` (ISO 2-letter) + `country_name`.
        return $this->people()
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->whereNotNull('country_code')
            ->select('country_code as country', 'country_name', DB::raw('COUNT(*) as count'))
            ->groupBy('country_code', 'country_name')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    public function getViewsOverTime(string $startDate, string $endDate, string $groupBy = 'day'): array
    {
        $format = $groupBy === 'month' ? '%Y-%m' : ($groupBy === 'week' ? '%Y-%u' : '%Y-%m-%d');

        return $this->people()
            ->where('event_type', 'view')
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$format}') as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    public function getDownloadsOverTime(string $startDate, string $endDate): array
    {
        return $this->people()
            ->where('event_type', 'download')
            ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    public function getItemStats(int $objectId, string $startDate, string $endDate): array
    {
        return [
            'views' => $this->people()
                ->where('object_id', $objectId)
                ->where('event_type', 'view')
                ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                ->count(),
            'downloads' => $this->people()
                ->where('object_id', $objectId)
                ->where('event_type', 'download')
                ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                ->count(),
        ];
    }

    public function getRepositoryStats(int $repositoryId, string $startDate, string $endDate): array
    {
        $objectIds = DB::table('information_object')
            ->where('repository_id', $repositoryId)
            ->pluck('id');

        return [
            'views' => $this->people()
                ->whereIn('object_id', $objectIds)
                ->where('event_type', 'view')
                ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                ->count(),
            'downloads' => $this->people()
                ->whereIn('object_id', $objectIds)
                ->where('event_type', 'download')
                ->whereBetween('created_at', [$startDate, $endDate.' 23:59:59'])
                ->count(),
            'total_objects' => $objectIds->count(),
        ];
    }

    public function exportToCsv(string $type, string $startDate, string $endDate): string
    {
        $output = fopen('php://temp', 'r+');

        if ($type === 'views') {
            fputcsv($output, ['Date', 'Views']);
            $data = $this->getViewsOverTime($startDate, $endDate);
            foreach ($data as $row) {
                fputcsv($output, [$row->period ?? $row['period'], $row->count ?? $row['count']]);
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function getConfig(string $key, $default = null)
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'statistics')
            ->where('setting_key', $key)
            ->first();

        return $row ? $row->setting_value : $default;
    }

    public function setConfig(string $key, $value, string $type = 'string'): void
    {
        DB::table('ahg_settings')->updateOrInsert(
            ['setting_group' => 'statistics', 'setting_key' => $key],
            ['setting_value' => (string) $value, 'updated_at' => now()]
        );
    }

    public function getBotList(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_bot_list')->orderBy('name')->get();
    }

    public function addBot(array $data): int
    {
        $data['created_at'] = now();
        $data['is_active'] = 1;

        return DB::table('ahg_bot_list')->insertGetId($data);
    }

    public function updateBot(int $id, array $data): void
    {
        DB::table('ahg_bot_list')->where('id', $id)->update($data);
    }

    public function deleteBot(int $id): void
    {
        DB::table('ahg_bot_list')->where('id', $id)->delete();
    }

    /**
     * Aggregate raw events from `ahg_usage_event` into `ahg_statistics_daily`.
     * Groups by (event_date, event_type, object_type, repository_id, country_code)
     * and upserts one row per combination with total/unique/auth/bot counts.
     * Heratio-specific - PSIS stats plugin has a different pipeline.
     *
     * @return array{rows: int, dates: int}
     */
    public function aggregateStats(): array
    {
        $buckets = DB::table('ahg_usage_event')
            ->selectRaw('
                event_date,
                event_type,
                object_type,
                repository_id,
                country_code,
                COUNT(*) as total_count,
                COUNT(DISTINCT COALESCE(ip_hash, ip_address)) as unique_visitors,
                SUM(CASE WHEN user_id IS NOT NULL THEN 1 ELSE 0 END) as authenticated_count,
                SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bot_count
            ')
            ->whereNotNull('event_date')
            ->groupBy('event_date', 'event_type', 'object_type', 'repository_id', 'country_code')
            ->get();

        $dates = [];
        foreach ($buckets as $b) {
            DB::table('ahg_statistics_daily')->updateOrInsert(
                [
                    'stat_date' => $b->event_date,
                    'event_type' => $b->event_type,
                    'object_type' => $b->object_type,
                    'repository_id' => $b->repository_id,
                    'country_code' => $b->country_code,
                ],
                [
                    'total_count' => (int) $b->total_count,
                    'unique_visitors' => (int) $b->unique_visitors,
                    'authenticated_count' => (int) $b->authenticated_count,
                    'bot_count' => (int) $b->bot_count,
                    'updated_at' => now(),
                ]
            );
            $dates[$b->event_date] = true;
        }

        return ['rows' => $buckets->count(), 'dates' => count($dates)];
    }
}
