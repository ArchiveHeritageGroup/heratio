<?php

/**
 * AiNerSyncCommand - export NER corrections for model retraining.
 *
 * Ported (partially) from the AtoM ahgAIPlugin ai:ner-sync task. The
 * ahg_ner_entity table records archivist corrections (correction_type,
 * original_value/original_type, training_exported). This command exports the
 * not-yet-exported corrections to a JSONL training file and marks them
 * exported.
 *
 *   --export   Export pending corrections to a JSONL file (DEFAULT action).
 *   --stats    Show correction statistics and exit.
 *   --retrain  NEEDS-DECISION: pushing corrections to a central training
 *              server requires a configured endpoint + the NerTrainingSync
 *              service, neither of which exists in Heratio yet. Per the AHG
 *              gateway rule, any such push must route through the gateway -
 *              there is no such route today. This option fails loudly rather
 *              than reporting a false success. Tracked for #1268 follow-up.
 *
 * Export is pure DB/filesystem work - no AI inference, so no gateway concern.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiNerSyncCommand extends Command
{
    protected $signature = 'ahg:ai-ner-sync
        {--export    : Export pending corrections to a JSONL training file (default)}
        {--retrain   : Push corrections to a central training server (NOT yet wired - see #1268)}
        {--stats     : Show correction statistics and exit}
        {--out=      : Output path for --export (default: storage/app/ner-training/corrections-<ts>.jsonl)}
        {--dry-run   : Count what would be exported, write nothing}';

    protected $description = 'Sync NER training data';

    public function handle(): int
    {
        if (!Schema::hasTable('ahg_ner_entity')) {
            $this->error('ahg_ner_entity table is missing (run ahg:ai-install).');
            return self::FAILURE;
        }

        if ($this->option('stats')) {
            return $this->showStats();
        }

        if ($this->option('retrain')) {
            // Honest failure - no backing service / configured endpoint exists.
            $this->error('--retrain is not implemented: Heratio has no NerTrainingSync service or configured training endpoint.');
            $this->warn('Any future push must route through the AHG AI gateway, not a direct node. Use --export and ship the file manually for now (#1268).');
            return self::FAILURE;
        }

        // Default action: export.
        return $this->export();
    }

    private function export(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('ahg_ner_entity')
            ->where('training_exported', 0)
            ->whereIn('correction_type', ['value_edit', 'type_change', 'both', 'rejected', 'approved'])
            ->orderBy('id')
            ->get();

        $count = $rows->count();
        if ($count === 0) {
            $this->info('No new corrections to export.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[DRY RUN] Would export {$count} correction(s).");
            return self::SUCCESS;
        }

        $out = (string) ($this->option('out') ?: storage_path('app/ner-training/corrections-' . date('Ymd-His') . '.jsonl'));
        $dir = dirname($out);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("Could not create output directory: {$dir}");
            return self::FAILURE;
        }

        $fh = @fopen($out, 'w');
        if ($fh === false) {
            $this->error("Could not open output file for writing: {$out}");
            return self::FAILURE;
        }

        $ids = [];
        foreach ($rows as $r) {
            fwrite($fh, json_encode([
                'object_id'       => (int) $r->object_id,
                'entity_value'    => $r->entity_value,
                'entity_type'     => $r->entity_type,
                'original_value'  => $r->original_value,
                'original_type'   => $r->original_type,
                'correction_type' => $r->correction_type,
                'confidence'      => (float) $r->confidence,
            ], JSON_UNESCAPED_UNICODE) . "\n");
            $ids[] = (int) $r->id;
        }
        fclose($fh);

        DB::table('ahg_ner_entity')->whereIn('id', $ids)->update(['training_exported' => 1]);

        $this->info("Exported {$count} correction(s) to {$out}");
        return self::SUCCESS;
    }

    private function showStats(): int
    {
        $this->info('NER Training Statistics');
        $rows = DB::table('ahg_ner_entity')
            ->select(
                'correction_type',
                DB::raw('COUNT(*) as c'),
                DB::raw('SUM(training_exported) as exported')
            )
            ->groupBy('correction_type')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  (no corrections recorded)');
            return self::SUCCESS;
        }

        foreach ($rows as $s) {
            $this->line(sprintf(
                '  %s: %d total, %d exported, %d pending',
                $s->correction_type,
                (int) $s->c,
                (int) $s->exported,
                (int) $s->c - (int) $s->exported
            ));
        }
        return self::SUCCESS;
    }
}
