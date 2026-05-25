<?php
/**
 * Heratio - retention pruner for the AI inference chain.
 *
 * Nulls out payload_json on rows past the configured retention window so
 * PII does not linger forever, while preserving seq/prev_hash/entry_hash/
 * signature so the chain remains structurally verifiable indefinitely.
 *
 * @copyright Copyright (c) 2026, The Archive and Heritage Group (Pty) Ltd
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgAiCompliance\Console\Commands;

use AhgAiCompliance\Models\AiInferenceLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PruneCommand extends Command
{
    protected $signature = 'ai-compliance:prune
        {--years=  : Override the configured retention window (years)}
        {--dry-run : Report what would be pruned without writing}';

    protected $description = 'Null payload_json on inference-log rows older than the retention window';

    public function handle(): int
    {
        $configured = $this->option('years') !== null
            ? (float) $this->option('years')
            : (float) (DB::table('ahg_setting')->where('key', 'ai_compliance.retention_years')->value('value') ?? 7);

        $threshold = now()->subDays((int) round($configured * 365));

        $eligible = AiInferenceLog::query()
            ->where('ts', '<', $threshold)
            ->whereNull('payload_pruned_at');

        $count = (int) $eligible->count();

        $this->line("Retention window:  {$configured} years");
        $this->line("Threshold:         {$threshold->toIso8601String()}");
        $this->line("Rows to prune:     {$count}");

        if ($count === 0) {
            $this->info('Nothing to prune.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('Dry run; no rows touched.');
            return self::SUCCESS;
        }

        $updated = $eligible->update([
            'payload_json'      => null,
            'payload_pruned_at' => now(),
        ]);

        $this->info("Pruned {$updated} rows (hash + signature + chain links preserved).");
        return self::SUCCESS;
    }
}
