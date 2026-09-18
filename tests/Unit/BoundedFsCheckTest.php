<?php

/**
 * BoundedFsCheckTest - the deadline in ahg:nas-watchdog's probe.
 *
 * The bug this guards against: the old probe checked the clock either side of
 * is_readable(), which does nothing, because the call has already blocked in
 * the kernel by the time control returns. A slow NFS mount therefore produced
 * a 91-second "5 second capped" probe, and - worse - an unanswered check was
 * left at false, so slow and absent reported identically as DOWN
 * (CH-000058, CH-000067, CH-000077).
 *
 * So the case that matters here is the timeout one: a process that will not
 * finish must be abandoned at the deadline and must report null, never false.
 *
 * Pure unit test - no app boot, no DB.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace Tests\Unit;

use AhgCore\Support\BoundedFsCheck;
use PHPUnit\Framework\TestCase;

class BoundedFsCheckTest extends TestCase
{
    public function test_it_reports_true_for_a_readable_directory(): void
    {
        $this->assertTrue(BoundedFsCheck::readable(sys_get_temp_dir(), 5));
        $this->assertTrue(BoundedFsCheck::isDir(sys_get_temp_dir(), 5));
    }

    public function test_it_reports_false_for_a_path_that_is_not_there(): void
    {
        $missing = sys_get_temp_dir() . '/ahg-bounded-fs-check-absent-' . bin2hex(random_bytes(6));

        // false, not null: the filesystem answered, and the answer was no.
        $this->assertFalse(BoundedFsCheck::isDir($missing, 5));
        $this->assertFalse(BoundedFsCheck::readable($missing, 5));
    }

    public function test_it_reports_a_file_is_not_a_directory(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ahg-bfc-');

        try {
            $this->assertTrue(BoundedFsCheck::readable($file, 5));
            $this->assertFalse(BoundedFsCheck::isDir($file, 5));
        } finally {
            @unlink($file);
        }
    }

    public function test_it_gives_up_at_the_deadline_instead_of_blocking(): void
    {
        $start = microtime(true);
        $result = BoundedFsCheck::run(['sleep', '30'], 1.0);
        $elapsed = microtime(true) - $start;

        // null, never 0 or 1: "no answer in time" is not an answer about the path.
        $this->assertNull($result, 'a process that outlives the deadline must report null');

        // The point of the whole class. Generous upper bound so a loaded CI box
        // does not flake, but 3s still fails the old behaviour, which waited 30s.
        $this->assertLessThan(3.0, $elapsed, 'the deadline was not honoured');
    }

    public function test_the_deadline_does_not_cut_short_a_process_that_answers(): void
    {
        $start = microtime(true);
        $result = BoundedFsCheck::run(['sleep', '0.2'], 5.0);

        $this->assertSame(0, $result);
        $this->assertLessThan(3.0, microtime(true) - $start);
    }

    public function test_a_missing_binary_is_unknown_rather_than_false(): void
    {
        $exit = BoundedFsCheck::run(['ahg-no-such-binary-' . bin2hex(random_bytes(4))], 5);

        // Whatever the platform reports, it must not be mistaken for a real
        // "no" about the mount: 0 and 1 are the only meaningful answers.
        $this->assertNotSame(0, $exit);
        $this->assertNotSame(1, $exit);
    }
}
