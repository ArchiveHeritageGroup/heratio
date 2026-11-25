<?php

/**
 * CronRunnerCapTest - the jobs_max_concurrent cap must not starve frequent jobs.
 *
 * On installs left at the default cap of 2, short jobs (run one after another
 * by the runner itself) were counted against the cap, so each tick ran two jobs
 * and deferred the rest; services-check got a turn twice an hour and the
 * missed-run check raised calls every five minutes (sasa, 24 Nov 2026,
 * CH-001049..1056). Only background launches run concurrently, so only they
 * count, and deferred jobs go first on the next tick.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\CronSchedulerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CronRunnerCapTest extends TestCase
{
    use DatabaseTransactions;

    /** Records what would run instead of running it, with the cap fixed at 2. */
    private function runner(): CronSchedulerService
    {
        return new class extends CronSchedulerService
        {
            public array $ran = [];

            public function isJobsEnabled(): bool
            {
                return true;
            }

            public function getJobsSetting(string $key, $default = null)
            {
                return $key === 'jobs_max_concurrent' ? 2 : $default;
            }

            public function runSingle(object $schedule): array
            {
                $this->ran[] = $schedule->slug;
                $this->advance($schedule);

                return ['slug' => $schedule->slug, 'status' => 'success'];
            }

            protected function launchInBackground(object $schedule): array
            {
                $this->ran[] = $schedule->slug;
                $this->advance($schedule);

                return ['slug' => $schedule->slug, 'status' => 'started'];
            }

            private function advance(object $schedule): void
            {
                DB::table('cron_schedule')->where('id', $schedule->id)
                    ->update(['next_run_at' => Carbon::now()->addMinutes(5), 'last_run_status' => 'success']);
            }
        };
    }

    private function job(string $slug, string $hint, int $sort, int $overdueMinutes): void
    {
        DB::table('cron_schedule')->insert([
            'slug' => $slug, 'name' => $slug, 'category' => 'Test', 'artisan_command' => 'list',
            'cron_expression' => '*/5 * * * *', 'is_enabled' => 1, 'duration_hint' => $hint,
            'sort_order' => $sort, 'next_run_at' => Carbon::now()->subMinutes($overdueMinutes),
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ]);
    }

    private function onlyTestJobs(): void
    {
        DB::table('cron_schedule')->update(['is_enabled' => 0]);
    }

    public function test_short_jobs_do_not_count_against_the_cap(): void
    {
        $this->onlyTestJobs();
        for ($i = 1; $i <= 15; $i++) {
            $this->job("zz-short-$i", 'short', $i, 1);
        }

        $runner = $this->runner();
        $runner->runDueSchedules();

        $this->assertCount(15, $runner->ran, 'every due short job runs in the same tick');
    }

    public function test_a_deferred_long_job_goes_first_on_the_next_tick(): void
    {
        $this->onlyTestJobs();
        // Low sort_order but only just due, against one that is long overdue.
        $this->job('zz-long-a', 'long', 1, 1);
        $this->job('zz-long-b', 'long', 2, 1);
        $this->job('zz-long-starved', 'long', 99, 30);

        $runner = $this->runner();
        $runner->runDueSchedules();
        $this->assertSame(['zz-long-starved', 'zz-long-a'], $runner->ran, 'most overdue first, two launches per tick');

        $runner->ran = [];
        $runner->runDueSchedules();
        $this->assertSame(['zz-long-b'], $runner->ran, 'the deferred job runs on the next tick');
    }
}
