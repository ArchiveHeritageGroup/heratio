<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueCleanupCommand extends Command
{
    protected $signature = 'heratio:queue:cleanup
        {--days=30 : Remove completed/failed jobs older than N days}
        {--queue= : Limit cleanup to a specific queue}
        {--include-logs : Also remove associated log entries}';

    protected $description = 'Remove old completed and failed jobs from the AHG queue';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $queue = $this->option('queue');
        $includeLogs = $this->option('include-logs');
        $cutoff = now()->subDays($days);

        $this->info("Cleaning up jobs older than {$days} day(s) (before {$cutoff})...");

        // Find job IDs to remove (for log cleanup)
        $jobQuery = DB::table('ahg_queue_job')
            ->whereIn('status', ['completed', 'failed'])
            ->where('updated_at', '<', $cutoff);

        if ($queue) {
            $jobQuery->where('queue', $queue);
        }

        $jobIds = $jobQuery->pluck('id')->toArray();

        if (empty($jobIds)) {
            $this->info('No jobs to clean up.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Remove " . count($jobIds) . " completed/failed job(s)?")) {
            return self::SUCCESS;
        }

        // Process in chunks to avoid memory issues
        $chunks = array_chunk($jobIds, 1000);
        $deletedJobs = 0;
        $deletedFailed = 0;
        $deletedLogs = 0;

        foreach ($chunks as $chunk) {
            // Remove failed records
            $deletedFailed += DB::table('ahg_queue_failed')
                ->whereIn('queue_job_id', $chunk)
                ->delete();

            // Remove log entries if requested
            if ($includeLogs) {
                $deletedLogs += DB::table('ahg_queue_log')
                    ->whereIn('queue_job_id', $chunk)
                    ->delete();
            }

            // Remove the jobs
            $deletedJobs += DB::table('ahg_queue_job')
                ->whereIn('id', $chunk)
                ->delete();
        }

        $this->info("Removed {$deletedJobs} job(s), {$deletedFailed} failed record(s).");

        if ($includeLogs) {
            $this->info("Removed {$deletedLogs} log record(s).");
        }

        // Clean up completed batches
        $deletedBatches = DB::table('ahg_queue_batch')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<', $cutoff)
            ->delete();

        if ($deletedBatches > 0) {
            $this->info("Removed {$deletedBatches} completed batch(es).");
        }

        return self::SUCCESS;
    }
}
