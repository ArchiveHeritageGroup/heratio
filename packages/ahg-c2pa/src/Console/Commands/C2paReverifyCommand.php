<?php
/**
 * Heratio - periodic C2PA integrity re-verification (issues #1209 / #1201).
 *
 * Re-checks every ALREADY-SIGNED provenance record against its bound manifest:
 * re-hashes the assertions and re-validates the Ed25519 claim signature via
 * ProvenanceRecordService::verifyRecord(). Where a record that used to verify
 * no longer does (tampering, bit-rot, key rotation, manifest corruption) it
 * flips the cached `sign_status` to 'invalid' and logs a WARNING naming the
 * digital object, so the drift is caught over time and flows straight into the
 * authenticity coverage report (CoverageReportService treats 'invalid' as a
 * failed record) and the truth-anchor mission.
 *
 * Distinct from the sibling commands:
 *   - ahg:c2pa-provenance-backfill CREATES missing provenance records.
 *   - c2pa:verify checks ONE sidecar file off disk.
 *   - ahg:c2pa-embed (re)writes JUMBF embeds for signed records.
 * This command RE-VERIFIES records that are already signed and UPDATES their
 * cached status; it never creates, signs, or embeds anything.
 *
 * Developer / cron command. Bounded: streams via a DB cursor, never loads all
 * rows into memory, honours --limit and a hard ceiling, and --dry-run computes
 * the result without writing. Resilient: a missing table is a clean exit, and a
 * single bad record is caught + counted, never aborting the run.
 *
 * The only write is the `sign_status` update on ahg_c2pa_provenance (its own
 * table); nothing else is touched.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgC2pa\Console\Commands;

use AhgC2pa\Services\ProvenanceRecordService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Re-verify already-signed C2PA provenance records and refresh their cached
 * sign_status, flagging any whose content credentials no longer validate.
 */
final class C2paReverifyCommand extends Command
{
    /**
     * Hard ceiling on rows processed in a single run, even when --limit is 0 or
     * larger. Keeps an unattended cron run bounded no matter how big the table
     * grows; re-run on the next schedule to continue the sweep.
     */
    private const CEILING = 100000;

    protected $signature = 'ahg:c2pa-reverify '
        . '{--limit=0 : Max number of signed records to re-verify (0 = up to the built-in ceiling)} '
        . '{--dry-run : Compute the live verification result without writing any sign_status change}';

    protected $description = 'Re-verify already-signed C2PA provenance records and flag any whose content credentials no longer validate';

    public function handle(ProvenanceRecordService $prov): int
    {
        if (!Schema::hasTable('ahg_c2pa_provenance')) {
            $this->info('C2PA provenance table not installed; nothing to re-verify.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $limit = (int) $this->option('limit');
        if ($limit <= 0 || $limit > self::CEILING) {
            $limit = self::CEILING;
        }

        $this->info(($dryRun ? 'DRY-RUN' : 'LIVE') . ': re-verifying up to ' . $limit . ' signed provenance record(s)');

        $checked = 0;
        $stillVerified = 0;
        $newlyInvalid = 0;
        $errors = 0;

        try {
            // Bounded streaming cursor over signed records only (manifest_id set).
            // cursor() pulls one row at a time, so the whole table is never
            // materialised in memory even when the ceiling is large.
            $cursor = DB::table('ahg_c2pa_provenance')
                ->whereNotNull('manifest_id')
                ->orderBy('id')
                ->select(['id', 'digital_object_id', 'information_object_id', 'sign_status'])
                ->cursor();

            foreach ($cursor as $row) {
                if ($checked >= $limit) {
                    break;
                }
                $checked++;

                // Snapshot the previously-cached status: anything that is NOT an
                // explicit failure marker counts as "was verified/considered ok"
                // for the integrity-changed alert below.
                $wasInvalid = $this->isInvalidStatus((string) ($row->sign_status ?? ''));

                try {
                    $result = $prov->verifyRecord((int) $row->id);
                } catch (Throwable $e) {
                    // Never let one bad record abort the sweep.
                    $errors++;
                    Log::warning('c2pa-reverify: verifyRecord threw', [
                        'provenance_id'     => (int) $row->id,
                        'digital_object_id' => $row->digital_object_id ?? null,
                        'err'               => $e->getMessage(),
                    ]);
                    $this->error("  #{$row->id} (do#" . ($row->digital_object_id ?? '-') . ') - verify threw: ' . $e->getMessage());
                    continue;
                }

                $ok = (bool) ($result['ok'] ?? false);

                if ($ok) {
                    $stillVerified++;
                    if (!$dryRun) {
                        $this->setStatus((int) $row->id, 'verified', $errors);
                    }
                    continue;
                }

                // Not ok. Count it as newly-invalid only when it was previously
                // considered good - a record that was already flagged invalid is
                // not a NEW integrity change, just a persistent failure.
                if (!$wasInvalid) {
                    $newlyInvalid++;
                    $reasons = $this->reasonText($result['errors'] ?? []);
                    Log::warning('c2pa-reverify: INTEGRITY CHANGED - content credentials no longer validate', [
                        'provenance_id'      => (int) $row->id,
                        'digital_object_id'  => $row->digital_object_id ?? null,
                        'information_object' => $row->information_object_id ?? null,
                        'previous_status'    => (string) ($row->sign_status ?? ''),
                        'verify_status'      => (string) ($result['status'] ?? 'failed'),
                        'errors'             => $reasons,
                    ]);
                    $this->warn(
                        "  INTEGRITY CHANGED  #{$row->id} digital object #"
                        . ($row->digital_object_id ?? '-')
                        . ' (IO #' . ($row->information_object_id ?? '-') . ') no longer validates: ' . $reasons
                    );
                }

                if (!$dryRun) {
                    $this->setStatus((int) $row->id, 'invalid', $errors);
                }
            }
        } catch (Throwable $e) {
            // A failure setting up / iterating the cursor itself: report whatever
            // we managed and exit cleanly rather than fatalling.
            Log::warning('c2pa-reverify: sweep aborted', ['err' => $e->getMessage()]);
            $this->error('Re-verify sweep aborted: ' . $e->getMessage());
        }

        $this->line('');
        $this->info('Re-verify summary' . ($dryRun ? ' (dry-run, no writes)' : '') . ':');
        $this->line("  checked:        {$checked}");
        $this->line("  still-verified: {$stillVerified}");
        $this->line("  newly-invalid:  {$newlyInvalid}");
        $this->line("  errors:         {$errors}");

        if ($newlyInvalid > 0) {
            $this->warn("{$newlyInvalid} record(s) changed from verified to INVALID - see the warning log for the affected digital objects.");
        }

        return self::SUCCESS;
    }

    /**
     * Apply the cached sign_status update on ahg_c2pa_provenance - the ONLY
     * write this command performs. Skips the write when the status is already
     * the target value (cheap no-op avoidance) and never throws: a failed update
     * is counted as an error and the sweep continues.
     */
    private function setStatus(int $id, string $status, int &$errors): void
    {
        try {
            DB::table('ahg_c2pa_provenance')
                ->where('id', $id)
                ->where('sign_status', '<>', $status)
                ->update([
                    'sign_status' => $status,
                    'updated_at'  => date('Y-m-d H:i:s.v'),
                ]);
        } catch (Throwable $e) {
            $errors++;
            Log::warning('c2pa-reverify: sign_status update failed', [
                'provenance_id' => $id,
                'status'        => $status,
                'err'           => $e->getMessage(),
            ]);
            $this->error("  #{$id} - could not update sign_status: " . $e->getMessage());
        }
    }

    /**
     * Whether a cached sign_status string is an explicit failure marker. Mirrors
     * the failure vocabulary CoverageReportService::signedSplit() treats as
     * invalid, so the two stay in lock-step.
     */
    private function isInvalidStatus(string $status): bool
    {
        return in_array(strtolower(trim($status)), ['invalid', 'failed', 'tampered', 'error'], true);
    }

    /**
     * Compact, single-line human reason from the verify errors list.
     *
     * @param mixed $errors
     */
    private function reasonText(mixed $errors): string
    {
        if (!is_array($errors) || $errors === []) {
            return 'signature/assertion check failed';
        }

        $parts = [];
        foreach ($errors as $e) {
            $s = trim((string) $e);
            if ($s !== '') {
                $parts[] = $s;
            }
        }

        return $parts === [] ? 'signature/assertion check failed' : implode('; ', $parts);
    }
}
