<?php

namespace AhgCore\Commands;

use AhgCore\Services\CronSchedulerService;
use Illuminate\Console\Command;

class CronRunCommand extends Command
{
    protected $signature = 'ahg:cron-run
        {--slug= : Run a specific job by slug}
        {--dry-run : List due jobs without running them}';

    protected $description = 'Run due cron schedules from the database';

    public function handle(CronSchedulerService $service): int
    {
        $slug = $this->option('slug');
        $dryRun = $this->option('dry-run');

        if ($slug) {
            return $this->runBySlug($service, $slug, $dryRun);
        }

        $due = $service->getDueSchedules();

        if ($due->isEmpty()) {
            $this->info('No schedules due.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Due schedules ({$due->count()}):");
            $rows = $due->map(fn ($s) => [$s->slug, $s->artisan_command, $s->cron_expression, $s->next_run_at]);
            $this->table(['Slug', 'Command', 'Cron', 'Next Run'], $rows->toArray());
            return self::SUCCESS;
        }

        $this->info("Running {$due->count()} due schedule(s)...");
        $results = $service->runDueSchedules();

        foreach ($results as $r) {
            $icon = $r['status'] === 'success' ? '<info>OK</info>' : '<error>FAIL</error>';
            $this->line("  [{$icon}] {$r['slug']} ({$r['duration_ms']}ms)");
        }

        $failed = collect($results)->where('status', 'failed')->count();
        if ($failed > 0) {
            $this->warn("{$failed} job(s) failed.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function runBySlug(CronSchedulerService $service, string $slug, bool $dryRun): int
    {
        $schedule = \Illuminate\Support\Facades\DB::table('cron_schedule')
            ->where('slug', $slug)
            ->first();

        if (!$schedule) {
            $this->error("Schedule not found: {$slug}");
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Would run: {$schedule->artisan_command} (cron: {$schedule->cron_expression})");
            return self::SUCCESS;
        }

        $this->info("Running: {$schedule->slug} ({$schedule->artisan_command})");
        $result = $service->runSingle($schedule);

        $icon = $result['status'] === 'success' ? 'OK' : 'FAIL';
        $this->line("[{$icon}] {$result['slug']} ({$result['duration_ms']}ms) — next run: {$result['next_run']}");

        return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
