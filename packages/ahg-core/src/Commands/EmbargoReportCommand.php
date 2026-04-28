<?php

namespace AhgCore\Commands;

use AhgExtendedRights\Services\EmbargoService;
use Illuminate\Console\Command;

class EmbargoReportCommand extends Command
{
    protected $signature = 'ahg:embargo-report
        {--expiring=30 : Window for "soon expiring" (days)}
        {--format=table : Output (table or json)}';

    protected $description = 'Embargo status report — counts + soon-expiring list';

    public function handle(EmbargoService $svc): int
    {
        $active = $svc->getActiveEmbargoes();
        $expiring = $svc->getExpiringEmbargoes((int) $this->option('expiring'));

        if ($this->option('format') === 'json') {
            $this->line(json_encode([
                'active_count' => $active->count(),
                'expiring_window_days' => (int) $this->option('expiring'),
                'expiring' => $expiring,
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info("=== embargo summary ===");
        $this->line("  active total:        {$active->count()}");
        $this->line("  expiring in {$this->option('expiring')}d:    {$expiring->count()}");

        $this->info("\n=== expiring soon (top 30) ===");
        foreach ($expiring->take(30) as $e) {
            $this->line(sprintf("  id=%-5d obj=%-7d type=%-15s end=%s", $e->id, $e->object_id, $e->embargo_type, $e->end_date ?? '-'));
        }
        return self::SUCCESS;
    }
}
