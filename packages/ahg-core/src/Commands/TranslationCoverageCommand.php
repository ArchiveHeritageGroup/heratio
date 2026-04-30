<?php

/**
 * TranslationCoverageCommand — per-locale coverage report.
 *
 * Output: a table per locale showing total keys, translated keys (non-identity),
 * and what % of the Heratio codebase __() keys are covered. Use this to see at
 * a glance which locales are deployment-ready and which need translator time.
 *
 * Examples:
 *   php artisan ahg:translation-coverage
 *   php artisan ahg:translation-coverage --heratio-only
 *   php artisan ahg:translation-coverage --fail-below=50
 *   php artisan ahg:translation-coverage --locale=af --locale=zu --locale=fr
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationCoverageCommand extends Command
{
    protected $signature = 'ahg:translation-coverage
                            {--locale=* : Restrict to specific locale code(s); default = all lang/*.json}
                            {--heratio-only : Score only against Heratio-only keys (excludes AtoM XLIFF keys)}
                            {--atom-source=/usr/share/nginx/archive/apps/qubit/i18n : AtoM i18n dir, used with --heratio-only}
                            {--fail-below= : Exit non-zero if any included locale falls below this codebase coverage % (e.g. 50)}';

    protected $description = 'Per-locale translation coverage report';

    public function handle(): int
    {
        $only = (array) $this->option('locale');
        $heratioOnly = (bool) $this->option('heratio-only');
        $failBelow = $this->option('fail-below');
        $failBelow = $failBelow !== null ? (float) $failBelow : null;

        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            $this->error('lang/ directory not found.');
            return self::FAILURE;
        }

        $langFiles = glob($langDir . '/*.json') ?: [];
        $locales = array_map(
            fn ($f) => pathinfo($f, PATHINFO_FILENAME),
            $langFiles
        );
        $locales = array_filter($locales, fn ($l) => $l !== '_meta');
        sort($locales);
        if (! empty($only)) {
            $locales = array_intersect($locales, $only);
        }

        $codebaseKeys = $this->scanCodebaseKeys();
        $atomKeys = [];
        if ($heratioOnly) {
            $atomDir = (string) $this->option('atom-source');
            $atomKeys = $this->scanAtomXliffKeys($atomDir);
            $this->info('AtoM XLIFF keys (excluded under --heratio-only): ' . count($atomKeys));
        }

        $referenceKeys = $heratioOnly
            ? array_diff_key($codebaseKeys, $atomKeys)
            : $codebaseKeys;
        $referenceTotal = count($referenceKeys);

        $this->info(sprintf(
            'Reference set: %d %s keys',
            $referenceTotal,
            $heratioOnly ? 'Heratio-only' : 'codebase __()'
        ));
        $this->newLine();

        $headers = ['locale', 'JSON keys', 'translated', '% translated', 'covers ref', '% coverage'];
        $rows = [];
        $worst = 100.0;

        foreach ($locales as $locale) {
            $jsonPath = $langDir . '/' . $locale . '.json';
            $data = json_decode(@file_get_contents($jsonPath) ?: '{}', true) ?? [];

            $jsonKeys = count($data);
            $translated = 0;
            foreach ($data as $k => $v) {
                if (is_string($v) && $v !== '' && $v !== $k) {
                    $translated++;
                }
            }
            $pctTranslated = $jsonKeys > 0 ? round($translated / $jsonKeys * 100, 1) : 0.0;

            $covered = 0;
            foreach ($referenceKeys as $k => $_) {
                $v = $data[$k] ?? null;
                if (is_string($v) && $v !== '' && $v !== $k) {
                    $covered++;
                }
            }
            $pctCoverage = $referenceTotal > 0 ? round($covered / $referenceTotal * 100, 1) : 0.0;
            if ($pctCoverage < $worst) {
                $worst = $pctCoverage;
            }

            $rows[] = [
                $locale,
                $jsonKeys,
                $translated,
                $pctTranslated . ' %',
                "{$covered} / {$referenceTotal}",
                $pctCoverage . ' %',
            ];
        }

        usort($rows, fn ($a, $b) => (float) trim($b[5], ' %') <=> (float) trim($a[5], ' %'));

        $this->table($headers, $rows);
        $this->newLine();

        if ($failBelow !== null && $worst < $failBelow) {
            $this->error(sprintf(
                'Lowest locale coverage %.1f%% is below threshold %.1f%%',
                $worst,
                $failBelow
            ));
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Scan blade + PHP files for __('...') keys.
     */
    private function scanCodebaseKeys(): array
    {
        $roots = [base_path('packages'), base_path('app')];
        $keys = [];
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

    /**
     * Scan a directory of AtoM-style XLIFF dirs ({locale}/messages.xml) for
     * the union of all source-language keys.
     */
    private function scanAtomXliffKeys(string $sourceDir): array
    {
        $keys = [];
        if (! is_dir($sourceDir)) {
            return $keys;
        }
        foreach (scandir($sourceDir) as $e) {
            if ($e === '.' || $e === '..') continue;
            $xliff = $sourceDir . '/' . $e . '/messages.xml';
            if (! is_file($xliff)) continue;
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_file($xliff, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            libxml_use_internal_errors($prev);
            if ($xml === false) continue;
            foreach ($xml->xpath('//trans-unit') ?: [] as $u) {
                $source = trim((string) ($u->source ?? ''));
                if ($source !== '') {
                    $keys[$source] = true;
                }
            }
        }
        return $keys;
    }
}
