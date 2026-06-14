<?php

/**
 * AiSuggestDescriptionCommand - bulk-generate scope-and-content suggestions
 * via LlmService::generateSuggestion(), which gathers per-object context,
 * applies the prompt template, generates through the AHG AI gateway, persists
 * a pending ahg_ai_suggestion row + provenance receipt. Suggestions are queued
 * for archivist review, never auto-applied.
 *
 * No GPU node port is contacted here - LlmService owns endpoint resolution.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\LlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiSuggestDescriptionCommand extends Command
{
    protected $signature = 'ahg:ai-suggest-description
        {--object=       : Limit to a single information_object id}
        {--repository=   : Limit to one repository_id}
        {--level=        : Limit to one level_of_description type id}
        {--empty-only    : Only objects with an empty scope_and_content}
        {--with-ocr      : Reserved - prefer objects that have OCR text}
        {--limit=50      : Max objects to process}
        {--template=     : Prompt template id to use (LlmService default otherwise)}
        {--llm-config=   : LLM configuration id to use (default config otherwise)}
        {--culture=en    : i18n culture}
        {--delay=2       : Seconds to sleep between calls (rate-limit courtesy)}
        {--dry-run       : List target objects, generate nothing}';

    protected $description = 'AI description suggestions';

    public function handle(LlmService $llm): int
    {
        $culture  = (string) $this->option('culture');
        $limit    = (int) ($this->option('limit') ?: 50);
        $delay    = max(0, (int) $this->option('delay'));
        $dryRun   = (bool) $this->option('dry-run');
        $template = $this->option('template') !== null ? (int) $this->option('template') : null;
        $config   = $this->option('llm-config') !== null ? (int) $this->option('llm-config') : null;

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->orderBy('io.id')
            ->select('io.id');

        if ($this->option('object')) {
            $query->where('io.id', (int) $this->option('object'));
        }
        if ($this->option('repository')) {
            $query->where('io.repository_id', (int) $this->option('repository'));
        }
        if ($this->option('level')) {
            $query->where('io.level_of_description_id', (int) $this->option('level'));
        }
        if ($this->option('empty-only')) {
            $query->where(function ($w) {
                $w->whereNull('ioi.scope_and_content')->orWhere('ioi.scope_and_content', '=', '');
            });
        }
        if ($this->option('with-ocr')) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))->from('iiif_ocr_text')
                    ->whereColumn('iiif_ocr_text.object_id', 'io.id')
                    ->whereNotNull('iiif_ocr_text.full_text');
            });
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows  = $query->get();
        $total = $rows->count();
        if ($total === 0) {
            $this->info('No matching objects.');
            return self::SUCCESS;
        }

        $this->info("Generating description suggestions for {$total} object(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $done   = 0;
        $failed = 0;

        foreach ($rows as $i => $row) {
            if ($dryRun) {
                $this->line("  object {$row->id}: would generate suggestion");
                $done++;
                continue;
            }

            try {
                $result = $llm->generateSuggestion((int) $row->id, $template, $config);
                if (empty($result['success'])) {
                    $failed++;
                    $this->warn("Object {$row->id}: " . ($result['error'] ?? 'generation failed'));
                } else {
                    $done++;
                    $this->line("  object {$row->id}: suggestion #" . ($result['suggestion_id'] ?? '?') . ' queued for review');
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn("Object {$row->id}: " . $e->getMessage());
            }

            if ($delay > 0 && $i < $total - 1) {
                sleep($delay);
            }
        }

        $this->info(sprintf('Suggestions queued: %d, failed: %d', $done, $failed));
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
