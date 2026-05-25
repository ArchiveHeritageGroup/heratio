<?php
/**
 * Heratio - audit the AI inference chain end-to-end (Article 12 verifier).
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Models\AiInferenceLog;
use AhgInferenceReceipts\ReceiptChain;
use Illuminate\Console\Command;

final class VerifyInferenceLogCommand extends Command
{
    protected $signature = 'ai-compliance:verify-inference-log
        {--from=     : Start verification from this ISO date (inclusive)}
        {--to=       : Stop verification at this ISO date (inclusive)}
        {--service=  : Restrict to a specific service (llm/htr/ner/donut/guardrail)}
        {--quiet-pass : Suppress per-chunk progress on PASS}';

    protected $description = 'Walk the ai_inference_log chain, recompute hashes, validate signatures, report tampering';

    public function handle(ReceiptChain $chain): int
    {
        $fromSeq = $this->resolveSeq($this->option('from'), 'from');
        $toSeq = $this->resolveSeq($this->option('to'), 'to');

        $count = AiInferenceLog::query()->count();
        if ($count === 0) {
            $this->info('ai_inference_log is empty; nothing to verify.');
            return self::SUCCESS;
        }

        if (!$this->option('quiet-pass')) {
            $rangeMsg = "Verifying chain (rows: {$count}";
            if ($fromSeq !== null) {
                $rangeMsg .= ", from seq {$fromSeq}";
            }
            if ($toSeq !== null) {
                $rangeMsg .= ", to seq {$toSeq}";
            }
            $rangeMsg .= ')...';
            $this->line($rangeMsg);
        }

        $started = microtime(true);
        $result = $chain->verify($fromSeq ?? 0, $toSeq);
        $elapsed = number_format((microtime(true) - $started) * 1000, 1);

        if ($result->ok) {
            $this->info("PASS - {$result->checkedCount} receipts verified in {$elapsed} ms");
            return self::SUCCESS;
        }

        $this->error("FAIL at seq {$result->brokenAtSeq}: {$result->reason}");
        $this->line('');
        $this->line('Investigation pointers:');
        $this->line("  - inspect: SELECT * FROM ai_inference_log WHERE seq = {$result->brokenAtSeq}");
        $this->line('  - the failure point is the FIRST broken receipt; tampering further down is masked until this is resolved');
        $this->line('  - if the kid is unknown, check the ai_inference_key table');
        return self::FAILURE;
    }

    private function resolveSeq(mixed $iso, string $bound): ?int
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        $direction = $bound === 'from' ? '>=' : '<=';

        $row = AiInferenceLog::query()
            ->where('ts', $direction, $iso)
            ->orderBy('seq', $bound === 'from' ? 'asc' : 'desc')
            ->first(['seq']);

        return $row === null ? null : (int) $row->seq;
    }
}
