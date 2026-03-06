<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QueueStatusCommand extends Command
{
    protected $signature = 'heratio:queue:status
        {--queue= : Filter by queue name}';

    protected $description = 'Show AHG queue statistics';

    public function handle(): int
    {
        $this->info('AHG Queue Status');
        $this->line('');

        // Overall status counts
        $stats = DB::table('ahg_queue_job')
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        if (empty($stats)) {
            $this->warn('No jobs found in the queue.');

            return self::SUCCESS;
        }

        $this->info('Overall Status:');
        $this->table(
            ['Status', 'Count'],
            collect($stats)->map(fn ($count, $status) => [$status, $count])->values()->toArray()
        );

        // Per-queue breakdown
        $queueFilter = $this->option('queue');
        $query = DB::table('ahg_queue_job')
            ->selectRaw('queue, status, COUNT(*) as count');

        if ($queueFilter) {
            $query->where('queue', $queueFilter);
        }

        $queues = $query->groupBy('queue', 'status')
            ->orderBy('queue')
            ->orderBy('status')
            ->get();

        if ($queues->isNotEmpty()) {
            $this->line('');
            $this->info('Per-Queue Breakdown:');
            $this->table(
                ['Queue', 'Status', 'Count'],
                $queues->map(fn ($row) => [$row->queue, $row->status, $row->count])->toArray()
            );
        }

        // Active workers
        $workers = DB::table('ahg_queue_job')
            ->where('status', 'processing')
            ->whereNotNull('worker_id')
            ->selectRaw('worker_id, COUNT(*) as active_jobs, MIN(started_at) as oldest_start')
            ->groupBy('worker_id')
            ->get();

        if ($workers->isNotEmpty()) {
            $this->line('');
            $this->info('Active Workers:');
            $this->table(
                ['Worker ID', 'Active Jobs', 'Oldest Start'],
                $workers->map(fn ($row) => [$row->worker_id, $row->active_jobs, $row->oldest_start])->toArray()
            );
        }

        // Batch summary
        $batches = DB::table('ahg_queue_batch')
            ->whereNull('completed_at')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($batches->isNotEmpty()) {
            $this->line('');
            $this->info('Active Batches:');
            $this->table(
                ['ID', 'Name', 'Completed', 'Failed', 'Total', 'Status'],
                $batches->map(fn ($b) => [
                    $b->id,
                    $b->name,
                    $b->completed_jobs,
                    $b->failed_jobs,
                    $b->total_jobs,
                    $b->status,
                ])->toArray()
            );
        }

        // Failed jobs count
        $failedCount = DB::table('ahg_queue_failed')
            ->whereNull('resolved_at')
            ->count();

        if ($failedCount > 0) {
            $this->line('');
            $this->warn("Unresolved failed jobs: {$failedCount}");
        }

        return self::SUCCESS;
    }
}
