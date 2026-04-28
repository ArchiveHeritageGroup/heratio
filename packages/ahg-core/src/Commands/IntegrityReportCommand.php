<?php

/**
 * IntegrityReportCommand — fixity / verification rollups.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityReportCommand extends Command
{
    protected $signature = 'ahg:integrity-report
        {--summary : Show summary only}
        {--dead-letter : Show dead-letter queue}
        {--date-from= : Start date filter}
        {--date-to= : End date filter}
        {--repository-id= : Filter by repository}
        {--format=text : Output format (text, json, csv)}
        {--export-csv= : Export to CSV file path}
        {--auditor-pack= : Generate auditor pack to path}';

    protected $description = 'Generate integrity reports';

    public function handle(): int
    {
        if ($this->option('dead-letter')) return $this->deadLetter();
        if ($this->option('auditor-pack')) return $this->auditorPack((string) $this->option('auditor-pack'));

        $report = $this->summarise();
        $format = (string) $this->option('format');
        if ($export = $this->option('export-csv')) {
            $this->writeCsv($report, $export);
            $this->info("CSV -> {$export}");
            return self::SUCCESS;
        }
        if ($format === 'json') {
            $this->line(json_encode($report, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }
        $this->renderText($report);
        return self::SUCCESS;
    }

    protected function summarise(): array
    {
        $from = $this->option('date-from');
        $to = $this->option('date-to');

        $runs = [];
        if (Schema::hasTable('integrity_run')) {
            $q = DB::table('integrity_run')->selectRaw('status, COUNT(*) AS n, SUM(objects_passed) AS verified, SUM(objects_failed) AS failed');
            if ($from) $q->where('started_at', '>=', $from);
            if ($to)   $q->where('started_at', '<=', $to);
            $runs = $q->groupBy('status')->get()->keyBy('status')->toArray();
        }

        $fixity = [];
        if (Schema::hasTable('oais_fixity_check')) {
            $col = Schema::hasColumn('oais_fixity_check', 'result') ? 'result' : (Schema::hasColumn('oais_fixity_check', 'status') ? 'status' : null);
            $tcol = Schema::hasColumn('oais_fixity_check', 'checked_at') ? 'checked_at' : 'created_at';
            if ($col) {
                $q = DB::table('oais_fixity_check')->selectRaw("{$col} as k, COUNT(*) AS n");
                if ($from) $q->where($tcol, '>=', $from);
                if ($to)   $q->where($tcol, '<=', $to);
                $fixity = $q->groupBy('k')->pluck('n', 'k')->toArray();
            }
        }

        $alerts = Schema::hasTable('integrity_alert')
            ? DB::table('integrity_alert')->selectRaw('severity, COUNT(*) AS n')->groupBy('severity')->pluck('n', 'severity')->toArray()
            : [];

        return ['runs' => $runs, 'fixity_results' => $fixity, 'alerts' => $alerts];
    }

    protected function renderText(array $r): void
    {
        $this->info('=== integrity runs ===');
        foreach ($r['runs'] as $s => $row) $this->line(sprintf('  %-12s n=%-5d verified=%-7d failed=%d',
            $s, $row->n ?? 0, $row->verified ?? 0, $row->failed ?? 0));
        $this->info("\n=== fixity check results ===");
        foreach ($r['fixity_results'] as $k => $n) $this->line(sprintf('  %-12s %d', $k, $n));
        $this->info("\n=== alerts by severity ===");
        foreach ($r['alerts'] as $k => $n) $this->line(sprintf('  %-12s %d', $k, $n));
    }

    protected function writeCsv(array $r, string $path): void
    {
        $h = fopen($path, 'w');
        fputcsv($h, ['section', 'key', 'value']);
        foreach (['runs' => 'integrity_runs', 'fixity_results' => 'fixity', 'alerts' => 'alerts'] as $k => $label) {
            foreach ($r[$k] as $kk => $v) fputcsv($h, [$label, $kk, is_object($v) ? json_encode($v) : $v]);
        }
        fclose($h);
    }

    protected function deadLetter(): int
    {
        if (! Schema::hasTable('integrity_dead_letter')) { $this->warn('integrity_dead_letter not present.'); return self::SUCCESS; }
        $rows = DB::table('integrity_dead_letter')->orderByDesc('id')->limit(50)->get();
        $this->info("=== dead-letter ({$rows->count()}) ===");
        foreach ($rows as $r) $this->line(sprintf('  #%-6d %s — %s', $r->id, $r->failed_at ?? '', mb_strimwidth((string) ($r->reason ?? ''), 0, 80, '..')));
        return self::SUCCESS;
    }

    protected function auditorPack(string $out): int
    {
        if (! is_dir(dirname($out))) @mkdir(dirname($out), 0775, true);
        $report = $this->summarise();
        $report['ledger_count'] = Schema::hasTable('integrity_ledger') ? DB::table('integrity_ledger')->count() : 0;
        $report['legal_holds_active'] = Schema::hasTable('integrity_legal_hold')
            ? DB::table('integrity_legal_hold')->where('status', 'active')->count() : 0;
        $report['generated_at'] = now()->toIso8601String();
        file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT));
        $this->info("auditor pack -> {$out}");
        return self::SUCCESS;
    }
}
