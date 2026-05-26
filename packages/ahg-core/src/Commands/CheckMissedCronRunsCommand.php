<?php

/**
 * cron:check-missed-runs - issue #673 Phase 2.
 *
 * For every command in CronSchedulerService::getDefaultSchedules(), walks
 * back through the cron expression to find the most recent expected run
 * timestamp and compares against the latest ahg_cron_run.finished_at for
 * that command. A gap > miss_threshold_multiplier x interval flags the
 * command as missed:
 *
 *   - INSERT IGNORE into ahg_cron_missed_run (unique on command+expected_at,
 *     so re-running this command every 5 minutes is idempotent)
 *   - bump heratio_cron_missed_runs_total{command} counter
 *   - if the command is in config('cron-monitoring.high_priority_commands'),
 *     drop a JSON notification into the Workbench inbox spool
 *     (/var/spool/workbench/notifications/) for operator visibility
 *
 * Resolution: CronRunTrackerService::markFinished('success') auto-stamps
 * resolved_at on any open miss rows for the command, so dashboards see
 * the gap close without an operator clicking anything.
 *
 * Embedded as a 5-minute schedule by CronSchedulerService::registerWithLaravelSchedule.
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

namespace AhgCore\Commands;

use AhgCore\Services\CronRunTrackerService;
use AhgCore\Services\CronSchedulerService;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckMissedCronRunsCommand extends Command
{
    protected $signature = 'cron:check-missed-runs
        {--dry-run : List would-be misses without inserting rows or notifying}';

    protected $description = 'Detect cron commands whose latest finished_at is more than 2x the expected interval behind, flag in ahg_cron_missed_run and notify on high-priority misses.';

    public function __construct(
        private readonly CronSchedulerService $scheduler,
        private readonly CronRunTrackerService $tracker,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! $this->tracker->ensureTable()) {
            $this->warn('ahg_cron_run / ahg_cron_missed_run table missing and could not be auto-installed.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $multiplier = (float) config('cron-monitoring.miss_threshold_multiplier', 2.0);
        $highPriority = (array) config('cron-monitoring.high_priority_commands', []);
        $now = Carbon::now();

        $missed = 0;
        $checked = 0;

        foreach ($this->scheduler->getDefaultSchedules() as $entry) {
            $checked++;
            $command = (string) $entry['artisan_command'];
            $cronExpr = (string) $entry['cron_expression'];

            try {
                $cron = new CronExpression($cronExpr);
            } catch (\Throwable $e) {
                $this->warn(sprintf('  [%s] invalid cron expression: %s', $command, $cronExpr));

                continue;
            }

            $expectedAt = Carbon::instance($cron->getPreviousRunDate($now->toDateTimeImmutable()));
            $intervalSeconds = $this->estimateIntervalSeconds($cron, $expectedAt);
            $thresholdSeconds = (int) max(60, $intervalSeconds * $multiplier);

            $lastFinishedAt = $this->lastFinishedAt($command);

            $gapSeconds = $lastFinishedAt
                ? $lastFinishedAt->diffInSeconds($now)
                : $now->diffInSeconds(Carbon::createFromTimestamp(0));

            // Compare gap from expected_at, not from "now", so a job that
            // legitimately ran 3 seconds ago for a 5-minute window
            // doesn't get re-flagged on the next detector tick.
            $gapFromExpected = $lastFinishedAt
                ? max(0, $expectedAt->diffInSeconds($lastFinishedAt, false) * -1)
                : (int) $expectedAt->diffInSeconds($now);

            if ($gapFromExpected <= $thresholdSeconds) {
                continue;
            }

            $missed++;
            $message = sprintf(
                '  [MISS] %s expected at %s, last finished %s (gap %ds > %ds)',
                $command,
                $expectedAt->toDateTimeString(),
                $lastFinishedAt ? $lastFinishedAt->toDateTimeString() : 'never',
                $gapFromExpected,
                $thresholdSeconds
            );
            $this->warn($message);

            if ($dryRun) {
                continue;
            }

            $this->recordMiss($command, $expectedAt, $gapFromExpected, $highPriority);
        }

        $this->info(sprintf('Checked %d commands, %d missed.', $checked, $missed));

        return self::SUCCESS;
    }

    /**
     * Most recent finished_at for a command. Successful or failed both
     * count - the metric of interest is "ran at all", because a failed
     * run still proves the scheduler tick fired.
     */
    private function lastFinishedAt(string $command): ?Carbon
    {
        try {
            $value = DB::table('ahg_cron_run')
                ->where('command', $command)
                ->whereNotNull('finished_at')
                ->orderByDesc('finished_at')
                ->value('finished_at');

            return $value ? Carbon::parse($value) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Insert (idempotent) a miss row, bump the Prometheus counter, and
     * fire the Workbench notification for high-priority commands.
     */
    private function recordMiss(string $command, Carbon $expectedAt, int $gapSeconds, array $highPriority): void
    {
        try {
            $existing = DB::table('ahg_cron_missed_run')
                ->where('command', $command)
                ->where('expected_at', $expectedAt)
                ->value('id');

            if (! $existing) {
                DB::table('ahg_cron_missed_run')->insert([
                    'command'     => $command,
                    'expected_at' => $expectedAt,
                    'gap_seconds' => $gapSeconds,
                    'detected_at' => Carbon::now(),
                    'created_at'  => Carbon::now(),
                ]);

                $this->tracker->emitMissedMetric($command);

                if ($this->isHighPriority($command, $highPriority)) {
                    $this->dropWorkbenchNotification($command, $expectedAt, $gapSeconds);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[cron:check-missed-runs] recordMiss failed for '.$command.': '.$e->getMessage());
        }
    }

    /**
     * Match either the full command string OR the base verb against the
     * high-priority allowlist so operators can list either form.
     */
    private function isHighPriority(string $command, array $highPriority): bool
    {
        if (in_array($command, $highPriority, true)) {
            return true;
        }

        $base = strtok(trim($command), " \t") ?: $command;
        foreach ($highPriority as $candidate) {
            $candidateBase = strtok(trim((string) $candidate), " \t") ?: $candidate;
            if ($candidateBase === $base) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop a JSON notification file into the spool. The workbench inbox
     * watcher (see /usr/share/nginx/workbench/api/src/services/notificationInboxWatcher.ts)
     * sweeps every 15s and surfaces it on Johan's bell.
     */
    private function dropWorkbenchNotification(string $command, Carbon $expectedAt, int $gapSeconds): void
    {
        $inbox = (string) config('cron-monitoring.inbox_path', '/var/spool/workbench/notifications');
        $user = (string) config('cron-monitoring.notification_user', 'johan');

        try {
            if (! is_dir($inbox)) {
                // Don't try to mkdir under /var/spool from a worker - it
                // may be on a read-only fs (php-fpm ProtectSystem=full)
                // and the operator owns the inbox layout.
                Log::warning('[cron:check-missed-runs] workbench inbox missing: '.$inbox);

                return;
            }

            $payload = [
                'username'     => $user,
                'title'        => 'Cron missed run: '.$command,
                'message'      => sprintf(
                    'Expected at %s; gap %ds. Check %s for the latest run row.',
                    $expectedAt->toDateTimeString(),
                    $gapSeconds,
                    'ahg_cron_run'
                ),
                'eventType'    => 'alert',
                'deadlineHint' => 'now',
            ];

            $filename = sprintf(
                '%s/heratio-cron-miss-%s-%s.json',
                rtrim($inbox, '/'),
                preg_replace('/[^A-Za-z0-9_-]/', '_', $command),
                $expectedAt->format('YmdHis')
            );

            @file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Log::warning('[cron:check-missed-runs] notification drop failed: '.$e->getMessage());
        }
    }

    /**
     * Estimate the interval (seconds) between consecutive runs of a cron
     * expression by stepping forward from $reference. For a
     * "(star)/5 (star) (star) (star) (star)" expression this returns 300;
     * for "0 2 (star) (star) (star)" it returns 86400; for irregular
     * expressions (e.g. multiple comma-separated minutes) it returns the
     * smallest gap so the detector errs on the side of catching misses.
     */
    private function estimateIntervalSeconds(CronExpression $cron, Carbon $reference): int
    {
        try {
            $a = Carbon::instance($cron->getNextRunDate($reference->toDateTimeImmutable()));
            $b = Carbon::instance($cron->getNextRunDate($a->toDateTimeImmutable()));

            $delta = $a->diffInSeconds($b);

            return $delta > 0 ? $delta : 60;
        } catch (\Throwable $e) {
            return 3600;
        }
    }
}
