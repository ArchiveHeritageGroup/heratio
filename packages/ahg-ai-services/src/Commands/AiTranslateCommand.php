<?php

/**
 * AiTranslateCommand - machine-translate archival description fields from one
 * culture into another using LlmService::translate().
 *
 * For each source-culture information_object_i18n row it translates the core
 * descriptive fields and upserts a target-culture i18n row. Translation runs
 * through LlmService, which checks the translation-memory cache and routes the
 * actual call through the MT adapter / LLM via the AHG AI gateway - never a
 * direct node port.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use AhgAiServices\Services\LlmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class AiTranslateCommand extends Command
{
    /** Free-text descriptive fields worth translating. */
    private const FIELDS = [
        'title', 'scope_and_content', 'archival_history', 'arrangement',
        'extent_and_medium', 'physical_characteristics',
    ];

    protected $signature = 'ahg:ai-translate
        {--from=en       : Source culture to read from}
        {--to=           : Target culture to write (required, e.g. af, fr)}
        {--object=       : Limit to a single information_object id}
        {--repository=   : Limit to one repository_id}
        {--limit=        : Max objects to process (0/blank = all)}
        {--overwrite     : Replace existing target-culture text instead of skipping}
        {--dry-run       : Report what would change, write nothing}';

    protected $description = 'Auto-translate record fields';

    public function handle(LlmService $llm): int
    {
        $from   = (string) $this->option('from');
        $to     = (string) $this->option('to');
        $limit  = (int) ($this->option('limit') ?: 0);
        $dryRun = (bool) $this->option('dry-run');

        if ($to === '') {
            $this->error('A target culture is required: --to=af (or fr, pt, ...).');
            return self::FAILURE;
        }
        if ($to === $from) {
            $this->error('Source and target cultures are identical; nothing to translate.');
            return self::FAILURE;
        }

        $query = DB::table('information_object as io')
            ->join('information_object_i18n as src', function ($j) use ($from) {
                $j->on('src.id', '=', 'io.id')->where('src.culture', '=', $from);
            })
            ->where('io.id', '!=', 1)
            ->orderBy('io.id')
            ->select('io.id', ...array_map(fn ($f) => "src.{$f} as {$f}", self::FIELDS));

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
            $this->info("No '{$from}' objects to translate.");
            return self::SUCCESS;
        }

        $this->info("Translating {$total} object(s) {$from} -> {$to}" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $updated = 0;
        $skipped = 0;
        $errors  = 0;

        foreach ($rows as $row) {
            $existing = DB::table('information_object_i18n')
                ->where('id', $row->id)->where('culture', $to)->first();

            $payload = [];
            $rowErr  = false;

            foreach (self::FIELDS as $field) {
                $srcVal = trim((string) ($row->$field ?? ''));
                if ($srcVal === '') {
                    continue;
                }
                if (!$this->option('overwrite') && $existing && trim((string) ($existing->$field ?? '')) !== '') {
                    continue; // already translated
                }

                try {
                    $t = $llm->translate($srcVal, $to, $from);
                } catch (Throwable $e) {
                    $rowErr = true;
                    $this->warn("Object {$row->id} [{$field}]: " . $e->getMessage());
                    break;
                }
                if (is_string($t) && trim($t) !== '') {
                    $payload[$field] = $t;
                }
            }

            if ($rowErr) {
                $errors++;
                continue;
            }
            if (empty($payload)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  object {$row->id}: would write " . implode(', ', array_keys($payload)));
                $updated++;
                continue;
            }

            if ($existing) {
                DB::table('information_object_i18n')
                    ->where('id', $row->id)->where('culture', $to)
                    ->update($payload);
            } else {
                DB::table('information_object_i18n')->insert(array_merge(
                    $payload,
                    ['id' => $row->id, 'culture' => $to]
                ));
            }
            $updated++;
        }

        $this->info(sprintf('Updated: %d, skipped: %d, errors: %d', $updated, $skipped, $errors));
        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
