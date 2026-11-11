<?php

/**
 * TranslationLintCommand - scan blade files for hardcoded UI strings that
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
 * Two further passes (heratio#1597) catch what the pattern bank cannot:
 *
 *   Showing {{ $n }} results       ← [text]  any text node between tags, with
 *                                    Blade expressions and @directives
 *                                    blanked first, so text that shares an
 *                                    element with an expression, spans lines,
 *                                    or sits in <p>/<span>/<a>/<td>/... is seen
 *   ->with('error', 'Not found.')  ← [flash] literal flash messages in PHP
 *                                    (each package's src/ and app/)
 *
 * Baseline (the same contract as tools/scan-form-fields.php): the backlog is
 * recorded in tools/translation-lint-baseline.txt as "file<TAB>kind<TAB>text"
 * lines - no line numbers, so editing a file elsewhere does not churn it.
 * --check fails only on findings that are not in the baseline, so new code
 * cannot add hardcoded strings while the backlog is cleared package by package;
 * --baseline rewrites it, and should only ever make it shorter.
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
 *   php artisan ahg:translation-lint --check      # CI: fail on anything new
 *   php artisan ahg:translation-lint --baseline   # record the current backlog
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
                            {--fail-above= : Exit non-zero if total candidate count exceeds this number}
                            {--check : Exit non-zero on any candidate not in tools/translation-lint-baseline.txt}
                            {--baseline : Write the current candidates to tools/translation-lint-baseline.txt}';

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
            $root .= '/'.$packageFilter;
        }
        if (! is_dir($root)) {
            $this->error("Scan root not found: {$root}");

            return self::FAILURE;
        }

        // Pull existing __() keys so we don't false-positive on already-translated strings.
        $enJson = base_path('lang/en.json');
        $known = is_file($enJson) ? (json_decode(file_get_contents($enJson), true) ?? []) : [];
        $known = array_flip(array_keys($known));

        // Blade roots: the package tree, plus the app's own views when the
        // scan is not restricted to one package. PHP roots (flash messages):
        // each package's src/ and app/.
        $bladeRoots = [$root];
        $phpRoots = [$packageFilter !== '' ? $root.'/src' : $root];
        if ($packageFilter === '') {
            $bladeRoots[] = base_path('resources/views');
            $phpRoots[] = base_path('app');
        }

        $perFile = [];
        $total = 0;

        foreach ($this->files($bladeRoots) as $path) {
            if (str_contains($path, '/worktree/')) {
                continue;
            }
            if (str_contains($path, '/vendor/')) {
                continue;
            }
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }

            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }

            // Strip <script>...</script> and <style>...</style> blocks so we
            // don't flag JS strings as UI hardcodes.
            $stripped = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $contents);
            $stripped = preg_replace('#<style\b[^>]*>.*?</style>#is', '', $stripped);

            $hits = $this->dedupe(array_merge(
                $this->findCandidates($stripped, $known),
                $this->findTextNodes($contents)
            ));
            if (empty($hits)) {
                continue;
            }

            $rel = str_replace(base_path().'/', '', $path);
            $perFile[$rel] = $hits;
            $total += count($hits);
        }

        foreach ($this->files($phpRoots) as $path) {
            if (str_ends_with($path, '.blade.php') || ! str_ends_with($path, '.php')
                || str_contains($path, '/resources/views/') || str_contains($path, '/tests/')) {
                continue;
            }
            $contents = @file_get_contents($path);
            if ($contents === false) {
                continue;
            }
            $hits = $this->findFlashMessages($contents);
            if (empty($hits)) {
                continue;
            }
            $rel = str_replace(base_path().'/', '', $path);
            $perFile[$rel] = $hits;
            $total += count($hits);
        }

        if ($this->option('baseline') || $this->option('check')) {
            return $this->baseline($perFile, (bool) $this->option('baseline'));
        }

        // Sort files by hit-count descending.
        uasort($perFile, fn ($a, $b) => count($b) <=> count($a));

        if ($verbose) {
            foreach ($perFile as $relPath => $hits) {
                $this->line("\n<comment>".$relPath.'</comment>  ('.count($hits).' candidates)');
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
                    if ($this->shouldSkip($text, $known)) {
                        continue;
                    }
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
        if (mb_strlen($text) < 2) {
            return true;
        }
        // Only HTML entities and spaces (&nbsp;, &times;): nothing to translate
        if (trim((string) preg_replace('/&[a-z0-9#]+;/i', '', $text)) === '') {
            return true;
        }
        // All numeric / dates / decimals
        if (preg_match('/^[\d\s.,:\/+\-%]+$/', $text)) {
            return true;
        }
        // Single icon-only spans (Font Awesome, Bootstrap icon class names)
        if (preg_match('/^(fa|fas|far|fal|fab|bi|icon)[-\s]/i', $text)) {
            return true;
        }
        // Already a Blade expression
        if (str_contains($text, '{{') || str_contains($text, '{!!')) {
            return true;
        }
        // Already wrapped via inline @php echo __() pattern
        if (str_contains($text, '@php') && str_contains($text, '__(')) {
            return true;
        }
        // Brand strings, file formats, acronyms - proper nouns we don't translate
        $properNouns = [
            'Heratio', 'AHG', 'OpenRiC', 'RiC', 'AtoM', 'Artefactual', 'ICA',
            'PDF', 'TIFF', 'JPEG', 'PNG', 'GIF', 'WAV', 'MP3', 'MP4', 'CSV',
            'JSON', 'XML', 'HTML', 'YAML', 'TXT', 'ZIP', 'RDF', 'TTL', 'JSON-LD',
            'URL', 'URI', 'UUID', 'API', 'OAI-PMH', 'EAD', 'EAD3', 'EAC', 'METS',
            'MODS', 'MARC', 'PREMIS', 'IIIF', 'ISAD', 'ISAAR', 'ISDIAH', 'ISDF',
            'CCO', 'CDWA', 'AAT', 'TGN', 'ULAN', 'LCSH', 'LCNAF', 'OAIS', 'SKOS',
            'CIDOC', 'CRM', 'NER', 'PII', 'AI', 'OCR', 'TTS', 'GLAM', 'DAM',
            'GLB', 'GLTF', 'STL', 'OBJ', 'FBX', 'GRAP', 'POPIA', 'PAIA', 'GDPR',
            'NMMZ', 'NAZ', 'CDPA', 'IPSAS', 'SPARQL',
        ];
        if (in_array($text, $properNouns, true)) {
            return true;
        }

        return false;
    }

    /** @return \Generator<string> every file under the given roots, skipping vendor/ and worktree/ */
    private function files(array $roots): \Generator
    {
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                $path = $file->getPathname();
                if (str_contains($path, '/vendor/') || str_contains($path, '/worktree/')) {
                    continue;
                }
                yield $path;
            }
        }
    }

    /**
     * Text between tags that still reads as English once Blade is removed.
     *
     * Comments, <script>/<style>/<pre>/<code>, @php blocks, {{ }} / {!! !!}
     * and @directives(...) are blanked first, keeping newlines so the line
     * numbers stay true. A node that contained an expression is reported with
     * {{…}} in its place.
     *
     * @return array<int, array{kind: string, text: string, line: int}>
     */
    private function findTextNodes(string $contents): array
    {
        $keepLines = fn (array $m) => str_repeat("\n", substr_count($m[0], "\n"));
        $src = preg_replace_callback(
            '#\{\{--.*?--\}\}|<!--.*?-->|<(script|style|pre|code)\b[^>]*>.*?</\1>|@php\b.*?@endphp|@verbatim\b.*?@endverbatim#is',
            $keepLines,
            $contents
        );
        $src = preg_replace_callback('#\{\{.*?\}\}|\{!!.*?!!\}#s', fn ($m) => "\x00".str_repeat("\n", substr_count($m[0], "\n")), $src);
        // A directive separates text: the branches of @if / @else are
        // different strings, so mark the boundary (\x01) instead of erasing it.
        $src = preg_replace_callback('#@[a-zA-Z]+\s*(\((?:[^()]++|(?1))*\))#', fn ($m) => "\x01".str_repeat("\n", substr_count($m[0], "\n")), $src);
        $src = preg_replace('#@[a-zA-Z]+\b#', "\x01", $src);

        $hits = [];
        $lineMap = $this->buildLineMap($src);
        if (! preg_match_all('#>([^<>]+)<#', $src, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $segments = [];
        foreach ($matches[1] as [$node, $offset]) {
            foreach (explode("\x01", $node) as $segment) {
                $segments[] = [$segment, $offset];
                $offset += strlen($segment) + 1;
            }
        }
        foreach ($segments as [$node, $offset]) {
            $plain = trim(preg_replace('#&[\#\w]+;#', ' ', str_replace("\x00", ' ', $node)));
            if (! preg_match('#[A-Za-z][a-z]{2,}#', $plain)) {
                continue; // no word: numbers, symbols, ALLCAPS acronyms
            }
            $text = trim(preg_replace('#\s+#', ' ', str_replace("\x00", '{{…}}', $node)));
            if ($this->shouldSkip($plain, [])) {
                continue;
            }
            $hits[] = [
                'kind' => 'text',
                'text' => $text,
                'line' => $this->offsetToLine($offset + strspn($node, " \t\r\n"), $lineMap),
            ];
        }

        return $hits;
    }

    /**
     * Literal flash messages: ->with('success'|'error'|..., 'English')
     * and session()->flash(...). A message already passed through __() or
     * trans() is not a candidate.
     *
     * @return array<int, array{kind: string, text: string, line: int}>
     */
    private function findFlashMessages(string $contents): array
    {
        $hits = [];
        $lineMap = $this->buildLineMap($contents);
        $re = '#(?:->with|\bflash)\(\s*([\'"])(success|error|warning|info|notice|status|message|danger)\1\s*,\s*([\'"])((?:(?!\3)[^\\\\]|\\\\.)*)\3#';
        if (! preg_match_all($re, $contents, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }
        foreach ($matches as $m) {
            $text = trim($m[4][0]);
            if (! preg_match('#[A-Za-z][a-z]{2,}#', $text)) {
                continue;
            }
            $hits[] = ['kind' => 'flash', 'text' => $text, 'line' => $this->offsetToLine($m[0][1], $lineMap)];
        }

        return $hits;
    }

    /** One hit per line + text; the pattern bank wins over the text-node pass. */
    private function dedupe(array $hits): array
    {
        $seen = [];
        $out = [];
        foreach ($hits as $h) {
            $key = $h['line']."\x00".preg_replace('#\s+#', ' ', $h['text']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $h;
        }

        return $out;
    }

    /**
     * --baseline writes the findings; --check fails on any not in it.
     * Entries are compared as a multiset, so a string that appears twice in a
     * file needs two baseline lines.
     */
    private function baseline(array $perFile, bool $write): int
    {
        $file = base_path('tools/translation-lint-baseline.txt');
        $findings = [];
        foreach ($perFile as $rel => $hits) {
            foreach ($hits as $h) {
                $findings[] = $rel."\t".$h['kind']."\t".str_replace(["\t", "\n"], ' ', $h['text']);
            }
        }
        sort($findings);
        $this->info(sprintf('Found %d candidate hardcoded UI strings in %d files', count($findings), count($perFile)));

        if ($write) {
            if (@file_put_contents($file, implode("\n", $findings)."\n") === false) {
                $this->error('Cannot write tools/translation-lint-baseline.txt (check permissions).');

                return self::FAILURE;
            }
            $this->info('baseline written: tools/translation-lint-baseline.txt');

            return self::SUCCESS;
        }

        $base = is_file($file) ? array_values(array_filter(explode("\n", (string) file_get_contents($file)), 'strlen')) : [];
        $remaining = array_count_values($base);
        $new = [];
        foreach ($findings as $f) {
            if (($remaining[$f] ?? 0) > 0) {
                $remaining[$f]--;
            } else {
                $new[] = $f;
            }
        }
        $gone = array_sum($remaining);

        if ($gone > 0) {
            $this->line("{$gone} baseline entr".($gone === 1 ? 'y is' : 'ies are').' gone - regenerate with --baseline so the baseline stays shrink-only.');
        }
        if ($new !== []) {
            $this->error(count($new).' NEW hardcoded UI string(s) - wrap them in __() (see heratio#1597):');
            foreach ($new as $n) {
                [$rel, $kind, $text] = explode("\t", $n, 3);
                $this->line("  {$rel}  [{$kind}]  ".mb_substr($text, 0, 100));
            }

            return self::FAILURE;
        }
        $this->info('no new hardcoded UI strings.');

        return self::SUCCESS;
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
