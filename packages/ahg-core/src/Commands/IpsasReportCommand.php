<?php

namespace AhgCore\Commands;

use AhgIpsas\Services\IpsasService;
use Illuminate\Console\Command;

class IpsasReportCommand extends Command
{
    protected $signature = 'ahg:ipsas-report
        {--format=table : Output (table or json)}';

    protected $description = 'IPSAS heritage asset report — dashboard stats + compliance status';

    public function handle(IpsasService $svc): int
    {
        $stats = $svc->getDashboardStats();
        $compliance = $svc->getComplianceStatus();

        if ($this->option('format') === 'json') {
            $this->line(json_encode(['stats' => $stats, 'compliance' => $compliance], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('=== IPSAS dashboard ===');
        foreach ($stats as $k => $v) $this->line(sprintf("  %-30s %s", $k, is_scalar($v) ? $v : json_encode($v)));

        $this->info("\n=== compliance status ===");
        foreach ($compliance as $k => $v) $this->line(sprintf("  %-30s %s", $k, is_scalar($v) ? $v : json_encode($v)));
        return self::SUCCESS;
    }
}
