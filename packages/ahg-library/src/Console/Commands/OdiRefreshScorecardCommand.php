<?php

/**
 * OdiRefreshScorecardCommand - recompute the ODI quality scorecard.
 *
 * Iterates every library collection (parent information_object) and recomputes
 * the four Open Discovery Initiative metrics plus the composite quality score
 * via OdiScorecardService, persisting the results into library_odi_collection.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\OdiScorecardService;
use Illuminate\Console\Command;

class OdiRefreshScorecardCommand extends Command
{
    protected $signature = 'ahg:library-odi-refresh';
    protected $description = 'Recompute the ODI (Open Discovery Initiative) quality scorecard for every library collection.';

    public function handle(OdiScorecardService $service): int
    {
        $this->info('Recomputing ODI scorecards...');

        $written = $service->refreshAll();

        if ($written === 0) {
            $this->warn('No collections found (or library_odi_collection table is missing).');
            return self::SUCCESS;
        }

        $this->line("ODI scorecards refreshed for {$written} collection(s).");

        return self::SUCCESS;
    }
}
