<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0
 */

declare(strict_types=1);

namespace AhgPreservation\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use AhgPreservation\Jobs\NormalizeDigitalObjectJob;
use AhgPreservation\Services\NormalizationService;

/**
 * #1385 Phase 2 - normalize digital objects that already exist (backfill).
 *
 * Walks master digital objects and produces preservation masters / access
 * copies via the rule registry. By default it queues a job per object; pass
 * --sync to run inline (useful for small sets / debugging).
 */
class NormalizeExistingCommand extends Command
{
    protected $signature = 'ahg:normalize-existing
        {--purpose=preservation : preservation|access}
        {--limit=0 : Max objects to process (0 = no limit)}
        {--mime= : Only objects with this MIME type}
        {--sync : Run inline instead of queueing}';

    protected $description = 'Backfill preservation masters / access copies for existing digital objects (#1385).';

    public function handle(NormalizationService $service): int
    {
        $purpose = $this->option('purpose') === 'access' ? 'access' : 'preservation';
        $limit = (int) $this->option('limit');
        $mime = $this->option('mime');
        $sync = (bool) $this->option('sync');

        // Master digital objects only (usage 140). Skip ones that already carry
        // a derivative of the target usage.
        $targetUsage = $purpose === 'access' ? 141 : $this->preservationMasterUsageId();

        $q = DB::table('digital_object')
            ->where('usage_id', 140)
            ->whereNotNull('mime_type');
        if ($mime) {
            $q->where('mime_type', $mime);
        }
        if ($targetUsage) {
            $q->whereNotExists(function ($sub) use ($targetUsage) {
                $sub->from('digital_object as d2')
                    ->whereColumn('d2.parent_id', 'digital_object.id')
                    ->where('d2.usage_id', $targetUsage)
                    ->selectRaw('1');
            });
        }
        if ($limit > 0) {
            $q->limit($limit);
        }

        $rows = $q->orderBy('id')->get(['id']);
        $total = $rows->count();
        if ($total === 0) {
            $this->info('Nothing to normalize.');
            return self::SUCCESS;
        }

        $this->info(($sync ? 'Running' : 'Queueing') . " {$purpose} normalization for {$total} object(s)...");
        $bar = $this->output->createProgressBar($total);
        $done = 0;

        foreach ($rows as $r) {
            try {
                if ($sync) {
                    $service->normalizeDigitalObject((int) $r->id, $purpose);
                } else {
                    NormalizeDigitalObjectJob::dispatch((int) $r->id, $purpose);
                }
                $done++;
            } catch (\Throwable $e) {
                $this->warn("DO {$r->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info(($sync ? 'Processed' : 'Queued') . " {$done}/{$total} object(s) for {$purpose} normalization.");

        return self::SUCCESS;
    }

    private function preservationMasterUsageId(): ?int
    {
        return (int) (DB::table('term_i18n')
            ->join('term', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 47)
            ->where('term_i18n.name', 'Preservation Master')
            ->value('term.id') ?? 0) ?: null;
    }
}
