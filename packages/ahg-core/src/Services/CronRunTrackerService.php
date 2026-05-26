<?php

/**
 * CronRunTrackerService - issue #673 Phase 2.
 *
 * Records every scheduled-command invocation into ahg_cron_run and emits
 * the matching Prometheus metrics:
 *
 *   heratio_cron_runs_total{command, status}     - counter
 *   heratio_cron_duration_seconds{command}       - histogram
 *   heratio_cron_missed_runs_total{command}      - counter (raised by the
 *                                                  CheckMissedCronRunsCommand)
 *
 * Hook points:
 *   - markStarted($command, $lockToken=null) inserts a row at started_at.
 *     Idempotent on (command, started_at_minute): a double-fire from two
 *     workers landing on the same minute returns the original row ID.
 *   - markFinished($runId, $exitCode) closes the row and emits the metric.
 *   - markFailed($runId, $throwable) is the throwable-aware equivalent.
 *
 * Distributed-lock detection: supportsDistributedLocks() returns true only
 * when the configured cache driver implements atomic locks (redis,
 * database, dynamodb, memcached). CronSchedulerService gates
 * ->onOneServer() on this so a 'file' or 'array' driver doesn't blow up
 * at schedule registration time.
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

namespace AhgCore\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CronRunTrackerService
{
    /**
     * Cache drivers that ship atomic locks suitable for ->onOneServer().
     * Source: Illuminate\Cache - the subset of stores that implement the
     * LockProvider contract.
     */
    private const LOCK_CAPABLE_DRIVERS = ['redis', 'database', 'dynamodb', 'memcached'];

    private ?bool $tableExists = null;

    /**
     * Insert a tracking row at the start of a scheduled run. Returns the
     * row ID, or null when the table is missing (so callers can swallow
     * the failure without crashing the schedule).
     *
     * The (command, started_at) unique key means a re-entrant call in the
     * same minute returns the existing row's ID rather than inserting a
     * duplicate - this is the "double-fire" protection the spec asked for.
     */
    public function markStarted(string $command, ?string $lockToken = null): ?int
    {
        if (! $this->ensureTable()) {
            return null;
        }

        $now = Carbon::now();
        $startedAt = $now->copy()->startOfMinute();
        $host = $this->hostname();

        try {
            // Idempotent upsert: same (command, minute) collapses to one row.
            $existing = DB::table('ahg_cron_run')
                ->where('command', $command)
                ->where('started_at', $startedAt)
                ->value('id');

            if ($existing) {
                return (int) $existing;
            }

            return (int) DB::table('ahg_cron_run')->insertGetId([
                'command'    => $command,
                'started_at' => $startedAt,
                'status'     => 'running',
                'lock_token' => $lockToken,
                'hostname'   => $host,
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] CronRunTracker markStarted failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Close a run with an exit code. Computes duration_ms from the row's
     * own started_at so failures inside the after() callback don't lose
     * the timing.
     */
    public function markFinished(?int $runId, int $exitCode, ?string $output = null): void
    {
        if ($runId === null || ! $this->ensureTable()) {
            return;
        }

        $now = Carbon::now();
        $status = $exitCode === 0 ? 'success' : 'failed';

        try {
            $row = DB::table('ahg_cron_run')->where('id', $runId)->first();
            if (! $row) {
                return;
            }

            $startedAt = Carbon::parse($row->started_at);
            $durationMs = (int) ($startedAt->diffInMilliseconds($now));

            DB::table('ahg_cron_run')->where('id', $runId)->update([
                'finished_at' => $now,
                'exit_code'   => $exitCode,
                'duration_ms' => $durationMs,
                'status'      => $status,
                'output'      => $output !== null ? $this->truncateOutput($output) : null,
            ]);

            $this->emitMetrics($row->command, $status, $durationMs);

            // Auto-resolve any open miss rows for this command - a fresh
            // successful run is the implicit "all good now" signal.
            if ($status === 'success') {
                DB::table('ahg_cron_missed_run')
                    ->where('command', $row->command)
                    ->whereNull('resolved_at')
                    ->update(['resolved_at' => $now]);
            }
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] CronRunTracker markFinished failed: '.$e->getMessage());
        }
    }

    /**
     * Failure shortcut for try/catch sites that have a Throwable rather
     * than an exit code.
     */
    public function markFailed(?int $runId, \Throwable $e): void
    {
        $this->markFinished($runId, 1, $e->getMessage());
    }

    /**
     * Returns true when the configured cache driver supports atomic locks,
     * so Laravel's ->onOneServer() will work. False means the caller
     * should skip the lock annotation and log a warning (per spec).
     */
    public function supportsDistributedLocks(): bool
    {
        $driver = (string) config('cache.default', 'file');

        return in_array($driver, self::LOCK_CAPABLE_DRIVERS, true);
    }

    /**
     * Emit Prometheus counter + histogram for a finished run. Best-effort:
     * if the observability package isn't installed (e.g. in a stripped
     * test environment) the metric calls silently no-op.
     */
    public function emitMetrics(string $command, string $status, int $durationMs): void
    {
        try {
            if (! class_exists(\AhgObservability\Services\MetricsRegistry::class)) {
                return;
            }

            $registry = app(\AhgObservability\Services\MetricsRegistry::class);
            $label = $this->commandLabel($command);

            $registry->counter(
                'cron_runs_total',
                'Total scheduled cron runs by command and final status.',
                ['command', 'status']
            )->inc([$label, $status]);

            $registry->histogram(
                'cron_duration_seconds',
                'Scheduled cron command duration in seconds.',
                ['command'],
                [0.1, 0.5, 1, 5, 15, 60, 300, 900, 3600]
            )->observe($durationMs / 1000.0, [$label]);
        } catch (\Throwable $e) {
            // Observability is a side-channel - never break the scheduler
            // because Prometheus storage hiccupped.
            Log::warning('[ahg-core] CronRunTracker emitMetrics failed: '.$e->getMessage());
        }
    }

    /**
     * Bump the missed-runs counter. Called by CheckMissedCronRunsCommand
     * when a new miss row lands.
     */
    public function emitMissedMetric(string $command): void
    {
        try {
            if (! class_exists(\AhgObservability\Services\MetricsRegistry::class)) {
                return;
            }

            app(\AhgObservability\Services\MetricsRegistry::class)
                ->counter(
                    'cron_missed_runs_total',
                    'Total times cron:check-missed-runs flagged a command as overdue.',
                    ['command']
                )
                ->inc([$this->commandLabel($command)]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] CronRunTracker emitMissedMetric failed: '.$e->getMessage());
        }
    }

    /**
     * Auto-install ahg_cron_run + ahg_cron_missed_run if either is missing.
     * Wraps Schema::hasTable + unprepared SQL in one outer try/catch per
     * reference_ci_schema_hastable.md.
     */
    public function ensureTable(): bool
    {
        if ($this->tableExists === true) {
            return true;
        }

        try {
            $runExists = Schema::hasTable('ahg_cron_run');
            $missedExists = Schema::hasTable('ahg_cron_missed_run');

            if ($runExists && $missedExists) {
                $this->tableExists = true;

                return true;
            }

            $sqlPath = __DIR__.'/../../database/install_cron_run.sql';
            if (! is_file($sqlPath)) {
                return false;
            }

            $sql = (string) file_get_contents($sqlPath);
            if ($sql !== '') {
                DB::unprepared($sql);
            }

            $this->tableExists = Schema::hasTable('ahg_cron_run')
                && Schema::hasTable('ahg_cron_missed_run');

            return $this->tableExists;
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] CronRunTracker ensureTable failed: '.$e->getMessage());
            $this->tableExists = false;

            return false;
        }
    }

    /**
     * Collapse a full artisan command string (incl. flags / args) into a
     * stable Prometheus label. Keeps the base verb so cardinality stays
     * one bucket per scheduled job, not one per per-flag variant.
     */
    public function commandLabel(string $command): string
    {
        $base = strtok(trim($command), " \t") ?: $command;

        // Prometheus label values are arbitrary strings, but keeping them
        // alphanumeric + : + - + _ keeps PromQL queries pleasant.
        return preg_replace('/[^A-Za-z0-9:_\-]/', '_', $base) ?: 'unknown';
    }

    private function hostname(): string
    {
        $host = gethostname();

        return is_string($host) && $host !== '' ? $host : 'unknown';
    }

    private function truncateOutput(string $output): ?string
    {
        $trimmed = trim($output);
        if ($trimmed === '') {
            return null;
        }

        if (strlen($trimmed) > 5000) {
            return '... (truncated) ...'.substr($trimmed, -5000);
        }

        return $trimmed;
    }
}
