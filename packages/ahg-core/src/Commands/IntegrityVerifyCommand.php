<?php

namespace AhgCore\Commands;

use AhgIntegrity\Services\FixityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrityVerifyCommand extends Command
{
    protected $signature = 'ahg:integrity-verify
        {--object-id= : Verify specific digital object}
        {--schedule-id= : Run specific schedule}
        {--repository-id= : Limit to repository}
        {--limit=200 : Maximum objects to verify}
        {--stale-days=7 : Reverify if older than N days}
        {--all : Verify all objects}
        {--throttle=10 : Milliseconds between verifications}
        {--status : Show verification status}
        {--dry-run : Simulate without executing}';

    protected $description = 'Ad-hoc fixity verification of digital objects';

    public function handle(): int
    {
        $fixity = app(FixityService::class);

        // --status: show stats
        if ($this->option('status')) {
            return $this->showStatus();
        }

        $algorithm = 'sha256';
        $limit = (int) $this->option('limit');
        $throttle = (int) $this->option('throttle');
        $dryRun = $this->option('dry-run');
        $repositoryId = $this->option('repository-id') ? (int) $this->option('repository-id') : null;
        $staleDays = (int) $this->option('stale-days');

        // Determine schedule if provided
        $schedule = null;
        if ($this->option('schedule-id')) {
            $scheduleId = (int) $this->option('schedule-id');
            if (Schema::hasTable('integrity_schedule')) {
                $schedule = DB::table('integrity_schedule')->where('id', $scheduleId)->first();
            }
            if (!$schedule) {
                $this->error('Schedule #' . $scheduleId . ' not found.');
                return 1;
            }
            $algorithm = $schedule->algorithm ?? 'sha256';
            $limit = $schedule->batch_size ?? $limit;
            $throttle = $schedule->io_throttle_ms ?? $throttle;
            $repositoryId = $schedule->repository_id ?? $repositoryId;
        }

        // Gather objects to verify
        $objectIds = [];

        if ($this->option('object-id')) {
            $objectIds = [(int) $this->option('object-id')];
        } elseif ($this->option('all')) {
            $objectIds = DB::table('digital_object')
                ->pluck('id')
                ->toArray();
            if ($limit > 0 && count($objectIds) > $limit) {
                $objectIds = array_slice($objectIds, 0, $limit);
            }
        } else {
            $staleObjects = $fixity->getStaleObjects($staleDays, $repositoryId, $limit);
            $objectIds = array_column($staleObjects, 'digital_object_id');
        }

        if (empty($objectIds)) {
            $this->info('No objects to verify.');
            return 0;
        }

        $this->info('Verifying ' . count($objectIds) . ' object(s) with ' . $algorithm . ($dryRun ? ' [DRY RUN]' : '') . '...');

        if ($dryRun) {
            foreach ($objectIds as $id) {
                $path = $fixity->getDigitalObjectPath($id);
                $this->line('  [DRY] Would verify digital_object #' . $id . ' => ' . ($path ?? 'no path'));
            }
            $this->info('Dry run complete. ' . count($objectIds) . ' object(s) would be verified.');
            return 0;
        }

        // Create integrity_run record
        $runId = null;
        if (Schema::hasTable('integrity_run')) {
            $runId = DB::table('integrity_run')->insertGetId([
                'schedule_id'       => $schedule->id ?? null,
                'status'            => 'running',
                'algorithm'         => $algorithm,
                'objects_scanned'   => 0,
                'objects_passed'    => 0,
                'objects_failed'    => 0,
                'objects_missing'   => 0,
                'objects_error'     => 0,
                'objects_skipped'   => 0,
                'bytes_scanned'     => 0,
                'triggered_by'     => $schedule ? 'schedule' : 'manual',
                'triggered_by_user' => 'CLI',
                'lock_token'        => bin2hex(random_bytes(16)),
                'started_at'        => now(),
                'created_at'        => now(),
            ]);
        }

        $bar = $this->output->createProgressBar(count($objectIds));
        $bar->start();

        $passed = 0;
        $failed = 0;
        $missing = 0;
        $errors = 0;
        $skipped = 0;
        $bytesScanned = 0;

        foreach ($objectIds as $doId) {
            $result = $fixity->verifyObject($doId, $algorithm);

            // Get IO and repo IDs
            $doRow = DB::table('digital_object')->where('id', $doId)->first(['object_id']);
            $ioId = $doRow->object_id ?? null;
            $repoId = null;
            if ($ioId) {
                $repoId = DB::table('information_object')->where('id', $ioId)->value('repository_id');
            }

            // Get previous hash for chain
            $previousHash = null;
            $chainValid = 1;
            if (Schema::hasTable('integrity_ledger') && Schema::hasColumn('integrity_ledger', 'previous_hash')) {
                $lastLedger = DB::table('integrity_ledger')
                    ->orderBy('id', 'desc')
                    ->first(['computed_hash']);
                $previousHash = $lastLedger->computed_hash ?? null;
            }

            // Insert ledger entry
            if (Schema::hasTable('integrity_ledger')) {
                $ledgerData = [
                    'run_id'            => $runId,
                    'digital_object_id' => $doId,
                    'information_object_id' => $ioId,
                    'repository_id'     => $repoId,
                    'file_path'         => $result['file_path'],
                    'file_size'         => $result['file_size'],
                    'file_exists'       => $result['file_exists'] ? 1 : 0,
                    'file_readable'     => $result['file_readable'] ? 1 : 0,
                    'algorithm'         => $algorithm,
                    'expected_hash'     => $result['expected'],
                    'computed_hash'     => $result['actual'],
                    'hash_match'        => $result['passed'] ? 1 : 0,
                    'outcome'           => $result['outcome'],
                    'error_detail'      => $result['error'],
                    'duration_ms'       => $result['duration_ms'],
                    'verified_at'       => now(),
                ];

                if (Schema::hasColumn('integrity_ledger', 'previous_hash')) {
                    $ledgerData['previous_hash'] = $previousHash;
                    $ledgerData['chain_valid'] = $chainValid;
                }

                DB::table('integrity_ledger')->insert($ledgerData);
            }

            // Track stats
            $bytesScanned += $result['file_size'] ?? 0;

            switch ($result['outcome']) {
                case 'pass':
                    $passed++;
                    break;
                case 'fail':
                    $failed++;
                    $this->upsertDeadLetter($doId, 'hash_mismatch', $result['error'] ?? 'Hash mismatch', $runId);
                    break;
                case 'missing':
                    $missing++;
                    $this->upsertDeadLetter($doId, 'file_missing', $result['error'] ?? 'File missing', $runId);
                    break;
                case 'error':
                    $errors++;
                    $this->upsertDeadLetter($doId, 'verification_error', $result['error'] ?? 'Verification error', $runId);
                    break;
                default:
                    $skipped++;
                    break;
            }

            $bar->advance();

            if ($throttle > 0) {
                usleep($throttle * 1000);
            }
        }

        $bar->finish();
        $this->newLine();

        // Update run record
        if ($runId && Schema::hasTable('integrity_run')) {
            DB::table('integrity_run')->where('id', $runId)->update([
                'status'          => 'completed',
                'objects_scanned' => count($objectIds),
                'objects_passed'  => $passed,
                'objects_failed'  => $failed,
                'objects_missing' => $missing,
                'objects_error'   => $errors,
                'objects_skipped' => $skipped,
                'bytes_scanned'   => $bytesScanned,
                'completed_at'    => now(),
            ]);
        }

        $this->info("Verification complete: {$passed} passed, {$failed} failed, {$missing} missing, {$errors} errors, {$skipped} skipped.");
        $this->info('Bytes scanned: ' . number_format($bytesScanned));

        return ($failed > 0 || $errors > 0) ? 1 : 0;
    }

    private function showStatus(): int
    {
        if (!Schema::hasTable('integrity_ledger')) {
            $this->warn('No integrity_ledger table found.');
            return 0;
        }

        $total = DB::table('integrity_ledger')->count();
        $passCount = DB::table('integrity_ledger')->where('outcome', 'pass')->count();
        $failCount = DB::table('integrity_ledger')->where('outcome', 'fail')->count();
        $missingCount = DB::table('integrity_ledger')->where('outcome', 'missing')->count();
        $passRate = $total > 0 ? round(($passCount / $total) * 100, 1) : 0;

        $lastRun = null;
        if (Schema::hasTable('integrity_run')) {
            $lastRun = DB::table('integrity_run')->orderBy('started_at', 'desc')->first();
        }

        $this->info('=== Integrity Verification Status ===');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total verified', number_format($total)],
                ['Passed', number_format($passCount)],
                ['Failed', number_format($failCount)],
                ['Missing', number_format($missingCount)],
                ['Pass rate', $passRate . '%'],
                ['Last run', $lastRun ? $lastRun->started_at . ' (' . $lastRun->status . ')' : 'Never'],
            ]
        );

        return 0;
    }

    private function upsertDeadLetter(int $doId, string $failureType, string $errorDetail, ?int $runId): void
    {
        if (!Schema::hasTable('integrity_dead_letter')) {
            return;
        }

        $existing = DB::table('integrity_dead_letter')
            ->where('digital_object_id', $doId)
            ->where('status', 'open')
            ->first();

        if ($existing) {
            DB::table('integrity_dead_letter')
                ->where('id', $existing->id)
                ->update([
                    'consecutive_failures' => $existing->consecutive_failures + 1,
                    'last_failure_at'      => now(),
                    'last_error_detail'    => $errorDetail,
                    'last_run_id'          => $runId,
                    'retry_count'          => $existing->retry_count + 1,
                    'updated_at'           => now(),
                ]);
        } else {
            DB::table('integrity_dead_letter')->insert([
                'digital_object_id'    => $doId,
                'failure_type'         => $failureType,
                'status'               => 'open',
                'consecutive_failures' => 1,
                'first_failure_at'     => now(),
                'last_failure_at'      => now(),
                'last_error_detail'    => $errorDetail,
                'last_run_id'          => $runId,
                'retry_count'          => 0,
                'max_retries'          => 3,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        }
    }
}
