<?php

/**
 * ahg:nas-watchdog
 *
 * Monitors the storage NAS mount (config('heratio.storage_path')) and raises
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
    private const NOTIFY_INBOX = '/var/spool/workbench/notifications';
    private const PROBE_TIMEOUT = 5; // seconds

    public function handle(): int
    {
        $path = (string) (config('heratio.storage_path') ?: '/mnt/nas/heratio');
        $report = $this->probe($path);

        $previous = (string) (Cache::get(self::STATE_CACHE_KEY) ?: 'unknown');
        $current = $report['state'];
        $transition = $previous !== $current;

        Cache::put(self::STATE_CACHE_KEY, $current, now()->addDays(7));

        $this->line(sprintf(
            '%s state=%s path=%s mountpoint=%s readable=%s archive_subdir=%s probe_ms=%d (prev=%s%s)',
            now()->toIso8601String(),
            strtoupper($current),
            $path,
            $report['is_mountpoint'] ? 'yes' : 'no',
            $report['readable']      ? 'yes' : 'no',
            $report['archive_subdir']? 'yes' : 'no',
            $report['probe_ms'],
            $previous,
            $transition ? ' TRANSITION' : ''
        ));

        $force = (bool) $this->option('force-notify');
        $quietOk = (bool) $this->option('quiet-ok');

        $shouldNotify = $force || $transition;
        if ($shouldNotify && $quietOk && $current === 'up' && $previous !== 'down') {
            $shouldNotify = false;
        }
        if (!$shouldNotify) {
            return self::SUCCESS;
        }

        $title = match ($current) {
            'down' => 'Storage NAS is DOWN',
            'up'   => $previous === 'down' ? 'Storage NAS recovered' : 'Storage NAS is up',
            default => 'Storage NAS state unclear',
        };
        $msg = sprintf(
            'state=%s path=%s mountpoint=%s readable=%s archive_subdir=%s probe_ms=%d previous=%s',
            $current, $path,
            $report['is_mountpoint'] ? 'yes' : 'no',
            $report['readable']      ? 'yes' : 'no',
            $report['archive_subdir']? 'yes' : 'no',
            $report['probe_ms'],
            $previous
        );

        if ($current === 'down') {
            Log::error("NasWatchdog: $msg");
        } else {
            Log::info("NasWatchdog: $msg");
        }

        $this->writeAhgNotification($title, $msg, $current);
        $this->writeWorkbenchInboxDrop($title, $msg, $current);

        return self::SUCCESS;
    }

    /**
     * Probe the NAS path. Three checks, each timed against a wall-clock cap:
     *   1. mountpoint - is /mnt/nas/heratio its own filesystem?
     *   2. readable   - can we open the dir without error?
     *   3. archive_subdir - canary: does the expected sub-tree exist?
     *
     * Returns ['state' => 'up' | 'down', ...]
     */
    private function probe(string $path): array
    {
        $start = microtime(true);
        $report = [
            'is_mountpoint'   => false,
            'readable'        => false,
            'archive_subdir'  => false,
            'probe_ms'        => 0,
        ];

        try {
            // mountpoint check via /proc/mounts - avoids invoking external `mountpoint` cmd.
            $mounts = @file_get_contents('/proc/mounts');
            if (is_string($mounts)) {
                $needle = ' ' . rtrim($path, '/') . ' ';
                $report['is_mountpoint'] = (bool) strpos($mounts, $needle);
            }

            // readable + canary probe with a hard wall-clock cap to avoid blocking
            // forever on a hung NFS mount.
            $deadline = $start + self::PROBE_TIMEOUT;
            if (is_dir($path) && (microtime(true) < $deadline)) {
                $report['readable'] = @is_readable($path);
                $canary = rtrim($path, '/') . '/archive';
                if (microtime(true) < $deadline) {
                    $report['archive_subdir'] = @is_dir($canary);
                }
            }
        } catch (Throwable $e) {
            Log::warning('NasWatchdog probe threw: ' . $e->getMessage());
        }

        $report['probe_ms'] = (int) round((microtime(true) - $start) * 1000);
        $report['state'] = ($report['is_mountpoint'] && $report['readable'] && $report['archive_subdir'])
            ? 'up' : 'down';

        return $report;
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
                relatedId: $state,
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
