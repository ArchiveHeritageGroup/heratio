<?php

/**
 * StatisticsReportCommand — render usage stats from aggregated tables.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StatisticsReportCommand extends Command
{
    protected $signature = 'ahg:statistics-report
        {--type=summary : Report type (summary, detailed, trends)}
        {--start= : Start date (Y-m-d)}
        {--end= : End date (Y-m-d)}
        {--limit= : Limit number of results}
        {--format=table : Output format (table, csv, json)}
        {--output= : Write report to file path}';

    protected $description = 'Generate statistics reports';

    public function handle(): int
    {
        if (! Schema::hasTable('ahg_statistics_daily')) {
            $this->warn('ahg_statistics_daily missing — run ahg:statistics-aggregate first.');
            return self::SUCCESS;
        }
        $type = (string) $this->option('type');
        $start = Carbon::parse((string) ($this->option('start') ?: now()->subDays(30)->toDateString()));
        $end = Carbon::parse((string) ($this->option('end') ?: now()->toDateString()));
        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : 25;

        $rows = match ($type) {
            'summary'  => $this->summary($start, $end),
            'detailed' => $this->detailed($start, $end, $limit),
            'trends'   => $this->trends($start, $end),
            default    => null,
        };
        if ($rows === null) { $this->error("unknown --type {$type}"); return self::FAILURE; }

        $format = (string) $this->option('format');
        $payload = $this->format($rows, $format);
        if ($out = $this->option('output')) { file_put_contents($out, $payload); $this->info("-> {$out}"); }
        else { $this->line($payload); }
        return self::SUCCESS;
    }

    protected function summary(Carbon $s, Carbon $e): array
    {
        $by = DB::table('ahg_statistics_daily')
            ->whereBetween('stat_date', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('event_type, SUM(total_count) AS total, SUM(unique_visitors) AS uniq')
            ->groupBy('event_type')->orderByDesc('total')->get()->toArray();
        return array_map(fn ($r) => (array) $r, $by);
    }

    protected function detailed(Carbon $s, Carbon $e, int $limit): array
    {
        $rows = DB::table('ahg_statistics_daily as sd')
            ->whereBetween('sd.stat_date', [$s->toDateString(), $e->toDateString()])
            ->where('sd.object_type', 'information_object')
            ->whereNotNull('sd.object_id')
            ->selectRaw('sd.object_id, SUM(sd.total_count) AS views, SUM(sd.unique_visitors) AS uniq')
            ->groupBy('sd.object_id')
            ->orderByDesc('views')
            ->limit($limit)->get()->toArray();
        return array_map(fn ($r) => (array) $r, $rows);
    }

    protected function trends(Carbon $s, Carbon $e): array
    {
        $rows = DB::table('ahg_statistics_daily')
            ->whereBetween('stat_date', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('stat_date, SUM(total_count) AS total, SUM(unique_visitors) AS uniq')
            ->groupBy('stat_date')->orderBy('stat_date')->get()->toArray();
        return array_map(fn ($r) => (array) $r, $rows);
    }

    protected function format(array $rows, string $format): string
    {
        if ($format === 'json') return json_encode($rows, JSON_PRETTY_PRINT);
        if (empty($rows)) return "(empty)\n";
        $headers = array_keys($rows[0]);
        if ($format === 'csv') {
            $lines = [implode(',', $headers)];
            foreach ($rows as $r) $lines[] = implode(',', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $r));
            return implode("\n", $lines) . "\n";
        }
        $widths = array_map('strlen', $headers);
        foreach ($rows as $r) foreach ($r as $k => $v) $widths[array_search($k, $headers)] = max($widths[array_search($k, $headers)], strlen((string) $v));
        $line = function (array $vals) use ($widths) {
            $out = [];
            $i = 0; foreach ($vals as $v) $out[] = str_pad((string) $v, $widths[$i++]);
            return '  ' . implode('  ', $out);
        };
        $lines = [$line($headers)];
        foreach ($rows as $r) $lines[] = $line(array_values($r));
        return implode("\n", $lines) . "\n";
    }
}
