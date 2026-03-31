<?php

/**
 * StatisticsService - Service for Heratio
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



namespace AhgStatistics\Services;

use Illuminate\Support\Facades\DB;

class StatisticsService
{
    public function getDashboardStats(string $startDate, string $endDate): array
    {
        $views = DB::table('ahg_usage_event')
            ->where('event_type', 'view')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->count();

        $downloads = DB::table('ahg_usage_event')
            ->where('event_type', 'download')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->count();

        $uniqueVisitors = DB::table('ahg_usage_event')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->distinct('ip_address')
            ->count('ip_address');

        return [
            'views' => $views,
            'downloads' => $downloads,
            'unique_visitors' => $uniqueVisitors,
        ];
    }

    public function getTopItems(string $eventType, int $limit, string $startDate, string $endDate): \Illuminate\Support\Collection
    {
        $culture = app()->getLocale();

        return DB::table('ahg_usage_event as e')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('e.object_id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'e.object_id', '=', 's.object_id')
            ->where('e.event_type', $eventType)
            ->whereBetween('e.created_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotNull('e.object_id')
            ->select('e.object_id', 'ioi.title', 's.slug', DB::raw('COUNT(*) as count'))
            ->groupBy('e.object_id', 'ioi.title', 's.slug')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    public function getGeographicStats(string $startDate, string $endDate): array
    {
        return DB::table('ahg_usage_event')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->whereNotNull('country')
            ->select('country', DB::raw('COUNT(*) as count'))
            ->groupBy('country')
            ->orderByDesc('count')
            ->get()
            ->toArray();
    }

    public function getViewsOverTime(string $startDate, string $endDate, string $groupBy = 'day'): array
    {
        $format = $groupBy === 'month' ? '%Y-%m' : ($groupBy === 'week' ? '%Y-%u' : '%Y-%m-%d');

        return DB::table('ahg_usage_event')
            ->where('event_type', 'view')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->select(DB::raw("DATE_FORMAT(created_at, '{$format}') as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    public function getDownloadsOverTime(string $startDate, string $endDate): array
    {
        return DB::table('ahg_usage_event')
            ->where('event_type', 'download')
            ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as period"), DB::raw('COUNT(*) as count'))
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    public function getItemStats(int $objectId, string $startDate, string $endDate): array
    {
        return [
            'views' => DB::table('ahg_usage_event')
                ->where('object_id', $objectId)
                ->where('event_type', 'view')
                ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
                ->count(),
            'downloads' => DB::table('ahg_usage_event')
                ->where('object_id', $objectId)
                ->where('event_type', 'download')
                ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
                ->count(),
        ];
    }

    public function getRepositoryStats(int $repositoryId, string $startDate, string $endDate): array
    {
        $objectIds = DB::table('information_object')
            ->where('repository_id', $repositoryId)
            ->pluck('id');

        return [
            'views' => DB::table('ahg_usage_event')
                ->whereIn('object_id', $objectIds)
                ->where('event_type', 'view')
                ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
                ->count(),
            'downloads' => DB::table('ahg_usage_event')
                ->whereIn('object_id', $objectIds)
                ->where('event_type', 'download')
                ->whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
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
}
