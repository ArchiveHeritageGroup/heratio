<?php

/**
 * PollArchivematicaCommand - `am:poll` scheduled poller for AM transfers.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

namespace AhgArchivematica\Commands;

use AhgArchivematica\Jobs\PollArchivematicaJobs;
use AhgArchivematica\Services\ArchivematicaDashboardClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs one poll sweep over every processing Heratio -> Archivematica transfer,
 * advancing each am_job by querying the Dashboard. Wire this into the Laravel
 * scheduler (e.g. everyFiveMinutes) so in-flight transfers converge to
 * complete/failed without manual intervention.
 *
 * Usage:
 *   php artisan am:poll
 */
class PollArchivematicaCommand extends Command
{
    protected $signature = 'am:poll';

    protected $description = 'Poll Archivematica for every in-flight (processing) Heratio -> AM transfer and advance its am_job.';

    public function handle(PollArchivematicaJobs $poller, ArchivematicaDashboardClient $client): int
    {
        if (! Schema::hasTable('am_job')) {
            $this->warn('am_job table not present - nothing to poll.');
            return self::SUCCESS;
        }

        $pending = DB::table('am_job')
            ->where('direction', 'to_am')
            ->where('status', 'processing')
            ->count();

        if ($pending === 0) {
            $this->info('No processing Archivematica transfers to poll.');
            return self::SUCCESS;
        }

        $this->info("Polling {$pending} in-flight Archivematica transfer(s) ...");

        // Run the sweep inline (cron context) rather than dispatching to a queue
        // so the command surfaces any fatal error to the operator directly.
        $poller->handle($client);

        // Report the resulting distribution for operator visibility.
        $summary = DB::table('am_job')
            ->where('direction', 'to_am')
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        foreach ($summary as $status => $n) {
            $this->line("  {$status}: {$n}");
        }

        $this->info('Poll sweep complete.');

        return self::SUCCESS;
    }
}
