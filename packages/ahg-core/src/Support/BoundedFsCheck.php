<?php

/**
 * BoundedFsCheck - filesystem probes that cannot outlive their deadline.
 *
 * PHP's own stat calls (is_dir, is_readable, file_exists) block inside the
 * kernel for as long as the filesystem takes to answer. On a slow or hung NFS
 * mount that is seconds to minutes, and no amount of checking microtime()
 * around the call can shorten it - by the time control comes back, the time is
 * already spent. That is how ahg:nas-watchdog recorded a 91,552ms probe
 * against a 5-second "cap" (CH-000077).
 *
 * The only way to bound a blocking stat is to put it in another process and
 * stop waiting. Each check runs /usr/bin/test in a child; the parent polls and
 * walks away at the deadline.
 *
 * Tri-state on purpose. null means "no answer in time", which is NOT the same
 * as false ("the filesystem answered, and the answer is no"). Collapsing those
 * two is what made a slow-but-alive NAS report as DOWN.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgCore\Support;

final class BoundedFsCheck
{
    /** Poll interval while waiting for the child, in microseconds. */
    private const POLL_US = 20000;

    /** Never allow a caller to pass a deadline so short the fork cannot finish. */
    private const MIN_SECONDS = 0.1;

    /**
     * Is $path readable? true / false / null when it did not answer in time.
     */
    public static function readable(string $path, float $seconds): ?bool
    {
        return self::toBool(self::run(['test', '-r', $path], $seconds));
    }

    /**
     * Is $path a directory? true / false / null when it did not answer in time.
     */
    public static function isDir(string $path, float $seconds): ?bool
    {
        return self::toBool(self::run(['test', '-d', $path], $seconds));
    }

    /**
     * Run $argv and return its exit code, or null if it outlived $seconds.
     *
     * Exposed (rather than private) so the behaviour that matters - that the
     * deadline is honoured against a process that refuses to finish - can be
     * tested with `sleep` instead of requiring a hung NFS mount to hand.
     *
     * @param  list<string>  $argv
     */
    public static function run(array $argv, float $seconds): ?int
    {
        $deadline = microtime(true) + max(self::MIN_SECONDS, $seconds);

        // Array form of proc_open execs directly, with no shell to quote for.
        // All three streams go to /dev/null: these checks answer with an exit
        // code, and a full pipe buffer would be one more way to block.
        $null = ['file', '/dev/null', 'r'];
        $sink = ['file', '/dev/null', 'w'];
        $proc = @proc_open($argv, [0 => $null, 1 => $sink, 2 => $sink], $pipes);

        if (! is_resource($proc)) {
            return null;
        }

        while (true) {
            $status = @proc_get_status($proc);

            if ($status === false) {
                return null;
            }

            if (! $status['running']) {
                // exitcode is only valid on the first status read after exit,
                // so take it here rather than from proc_close's return.
                $code = (int) $status['exitcode'];
                @proc_close($proc);

                return $code;
            }

            if (microtime(true) >= $deadline) {
                @proc_terminate($proc, 9);

                // Deliberately NOT proc_close: it waits for the child to be
                // reaped, and a process stuck in an uninterruptible NFS syscall
                // does not die even on SIGKILL. Waiting here would reintroduce
                // exactly the unbounded block this class exists to prevent. The
                // orphan is reparented to init and reaped when the mount frees,
                // and this command is a short-lived CLI process anyway.
                return null;
            }

            usleep(self::POLL_US);
        }
    }

    /**
     * /usr/bin/test exits 0 for true and 1 for false. Anything else (127 for a
     * missing binary, 126 for a permission problem on it) is not an answer
     * about the path, so it is reported as "unknown" rather than as false.
     */
    private static function toBool(?int $exitCode): ?bool
    {
        return match ($exitCode) {
            0       => true,
            1       => false,
            default => null,
        };
    }
}
