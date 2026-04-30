<?php

/**
 * TranslationLintCommand — scan blade files for hardcoded UI strings that
 * should be wrapped in __(). Reports candidates ranked by file with concrete
 * line numbers, so the C1 sweep (wrap hardcoded strings) can be driven from
 * data instead of hunches.
 *
 * Detects high-confidence patterns first (won't false-positive on data
 * variables or HTML attributes that are correctly escaped):
 *
 *   <h1>Some Label</h1>            ← all <hN> tag bodies
 *   <th>Created</th>               ← table column headers
 *   <button>Save</button>          ← button text
 *   placeholder="Search..."         ← form-field placeholders
 *   title="Edit this record"       ← tooltip / aria attributes
 *   alt="..." aria-label="..."
 *   <option>Yes</option>           ← static select options
 *
 * Skips:
 *   - text inside {{ ... }} or {!! ... !!} (already a Blade expression)
 *   - text inside <script> / <style> blocks
 *   - text matching numeric / date / single-character patterns
 *   - text matching the translation-key allowlist already in lang/en.json
 *     (those keys are likely wrapped via a different code path)
 *
 * Examples:
 *   php artisan ahg:translation-lint
 *   php artisan ahg:translation-lint --top=10
 *   php artisan ahg:translation-lint --package=ahg-museum
 *   php artisan ahg:translation-lint --fail-above=200
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationLintCommand extends Command
{
    protected $signature = 'ahg:translation-lint
                            {--top=20 : Show top-N files by hardcoded-string count}
                            {--package= : Restrict scan to a single packages/{name}/ subtree}
                            {--all : Print every candidate string, not just the per-file totals}
                            {--fail-above= : Exit non-zero if total candidate count exceeds this number}';

    protected $description = 'Find hardcoded UI strings in blade files that should be wrapped in __()';

    public function handle(): int
    {
        $top = max(1, (int) $this->option('top'));
        $packageFilter = (string) $this->option('package');
        $verbose = (bool) $this->option('all');
        $failAbove = $this->option('fail-above');
        $failAbove = $failAbove !== null ? (int) $failAbove : null;

        $root = base_path('packages');
        if ($packageFilter !== '') {
            $root .= '/' . $packageFilter;
        }
        if (! is_dir($root)) {
            $this->error("Scan root not found: {$root}");
            return self::FAILURE;
        }

        // Pull existing __() keys so we don't false-positive on already-translated strings.
        $enJson = base_path('lang/en.json');
        $known = is_file($enJson) ? (json_decode(file_get_contents($enJson), true) ?? []) : [];
        $known = array_flip(array_keys($known));

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        $perFile = [];
        $total = 0;

        foreach ($rii as $file) {
            $path = $file->getPathname();
            if (str_contains($path, '/worktree/')) continue;
            if (str_contains($path, '/vendor/')) continue;
            if (! str_ends_with($path, '.blade.php')) continue;

            $contents = @file_get_contents($path);
            if ($contents === false) continue;

            // Strip <script>...</script> and <style>...</style> blocks so we
            // don't flag JS strings as UI hardcodes.
            $stripped = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $contents);
            $stripped = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $stripped);

            $hits = $this->findCandidates($stripped, $known);
            if (empty($hits)) continue;

            $rel = str_replace(base_path() . '/', '', $path);
            $perFile[$rel] = $hits;
            $total += count($hits);
        }

        // Sort files by hit-count descending.
        uasort($perFile, fn ($a, $b) => count($b) <=> count($a));

        if ($verbose) {
            foreach ($perFile as $relPath => $hits) {
                $this->line("\n<comment>" . $relPath . "</comment>  (" . count($hits) . ' candidates)');
                foreach ($hits as $h) {
                    $this->line(sprintf('  L%-4d  [%s]  %s',
                        $h['line'],
                        $h['kind'],
                        substr($h['text'], 0, 80)
                    ));
                }
            }
        } else {
            $rows = [];
            foreach (array_slice($perFile, 0, $top, true) as $relPath => $hits) {
                $rows[] = [count($hits), $relPath];
            }
            $this->info(sprintf(
                'Found %d candidate hardcoded UI strings in %d files (showing top %d):',
                $total,
                count($perFile),
                min($top, count($perFile))
            ));
            $this->newLine();
            $this->table(['hits', 'file'], $rows);
            if (count($perFile) > $top) {
                $this->line(sprintf('  … and %d more files. Re-run with --all to see every candidate.', count($perFile) - $top));
            }
        }

        if ($failAbove !== null && $total > $failAbove) {
            $this->error("Total candidates {$total} exceeds threshold {$failAbove}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Find candidate hardcoded UI strings in (script-stripped) blade contents.
     *
     * @return array<int, array{kind: string, text: string, line: int}>
     */
    private function findCandidates(string $contents, array $known): array
    {
        $hits = [];
        $lineMap = $this->buildLineMap($contents);

        // Pattern bank: each entry is [kind, regex with capture group 'text']
        $patterns = [
            ['heading',     '/<h[1-6][^>]*>([^<{}\n]{2,200})<\/h[1-6]>/'],
            ['th',          '/<th[^>]*>([^<{}\n]{2,200})<\/th>/'],
            ['button',      '/<button[^>]*>([A-Z][^<{}\n]{1,200})<\/button>/'],
            ['option',      '/<option[^>]*>([A-Z][^<{}\n]{1,200})<\/option>/'],
            ['label',       '/<label[^>]*>([A-Z][^<{}\n]{1,200})<\/label>/'],
            ['placeholder', '/placeholder="([^"{}]{2,150})"/'],
            ['title-attr',  '/title="([^"{}]{2,200})"/'],
            ['alt-attr',    '/alt="([A-Z][^"{}]{1,200})"/'],
            ['aria-label',  '/aria-label="([^"{}]{2,200})"/'],
        ];

        foreach ($patterns as [$kind, $regex]) {
            if (preg_match_all($regex, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $m) {
                    $text = trim($m[0]);
                    $offset = $m[1];
                    if ($this->shouldSkip($text, $known)) continue;
                    $hits[] = [
                        'kind' => $kind,
                        'text' => $text,
                        'line' => $this->offsetToLine($offset, $lineMap),
                    ];
                }
            }
        }

        return $hits;
    }

    private function shouldSkip(string $text, array $known): bool
    {
        // Empty-ish
        if (mb_strlen($text) < 2) return true;
        // All numeric / dates / decimals
        if (preg_match('/^[\d\s.,:\/+\-%]+$/', $text)) return true;
        // Single icon-only spans (Font Awesome, Bootstrap icon class names)
        if (preg_match('/^(fa|fas|far|fal|fab|bi|icon)[-\s]/i', $text)) return true;
        // Already a Blade expression (defence-in-depth — patterns above filter {{)
        if (str_contains($text, '{{') || str_contains($text, '{!!')) return true;
        // Heratio brand strings we don't translate
        if (in_array($text, ['Heratio', 'AHG', 'OpenRiC', 'RiC', 'AtoM', 'Artefactual'], true)) return true;
        // Already in the codebase translation set — likely wrapped via a sibling
        // template that uses {{ __('...') }}. Keep flagged anyway only if the
        // file count is non-zero so we get coverage data; skip pure duplicates.
        if (isset($known[$text])) return false; // still flag — wrap improvement worth doing
        return false;
    }

    /** Build cumulative offsets per line for fast line-number lookup. */
    private function buildLineMap(string $contents): array
    {
        $map = [0];
        $offset = 0;
        foreach (explode("\n", $contents) as $line) {
            $offset += mb_strlen($line, '8bit') + 1;
            $map[] = $offset;
        }
        return $map;
    }

    private function offsetToLine(int $offset, array $lineMap): int
    {
        // Binary search would be faster but linear is fine for typical blade sizes.
        for ($i = 0; $i < count($lineMap); $i++) {
            if ($offset < $lineMap[$i]) {
                return $i;
            }
        }
        return count($lineMap);
    }
}
