<?php

/**
 * AiSpellcheckCommand - run LlmService::spellcheck() over archival
 * descriptions and record the suggested corrections into
 * ahg_spellcheck_result (status='pending') for archivist review.
 *
 * This command is non-destructive: it never rewrites description text. It only
 * records suggestions. LlmService::spellcheck() routes the call through the
 * operator AI configuration and the AHG AI gateway - no node port here.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\LlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiSpellcheckCommand extends Command
{
    protected $signature = 'ahg:ai-spellcheck
        {--object=       : Limit to a single information_object id}
        {--repository=   : Limit to one repository_id}
        {--all           : Process every object with description text}
        {--limit=100     : Max objects to process}
        {--culture=en    : i18n culture}
        {--dry-run       : Report findings, do not write ahg_spellcheck_result rows}';

    protected $description = 'Spellcheck records';

    public function handle(LlmService $llm): int
    {
        $culture = (string) $this->option('culture');
        $limit   = (int) ($this->option('limit') ?: 100);
        $dryRun  = (bool) $this->option('dry-run');

        if (!$this->option('object') && !$this->option('repository') && !$this->option('all')) {
            $this->error('Specify a scope: --object=ID, --repository=ID, or --all.');
            return self::FAILURE;
        }

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', $culture);
            })
            ->where('io.id', '!=', 1)
            ->whereNotNull('ioi.scope_and_content')
            ->where('ioi.scope_and_content', '!=', '')
            ->orderBy('io.id')
            ->select('io.id', 'ioi.scope_and_content');

        if ($this->option('object')) {
            $query->where('io.id', (int) $this->option('object'));
        }
        if ($this->option('repository')) {
            $query->where('io.repository_id', (int) $this->option('repository'));
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows  = $query->get();
        $total = $rows->count();
        if ($total === 0) {
            $this->info('No matching objects with description text.');
            return self::SUCCESS;
        }

        $this->info("Spellchecking {$total} object(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $checked   = 0;
        $withIssue = 0;
        $errors    = 0;

        foreach ($rows as $row) {
            $text = trim(strip_tags((string) $row->scope_and_content));
            if ($text === '') {
                continue;
            }

            try {
                $corrections = $llm->spellcheck($text, ['lang' => $culture]);
            } catch (Throwable $e) {
                $errors++;
                $this->warn("Object {$row->id}: " . $e->getMessage());
                continue;
            }

            $checked++;
            $count = count($corrections);
            if ($count === 0) {
                continue;
            }
            $withIssue++;

            if ($dryRun) {
                $this->line("  object {$row->id}: {$count} suggestion(s)");
                continue;
            }

            DB::table('ahg_spellcheck_result')->insert([
                'object_id'   => (int) $row->id,
                'errors_json' => json_encode($corrections, JSON_UNESCAPED_UNICODE),
                'error_count' => $count,
                'status'      => 'pending',
                'created_at'  => now(),
            ]);
        }

        $this->info(sprintf('Checked: %d, with suggestions: %d, errors: %d', $checked, $withIssue, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
