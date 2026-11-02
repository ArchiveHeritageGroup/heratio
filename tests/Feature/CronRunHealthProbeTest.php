<?php

/**
 * CronRunHealthProbeTest - a health probe finding a service down (the GPU
 * nodes are off 18:00-06:00 by policy) does not fail ahg:cron-run, and a clean
 * run resolves the runner's own earlier "failed" errors, so the admin banner
 * does not hang on (heratio#1572).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Commands\CronRunCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CronRunHealthProbeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_health_probes_are_recognised(): void
    {
        $this->assertTrue(CronRunCommand::isHealthProbe('llm-health'));
        $this->assertTrue(CronRunCommand::isHealthProbe('services-check'));
        $this->assertTrue(CronRunCommand::isHealthProbe('triposr-health'));
        $this->assertFalse(CronRunCommand::isHealthProbe('search-update'));
        $this->assertFalse(CronRunCommand::isHealthProbe(''));
    }

    public function test_a_clean_run_resolves_only_the_runners_own_failures(): void
    {
        $row = fn (string $msg) => DB::table('ahg_error_log')->insertGetId([
            'level' => 'error', 'status_code' => 500, 'message' => $msg, 'is_read' => 0, 'created_at' => now(),
        ]);
        $runner = $row("Scheduled command ['/usr/bin/php8.3' 'artisan' ahg:cron-run] failed with exit code [1].");
        $other = $row('Something else broke');

        $m = new \ReflectionMethod(CronRunCommand::class, 'resolveOwnFailures');
        $m->invoke(app(CronRunCommand::class));

        $this->assertNotNull(DB::table('ahg_error_log')->where('id', $runner)->value('resolved_at'));
        $this->assertNull(DB::table('ahg_error_log')->where('id', $other)->value('resolved_at'));
    }
}
