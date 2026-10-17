<?php

/**
 * CronMissedAlertEntityIdTest - every missed-run alert for one outage carries the
 * same entityId (a UUID), so the workbench files one CallHub call per outage
 * instead of one per check (484 calls for one stalled job, Oct 2026); a separate
 * outage gets a new id.
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

namespace Tests\Feature;

use AhgCore\Commands\CheckMissedCronRunsCommand;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CronMissedAlertEntityIdTest extends TestCase
{
    private string $inbox;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inbox = sys_get_temp_dir().'/cron-miss-'.uniqid();
        mkdir($this->inbox);
        config(['cron-monitoring.inbox_path' => $this->inbox]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->inbox.'/*') ?: []);
        @rmdir($this->inbox);
        parent::tearDown();
    }

    /** Drop one alert and return its entityId. */
    private function drop(string $expected, int $gap): string
    {
        $m = new \ReflectionMethod(CheckMissedCronRunsCommand::class, 'dropWorkbenchNotification');
        $m->setAccessible(true);
        $before = glob($this->inbox.'/*.json') ?: [];
        $m->invoke(app(CheckMissedCronRunsCommand::class), 'ahg:search-update', Carbon::parse($expected), $gap);
        $file = array_values(array_diff(glob($this->inbox.'/*.json') ?: [], $before))[0];

        return json_decode((string) file_get_contents($file), true)['entityId'];
    }

    public function test_alerts_for_one_outage_share_a_uuid_and_a_new_outage_gets_another(): void
    {
        // Last success 2026-10-16 02:30; checked at two later expected times.
        $a = $this->drop('2026-10-17 16:30:00', 136800);
        $b = $this->drop('2026-10-17 16:35:00', 137100);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $a, 'a version 5 UUID');
        $this->assertSame($a, $b, 'same outage, same call');

        // A later outage after the job recovered (last success 2026-10-20 08:00).
        $this->assertNotSame($a, $this->drop('2026-10-20 10:00:00', 7200));
    }
}
