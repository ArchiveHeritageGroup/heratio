<?php

/**
 * AiSummarizeCommand - generate scope-and-content summaries from existing
 * description text using LlmService::summarize().
 *
 * Selects information_object rows (single object, whole repository, or every
 * record with an empty target field) and writes the summary back into the
 * chosen information_object_i18n column.
 *
 * LlmService::summarize() routes the inference through the operator AI
 * configuration and the AHG AI gateway - no GPU node port is contacted here.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\LlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiSummarizeCommand extends Command
{
    /** Columns we are willing to read source text from / write summaries to. */
    private const ALLOWED_FIELDS = [
        'scope_and_content', 'archival_history', 'arrangement', 'physical_characteristics',
    ];

    protected $signature = 'ahg:ai-summarize
        {--all-empty                 : Only objects whose target field is empty}
        {--field=scope_and_content   : i18n field to write the summary into}
        {--source=                   : Source field to summarise (defaults to the same field; use ocr_text/scope_and_content)}
        {--object=                   : Limit to a single information_object id}
        {--repository=               : Limit to one repository_id}
        {--limit=                    : Max objects to process (0/blank = all)}
        {--culture=en                : i18n culture}
        {--dry-run                   : Show what would change, write nothing}';

    protected $description = 'OCR transcript to scope summary';

    public function handle(LlmService $llm): int
    {
        $field   = (string) $this->option('field');
        $source  = (string) ($this->option('source') ?: $field);
        $culture = (string) $this->option('culture');
        $limit   = (int) ($this->option('limit') ?: 0);
        $dryRun  = (bool) $this->option('dry-run');

        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            $this->error("Refusing to write to unknown/unsafe field '{$field}'. Allowed: " . implode(', ', self::ALLOWED_FIELDS));
            return self::FAILURE;
        }
        if (!in_array($source, array_merge(self::ALLOWED_FIELDS, ['ocr_text']), true)) {
            $this->error("Unknown source field '{$source}'.");
            return self::FAILURE;
        }

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->whereNotNull("ioi.{$source}")
            ->where("ioi.{$source}", '!=', '')
            ->orderBy('io.id')
            ->select('io.id', "ioi.{$source} as source_text", "ioi.{$field} as target_text");

        if ($this->option('object')) {
            $query->where('io.id', (int) $this->option('object'));
        }
        if ($this->option('repository')) {
            $query->where('io.repository_id', (int) $this->option('repository'));
        }
        if ($this->option('all-empty')) {
            $query->where(function ($w) use ($field) {
                $w->whereNull("ioi.{$field}")->orWhere("ioi.{$field}", '=', '');
            });
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows  = $query->get();
        $total = $rows->count();
        if ($total === 0) {
            $this->info('No matching objects to summarise.');
            return self::SUCCESS;
        }

        $this->info("Summarising {$total} object(s) -> {$field}" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            $src = trim(strip_tags((string) $row->source_text));
            if ($src === '') {
                $skipped++;
                continue;
            }

            try {
                $summary = $llm->summarize($src);
            } catch (Throwable $e) {
                $errors++;
                $this->warn("Object {$row->id}: " . $e->getMessage());
                continue;
            }

            if ($summary === null || trim($summary) === '') {
                $skipped++;
                $this->line("  object {$row->id}: no summary returned (gate off or empty)");
                continue;
            }

            if ($dryRun) {
                $this->line("  object {$row->id}: would write " . mb_strlen($summary) . ' chars');
                $updated++;
                continue;
            }

            DB::table('information_object_i18n')
                ->where('id', $row->id)
                ->where('culture', $culture)
                ->update([$field => $summary]);
            $updated++;
        }

        $this->info(sprintf('Updated: %d, skipped: %d, errors: %d', $updated, $skipped, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
