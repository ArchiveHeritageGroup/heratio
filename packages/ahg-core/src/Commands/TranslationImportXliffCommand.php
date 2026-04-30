<?php

/**
 * TranslationImportXliffCommand — bootstrap (and re-sync) Heratio's lang/*.json
 * files from AtoM's XLIFF translation work at apps/qubit/i18n/{lang}/messages.xml.
 *
 * Three modes (--mode=…) controlling collision handling:
 *
 *   merge          (default) — existing JSON value wins; only keys missing
 *                  from the JSON or where the JSON value === source identity
 *                  are written from the XLIFF. Use case: day-to-day, protect
 *                  manual translations.
 *   prefer-source  — XLIFF target wins UNLESS lang/_meta.json marks the entry
 *                  as `hand_edited: true`. Use case: AtoM upgrade run; pull in
 *                  upstream improvements while preserving local hand-edits.
 *   overwrite      — XLIFF target ALWAYS wins (full replace per key present
 *                  in source). Use case: initial install, recovery from
 *                  corrupted JSON.
 *
 * Plus:
 *   --prune        Delete keys from lang/{locale}.json that are absent from
 *                  ALL XLIFF sources AND absent from Heratio's __() codebase
 *                  scan. Used after AtoM removes/renames a key.
 *   --diff         Dry run; report what would change without writing.
 *   --source-extra Repeatable; additional XLIFF source dirs (plugin XLIFFs).
 *                  Plugin sources stack on top of core; later sources win on
 *                  collision (matching AtoM runtime behaviour).
 *   --overwrite    DEPRECATED alias for --mode=overwrite (kept for compat).
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationImportXliffCommand extends Command
{
    protected $signature = 'ahg:translation-import-xliff
                            {--source=/usr/share/nginx/archive/apps/qubit/i18n : Primary XLIFF source dir}
                            {--source-extra=* : Additional XLIFF source dirs (plugin XLIFFs); repeatable}
                            {--locale=* : Restrict to specific locale code(s); default = all found}
                            {--mode=merge : merge|prefer-source|overwrite}
                            {--prune : Remove keys absent from XLIFFs and absent from Heratio __() scan}
                            {--diff : Dry run — print what would change without writing}
                            {--overwrite : DEPRECATED — alias for --mode=overwrite}';

    protected $description = 'Import AtoM XLIFF translations into Heratio lang/*.json files';

    public function handle(): int
    {
        $sourceDirs = array_merge(
            [(string) $this->option('source')],
            (array) $this->option('source-extra')
        );
        $sourceDirs = array_values(array_filter($sourceDirs, 'is_dir'));

        if (empty($sourceDirs)) {
            $this->error('No valid source dir(s) found. Pass --source=/path/to/i18n.');
            return self::FAILURE;
        }

        $only = (array) $this->option('locale');
        $mode = (string) $this->option('mode');
        if ($this->option('overwrite')) {
            $mode = 'overwrite'; // deprecated alias
        }
        if (! in_array($mode, ['merge', 'prefer-source', 'overwrite'], true)) {
            $this->error("Invalid --mode={$mode}. Use merge, prefer-source, or overwrite.");
            return self::FAILURE;
        }
        $prune = (bool) $this->option('prune');
        $diff = (bool) $this->option('diff');

        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        // 1) Discover all locales present across all source dirs (union).
        $locales = [];
        foreach ($sourceDirs as $sd) {
            foreach ((array) scandir($sd) as $e) {
                if ($e === '.' || $e === '..') continue;
                if (is_dir($sd . '/' . $e)) {
                    $locales[$e] = true;
                }
            }
        }
        $locales = array_keys($locales);
        sort($locales);

        if (! empty($only)) {
            $locales = array_intersect($locales, $only);
        }

        $this->info(sprintf(
            'Mode: %s%s%s — %d locales across %d source dir(s)',
            $mode,
            $prune ? ' +prune' : '',
            $diff ? ' (DRY RUN)' : '',
            count($locales),
            count($sourceDirs)
        ));
        $this->newLine();

        // 2) Load _meta.json for prefer-source mode (per-key hand_edited flags).
        $metaPath = $langDir . '/_meta.json';
        $meta = is_file($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?? []) : [];

        // 3) Codebase __() scan, only if pruning (it's expensive).
        $codebaseKeys = $prune ? $this->scanCodebaseKeys() : null;
        if ($prune) {
            $this->info('Codebase __() scan: ' . count($codebaseKeys) . ' keys');
        }

        $headers = ['locale', 'xliff units', 'with target', 'wrote', 'pruned', 'total after'];
        $rows = [];

        foreach ($locales as $locale) {
            // Union XLIFFs from all source dirs (later sources win)
            $xliffMerged = [];
            foreach ($sourceDirs as $sd) {
                $xliff = $sd . '/' . $locale . '/messages.xml';
                if (is_file($xliff)) {
                    foreach ($this->parseXliff($xliff) as $src => $tgt) {
                        if ($tgt !== '') {
                            $xliffMerged[$src] = $tgt;
                        }
                    }
                }
            }

            $jsonPath = $langDir . '/' . $locale . '.json';
            $existing = is_file($jsonPath)
                ? (json_decode(file_get_contents($jsonPath), true) ?? [])
                : [];
            $perKeyMeta = $meta['locales'][$locale]['keys'] ?? [];

            $merged = $existing;
            $wrote = 0;

            foreach ($xliffMerged as $source => $target) {
                $existingValue = $merged[$source] ?? null;
                $isHandEdited = ($perKeyMeta[$source]['hand_edited'] ?? false) === true;

                $write = match ($mode) {
                    'merge'         => $existingValue === null || $existingValue === $source,
                    'prefer-source' => ! $isHandEdited,
                    'overwrite'     => true,
                };

                if ($write && $existingValue !== $target) {
                    $merged[$source] = $target;
                    $wrote++;
                }
            }

            // Pruning: remove keys absent from XLIFFs AND absent from codebase scan.
            $pruned = 0;
            if ($prune) {
                foreach (array_keys($merged) as $k) {
                    $inXliff = isset($xliffMerged[$k]);
                    $inCodebase = isset($codebaseKeys[$k]);
                    $isHandEdited = ($perKeyMeta[$k]['hand_edited'] ?? false) === true;
                    if (! $inXliff && ! $inCodebase && ! $isHandEdited) {
                        unset($merged[$k]);
                        $pruned++;
                    }
                }
            }

            ksort($merged);

            // 4) Write (or dry-run report).
            if (! $diff) {
                file_put_contents(
                    $jsonPath,
                    json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
                );
            }

            $rows[] = [
                $locale,
                count($xliffMerged),
                count(array_filter($xliffMerged, fn ($t) => $t !== '')),
                $wrote,
                $pruned,
                count($merged),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        if ($diff) {
            $this->warn('DRY RUN — no files written. Re-run without --diff to apply.');
        } else {
            $this->info('Done. Run `php artisan view:clear` if you have cached views.');
        }

        return self::SUCCESS;
    }

    /**
     * Parse XLIFF 1.0/1.2 file into an associative array of source → target.
     */
    private function parseXliff(string $path): array
    {
        $out = [];
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            $this->warn("Could not parse: {$path}");
            return $out;
        }
        $units = $xml->xpath('//trans-unit') ?: [];
        foreach ($units as $u) {
            $source = trim((string) ($u->source ?? ''));
            $target = trim((string) ($u->target ?? ''));
            if ($source === '') continue;
            $out[$source] = $target;
        }
        return $out;
    }

    /**
     * Walk every blade and PHP file in packages/ + app/ extracting __('…') keys.
     * Returns a hash-set (key => true) for O(1) lookup.
     */
    private function scanCodebaseKeys(): array
    {
        $roots = [base_path('packages'), base_path('app')];
        $keys = [];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            array_filter($roots, 'is_dir')[0] ?? base_path('packages'),
            \FilesystemIterator::SKIP_DOTS
        ));

        foreach ($roots as $root) {
            if (! is_dir($root)) continue;
            $rii = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($rii as $file) {
                $path = $file->getPathname();
                if (str_contains($path, '/worktree/')) continue;
                if (str_contains($path, '/vendor/')) continue;
                if (! preg_match('/\.(blade\.php|php)$/', $path)) continue;
                $contents = @file_get_contents($path);
                if ($contents === false) continue;
                if (preg_match_all("/__\(\s*['\"]([^'\"]{1,500})['\"]/", $contents, $matches)) {
                    foreach ($matches[1] as $k) {
                        $keys[$k] = true;
                    }
                }
            }
        }

        return $keys;
    }
}
