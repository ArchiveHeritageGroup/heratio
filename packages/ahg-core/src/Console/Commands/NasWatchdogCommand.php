<?php

/**
 * ahg:nas-watchdog
 *
 * Monitors the storage NAS mount (config('heratio.nas_watchdog_path'), else
 * config('heratio.storage_path')) and raises
 * a notification when it goes down or comes back up. Does NOT attempt to
 * remount - operator preference is to leave the mount alone and surface the
 * outage instead, so a transient NFS blip doesn't get masked.
 *
 * State is tracked in cache (key: nas_watchdog:last_state) so notifications
 * only fire on transitions, not every tick.
 *
 * Recommended schedule: every 5 minutes.
 *
 * Notifications go to three surfaces:
 *  - ahg_notification table (in-app bell)
 *  - /var/spool/workbench/notifications/ JSON drop (workbench bell + chime)
 *  - Laravel log (text trail for ops greppability)
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgCore\Console\Commands;

use AhgCore\Support\BoundedFsCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class NasWatchdogCommand extends Command
{
    protected $signature = 'ahg:nas-watchdog
        {--force-notify : Always emit a notification this tick, regardless of state-transition}
        {--quiet-ok : Skip notification when state is up - only notify on down or up->recovered}';

    protected $description = 'Probe the storage NAS mount and notify on state transitions (no auto-remount).';

    private const STATE_CACHE_KEY = 'nas_watchdog:last_state';
    private const DEGRADED_STREAK_KEY = 'nas_watchdog:degraded_streak';
    private const NOTIFY_INBOX = '/var/spool/workbench/notifications';
    private const PROBE_TIMEOUT = 5; // seconds

    public function handle(): int
    {
        // Self-gate, like ahg:encryption-bulk-apply: a no-op when this install's
        // storage is deliberately local. The probe requires storage_path to be a
        // MOUNTPOINT with an archive/ subdirectory, so a local path fails two of
        // three checks and reports "down" forever - notifying the operator about
        // a NAS the install does not use. Default stays true so NAS-backed
        // installs are unaffected.
        if (!config('heratio.nas_watchdog_enabled', true)) {
            $this->line('ahg:nas-watchdog disabled (heratio.nas_watchdog_enabled=false) - storage is local on this install.');
            return self::SUCCESS;
        }

        $path = (string) (config('heratio.nas_watchdog_path') ?: config('heratio.storage_path') ?: '/mnt/nas/heratio');
        $report = $this->probe($path);

        $previous = (string) (Cache::get(self::STATE_CACHE_KEY) ?: 'unknown');
        $raw = $report['state'];

        // Escalation. Without it, a mount that is hung rather than merely slow
        // would sit at 'degraded' for ever and never ring a bell - which would
        // trade the old false alarms for a missed real outage. A NAS that has
        // failed to answer for N consecutive ticks is treated as down.
        $escalateAfter = (int) config('heratio.nas_watchdog_degraded_escalate', 3);
        $streak = $raw === 'degraded' ? ((int) Cache::get(self::DEGRADED_STREAK_KEY, 0)) + 1 : 0;
        Cache::put(self::DEGRADED_STREAK_KEY, $streak, now()->addDays(7));

        $current = ($raw === 'degraded' && $escalateAfter > 0 && $streak >= $escalateAfter)
            ? 'down'
            : $raw;
        $transition = $previous !== $current;

        Cache::put(self::STATE_CACHE_KEY, $current, now()->addDays(7));

        $this->line(sprintf(
            '%s state=%s path=%s mountpoint=%s readable=%s archive_subdir=%s probe_ms=%d%s (prev=%s%s)',
            now()->toIso8601String(),
            strtoupper($current),
            $path,
            $report['is_mountpoint'] ? 'yes' : 'no',
            $this->fmtCheck($report['readable']),
            $this->fmtCheck($report['archive_subdir']),
            $report['probe_ms'],
            $raw === 'degraded' ? ' degraded_streak=' . $streak : '',
            $previous,
            $transition ? ' TRANSITION' : ''
        ));

        $msg = sprintf(
            'state=%s path=%s mountpoint=%s readable=%s archive_subdir=%s probe_ms=%d timed_out=%s previous=%s',
            $current, $path,
            $report['is_mountpoint'] ? 'yes' : 'no',
            $this->fmtCheck($report['readable']),
            $this->fmtCheck($report['archive_subdir']),
            $report['probe_ms'],
            $report['timed_out'] ? 'yes' : 'no',
            $previous
        );

        // Log every tick that is not plainly healthy, whether or not it notifies.
        // The scheduler runs this with stdout to /dev/null, so without this a
        // degraded tick would leave no trace anywhere.
        if ($current === 'down') {
            Log::error("NasWatchdog: $msg");
        } elseif ($current === 'degraded') {
            Log::warning("NasWatchdog: $msg");
        } elseif ($transition) {
            Log::info("NasWatchdog: $msg");
        }

        $force = (bool) $this->option('force-notify');
        $quietOk = (bool) $this->option('quiet-ok');

        // Under --quiet-ok (how the scheduler runs it) the bell is reserved for
        // a real outage and its recovery. 'degraded' is deliberately silent: it
        // is a latency signal for the log and /admin/health, and ringing on it
        // would recreate the noise this change exists to stop. It still reaches
        // the operator if it persists, via the escalation above.
        $recovered = $previous === 'down' && $current !== 'down';
        $shouldNotify = $force
            || ($transition && $current === 'down')
            || ($transition && $recovered)
            || ($transition && ! $quietOk);
        if (!$shouldNotify) {
            return self::SUCCESS;
        }

        $title = match ($current) {
            'down'     => 'Storage NAS is DOWN',
            'degraded' => 'Storage NAS is SLOW',
            'up'       => $previous === 'down' ? 'Storage NAS recovered' : 'Storage NAS is up',
            default    => 'Storage NAS state unclear',
        };
        $this->writeAhgNotification($title, $msg, $current);
        $this->writeWorkbenchInboxDrop($title, $msg, $current);

        return self::SUCCESS;
    }

    /**
     * Probe the NAS path. Three checks:
     *   1. mountpoint - is /mnt/nas/heratio its own filesystem?
     *   2. readable   - can we open the dir without error?
     *   3. archive_subdir - canary: does the expected sub-tree exist?
     *
     * Checks 2 and 3 are tri-state: true, false, or null for "did not answer
     * within the deadline". They run through BoundedFsCheck because a direct
     * is_readable() on a hung mount blocks inside the kernel and cannot be cut
     * short from this process, however often the clock is consulted around it.
     *
     * Returns ['state' => 'up' | 'degraded' | 'down', ...]
     */
    private function probe(string $path): array
    {
        $start = microtime(true);
        $timeout = (float) config('heratio.nas_watchdog_timeout', self::PROBE_TIMEOUT);
        $report = [
            'is_mountpoint'   => false,
            'readable'        => null,
            'archive_subdir'  => null,
            'probe_ms'        => 0,
            'timed_out'       => false,
        ];

        try {
            // mountpoint check via /proc/mounts - avoids invoking external `mountpoint` cmd.
            // procfs answers from memory, so unlike a stat of the mount itself this
            // cannot block on the NAS and needs no deadline.
            $mounts = @file_get_contents('/proc/mounts');
            if (is_string($mounts)) {
                $needle = ' ' . rtrim($path, '/') . ' ';
                $report['is_mountpoint'] = (bool) strpos($mounts, $needle);
            }

            $report['readable'] = BoundedFsCheck::readable($path, $timeout);

            // One budget shared across both checks: a full timeout each would let
            // a slow mount hold the probe for twice as long as configured.
            $remaining = $timeout - (microtime(true) - $start);
            $report['archive_subdir'] = $remaining > 0.1
                ? BoundedFsCheck::isDir(rtrim($path, '/') . '/archive', $remaining)
                : null;
        } catch (Throwable $e) {
            Log::warning('NasWatchdog probe threw: ' . $e->getMessage());
        }

        $report['probe_ms'] = (int) round((microtime(true) - $start) * 1000);
        $report['timed_out'] = $report['readable'] === null || $report['archive_subdir'] === null;
        $report['state'] = $this->classify($report);

        return $report;
    }

    /**
     * Turn a probe report into a state.
     *
     * The rule that matters: only a definite failure reports down. A check that
     * ran out of time proves the NAS is slow, not that it is gone. The two used
     * to collapse into one value because an unanswered check was left at its
     * initialised false, which is what raised CH-000058, CH-000067 and
     * CH-000077 against a mount that answered normally a minute later.
     */
    private function classify(array $report): string
    {
        // Absent from /proc/mounts: the mount is genuinely not there. This is the
        // one check that cannot time out, so it stays authoritative.
        if (! $report['is_mountpoint']) {
            return 'down';
        }

        // The filesystem answered, and the answer was no.
        if ($report['readable'] === false || $report['archive_subdir'] === false) {
            return 'down';
        }

        if ($report['timed_out']) {
            return 'degraded';
        }

        $slowMs = (int) config('heratio.nas_watchdog_slow_ms', 2000);

        return $report['probe_ms'] >= $slowMs ? 'degraded' : 'up';
    }

    /**
     * Render a tri-state check for the log line: yes / no / timeout.
     */
    private function fmtCheck(?bool $value): string
    {
        return $value === null ? 'timeout' : ($value ? 'yes' : 'no');
    }

    private function writeAhgNotification(string $title, string $body, string $state): void
    {
        try {
            app(\AhgCore\Services\NotificationService::class)->notifyAdmins(
                type: 'nas-watchdog',
                title: '[NAS] ' . $title,
                message: $body,
                link: '/admin/health',
                relatedType: 'nas',
                // NOT the state string. ahg_notification.related_id is
                // `bigint unsigned`, so passing 'down' threw SQLSTATE[HY000]
                // 1366 "Incorrect integer value" on EVERY alarm and the in-app
                // bell row was never written - only the workbench drop-file
                // landed. There is no numeric entity behind a mount check;
                // relatedType 'nas' already carries the meaning.
                relatedId: null,
            );
        } catch (Throwable $e) {
            Log::warning('NasWatchdog: ahg_notification insert failed - ' . $e->getMessage());
        }
    }

    /**
     * Drop a workbench notification JSON so the bell + chime + toast fire on
     * ai.theahg.co.za. The watcher sweeps the inbox every 15s and archives
     * ingested files; malformed payloads go to ./failed/.
     */
    private function writeWorkbenchInboxDrop(string $title, string $body, string $state): void
    {
        try {
            $inbox = (string) (env('WORKBENCH_NOTIFICATIONS_INBOX') ?: self::NOTIFY_INBOX);
            if (!is_dir($inbox) || !is_writable($inbox)) return;

            $payload = [
                'username'   => 'johan',
                'title'      => $title,
                'message'    => $body,
                'eventType'  => $state === 'down' ? 'alert' : 'reminder',
                'webLink'    => 'https://heratio.theahg.co.za/admin/health',
            ];
            $file = $inbox . '/nas-watchdog-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8) . '.json';
            @file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        } catch (Throwable $e) {
            Log::warning('NasWatchdog: workbench inbox drop failed - ' . $e->getMessage());
        }
    }
}
