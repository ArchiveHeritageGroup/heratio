<?php

/**
 * RunFixitySchedulesCommand — Phase 3.5 (Scheduled fixity verification).
 *
 * Walks `preservation_workflow_schedule` rows where workflow_type='fixity_check',
 * picks up the ones whose next_run_at is due, and runs FixityService::batchVerify
 * across a batch of digital_objects. Each schedule run writes a
 * `preservation_workflow_run` audit row with throughput counters, plus a PREMIS
 * event per object via PreservationService::logEvent.
 *
 * Designed to be invoked by Heratio's DB-driven cron runner (ahg:cron-run) or
 * by hand for manual catch-up runs.
 *
 *   php artisan ahg:preservation-fixity-run             # run any due schedules
 *   php artisan ahg:preservation-fixity-run --force     # run ALL enabled schedules now
 *   php artisan ahg:preservation-fixity-run --schedule=3  # run schedule #3 specifically
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgPreservation\Console;

use AhgIntegrity\Services\FixityService;
use AhgPreservation\Services\PreservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunFixitySchedulesCommand extends Command
{
    protected $signature = 'ahg:preservation-fixity-run
        {--force        : Ignore next_run_at; run every enabled fixity schedule}
        {--schedule=    : Run a specific schedule id only}
        {--max-batch=   : Override per-schedule batch_limit (defaults to schedule.batch_limit)}';

    protected $description = 'Run due fixity-check schedules from preservation_workflow_schedule';

    public function handle(FixityService $fixity, PreservationService $preservation): int
    {
        // Master gate. integrity_enabled=false silences scheduled scans
        // entirely - matches IntegrityVerifyCommand behaviour. --force can
        // bypass this so an operator can run a one-off scan during incident
        // response without flipping the global toggle on first.
        if (!\AhgIntegrity\Support\IntegritySettings::enabled() && !$this->option('force')) {
            $this->warn('integrity_enabled is off; scheduled fixity scans skipping (use --force to run anyway).');
            return self::SUCCESS;
        }

        // Apply integrity_default_max_memory globally for this command run.
        // Per-schedule loops inherit it; matches IntegrityVerifyCommand
        // behaviour so the same setting governs both code paths.
        @ini_set('memory_limit', \AhgIntegrity\Support\IntegritySettings::defaultMaxMemoryMb() . 'M');

        $now = Carbon::now();

        $q = DB::table('preservation_workflow_schedule')
            ->where('workflow_type', 'fixity_check')
            ->where('is_enabled', 1);

        if ($id = $this->option('schedule')) {
            $q->where('id', (int) $id);
        } elseif (! $this->option('force')) {
            $q->where(function ($w) use ($now) {
                $w->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            });
        }

        $schedules = $q->orderBy('id')->get();

        if ($schedules->isEmpty()) {
            $this->info('No fixity schedules due.');
            return self::SUCCESS;
        }

        $this->info("Running {$schedules->count()} schedule(s)…");
        $totalProcessed = 0;
        $totalFailed    = 0;
        $totalSkipped   = 0;

        foreach ($schedules as $schedule) {
            $stats = $this->runOne($schedule, $fixity, $preservation);
            $totalProcessed += $stats['succeeded'];
            $totalFailed    += $stats['failed'];
            $totalSkipped   += $stats['skipped'];
            $this->line(sprintf(
                '  [%d %s] processed=%d ok=%d fail=%d skip=%d (%dms)',
                $schedule->id,
                $schedule->name,
                $stats['processed'],
                $stats['succeeded'],
                $stats['failed'],
                $stats['skipped'],
                $stats['duration_ms']
            ));
        }

        $this->info("Done. ok={$totalProcessed} fail={$totalFailed} skip={$totalSkipped}");
        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Execute one schedule. Always writes a preservation_workflow_run row, even on failure.
     *
     * @return array{processed:int, succeeded:int, failed:int, skipped:int, duration_ms:int}
     */
    protected function runOne(object $schedule, FixityService $fixity, PreservationService $preservation): array
    {
        $startedAt = microtime(true);
        $startCarbon = Carbon::now();
        // Resolution order: --max-batch CLI option > schedule.batch_limit
        // column > integrity_default_batch_size setting > built-in 100. Same
        // pattern as IntegrityVerifyCommand so an operator who configures the
        // global default sees it apply to both code paths.
        $batchLimit = (int) ($this->option('max-batch')
            ?: ($schedule->batch_limit
                ?? \AhgIntegrity\Support\IntegritySettings::defaultBatchSize()));
        $options    = $this->decodeOptions($schedule->options ?? null);
        // Schedule-level overrides win; otherwise fall back to the global
        // integrity_default_algorithm / integrity_io_throttle_ms settings so
        // a single operator knob applies to every fixity path. Previously
        // batchVerify() got a hardcoded 'sha256' + throttle=0, ignoring the
        // operator's configuration.
        $algorithm  = (string) ($options['algorithm'] ?? \AhgIntegrity\Support\IntegritySettings::defaultAlgorithm());
        $throttleMs = (int) ($options['io_throttle_ms'] ?? \AhgIntegrity\Support\IntegritySettings::ioThrottleMs());
        $staleDays  = (int) ($options['stale_days'] ?? 90);
        $repoId     = isset($options['repository_id']) ? (int) $options['repository_id'] : null;

        $runId = DB::table('preservation_workflow_run')->insertGetId([
            'schedule_id'   => $schedule->id,
            'workflow_type' => 'fixity_check',
            'status'        => 'running',
            'started_at'    => $startCarbon,
            'triggered_by'  => 'scheduler',
        ]);

        $stats = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'skipped' => 0];
        $errorMessage = null;

        try {
            // Find the slowest-verified or never-verified objects up to batch limit.
            // FixityService::getStaleObjects() aliases the id column to `digital_object_id`.
            $stale = $fixity->getStaleObjects($staleDays, $repoId, $batchLimit);
            $ids   = array_map(fn($r) => (int) $r->digital_object_id, $stale);

            if (empty($ids)) {
                $stats['skipped'] = 0; // nothing due
            } else {
                // FixityService::batchVerify returns an array keyed by digital_object_id whose
                // values are the verifyObject() result arrays (with 'outcome' = verified|mismatch|missing|error).
                // Wall-clock deadline derived from integrity_default_max_runtime
                // (per-runOne; counted from this schedule's $startedAt). 0 disables
                // the cap. batchVerify checks the deadline at the top of each
                // iteration and breaks before the next verifyObject call - any
                // objects past the cut-off are simply not present in the
                // returned array, and the schedule's stats reflect "processed
                // up to the cap" cleanly.
                $maxRuntime = \AhgIntegrity\Support\IntegritySettings::defaultMaxRuntimeSeconds();
                $deadline = $maxRuntime > 0 ? ((int) $startedAt + $maxRuntime) : null;
                $batch = $fixity->batchVerify($ids, $algorithm, $throttleMs, $deadline);
                $stats['processed'] = count($batch);
                foreach ($batch as $digitalObjectId => $r) {
                    $outcomeRaw = is_array($r) ? ($r['outcome'] ?? 'unknown') : 'unknown';
                    $passed     = $outcomeRaw === 'verified';
                    $premisOutcome = $passed ? 'success' : 'failure';

                    if ($passed) {
                        $stats['succeeded']++;
                    } else {
                        $stats['failed']++;
                    }

                    if ((int) $digitalObjectId > 0) {
                        try {
                            $preservation->logEvent(
                                (int) $digitalObjectId,
                                null,
                                'fixityCheck',
                                sprintf('Scheduled fixity (%s): %s', $algorithm, $outcomeRaw),
                                $premisOutcome
                            );
                        } catch (Throwable $logErr) {
                            // Logging failure must not abort the run; capture and continue.
                            Log::warning('preservation: PREMIS logEvent failed', [
                                'digital_object_id' => $digitalObjectId,
                                'error'             => $logErr->getMessage(),
                            ]);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $errorMessage = mb_substr($e->getMessage(), 0, 1000);
            Log::error('preservation: scheduled fixity failed', [
                'schedule_id' => $schedule->id,
                'error'       => $errorMessage,
            ]);
        }

        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        // Status decision honours integrity_dead_letter_threshold so a single
        // schedule run that hard-fails above the operator's tolerance gets
        // marked dead_letter and won't be silently retried by the scheduler
        // until an operator reviews. Mirrors IntegrityVerifyCommand's run
        // status decision against integrity_run.
        $deadLetterThreshold = \AhgIntegrity\Support\IntegritySettings::deadLetterThreshold();
        if ($errorMessage) {
            $endStatus = 'failed';
        } elseif ($stats['failed'] >= $deadLetterThreshold) {
            $endStatus = 'dead_letter';
        } elseif ($stats['failed'] > 0) {
            $endStatus = 'completed_with_errors';
        } else {
            $endStatus = 'completed';
        }

        // Wall-clock signal: when the deadline parameter passed into
        // batchVerify caused an early break, fewer objects landed in the
        // result array than $batchLimit asked for. Log so the operator sees
        // the cap fired.
        $maxRuntime = \AhgIntegrity\Support\IntegritySettings::defaultMaxRuntimeSeconds();
        if ($maxRuntime > 0
            && !empty($ids)
            && $stats['processed'] < count($ids)
            && $durationMs >= ($maxRuntime * 1000) - 1000
        ) {
            Log::warning('preservation: fixity schedule hit integrity_default_max_runtime cap', [
                'schedule_id' => $schedule->id,
                'duration_ms' => $durationMs,
                'cap_seconds' => $maxRuntime,
                'processed' => $stats['processed'],
                'requested' => count($ids),
            ]);
        }

        DB::table('preservation_workflow_run')->where('id', $runId)->update([
            'status'              => $endStatus,
            'completed_at'        => Carbon::now(),
            'duration_ms'         => $durationMs,
            'objects_processed'   => $stats['processed'],
            'objects_succeeded'   => $stats['succeeded'],
            'objects_failed'      => $stats['failed'],
            'objects_skipped'     => $stats['skipped'],
            'error_message'       => $errorMessage,
            'summary'             => json_encode([
                'algorithm'   => $algorithm,
                'stale_days'  => $staleDays,
                'batch_limit' => $batchLimit,
                'repository'  => $repoId,
            ]),
        ]);

        // Compute next_run_at from the cron expression (if cron-driven), else interval-driven.
        $nextRunAt = $this->computeNextRunAt($schedule);

        DB::table('preservation_workflow_schedule')->where('id', $schedule->id)->update([
            'last_run_at'          => $startCarbon,
            'last_run_status'      => $endStatus,
            'last_run_processed'   => $stats['processed'],
            'last_run_duration_ms' => $durationMs,
            'next_run_at'          => $nextRunAt,
            'total_runs'           => DB::raw('COALESCE(total_runs,0) + 1'),
            'total_processed'      => DB::raw('COALESCE(total_processed,0) + ' . (int) $stats['processed']),
        ]);

        $stats['duration_ms'] = $durationMs;
        return $stats;
    }

    protected function decodeOptions($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (! is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function computeNextRunAt(object $schedule): ?string
    {
        $now = Carbon::now();
        $type = $schedule->schedule_type ?? 'cron';

        if ($type === 'interval' && ! empty($schedule->interval_hours)) {
            return $now->addHours((int) $schedule->interval_hours)->toDateTimeString();
        }
        if ($type === 'cron' && ! empty($schedule->cron_expression)) {
            try {
                $cron = new \Cron\CronExpression($schedule->cron_expression);
                return Carbon::instance($cron->getNextRunDate($now))->toDateTimeString();
            } catch (Throwable $e) {
                // Fall through to default daily.
            }
        }
        return $now->copy()->addDay()->toDateTimeString();
    }
}
