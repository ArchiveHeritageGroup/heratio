<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueRetryCommand extends Command
{
    protected $signature = 'heratio:queue:retry
        {id : Job ID to retry, or "all" to retry all failed jobs}
        {--queue= : When using "all", limit to a specific queue}';

    protected $description = 'Retry failed jobs in the AHG queue';

    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id === 'all') {
            return $this->retryAll();
        }

        if (! is_numeric($id)) {
            $this->error('Job ID must be a number or "all".');

            return self::FAILURE;
        }

        return $this->retryJob((int) $id);
    }

    protected function retryJob(int $id): int
    {
        $job = DB::table('ahg_queue_job')->where('id', $id)->first();

        if (! $job) {
            $this->error("Job #{$id} not found.");

            return self::FAILURE;
        }

        if ($job->status !== 'failed') {
            $this->error("Job #{$id} has status '{$job->status}' — only failed jobs can be retried.");

            return self::FAILURE;
        }

        DB::table('ahg_queue_job')->where('id', $id)->update([
            'status' => 'pending',
            'attempt_count' => 0,
            'error_message' => null,
            'error_code' => null,
            'error_trace' => null,
            'reserved_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_ms' => null,
            'worker_id' => null,
            'available_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark the failed record as resolved
        DB::table('ahg_queue_failed')
            ->where('queue_job_id', $id)
            ->whereNull('resolved_at')
            ->update([
                'resolved_at' => now(),
                'resolution' => 'retried',
            ]);

        $this->info("Job #{$id} has been reset to pending.");

        return self::SUCCESS;
    }

    protected function retryAll(): int
    {
        $query = DB::table('ahg_queue_job')->where('status', 'failed');

        if ($queue = $this->option('queue')) {
            $query->where('queue', $queue);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No failed jobs to retry.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Retry {$count} failed job(s)?")) {
            return self::SUCCESS;
        }

        $query->update([
            'status' => 'pending',
            'attempt_count' => 0,
            'error_message' => null,
            'error_code' => null,
            'error_trace' => null,
            'reserved_at' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_ms' => null,
            'worker_id' => null,
            'available_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark all corresponding failed records as resolved
        $failedQuery = DB::table('ahg_queue_failed')->whereNull('resolved_at');
        if ($queue = $this->option('queue')) {
            $failedQuery->where('queue', $queue);
        }
        $failedQuery->update([
            'resolved_at' => now(),
            'resolution' => 'retried',
        ]);

        $this->info("Reset {$count} failed job(s) to pending.");

        return self::SUCCESS;
    }
}
