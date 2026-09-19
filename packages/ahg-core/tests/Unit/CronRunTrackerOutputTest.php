<?php

/**
 * CronRunTrackerOutputTest - CallHub CH-000096 / CH-000097.
 *
 * truncateOutput() used byte substr(), which split the 3-byte progress-bar
 * glyphs (U+2591-2593) and left an orphan byte that MySQL utf8mb4 rejects
 * with 1366, so finished_at was never written. supportsDistributedLocks()
 * used a driver-name allowlist that left out 'file', so onOneServer() was
 * skipped on every install.
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

use AhgCore\Services\CronRunTrackerService;
use ReflectionMethod;
use Tests\TestCase;

class CronRunTrackerOutputTest extends TestCase
{
    private function truncate(string $output): ?string
    {
        $m = new ReflectionMethod(CronRunTrackerService::class, 'truncateOutput');

        return $m->invoke(new CronRunTrackerService, $output);
    }

    public function test_long_progress_bar_output_stays_valid_utf8(): void
    {
        // Offsets 0..2 so one of them lands mid-glyph under a byte cut.
        foreach (['', 'a', 'ab'] as $pad) {
            $out = $this->truncate($pad.str_repeat("[\u{2593}\u{2593}\u{2591}\u{2591}]  38%  id=903650\n", 400));

            $this->assertTrue(mb_check_encoding($out, 'UTF-8'));
            $this->assertStringStartsWith('... (truncated) ...', $out);
        }
    }

    public function test_invalid_bytes_from_the_command_are_scrubbed(): void
    {
        $this->assertTrue(mb_check_encoding($this->truncate("ok \x91\xE2 done"), 'UTF-8'));
    }

    public function test_file_cache_store_supports_locks(): void
    {
        config(['cache.default' => 'file']);

        $this->assertTrue((new CronRunTrackerService)->supportsDistributedLocks());
    }
}
