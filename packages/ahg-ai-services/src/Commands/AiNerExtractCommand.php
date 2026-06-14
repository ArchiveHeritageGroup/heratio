<?php

/**
 * AiNerExtractCommand - bulk NER extraction over archival descriptions.
 *
 * Walks information_object rows that carry scope_and_content text and runs
 * NerService::extractAndRecord() on each, persisting the detected entities as
 * pending ahg_ner_entity rows for archivist review.
 *
 * NerService routes every inference call through the AHG AI gateway
 * abstraction (NER API / LLM fallback resolved from operator settings) - this
 * command never contacts a GPU node port directly.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\NerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiNerExtractCommand extends Command
{
    protected $signature = 'ahg:ai-ner
        {--limit=        : Maximum number of objects to process (0/blank = all matching)}
        {--unprocessed   : Only objects that have no ahg_ner_entity rows yet}
        {--batch=20      : Reporting batch size}
        {--culture=en    : i18n culture to read descriptions from}
        {--dry-run       : List the objects that would be processed, extract nothing}';

    protected $description = 'Extract named entities from descriptions using AI';

    public function handle(NerService $ner): int
    {
        $limit   = (int) ($this->option('limit') ?: 0);
        $batch   = max(1, (int) $this->option('batch'));
        $culture = (string) $this->option('culture');
        $dryRun  = (bool) $this->option('dry-run');
        $onlyUnprocessed = (bool) $this->option('unprocessed');

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->whereNotNull('ioi.scope_and_content')
            ->where('ioi.scope_and_content', '!=', '')
            ->orderBy('io.id')
            ->select('io.id', 'ioi.title', 'ioi.scope_and_content');

        if ($onlyUnprocessed) {
            $query->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('ahg_ner_entity')
                    ->whereColumn('ahg_ner_entity.object_id', 'io.id');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();
        $total = $rows->count();

        if ($total === 0) {
            $this->info('No matching objects with description text to process.');
            return self::SUCCESS;
        }

        $this->info("NER extraction over {$total} object(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $processed = 0;
        $entities  = 0;
        $errors    = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($rows as $row) {
            $text = trim(strip_tags((string) $row->scope_and_content));
            if ($text === '') {
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $processed++;
                $bar->advance();
                continue;
            }

            try {
                $found = $ner->extractAndRecord($text, (int) $row->id);
                // Persist as reviewable access points (ahg_ner_entity rows),
                // mirroring AiController::extractEntities. Forward $text so
                // authority-resolution context derivation gets a full match.
                $ner->createAccessPoints((int) $row->id, $found, $text);
                $entities += (is_array($found))
                    ? count($found['persons'] ?? [])
                        + count($found['organizations'] ?? [])
                        + count($found['places'] ?? [])
                        + count($found['dates'] ?? [])
                    : 0;
                $processed++;
            } catch (Throwable $e) {
                $errors++;
                $this->newLine();
                $this->warn("Object {$row->id}: " . $e->getMessage());
            }

            $bar->advance();
            if ($processed % $batch === 0) {
                $bar->setMessage("processed {$processed}");
            }
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf('Objects processed: %d, entities recorded: ~%d, errors: %d', $processed, $entities, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
