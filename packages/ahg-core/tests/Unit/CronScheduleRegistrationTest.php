<?php

/**
 * CronScheduleRegistrationTest - CallHub CH-000111.
 *
 * The managed cron_schedule entries are dispatched by ahg:cron-run, which
 * routes/console.php schedules every minute. They were ALSO registered as
 * native Schedule events, so every managed job ran twice. This pins the
 * native registration down to the missed-run detector alone.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace Tests\Unit;

use AhgCore\Services\CronSchedulerService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CronScheduleRegistrationTest extends TestCase
{
    public function test_only_the_missed_run_detector_is_registered_natively(): void
    {
        $schedule = new Schedule;
        app(CronSchedulerService::class)->registerWithLaravelSchedule($schedule);

        $events = collect($schedule->events());

        $this->assertCount(1, $events);
        $this->assertStringContainsString('cron:check-missed-runs', (string) $events->first()->command);
    }

    /**
     * The live schedule must not name any command that the cron_schedule table
     * already dispatches - that is the double-run itself, whoever registered it.
     */
    public function test_no_registered_command_duplicates_an_enabled_cron_schedule_row(): void
    {
        $managed = DB::table('cron_schedule')
            ->where('is_enabled', 1)
            ->whereNotNull('artisan_command')
            ->pluck('artisan_command')
            ->map(fn ($c) => trim((string) $c))
            ->filter()
            ->all();

        $this->assertNotEmpty($managed, 'cron_schedule has no enabled rows - nothing to compare against.');

        $registered = collect(app(Schedule::class)->events())
            ->map(fn ($e) => trim(preg_replace('/^.*artisan.\s*/', '', (string) $e->command), " '"))
            ->all();

        $this->assertSame([], array_values(array_intersect($registered, $managed)));
    }
}
