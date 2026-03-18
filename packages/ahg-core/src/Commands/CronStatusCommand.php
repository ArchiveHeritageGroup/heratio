<?php

namespace AhgCore\Commands;

use AhgCore\Services\CronSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CronStatusCommand extends Command
{
    protected $signature = 'ahg:cron-status
        {--category= : Filter by category}
        {--failed : Show only failed jobs}';

    protected $description = 'Display cron schedule status dashboard';

    public function handle(CronSchedulerService $service): int
    {
        $stats = $service->getStats();
        $this->info("Cron Scheduler: {$stats->total} total | {$stats->enabled} enabled | {$stats->disabled} disabled | {$stats->failed_24h} failed (24h) | {$stats->running} running");
        $this->newLine();

        $query = DB::table('cron_schedule')->orderBy('category')->orderBy('sort_order');

        if ($category = $this->option('category')) {
            $query->where('category', 'like', "%{$category}%");
        }

        if ($this->option('failed')) {
            $query->where('last_run_status', 'failed');
        }

        $schedules = $query->get();

        if ($schedules->isEmpty()) {
            $this->info('No schedules found matching the filter.');
            return self::SUCCESS;
        }

        $rows = $schedules->map(function ($s) {
            $status = match ($s->last_run_status) {
                'success' => '<info>OK</info>',
                'failed' => '<error>FAIL</error>',
                'running' => '<comment>RUN</comment>',
                default => '<fg=gray>—</>',
            };

            $enabled = $s->is_enabled ? '<info>Yes</info>' : '<fg=gray>No</>';

            $duration = $s->last_run_duration_ms !== null
                ? ($s->last_run_duration_ms >= 1000
                    ? round($s->last_run_duration_ms / 1000, 1) . 's'
                    : $s->last_run_duration_ms . 'ms')
                : '—';

            return [
                $s->category,
                $s->slug,
                $enabled,
                $s->cron_expression,
                $status,
                $s->last_run_at ?? '—',
                $duration,
                $s->next_run_at ?? '—',
                $s->total_runs,
                $s->total_failures,
            ];
        });

        $this->table(
            ['Category', 'Slug', 'Enabled', 'Cron', 'Status', 'Last Run', 'Duration', 'Next Run', 'Runs', 'Fails'],
            $rows->toArray()
        );

        return self::SUCCESS;
    }
}
