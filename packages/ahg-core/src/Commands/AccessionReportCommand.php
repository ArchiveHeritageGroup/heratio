<?php

/**
 * AccessionReportCommand — accession status / valuation summaries.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccessionReportCommand extends Command
{
    protected $signature = 'ahg:accession-report
        {--status : Status summary report}
        {--valuation : Valuation report}
        {--export-csv : Export as CSV}
        {--repository= : Filter by repository slug}
        {--date-from= : Start date filter}
        {--date-to= : End date filter}';

    protected $description = 'Accession reports — status, valuation, CSV export';

    public function handle(): int
    {
        if (! Schema::hasTable('accession')) { $this->warn('accession table missing.'); return self::SUCCESS; }

        $base = DB::table('accession');
        if ($from = $this->option('date-from')) $base->where('date', '>=', $from);
        if ($to = $this->option('date-to'))     $base->where('date', '<=', $to);

        if ($this->option('valuation')) return $this->valuation($base);
        return $this->status($base);
    }

    protected function status($base): int
    {
        $rows = (clone $base)
            ->leftJoin('term as t', 't.id', '=', 'accession.processing_status_id')
            ->selectRaw('COALESCE(t.code, "unset") as status, COUNT(*) AS n, MIN(accession.date) AS earliest, MAX(accession.date) AS latest')
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        if ($this->option('export-csv')) {
            $this->line('status,count,earliest,latest');
            foreach ($rows as $r) $this->line("{$r->status},{$r->n},{$r->earliest},{$r->latest}");
            return self::SUCCESS;
        }
        $this->info('=== accession status report ===');
        foreach ($rows as $r) $this->line(sprintf('  %-20s %5d   %s — %s', $r->status, $r->n, $r->earliest ?: 'n/a', $r->latest ?: 'n/a'));
        $this->info('total: ' . (clone $base)->count());
        return self::SUCCESS;
    }

    protected function valuation($base): int
    {
        if (! Schema::hasTable('accession_valuation_history')) {
            $this->warn('accession_valuation_history not present.');
            return self::SUCCESS;
        }
        $rows = DB::table('accession_valuation_history')
            ->selectRaw('YEAR(valuation_date) AS y, COUNT(*) AS n, SUM(value) AS total, AVG(value) AS avg')
            ->groupBy('y')->orderBy('y')->get();
        if ($this->option('export-csv')) {
            $this->line('year,count,total,avg');
            foreach ($rows as $r) $this->line("{$r->y},{$r->n}," . round((float) $r->total, 2) . ',' . round((float) $r->avg, 2));
            return self::SUCCESS;
        }
        $this->info('=== accession valuation by year ===');
        foreach ($rows as $r) $this->line(sprintf('  %s  count=%-5d total=%-12s avg=%s', $r->y, $r->n, number_format((float) $r->total, 2), number_format((float) $r->avg, 2)));
        return self::SUCCESS;
    }
}
