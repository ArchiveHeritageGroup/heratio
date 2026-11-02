<?php

namespace AhgCore\Commands;

use AhgCore\Services\CronSchedulerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
            // CronSchedulerService::runDueSchedules returns 2 result shapes:
            // (a) full row from runSingle() with id/slug/status/duration_ms/next_run
            // (b) skipped/deferred row with only status + reason
            // The skipped shape was crashing this loop with "Undefined array
            // key duration_ms" once schedule:run actually started firing
            // every minute (the cron infra fix in v1.52.41). Tolerate both.
            $status = $r['status'] ?? 'unknown';
            $icon = $status === 'success' ? '<info>OK</info>'
                  : ($status === 'skipped' || $status === 'deferred' ? '<comment>SKIP</comment>' : '<error>FAIL</error>');
            $label = $r['slug'] ?? $r['reason'] ?? '(unknown)';
            $duration = isset($r['duration_ms']) ? "({$r['duration_ms']}ms)" : '';
            $pending = $status === 'failed' && ! ($r['confirmed'] ?? true) ? ' - first failure, reported if the next run fails too' : '';
            $this->line(trim('['.now()->format('Y-m-d H:i:s')."] [{$icon}] {$label} {$duration}{$pending}"));
        }

        // A frequent job's first failure waits one run for confirmation, so a
        // momentary blip does not fail the runner (see CronSchedulerService::isFrequent).
        // It is still recorded as failed in cron_schedule and ahg_cron_run.
        // Health probes (llm-health, services-check, *-health) report on other
        // services; a service being down is their RESULT, recorded on their own
        // cron_schedule row, not a runner error. Counting them failed the runner
        // every night while the GPU nodes are off by policy (18:00-06:00), and that
        // put a red "ahg:cron-run failed" banner on every admin page (#1572).
        $failed = collect($results)->where('status', 'failed')
            ->filter(fn ($r) => ($r['confirmed'] ?? true) && ! self::isHealthProbe((string) ($r['slug'] ?? '')))
            ->count();
        if ($failed > 0) {
            $this->warn('['.now()->format('Y-m-d H:i:s')."] {$failed} job(s) failed.");

            return self::FAILURE;
        }

        $this->resolveOwnFailures();

        return self::SUCCESS;
    }

    public static function isHealthProbe(string $slug): bool
    {
        return $slug === 'services-check' || str_ends_with($slug, '-health');
    }

    /**
     * A clean run means the runner's earlier "Scheduled command [... ahg:cron-run]
     * failed" errors are over: resolve them, so a stale banner does not hang on.
     * Each failed job keeps its own record in cron_schedule and ahg_cron_run.
     */
    protected function resolveOwnFailures(): void
    {
        try {
            DB::table('ahg_error_log')
                ->whereNull('resolved_at')
                ->where('message', 'like', 'Scheduled command [%ahg:cron-run] failed%')
                ->update(['resolved_at' => now(), 'is_read' => 1]);
        } catch (\Throwable $e) {
            // Never let error-log housekeeping fail the runner.
        }
    }

    protected function runBySlug(CronSchedulerService $service, string $slug, bool $dryRun): int
    {
        $schedule = \Illuminate\Support\Facades\DB::table('cron_schedule')
            ->where('slug', $slug)
            ->first();

        if (! $schedule) {
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
        $this->line("[{$icon}] {$result['slug']} ({$result['duration_ms']}ms) - next run: {$result['next_run']}");

        return $result['status'] === 'success' ? self::SUCCESS : self::FAILURE;
    }
}
