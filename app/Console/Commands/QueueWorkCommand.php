<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueWorkCommand extends Command
{
    protected $signature = 'heratio:queue:work
        {--queue=default : Queue name to process}
        {--once : Process one job and exit}
        {--max-jobs=0 : Max jobs to process (0=unlimited)}
        {--sleep=3 : Seconds to sleep when no jobs}
        {--timeout=60 : Job timeout in seconds}';

    protected $description = 'Process jobs from the AHG queue';

    public function handle(): int
    {
        $queue = $this->option('queue');
        $once = $this->option('once');
        $maxJobs = (int) $this->option('max-jobs');
        $sleep = (int) $this->option('sleep');
        $processed = 0;

        $this->info("Processing queue: {$queue}");

        while (true) {
            $job = DB::table('ahg_queue_job')
                ->where('queue', $queue)
                ->where('status', 'pending')
                ->where('available_at', '<=', now())
                ->orderBy('priority', 'desc')
                ->orderBy('available_at')
                ->first();

            if (! $job) {
                if ($once || ($maxJobs > 0 && $processed >= $maxJobs)) {
                    break;
                }
                sleep($sleep);

                continue;
            }

            // Reserve the job atomically
            $claimed = DB::table('ahg_queue_job')
                ->where('id', $job->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'processing',
                    'reserved_at' => now(),
                    'started_at' => now(),
                    'worker_id' => gethostname() . ':' . getmypid(),
                    'attempt_count' => $job->attempt_count + 1,
                ]);

            // Another worker may have claimed it
            if ($claimed === 0) {
                continue;
            }

            $this->line("Processing job #{$job->id}: {$job->job_type}");
            $startTime = microtime(true);

            try {
                $payload = json_decode($job->payload, true) ?? [];
                $this->executeJob($job->job_type, $payload);

                $elapsed = (int) ((microtime(true) - $startTime) * 1000);
                DB::table('ahg_queue_job')->where('id', $job->id)->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'processing_time_ms' => $elapsed,
                ]);

                $this->info("  Completed in {$elapsed}ms");

                // Update batch counters if applicable
                if ($job->batch_id) {
                    DB::table('ahg_queue_batch')
                        ->where('id', $job->batch_id)
                        ->increment('completed_jobs');
                }
            } catch (\Throwable $e) {
                $elapsed = (int) ((microtime(true) - $startTime) * 1000);
                $maxReached = $job->attempt_count + 1 >= $job->max_attempts;

                DB::table('ahg_queue_job')->where('id', $job->id)->update([
                    'status' => $maxReached ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                    'error_trace' => substr($e->getTraceAsString(), 0, 5000),
                    'processing_time_ms' => $elapsed,
                    'reserved_at' => null,
                    'started_at' => $maxReached ? $job->started_at : null,
                    'available_at' => $maxReached ? $job->available_at : now()->addSeconds(
                        $this->calculateBackoff($job->backoff_strategy, $job->attempt_count + 1, $job->delay_seconds)
                    ),
                ]);

                $this->error("  Failed: {$e->getMessage()}");

                if ($maxReached) {
                    DB::table('ahg_queue_failed')->insert([
                        'queue_job_id' => $job->id,
                        'queue' => $job->queue,
                        'job_type' => $job->job_type,
                        'payload' => $job->payload,
                        'error_message' => $e->getMessage(),
                        'error_trace' => substr($e->getTraceAsString(), 0, 5000),
                        'failed_at' => now(),
                    ]);

                    // Update batch failure counter
                    if ($job->batch_id) {
                        DB::table('ahg_queue_batch')
                            ->where('id', $job->batch_id)
                            ->increment('failed_jobs');
                    }
                }

                // Log the error
                DB::table('ahg_queue_log')->insert([
                    'queue_job_id' => $job->id,
                    'level' => 'error',
                    'message' => $e->getMessage(),
                    'context' => json_encode([
                        'attempt' => $job->attempt_count + 1,
                        'max_attempts' => $job->max_attempts,
                        'elapsed_ms' => $elapsed,
                    ]),
                    'created_at' => now(),
                ]);
            }

            $processed++;

            if ($once || ($maxJobs > 0 && $processed >= $maxJobs)) {
                break;
            }
        }

        $this->info("Processed {$processed} job(s).");

        return self::SUCCESS;
    }

    protected function executeJob(string $jobType, array $payload): void
    {
        // Legacy job types — should be processed by the Symfony job worker
        if (str_starts_with($jobType, 'ar') || str_starts_with($jobType, 'Qubit')) {
            throw new \RuntimeException(
                "Legacy job type '{$jobType}' should be processed by the Symfony job worker, not the Heratio queue worker."
            );
        }

        // CLI task bridge: payload contains a 'command' key with Symfony CLI task
        if (isset($payload['command'])) {
            $cmd = 'php /usr/share/nginx/archive/symfony ' . escapeshellarg($payload['command']);

            if (isset($payload['arguments']) && is_array($payload['arguments'])) {
                foreach ($payload['arguments'] as $key => $value) {
                    $cmd .= ' --' . escapeshellarg($key) . '=' . escapeshellarg($value);
                }
            }

            passthru($cmd, $exitCode);

            if ($exitCode !== 0) {
                throw new \RuntimeException("Command failed with exit code {$exitCode}");
            }

            return;
        }

        // Laravel job class bridge: payload contains a 'class' key
        if (isset($payload['class']) && class_exists($payload['class'])) {
            $instance = app()->make($payload['class']);

            if (method_exists($instance, 'handle')) {
                $instance->handle($payload['data'] ?? []);

                return;
            }
        }

        throw new \RuntimeException("Unknown job type: {$jobType}");
    }

    protected function calculateBackoff(string $strategy, int $attempt, int $baseDelay): int
    {
        return match ($strategy) {
            'exponential' => (int) ($baseDelay * pow(2, $attempt - 1)),
            'linear' => $baseDelay * $attempt,
            default => $baseDelay,
        };
    }
}
