<?php

namespace AhgCore\Commands;

use AhgNmmz\Services\NmmzService;
use Illuminate\Console\Command;

class NmmzReportCommand extends Command
{
    protected $signature = 'ahg:nmmz-report
        {--format=table : Output (table or json)}';

    protected $description = 'Zimbabwe NMMZ monuments report — dashboard stats + compliance status';

    public function handle(NmmzService $svc): int
    {
        $stats = $svc->getDashboardStats();
        $compliance = $svc->getComplianceStatus();

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['stats' => $stats, 'compliance' => $compliance], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== NMMZ dashboard ===');
        foreach ($stats as $k => $v) $this->line(sprintf("  %-30s %s", $k, is_scalar($v) ? $v : json_encode($v)));

        $this->info("\n=== compliance status ===");
        foreach ($compliance as $k => $v) $this->line(sprintf("  %-30s %s", $k, is_scalar($v) ? $v : json_encode($v)));
        return self::SUCCESS;
    }
}
