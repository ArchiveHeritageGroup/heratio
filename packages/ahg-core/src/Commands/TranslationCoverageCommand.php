<?php

/**
 * TranslationCoverageCommand - per-locale coverage report.
 *
 * Output: a table per locale of what the interface really shows - lang/*.json
 * with the ui_string overrides on top, as DbAwareLoader serves it - against the
 * Heratio codebase __() keys (see UiCoverageService). Strings known to read the
 * same as English count as done; the South African languages are listed first.
 * Use this to see at
 * a glance which locales are deployment-ready and which need translator time.
 *
 * Examples:
 *   php artisan ahg:translation-coverage
 *   php artisan ahg:translation-coverage --heratio-only
 *   php artisan ahg:translation-coverage --fail-below=50
 *   php artisan ahg:translation-coverage --locale=af --locale=zu --locale=fr
 *   php artisan ahg:translation-coverage --sa
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgCore\Services\UiCoverageService;
use Illuminate\Console\Command;

class TranslationCoverageCommand extends Command
{
    protected $signature = 'ahg:translation-coverage
                            {--locale=* : Restrict to specific locale code(s); default = all lang/*.json}
                            {--sa : Only the South African official languages}
                            {--files-only : Measure lang/*.json alone, without the ui_string overrides}
                            {--heratio-only : Score only against Heratio-only keys (excludes AtoM XLIFF keys)}
                            {--atom-source=/usr/share/nginx/archive/apps/qubit/i18n : AtoM i18n dir, used with --heratio-only}
                            {--fail-below= : Exit non-zero if any included locale falls below this codebase coverage % (e.g. 50)}';

    protected $description = 'Per-locale translation coverage report (lang/*.json with the ui_string overrides)';

    public function handle(): int
    {
        $only = (array) $this->option('locale');
        if ($this->option('sa')) {
            $only = $only ? array_values(array_intersect($only, UiCoverageService::SA_LOCALES)) : UiCoverageService::SA_LOCALES;
        }
        $failBelow = $this->option('fail-below');
        $failBelow = $failBelow !== null ? (float) $failBelow : null;

        if (! is_dir(base_path('lang'))) {
            $this->error('lang/ directory not found.');

            return self::FAILURE;
        }

        $referenceKeys = UiCoverageService::codebaseKeys();
        if ($this->option('heratio-only')) {
            $atomKeys = $this->scanAtomXliffKeys((string) $this->option('atom-source'));
            $this->info('AtoM XLIFF keys (excluded under --heratio-only): '.count($atomKeys));
            $referenceKeys = array_diff_key($referenceKeys, $atomKeys);
        }
        $withDb = ! $this->option('files-only');
        $this->info(sprintf('Reference set: %d %s keys; measured on %s', count($referenceKeys),
            $this->option('heratio-only') ? 'Heratio-only' : 'codebase __()', $withDb ? 'lang/*.json + ui_string' : 'lang/*.json only'));
        $this->newLine();

        $rows = (new UiCoverageService)->report($referenceKeys, $only, $withDb);
        $this->table(
            ['locale', 'SA', 'file keys', 'ui_string', 'translated', 'same as English', 'missing', 'reviewed', 'ui_string hides file', '% coverage'],
            array_map(fn ($r) => [$r['locale'], $r['sa'] ? 'yes' : '', $r['file_keys'], $r['db_rows'], $r['translated'],
                $r['same_as_source'], $r['missing'], $r['reviewed'], $r['db_hides_file'] + $r['db_differs'], $r['pct'].' %'], $rows)
        );
        $this->newLine();

        // Malformed locale files are always a failure (regression guard, #1410):
        // they parse as something other than a JSON object and silently
        // contribute zero strings.
        $malformed = array_column(array_filter($rows, fn ($r) => $r['malformed']), 'locale');
        if ($malformed) {
            $this->error(sprintf('%d malformed locale file(s) (not a JSON object): %s', count($malformed), implode(', ', $malformed)));

            return self::FAILURE;
        }

        $worst = $rows ? min(array_column($rows, 'pct')) : 100.0;
        if ($failBelow !== null && $worst < $failBelow) {
            $this->error(sprintf('Lowest locale coverage %.1f%% is below threshold %.1f%%', $worst, $failBelow));

            return self::FAILURE;
        }

        return self::SUCCESS;
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
            if ($e === '.' || $e === '..') {
                continue;
            }
            $xliff = $sourceDir.'/'.$e.'/messages.xml';
            if (! is_file($xliff)) {
                continue;
            }
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_file($xliff, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOENT);
            libxml_use_internal_errors($prev);
            if ($xml === false) {
                continue;
            }
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
