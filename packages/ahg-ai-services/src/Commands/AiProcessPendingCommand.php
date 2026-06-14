<?php

/**
 * AiProcessPendingCommand - drain the ahg_ai_pending_extraction fallback queue.
 *
 * Rows land here when an upload auto-triggers AI work but no Gearman worker is
 * available. This command claims pending rows of the requested task-type and
 * runs them through the matching service (NER -> NerService) which routes all
 * inference through the AHG AI gateway - never a node port.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\NerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiProcessPendingCommand extends Command
{
    protected $signature = 'ahg:ai-process-pending
        {--limit=50      : Max queued rows to process}
        {--task-type=ner : Queue task_type to drain (currently only "ner" is supported)}
        {--culture=en    : i18n culture to read description text from}
        {--dry-run       : List the rows that would be processed, run nothing}';

    protected $description = 'Process pending AI queue';

    public function handle(NerService $ner): int
    {
        $taskType = (string) $this->option('task-type');
        $limit    = max(1, (int) ($this->option('limit') ?: 50));
        $culture  = (string) $this->option('culture');
        $dryRun    = (bool) $this->option('dry-run');

        if (!DB::getSchemaBuilder()->hasTable('ahg_ai_pending_extraction')) {
            $this->error('Queue table ahg_ai_pending_extraction is missing. Run ahg:ai-install first.');
            return self::FAILURE;
        }

        if ($taskType !== 'ner') {
            $this->error("Unsupported task-type '{$taskType}'. Only 'ner' is wired to a service today.");
            return self::FAILURE;
        }

        $rows = DB::table('ahg_ai_pending_extraction')
            ->where('status', 'pending')
            ->where('task_type', $taskType)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $total = $rows->count();
        if ($total === 0) {
            $this->info("No pending '{$taskType}' rows in the queue.");
            return self::SUCCESS;
        }

        $this->info("Processing {$total} pending '{$taskType}' row(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $done   = 0;
        $failed = 0;
        $empty  = 0;

        foreach ($rows as $row) {
            $text = DB::table('information_object_i18n')
                ->where('id', $row->object_id)
                ->where('culture', $culture)
                ->value('scope_and_content');
            $text = trim(strip_tags((string) $text));

            if ($dryRun) {
                $this->line("  queue#{$row->id} object={$row->object_id} (" . mb_strlen($text) . ' chars)');
                continue;
            }

            if ($text === '') {
                $empty++;
                $this->markRow((int) $row->id, 'skipped', 'no description text');
                continue;
            }

            // Claim the row.
            DB::table('ahg_ai_pending_extraction')->where('id', $row->id)->update([
                'status'        => 'processing',
                'attempt_count' => DB::raw('attempt_count + 1'),
            ]);

            try {
                $entities = $ner->extractAndRecord($text, (int) $row->object_id, null, $row->digital_object_id ? (int) $row->digital_object_id : null);
                $ner->createAccessPoints((int) $row->object_id, $entities, $text);
                $this->markRow((int) $row->id, 'completed');
                $done++;
            } catch (Throwable $e) {
                $failed++;
                $this->markRow((int) $row->id, 'failed', $e->getMessage());
                $this->warn("queue#{$row->id}: " . $e->getMessage());
            }
        }

        $this->info(sprintf('Completed: %d, empty/skipped: %d, failed: %d', $done, $empty, $failed));
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function markRow(int $id, string $status, ?string $error = null): void
    {
        DB::table('ahg_ai_pending_extraction')->where('id', $id)->update([
            'status'        => $status,
            'error_message' => $error,
            'processed_at'  => now(),
        ]);
    }
}
