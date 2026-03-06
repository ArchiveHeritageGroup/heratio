<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class JobWorkerCommand extends Command
{
    protected $signature = 'heratio:jobs:worker
        {--once : Process one job and exit}
        {--sleep=5 : Seconds to sleep when no jobs}';

    protected $description = "Process Heratio's built-in job queue";

    public function handle(): int
    {
        $once = $this->option('once');
        $sleep = (int) $this->option('sleep');
        $processed = 0;

        $this->info("Processing Heratio jobs...");

        while (true) {
            // Check for pending jobs (completed_at IS NULL and status is not error/completed)
            $pendingCount = DB::table('job')
                ->whereNull('completed_at')
                ->count();

            if ($pendingCount === 0) {
                if ($once) {
                    break;
                }
                sleep($sleep);

                continue;
            }

            $this->line("Found {$pendingCount} pending job(s). Launching worker...");

            // Bridge to Symfony job worker
            $cmd = 'php /usr/share/nginx/archive/symfony jobs:worker';

            if ($once) {
                // Worker processes one batch and exits
                passthru($cmd, $exitCode);

                if ($exitCode !== 0) {
                    $this->error("Job worker exited with code {$exitCode}.");

                    return self::FAILURE;
                }

                $processed++;

                break;
            }

            // Run continuously — worker will process pending jobs then exit
            passthru($cmd, $exitCode);

            if ($exitCode !== 0) {
                $this->warn("Job worker exited with code {$exitCode}. Retrying after sleep...");
            }

            $processed++;
            sleep($sleep);
        }

        $this->info("Job worker session complete. Ran {$processed} time(s).");

        return self::SUCCESS;
    }
}
