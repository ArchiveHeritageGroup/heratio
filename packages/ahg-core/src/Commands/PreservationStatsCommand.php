<?php

namespace AhgCore\Commands;

use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;

class PreservationStatsCommand extends Command
{
    protected $signature = 'ahg:preservation-stats
        {--days=30 : Daily stats window}
        {--format=table : Output (table or json)}';

    protected $description = 'Preservation statistics — overall counts + daily fixity/migration trend';

    public function handle(PreservationService $svc): int
    {
        $stats = $svc->getStatistics();
        $daily = $svc->getDailyStats((int) $this->option('days'));

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['summary' => $stats, 'daily' => $daily], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== summary ===');
        foreach ((array) $stats as $k => $v) $this->line(sprintf("  %-30s %s", $k, is_scalar($v) ? $v : json_encode($v)));

        $this->info("\n=== daily (last " . $this->option('days') . " days) ===");
        foreach ($daily as $row) {
            $this->line(sprintf("  %s  fixity=%-5d migrations=%-5d events=%d",
                $row->day ?? '-', $row->fixity_checks ?? 0, $row->migrations ?? 0, $row->events ?? 0));
        }
        return self::SUCCESS;
    }
}
