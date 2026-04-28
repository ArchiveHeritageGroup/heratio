<?php

/**
 * StatisticsAggregateCommand — roll ahg_usage_event into daily/monthly buckets.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatisticsAggregateCommand extends Command
{
    protected $signature = 'ahg:statistics-aggregate
        {--all : Aggregate all statistics}
        {--daily : Aggregate daily statistics}
        {--monthly : Aggregate monthly statistics}
        {--cleanup : Clean up old raw statistics}
        {--days=90 : Retention period in days for cleanup}
        {--backfill= : Backfill statistics from a specific date (Y-m-d)}';

    protected $description = 'Aggregate usage statistics (ahg_usage_event → ahg_statistics_*)';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_usage_event')) { $this->warn('ahg_usage_event missing'); return self::SUCCESS; }

        $doDaily = $this->option('daily') || $this->option('all');
        $doMonthly = $this->option('monthly') || $this->option('all');
        if (! $doDaily && ! $doMonthly && ! $this->option('cleanup')) $doDaily = true;

        $from = $this->option('backfill') ? Carbon::parse((string) $this->option('backfill'))->startOfDay() : Carbon::yesterday();
        $to = Carbon::now()->endOfDay();

        if ($doDaily) $this->info('daily: rolled ' . $this->rollDaily($from, $to) . ' rows');
        if ($doMonthly) $this->info('monthly: rolled ' . $this->rollMonthly($from, $to) . ' rows');
        if ($this->option('cleanup')) $this->info('cleanup: deleted ' . $this->cleanup((int) $this->option('days')) . ' raw events');
        return self::SUCCESS;
    }

    protected function rollDaily(Carbon $from, Carbon $to): int
    {
        $rows = DB::table('ahg_usage_event')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) AS d, event_type, object_type, object_id, repository_id, country_code,
                COUNT(*) AS total, COUNT(DISTINCT COALESCE(ip_hash, ip_address)) AS uniq,
                SUM(user_id IS NOT NULL) AS auth, SUM(is_bot=1) AS bots')
            ->groupBy('d', 'event_type', 'object_type', 'object_id', 'repository_id', 'country_code')
            ->get();

        $n = 0;
        foreach ($rows as $r) {
            DB::table('ahg_statistics_daily')->updateOrInsert(
                ['stat_date' => $r->d, 'event_type' => $r->event_type, 'object_type' => $r->object_type,
                 'object_id' => $r->object_id, 'repository_id' => $r->repository_id, 'country_code' => $r->country_code],
                ['total_count' => $r->total, 'unique_visitors' => $r->uniq,
                 'authenticated_count' => $r->auth, 'bot_count' => $r->bots, 'updated_at' => now()],
            );
            $n++;
        }
        return $n;
    }

    protected function rollMonthly(Carbon $from, Carbon $to): int
    {
        $rows = DB::table('ahg_statistics_daily')
            ->whereBetween('stat_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('YEAR(stat_date) AS y, MONTH(stat_date) AS m, event_type, object_type, object_id, repository_id, country_code,
                SUM(total_count) AS total, MAX(total_count) AS peak_count, MAX(stat_date) AS peak_day, SUM(unique_visitors) AS uniq')
            ->groupBy('y', 'm', 'event_type', 'object_type', 'object_id', 'repository_id', 'country_code')
            ->get();

        $n = 0;
        foreach ($rows as $r) {
            DB::table('ahg_statistics_monthly')->updateOrInsert(
                ['stat_year' => $r->y, 'stat_month' => $r->m, 'event_type' => $r->event_type,
                 'object_type' => $r->object_type, 'object_id' => $r->object_id,
                 'repository_id' => $r->repository_id, 'country_code' => $r->country_code],
                ['total_count' => $r->total, 'peak_count' => $r->peak_count, 'peak_day' => $r->peak_day,
                 'unique_visitors' => $r->uniq, 'updated_at' => now()],
            );
            $n++;
        }
        return $n;
    }

    protected function cleanup(int $days): int
    {
        $cutoff = Carbon::now()->subDays(max(1, $days));
        return DB::table('ahg_usage_event')->where('created_at', '<', $cutoff)->delete();
    }
}
