<?php

/**
 * LibraryUsageService - COUNTER 5 usage statistics + SUSHI harvesting
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

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

/**
 * Manages COUNTER 5 usage statistics and SUSHI partner subscriptions.
 *
 * Table: library_usage_stats
 *   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   stat_date DATE NOT NULL (indexed)
 *   library_item_id BIGINT UNSIGNED NULL
 *   patron_id BIGINT UNSIGNED NULL
 *   metric_type VARCHAR(50) NOT NULL (indexed)
 *   count INT UNSIGNED DEFAULT 0
 *   partner_code VARCHAR(20) DEFAULT 'heratio'
 *   reporting_period VARCHAR(20) DEFAULT ''
 *   created_at TIMESTAMP
 *
 * Table: library_sushi_subscription
 *   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
 *   partner_code VARCHAR(50) NOT NULL
 *   api_key VARCHAR(255) NOT NULL
 *   base_url VARCHAR(500) NOT NULL
 *   report_types JSON NOT NULL
 *   contact_email VARCHAR(255) DEFAULT ''
 *   active TINYINT(1) DEFAULT 1
 *   created_at TIMESTAMP
 *   updated_at TIMESTAMP
 */
class LibraryUsageService
{
    // COUNTER 5 metric type constants
    public const METRIC_TOTAL_REQUESTS = 'Total_Item_Requests';
    public const METRIC_UNIQUE_REQUESTS = 'Unique_Item_Requests';
    public const METRIC_INVESTIGATIONS = 'Total_Item_Investigations';
    public const METRIC_UNIQUE_INVESTIGATIONS = 'Unique_Item_Investigations';
    public const METRIC_ACCESS_DENIED = 'Access_Denied';
    public const METRIC_ACCESS_GBV = 'Access_Denied_GBV';
    public const METRIC_OPEN_ACCESS = 'Open_Access_Count';
    public const METRIC_SEARCHES = 'Searches_Platform';

    public const ALL_METRICS = [
        self::METRIC_TOTAL_REQUESTS,
        self::METRIC_UNIQUE_REQUESTS,
        self::METRIC_INVESTIGATIONS,
        self::METRIC_UNIQUE_INVESTIGATIONS,
        self::METRIC_ACCESS_DENIED,
        self::METRIC_ACCESS_GBV,
        self::METRIC_OPEN_ACCESS,
        self::METRIC_SEARCHES,
    ];

    /**
     * Lazily create library_usage_stats and library_sushi_subscription
     * tables if they do not yet exist.
     */
    public function ensureTables(): void
    {
        if (!Schema::hasTable('library_usage_stats')) {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `library_usage_stats` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `stat_date` date NOT NULL COMMENT 'Date stat was recorded',
                    `library_item_id` bigint unsigned DEFAULT NULL COMMENT 'FK to library_item',
                    `patron_id` bigint unsigned DEFAULT NULL COMMENT 'FK to library_patron',
                    `metric_type` varchar(50) NOT NULL COMMENT 'COUNTER 5 metric',
                    `count` int unsigned NOT NULL DEFAULT '0',
                    `partner_code` varchar(20) NOT NULL DEFAULT 'heratio',
                    `reporting_period` varchar(20) NOT NULL DEFAULT '',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_stat_date` (`stat_date`),
                    KEY `idx_metric_type` (`metric_type`),
                    KEY `idx_library_item` (`library_item_id`),
                    KEY `idx_partner` (`partner_code`),
                    KEY `idx_stat_date_metric` (`stat_date`,`metric_type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!Schema::hasTable('library_counter_log')) {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `library_counter_log` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `session_id` varchar(64) DEFAULT NULL,
                    `user_id` bigint unsigned DEFAULT NULL,
                    `resource_id` bigint unsigned DEFAULT NULL,
                    `resource_type` varchar(30) NOT NULL DEFAULT 'item',
                    `access_type` varchar(30) NOT NULL DEFAULT 'Controlled',
                    `event` varchar(30) NOT NULL DEFAULT 'investigation',
                    `event_date` date NOT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'success',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_counter_session` (`session_id`),
                    KEY `idx_counter_date` (`event_date`),
                    KEY `idx_counter_resource` (`resource_id`),
                    KEY `idx_counter_date_event` (`event_date`,`event`),
                    KEY `idx_counter_unique_probe` (`session_id`,`resource_id`,`event_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }

        if (!Schema::hasTable('library_sushi_subscription')) {
            DB::statement("
                CREATE TABLE IF NOT EXISTS `library_sushi_subscription` (
                    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                    `partner_code` varchar(50) NOT NULL,
                    `api_key` varchar(255) NOT NULL,
                    `base_url` varchar(500) NOT NULL,
                    `report_types` json NOT NULL,
                    `contact_email` varchar(255) NOT NULL DEFAULT '',
                    `active` tinyint(1) NOT NULL DEFAULT '1',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_partner_code` (`partner_code`),
                    KEY `idx_active` (`active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    }

    /**
     * Record a single checkout as a Total_Item_Request stat.
     */
    public function recordCheckout(int $libraryItemId, int $patronId): void
    {
        if ($libraryItemId <= 0 || $patronId <= 0) {
            return;
        }
        $this->ensureTables();
        $today = now()->toDateString();

        // Upsert: increment existing count for today or insert new row
        $existing = DB::table('library_usage_stats')
            ->where('stat_date', $today)
            ->where('library_item_id', $libraryItemId)
            ->where('patron_id', $patronId)
            ->where('metric_type', self::METRIC_TOTAL_REQUESTS)
            ->first();

        if ($existing) {
            DB::table('library_usage_stats')
                ->where('id', $existing->id)
                ->increment('count');
        } else {
            DB::table('library_usage_stats')->insert([
                'stat_date' => $today,
                'library_item_id' => $libraryItemId,
                'patron_id' => $patronId,
                'metric_type' => self::METRIC_TOTAL_REQUESTS,
                'count' => 1,
                'partner_code' => 'heratio',
                'reporting_period' => '',
            ]);
        }

        // Per-event log: a checkout is a controlled Total_Item_Request. The
        // patron is the natural session here, hashed for anonymisation.
        try {
            DB::table('library_counter_log')->insert([
                'session_id'    => substr(hash('sha256', 'patron:' . $patronId), 0, 64),
                'user_id'       => $patronId,
                'resource_id'   => $libraryItemId,
                'resource_type' => 'item',
                'access_type'   => 'Controlled',
                'event'         => 'request',
                'event_date'    => $today,
                'status'        => 'success',
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('LibraryUsageService recordCheckout counter-log insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Record an access event for the given item.
     *
     * Writes BOTH the aggregate counter row (library_usage_stats) AND a
     * per-event row (library_counter_log) so session-level COUNTER metrics
     * (Unique_Item_Requests, PR1 searches) can be derived.
     *
     * @param int         $libraryItemId
     * @param string      $type 'request', 'investigation', 'denied', 'open_access', 'search'
     * @param string|null $sessionId  Anonymised per-session token (sha256 hex)
     * @param int|null    $userId
     * @param string      $accessType COUNTER access type: 'Controlled' | 'OA_Gold'
     */
    public function recordAccess(
        int $libraryItemId,
        string $type = 'investigation',
        ?string $sessionId = null,
        ?int $userId = null,
        string $accessType = 'Controlled'
    ): void {
        // A search event is platform-level and carries no item id.
        if ($type !== 'search' && $libraryItemId <= 0) {
            return;
        }
        $this->ensureTables();
        $today = now()->toDateString();

        $metric = match ($type) {
            'request'     => self::METRIC_TOTAL_REQUESTS,
            'denied'      => self::METRIC_ACCESS_DENIED,
            'open_access' => self::METRIC_OPEN_ACCESS,
            'search'      => self::METRIC_SEARCHES,
            default       => self::METRIC_INVESTIGATIONS,
        };

        DB::table('library_usage_stats')->insert([
            'stat_date' => $today,
            'library_item_id' => $type === 'search' ? null : $libraryItemId,
            'patron_id' => null,
            'metric_type' => $metric,
            'count' => 1,
            'partner_code' => 'heratio',
        ]);

        // Per-event log for session-level de-duplication.
        try {
            DB::table('library_counter_log')->insert([
                'session_id'    => $sessionId ? substr(hash('sha256', $sessionId), 0, 64) : null,
                'user_id'       => $userId,
                'resource_id'   => $type === 'search' ? null : $libraryItemId,
                'resource_type' => $type === 'search' ? 'search' : 'item',
                'access_type'   => $type === 'open_access' ? 'OA_Gold' : $accessType,
                'event'         => $type,
                'event_date'    => $today,
                'status'        => $type === 'denied' ? 'denied' : 'success',
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('LibraryUsageService recordAccess counter-log insert failed: ' . $e->getMessage());
        }
    }

    /**
     * Number of successful platform searches (COUNTER PR1 "Searches_Platform")
     * in the given date range, read from the per-event log.
     */
    public function countSearches(string $fromDate, string $toDate): int
    {
        if (!Schema::hasTable('library_counter_log')) {
            return 0;
        }
        return (int) DB::table('library_counter_log')
            ->where('event', 'search')
            ->where('status', 'success')
            ->whereBetween('event_date', [$fromDate, $toDate])
            ->count();
    }

    /**
     * Unique items requested/investigated, de-duplicated per session per day
     * (COUNTER R5 Unique_Item_Requests / Unique_Item_Investigations).
     *
     * @param string $kind 'request' counts request+open_access events;
     *                      'investigation' counts every item-bearing event.
     */
    public function countUniqueItems(string $fromDate, string $toDate, string $kind = 'request'): int
    {
        if (!Schema::hasTable('library_counter_log')) {
            return 0;
        }
        $q = DB::table('library_counter_log')
            ->whereNotNull('resource_id')
            ->where('status', 'success')
            ->whereBetween('event_date', [$fromDate, $toDate]);

        if ($kind === 'request') {
            $q->whereIn('event', ['request', 'open_access']);
        }
        // 'investigation' includes every item-bearing event (request implies
        // an investigation per the COUNTER model), so no event filter.

        // Distinct (session, item, day) tuple = COUNTER unique-item rule.
        return (int) $q->distinct()
            ->count(DB::raw("CONCAT(COALESCE(session_id,''),'|',resource_id,'|',event_date)"));
    }

    /**
     * Get aggregated usage stats for a given period and optional date range.
     *
     * @param string      $period 'weekly', 'monthly', 'yearly', 'quarterly'
     * @param string|null $from   ISO date
     * @param string|null $to     ISO date
     * @return array{periods: array<array{label:string,data:array<string,int>}>, totals: array<string,int>}
     */
    public function getStats(string $period = 'monthly', ?string $from = null, ?string $to = null): array
    {
        if (!Schema::hasTable('library_usage_stats')) {
            $zeroMetrics = array_fill_keys(self::ALL_METRICS, 0);
            return ['periods' => [], 'totals' => $zeroMetrics];
        }

        $labelSql = match ($period) {
            'weekly'     => "DATE_FORMAT(DATE_SUB(stat_date, INTERVAL WEEKDAY(stat_date) DAY), '%Y-%m-%d')",
            'yearly'    => "CAST(YEAR(stat_date) AS CHAR)",
            'quarterly' => "CONCAT(YEAR(stat_date),'-Q',QUARTER(stat_date))",
            default     => "DATE_FORMAT(stat_date, '%Y-%m')",
        };

        $query = DB::table('library_usage_stats')
            ->select(DB::raw($labelSql . ' AS label'), 'metric_type', DB::raw('SUM(`count`) as total_count'))
            ->groupBy('label', 'metric_type')
            ->orderBy('label');

        if ($from) {
            $query->where('stat_date', '>=', $from);
        }
        if ($to) {
            $query->where('stat_date', '<=', $to);
        }

        $rows = $query->get();

        $periodsMap = [];
        foreach ($rows as $row) {
            $label = (string) $row->label;
            $metric = (string) $row->metric_type;
            if (!isset($periodsMap[$label])) {
                $periodsMap[$label] = ['label' => $label, 'data' => array_fill_keys(self::ALL_METRICS, 0)];
            }
            $periodsMap[$label]['data'][$metric] = (int) $row->total_count;
        }

        $periods = array_values($periodsMap);
        usort($periods, fn($a, $b) => strcmp($a['label'], $b['label']));

        $totals = array_fill_keys(self::ALL_METRICS, 0);
        foreach ($rows as $row) {
            $key = (string) $row->metric_type;
            if (isset($totals[$key])) {
                $totals[$key] += (int) $row->total_count;
            }
        }

        return compact('periods', 'totals');
    }

    /**
     * Build a COUNTER 5 JSON report from library_usage_stats.
     *
     * @param string $reportType 'PR' (Platform), 'TR' (Title), 'DR' (Database)
     * @param string $fromDate  YYYY-MM-DD
     * @param string $toDate    YYYY-MM-DD
     * @return array COUNTER 5 JSON structure
     */
    public function buildCounterReport(string $reportType, string $fromDate, string $toDate): array
    {
        $reportMeta = match ($reportType) {
            'PR' => ['id' => 'PR', 'name' => 'Platform Usage Report'],
            'TR' => ['id' => 'TR', 'name' => 'Title Usage Report'],
            'TR_J1' => ['id' => 'TR_J1', 'name' => 'Journal Requests (Excluding OA_Gold)'],
            'TR_J3' => ['id' => 'TR_J3', 'name' => 'Journal Usage by Access Type'],
            'DR' => ['id' => 'DR', 'name' => 'Database Usage Report'],
            'IR' => ['id' => 'IR', 'name' => 'Item Usage Report'],
            default => ['id' => 'PR', 'name' => 'Platform Usage Report'],
        };

        $items = [];

        // IR (Item Report) - per-item granularity. Same shape as TR but one row
        // per library_item rather than per title aggregate.
        if ($reportType === 'IR') {
            if (Schema::hasTable('library_usage_stats')
                && Schema::hasTable('library_item')
                && Schema::hasTable('information_object_i18n')
            ) {
                $rows = DB::table('library_usage_stats as us')
                    ->join('library_item as li', 'us.library_item_id', '=', 'li.id')
                    ->join('information_object as io', 'li.information_object_id', '=', 'io.id')
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('io.id', '=', 'ioi.id')
                            ->where('ioi.culture', '=', app()->getLocale());
                    })
                    ->whereBetween('us.stat_date', [$fromDate, $toDate])
                    ->select(
                        'us.library_item_id as item_id',
                        'ioi.title as item_name',
                        'li.isbn',
                        'li.issn',
                        'us.metric_type',
                        DB::raw('SUM(us.count) as total_count')
                    )
                    ->groupBy('us.library_item_id', 'us.metric_type')
                    ->get();
                foreach ($rows as $row) {
                    $items[] = [
                        'Item_ID'     => (int) $row->item_id,
                        'Item_Name'   => $row->item_name ?? '',
                        'Print_ID'    => $row->isbn ?: $row->issn ?: null,
                        'Metric_Type' => $row->metric_type,
                        'Count'       => (int) $row->total_count,
                    ];
                }
            }
            return $this->finaliseCounterReport($reportMeta, $fromDate, $toDate, $items);
        }

        // TR_J1 - per-journal requests, excluding Open-Access Gold.
        // TR_J3 - per-journal split by access type (Controlled vs OA_Gold).
        if ($reportType === 'TR_J1' || $reportType === 'TR_J3') {
            if (Schema::hasTable('library_usage_stats')
                && Schema::hasTable('library_item')
                && Schema::hasTable('information_object_i18n')
            ) {
                $query = DB::table('library_usage_stats as us')
                    ->join('library_item as li', 'us.library_item_id', '=', 'li.id')
                    ->join('information_object as io', 'li.information_object_id', '=', 'io.id')
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('io.id', '=', 'ioi.id')
                            ->where('ioi.culture', '=', app()->getLocale());
                    })
                    ->whereBetween('us.stat_date', [$fromDate, $toDate])
                    ->whereNotNull('li.issn'); // Journals only.

                if ($reportType === 'TR_J1') {
                    // TR_J1 excludes OA_Gold metric; only Total/Unique requests.
                    $query->whereIn('us.metric_type', [self::METRIC_TOTAL_REQUESTS, self::METRIC_UNIQUE_REQUESTS]);
                }

                $rows = $query
                    ->select(
                        'li.issn',
                        'ioi.title as item_name',
                        'us.metric_type',
                        DB::raw('SUM(us.count) as total_count')
                    )
                    ->groupBy('li.issn', 'us.metric_type')
                    ->get();

                foreach ($rows as $row) {
                    $entry = [
                        'Item_Name'   => $row->item_name ?? '',
                        'Print_ISSN'  => $row->issn,
                        'Metric_Type' => $row->metric_type,
                        'Count'       => (int) $row->total_count,
                    ];
                    if ($reportType === 'TR_J3') {
                        $entry['Access_Type'] = $row->metric_type === self::METRIC_OPEN_ACCESS ? 'OA_Gold' : 'Controlled';
                    }
                    $items[] = $entry;
                }
            }
            return $this->finaliseCounterReport($reportMeta, $fromDate, $toDate, $items);
        }

        if ($reportType === 'TR') {
            if (Schema::hasTable('library_usage_stats')
                && Schema::hasTable('library_item')
                && Schema::hasTable('information_object_i18n')
            ) {
                $rows = DB::table('library_usage_stats as us')
                    ->join('library_item as li', 'us.library_item_id', '=', 'li.id')
                    ->join('information_object as io', 'li.information_object_id', '=', 'io.id')
                    ->leftJoin('information_object_i18n as ioi', function ($j) {
                        $j->on('io.id', '=', 'ioi.id')
                            ->where('ioi.culture', '=', app()->getLocale());
                    })
                    ->whereBetween('us.stat_date', [$fromDate, $toDate])
                    ->select('ioi.title as item_name', 'us.metric_type', DB::raw('SUM(us.count) as total_count'))
                    ->groupBy('us.library_item_id', 'us.metric_type')
                    ->get();

                foreach ($rows as $row) {
                    $items[] = [
                        'Item_Name'  => $row->item_name ?? '',
                        'Metric_Type' => $row->metric_type,
                        'Count'       => (int) $row->total_count,
                    ];
                }
            }
        } else {
            // PR (platform) / DR (database) - aggregate every metric over the
            // window. The pre-aggregated totals come from library_usage_stats;
            // the session-derived unique metrics + search count come from the
            // per-event log (library_counter_log), since they cannot be summed
            // from a counter alone.
            $seen = [];
            if (Schema::hasTable('library_usage_stats')) {
                $rows = DB::table('library_usage_stats')
                    ->whereBetween('stat_date', [$fromDate, $toDate])
                    ->select('metric_type', DB::raw('SUM(`count`) as total_count'))
                    ->groupBy('metric_type')
                    ->get();

                foreach ($rows as $row) {
                    // Skip the persisted unique metric: it is recomputed from
                    // the session log below to avoid double counting.
                    if (in_array($row->metric_type, [self::METRIC_UNIQUE_REQUESTS, self::METRIC_UNIQUE_INVESTIGATIONS], true)) {
                        continue;
                    }
                    $items[] = [
                        'Metric_Type' => $row->metric_type,
                        'Count'       => (int) $row->total_count,
                    ];
                    $seen[$row->metric_type] = true;
                }
            }

            // Session-derived COUNTER metrics (BR1 = unique title requests,
            // DR1 = unique items, PR1 = successful searches).
            $uniqueRequests = $this->countUniqueItems($fromDate, $toDate, 'request');
            $uniqueInvest   = $this->countUniqueItems($fromDate, $toDate, 'investigation');
            $searches       = $this->countSearches($fromDate, $toDate);

            if ($uniqueRequests > 0 || !isset($seen[self::METRIC_UNIQUE_REQUESTS])) {
                $items[] = ['Metric_Type' => self::METRIC_UNIQUE_REQUESTS, 'Count' => $uniqueRequests];
            }
            if ($uniqueInvest > 0 || !isset($seen[self::METRIC_UNIQUE_INVESTIGATIONS])) {
                $items[] = ['Metric_Type' => self::METRIC_UNIQUE_INVESTIGATIONS, 'Count' => $uniqueInvest];
            }
            if (!isset($seen[self::METRIC_SEARCHES])) {
                $items[] = ['Metric_Type' => self::METRIC_SEARCHES, 'Count' => $searches];
            }
        }

        return $this->finaliseCounterReport($reportMeta, $fromDate, $toDate, $items);
    }

    /**
     * Wrap an items list in the standard COUNTER R5 report envelope. Fills
     * empty results with zero-row placeholders so the export is well-formed.
     */
    private function finaliseCounterReport(array $reportMeta, string $fromDate, string $toDate, array $items): array
    {
        if (empty($items)) {
            foreach (self::ALL_METRICS as $metric) {
                $items[] = ['Metric_Type' => $metric, 'Count' => 0];
            }
        }

        return [
            'Report_ID'   => $reportMeta['id'],
            'Report_Name' => $reportMeta['name'],
            'Release'     => '5',
            'Customer_ID' => (string) (config('library.counter.customer_id') ?: 'heratio-self'),
            'Institution_Name' => (string) (config('library.counter.institution_name') ?: config('app.name')),
            'Created'     => now()->toIso8601String(),
            'Created_By'  => 'Heratio (heratio.theahg.co.za)',
            'Reporting_Period' => ['Begin_Date' => $fromDate, 'End_Date' => $toDate],
            'Report_Items'     => $items,
        ];
    }

    /**
     * Build a flat row matrix (header row + data rows) for a COUNTER report.
     * Shared by the TSV and XLSX exporters so both stay column-identical.
     *
     * @return array{0: array<string>, ...} First element is the header row.
     */
    public function getReportRows(string $reportType, string $fromDate, string $toDate): array
    {
        $report = $this->buildCounterReport($reportType, $fromDate, $toDate);
        $items = $report['Report_Items'] ?? [];
        $rows = [];

        if (in_array($reportType, ['TR', 'TR_J1', 'TR_J3', 'IR'], true)) {
            $header = ['Item_Name'];
            if ($reportType === 'IR') {
                $header[] = 'Item_ID';
            }
            if (in_array($reportType, ['TR_J1', 'TR_J3'], true)) {
                $header[] = 'Print_ISSN';
            }
            if ($reportType === 'TR_J3') {
                $header[] = 'Access_Type';
            }
            $header[] = 'Metric_Type';
            $header[] = 'Count';
            $rows[] = $header;

            foreach ($items as $item) {
                $row = [$item['Item_Name'] ?? ''];
                if ($reportType === 'IR') {
                    $row[] = $item['Item_ID'] ?? '';
                }
                if (in_array($reportType, ['TR_J1', 'TR_J3'], true)) {
                    $row[] = $item['Print_ISSN'] ?? '';
                }
                if ($reportType === 'TR_J3') {
                    $row[] = $item['Access_Type'] ?? '';
                }
                $row[] = $item['Metric_Type'] ?? '';
                $row[] = (int) ($item['Count'] ?? 0);
                $rows[] = $row;
            }
            return $rows;
        }

        // PR / DR: one header band carrying report metadata then metric rows.
        $rows[] = ['Report_ID', 'Report_Name', 'Created', 'Begin_Date', 'End_Date', 'Metric_Type', 'Count'];
        $first = true;
        foreach ($items as $item) {
            if ($first) {
                $rows[] = [
                    $report['Report_ID'],
                    $report['Report_Name'],
                    $report['Created'],
                    $report['Reporting_Period']['Begin_Date'] ?? $fromDate,
                    $report['Reporting_Period']['End_Date'] ?? $toDate,
                    $item['Metric_Type'] ?? '',
                    (int) ($item['Count'] ?? 0),
                ];
                $first = false;
            } else {
                $rows[] = ['', '', '', '', '', $item['Metric_Type'] ?? '', (int) ($item['Count'] ?? 0)];
            }
        }
        return $rows;
    }

    /**
     * Return a TSV string for the given report type and date range.
     */
    public function getReportCsv(string $reportType, string $fromDate, string $toDate): string
    {
        $rows = $this->getReportRows($reportType, $fromDate, $toDate);
        $lines = [];
        foreach ($rows as $row) {
            // Sanitise any embedded tabs/newlines so the TSV stays well-formed.
            $clean = array_map(
                fn ($v) => str_replace(["\t", "\n", "\r"], ' ', (string) $v),
                $row
            );
            $lines[] = implode("\t", $clean);
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Build an XLSX workbook (binary string) for the given report. Uses
     * PhpSpreadsheet, which ships in the Heratio root composer (^5.5).
     */
    public function getReportXlsx(string $reportType, string $fromDate, string $toDate): string
    {
        $rows = $this->getReportRows($reportType, $fromDate, $toDate);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr('COUNTER ' . $reportType, 0, 31));

        $r = 1;
        foreach ($rows as $row) {
            $c = 1;
            foreach ($row as $value) {
                $sheet->setCellValue([$c, $r], $value);
                $c++;
            }
            $r++;
        }

        // Bold the header row.
        if (!empty($rows)) {
            $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($rows[0]));
            $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $binary = (string) ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $binary;
    }

    /**
     * Subscribe a new SUSHI partner, or update an existing one (upsert).
     *
     * @return int Subscription ID
     */
    public function subscribePartner(
        string $partnerCode,
        string $contactEmail,
        string $apiKey,
        string $baseUrl,
        array $reportTypes = []
    ): int {
        $this->ensureTables();

        $existing = DB::table('library_sushi_subscription')
            ->where('partner_code', $partnerCode)
            ->first();

        $payload = [
            'partner_code'  => $partnerCode,
            'api_key'       => encrypt($apiKey),
            'base_url'      => rtrim($baseUrl, '/'),
            'report_types'  => json_encode($reportTypes),
            'contact_email' => $contactEmail,
            'active'        => 1,
            'updated_at'    => now(),
        ];

        if ($existing) {
            DB::table('library_sushi_subscription')
                ->where('id', $existing->id)
                ->update($payload);
            return (int) $existing->id;
        }

        $payload['created_at'] = now();
        return (int) DB::table('library_sushi_subscription')->insertGetId($payload);
    }

    /**
     * Return all active SUSHI subscriptions.
     *
     * @return array<array{id:int,partner_code:string,base_url:string,api_key:string,report_types:array,contact_email:string}>
     */
    public function getActiveSubscriptions(): array
    {
        if (!Schema::hasTable('library_sushi_subscription')) {
            return [];
        }

        $rows = DB::table('library_sushi_subscription')
            ->where('active', 1)
            ->get();

        return $rows->map(function ($row) {
            return [
                'id'            => (int) $row->id,
                'partner_code'  => $row->partner_code,
                'base_url'      => $row->base_url,
                'api_key'       => decrypt($row->api_key),
                'report_types'  => json_decode($row->report_types, true) ?? [],
                'contact_email' => $row->contact_email,
            ];
        })->toArray();
    }

    /**
     * Harvest SUSHI reports from all active partners and store records.
     *
     * @param string|null $fromDate     YYYY-MM-DD, defaults to 30 days ago
     * @param string|null $toDate       YYYY-MM-DD, defaults to today
     * @param array       $reportTypes  Override which report types to fetch per partner
     * @return int Total records collected
     */
    public function harvestFromAllPartners(
        ?string $fromDate = null,
        ?string $toDate = null,
        array $reportTypes = ['PR', 'TR']
    ): int {
        $this->ensureTables();

        $fromDate = $fromDate ?? now()->subMonth()->toDateString();
        $toDate = $toDate ?? now()->toDateString();

        $totalRecords = 0;
        $sushi = app(SushiService::class);

        foreach ($this->getActiveSubscriptions() as $sub) {
            foreach ($sub['report_types'] as $reportType) {
                try {
                    $count = $sushi->fetchAndStoreReport(
                        $sub['partner_code'],
                        $reportType,
                        $fromDate,
                        $toDate
                    );
                    $totalRecords += $count;
                } catch (\Throwable $e) {
                    \Log::warning("SushiService harvest failed for partner {$sub['partner_code']}: {$e->getMessage()}");
                }
            }
        }

        return $totalRecords;
    }
}
