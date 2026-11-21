<?php

/**
 * CronLongJobBackgroundTest - a long job must not hold up the runner.
 *
 * The nightly virus scan (about 50 minutes) ran inside the one sequential
 * ahg:cron-run process, so nothing else ran until it finished and the
 * missed-run check raised calls on 20-21 Nov 2026. Long jobs now start in
 * their own process.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Unit;

use AhgCore\Services\CronSchedulerService;
use Tests\TestCase;

class CronLongJobBackgroundTest extends TestCase
{
    public function test_long_jobs_are_recognised(): void
    {
        $svc = new CronSchedulerService();
        $this->assertTrue($svc->runsLong((object) ['duration_hint' => 'medium']));
        $this->assertTrue($svc->runsLong((object) ['duration_hint' => 'long']));
        $this->assertTrue($svc->runsLong((object) ['duration_hint' => 'short', 'last_run_duration_ms' => 3034043]), 'a measured long run counts even when hinted short');
        $this->assertFalse($svc->runsLong((object) ['duration_hint' => 'short', 'last_run_duration_ms' => 400]));
        $this->assertFalse($svc->runsLong((object) []));
    }
}
