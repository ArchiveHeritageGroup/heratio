<?php

/**
 * IllRouteTimeoutsCommand - fail ILL requests over when a supplier stops answering (#1475).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\IllRoutingService;
use Illuminate\Console\Command;

class IllRouteTimeoutsCommand extends Command
{
    protected $signature = 'ahg:library-ill-route-timeouts {--dry-run : Report without changing or sending anything}';
    protected $description = 'Time out unanswered ILL supplier attempts and route to the next supplier.';

    public function handle(IllRoutingService $routing): int
    {
        $dryRun = (bool) $this->option('dry-run');
        if (!$dryRun && !$routing->autoRoutingEnabled()) {
            $this->info(IllRoutingService::SETTING_AUTO . ' is off; nothing to do.');

            return self::SUCCESS;
        }

        $s = $routing->sweepTimeouts($dryRun);
        $this->info(sprintf(
            '%stimed out=%d withdrawn=%d rerouted=%d retried=%d exhausted=%d accepted past deadline=%d',
            $dryRun ? '[dry run] ' : '', $s['timed_out'], $s['withdrawn'], $s['rerouted'], $s['retried'],
            $s['exhausted'], $s['overdue_accepted']
        ));

        return self::SUCCESS;
    }
}
