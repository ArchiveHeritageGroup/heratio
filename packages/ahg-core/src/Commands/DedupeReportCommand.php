<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DedupeReportCommand extends Command
{
    protected $signature = 'ahg:dedupe-report
        {--format=table : Output format (table or json)}
        {--status= : Filter ahg_dedupe_scan by status}
        {--limit=20 : Max rows}';

    protected $description = 'Report most recent ahg_dedupe_scan runs and their duplicate counts';

    public function handle(): int
    {
        $q = DB::table('ahg_dedupe_scan')->orderByDesc('id')->limit(max(1, (int) $this->option('limit')));
        if ($s = $this->option('status')) $q->where('status', $s);
        $rows = $q->get();

        if ($this->option('format') === 'json') {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info("=== ahg_dedupe_scan recent runs ===");
        $this->line(sprintf("  %-5s %-12s %-12s %-7s %-7s  scope", 'id', 'status', 'started_at', 'total', 'dups'));
        foreach ($rows as $r) {
            $this->line(sprintf("  %-5d %-12s %-12s %-7d %-7d  %s",
                $r->id, $r->status, substr($r->started_at ?? '', 0, 10),
                $r->total_records ?? 0, $r->duplicates_found ?? 0,
                $r->scope ?? ''));
        }
        return self::SUCCESS;
    }
}
