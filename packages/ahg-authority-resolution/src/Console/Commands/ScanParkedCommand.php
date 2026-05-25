<?php

/**
 * ScanParkedCommand - Console command for Heratio
 *
 * Task 7 background scan. Iterates every ahg_mention_park row and asks
 * ParkQueueService::scanForNewCandidates() to compare each mention's
 * current candidate set against a freshly-generated set. When the set has
 * changed since parking, ahg_mention_park.new_candidate_available is
 * flipped to 1 so the archivist sees a flag on /admin/authority-resolution/park.
 *
 * Usage:
 *   php artisan auth-res:scan-parked
 *   php artisan auth-res:scan-parked --dry-run     (count only, no writes)
 *
 * Wire via cron (or php artisan schedule:run) - daily is plenty.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Console\Commands;

use AhgAuthorityResolution\Services\ParkQueueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ScanParkedCommand extends Command
{
    protected $signature = 'auth-res:scan-parked
                            {--dry-run : Report counts only; do not flip new_candidate_available}';

    protected $description = 'Scan every parked mention; flag rows whose candidate set has changed since parking.';

    public function handle(ParkQueueService $parkQueue): int
    {
        $parkedTotal = (int) DB::table('ahg_mention_park')->count();
        if ($parkedTotal === 0) {
            $this->info('No parked mentions - nothing to scan.');

            return self::SUCCESS;
        }

        $this->info("Scanning {$parkedTotal} parked mention(s)...");

        if ((bool) $this->option('dry-run')) {
            $this->warn('--dry-run set: no DB writes will be made.');
            // A true dry-run is awkward because candidate generation persists.
            // We still report the table-level summary so the operator has a
            // sense of the workload size.
            $byEntity = DB::table('ahg_mention_park as p')
                ->join('ahg_mention as m', 'm.id', '=', 'p.mention_id')
                ->select('m.entity_type', DB::raw('COUNT(*) AS c'))
                ->groupBy('m.entity_type')
                ->pluck('c', 'entity_type')
                ->all();
            foreach ($byEntity as $type => $c) {
                $this->line(sprintf('  %-8s  %d', $type, $c));
            }

            return self::SUCCESS;
        }

        $newlyFlagged = $parkQueue->scanForNewCandidates();

        $this->info(sprintf(
            'Scan complete. %d mention(s) newly flagged as having a new candidate.',
            $newlyFlagged
        ));

        $stillFlagged = (int) DB::table('ahg_mention_park')->where('new_candidate_available', 1)->count();
        $this->line(sprintf('  Currently flagged total: %d', $stillFlagged));

        return self::SUCCESS;
    }
}
