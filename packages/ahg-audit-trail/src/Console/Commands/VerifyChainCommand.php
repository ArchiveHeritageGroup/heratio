<?php
/**
 * Heratio - walk ahg_audit_log chained rows and verify hashes + signatures.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAuditTrail\Console\Commands;

use AhgInferenceReceipts\JcsEncoder;
use AhgInferenceReceipts\Receipt;
use AhgInferenceReceipts\Signer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Companion to ai-compliance:verify-inference-log but for the audit-trail
 * chain. Walks ahg_audit_log rows where seq IS NOT NULL in ascending seq
 * order, recomputes the entry_hash from the canonical signing view, checks
 * prev_hash continuity, verifies the Ed25519 signature against the kid's
 * public key in ai_inference_key, and reports PASS / FAIL with the first
 * broken seq.
 *
 * Legacy rows (seq IS NULL) pre-date the chain and are skipped; the command
 * prints a count of them so the operator knows how much of the table is
 * non-tamper-evident.
 */
final class VerifyChainCommand extends Command
{
    protected $signature = 'auditlog:verify-chain
        {--from=     : Start verification at this seq (inclusive)}
        {--to=       : Stop verification at this seq (inclusive)}
        {--limit=    : Verify at most N receipts then stop (debug aid)}
        {--quiet-pass : Suppress per-batch progress lines on PASS}';

    protected $description = 'Walk ahg_audit_log chained rows, recompute hashes, validate Ed25519 signatures, report tampering';

    public function handle(): int
    {
        if (!Schema::hasTable('ahg_audit_log')) {
            $this->error('ahg_audit_log table does not exist - nothing to verify.');
            return self::FAILURE;
        }
        if (!Schema::hasColumn('ahg_audit_log', 'seq')) {
            $this->error('ahg_audit_log is missing the chain columns - run service-provider boot to apply install-chain.sql first.');
            return self::FAILURE;
        }

        $fromSeq = $this->intOption('from', 0);
        $toSeq = $this->intOption('to', null);
        $limit = $this->intOption('limit', null);

        $legacyCount = (int) DB::table('ahg_audit_log')->whereNull('seq')->count();
        $chainedCount = (int) DB::table('ahg_audit_log')->whereNotNull('seq')->count();

        $this->line("ahg_audit_log: {$chainedCount} chained row(s), {$legacyCount} legacy row(s, seq IS NULL, skipped).");

        if ($chainedCount === 0) {
            $this->info('No chained rows yet - nothing to verify.');
            return self::SUCCESS;
        }

        // Anchor: if --from > 0 we need the previous receipt's entry_hash to
        // confirm prev_hash continuity into the first row we walk.
        $expectedPrev = Receipt::GENESIS_PREV_HASH;
        $expectedSeq = $fromSeq;

        if ($fromSeq > 0) {
            $anchor = DB::table('ahg_audit_log')
                ->where('seq', $fromSeq - 1)
                ->first(['entry_hash']);
            if ($anchor === null) {
                $this->error("Cannot anchor verification at seq {$fromSeq} - previous row missing.");
                return self::FAILURE;
            }
            $expectedPrev = (string) $anchor->entry_hash;
        }

        $started = microtime(true);
        $checked = 0;

        $query = DB::table('ahg_audit_log')
            ->whereNotNull('seq')
            ->where('seq', '>=', $fromSeq)
            ->orderBy('seq');
        if ($toSeq !== null) {
            $query->where('seq', '<=', $toSeq);
        }

        foreach ($query->cursor() as $row) {
            if ($limit !== null && $checked >= $limit) {
                break;
            }

            $seq = (int) $row->seq;
            if ($seq !== $expectedSeq) {
                $this->reportBreak($seq, "seq gap: expected {$expectedSeq}, got {$seq}");
                return self::FAILURE;
            }
            if ((string) $row->prev_hash !== $expectedPrev) {
                $this->reportBreak($seq, "prev_hash mismatch at seq {$seq}");
                return self::FAILURE;
            }

            // Rebuild the signing view exactly as ChainedAuditWriter did.
            $signingView = [
                'v'         => Receipt::VERSION,
                'seq'       => $seq,
                'ts'        => $this->normaliseTs((string) $row->created_at),
                'prev_hash' => (string) $row->prev_hash,
                'payload'   => $this->payloadFromRow((array) $row),
                'kid'       => (string) $row->kid,
                'alg'       => Receipt::ALG,
            ];
            $recomputed = hash('sha256', JcsEncoder::encode($signingView));

            if (!hash_equals((string) $row->entry_hash, $recomputed)) {
                $this->reportBreak($seq, "entry_hash mismatch (row tampered or canonicalisation differs)");
                return self::FAILURE;
            }

            $publicKey = $this->publicKeyForKid((string) $row->kid);
            if ($publicKey === null) {
                $this->reportBreak($seq, "unknown kid '{$row->kid}' (no row in ai_inference_key)");
                return self::FAILURE;
            }

            if (!Signer::verifyBase64((string) $row->signature, hex2bin((string) $row->entry_hash), $publicKey)) {
                $this->reportBreak($seq, "Ed25519 signature invalid at seq {$seq}");
                return self::FAILURE;
            }

            $expectedSeq = $seq + 1;
            $expectedPrev = (string) $row->entry_hash;
            $checked++;

            if (!$this->option('quiet-pass') && $checked % 5000 === 0) {
                $this->line("  ... {$checked} receipts OK (latest seq {$seq})");
            }
        }

        $elapsed = number_format((microtime(true) - $started) * 1000, 1);
        $this->info("PASS - {$checked} receipts verified in {$elapsed} ms");
        return self::SUCCESS;
    }

    private function reportBreak(int $seq, string $reason): void
    {
        $this->error("FAIL at seq {$seq}: {$reason}");
        $this->line('');
        $this->line('Investigation pointers:');
        $this->line("  - inspect: SELECT id, seq, kid, created_at FROM ahg_audit_log WHERE seq = {$seq}");
        $this->line('  - tampering further down the chain is masked until this is resolved');
        $this->line('  - if the kid is unknown, confirm ai_inference_key has a row for it');
    }

    private function intOption(string $name, ?int $default): ?int
    {
        $v = $this->option($name);
        if ($v === null || $v === '') {
            return $default;
        }
        if (!is_numeric($v)) {
            return $default;
        }
        return (int) $v;
    }

    /**
     * Must mirror ChainedAuditWriter::payloadFromRow exactly - any drift
     * here means the recomputed entry_hash will not match the stored one.
     */
    private function payloadFromRow(array $row): array
    {
        static $excluded = ['id', 'seq', 'prev_hash', 'entry_hash', 'signature', 'kid', 'created_at'];

        $payload = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $excluded, true)) {
                continue;
            }
            if ($v === null) {
                continue;
            }
            if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) {
                $decoded = json_decode($v, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload[$k] = $decoded;
                    continue;
                }
            }
            $payload[$k] = $v;
        }
        return $payload;
    }

    private function normaliseTs(string $createdAt): string
    {
        // ChainedAuditWriter signs a second-precision UTC ISO timestamp
        // (Y-m-d\TH:i:s\Z) and writes the same instant to the MySQL
        // TIMESTAMP column verbatim. Rebuild the signing view ts from the
        // stored created_at exactly so the recomputed entry_hash matches.
        try {
            $dt = new \DateTimeImmutable($createdAt, new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d\TH:i:s\Z');
        } catch (Throwable $e) {
            return $createdAt;
        }
    }

    private function publicKeyForKid(string $kid): ?string
    {
        if (!Schema::hasTable('ai_inference_key')) {
            return null;
        }
        $row = DB::table('ai_inference_key')->where('kid', $kid)->first(['public_key']);
        return $row === null ? null : (string) $row->public_key;
    }
}
