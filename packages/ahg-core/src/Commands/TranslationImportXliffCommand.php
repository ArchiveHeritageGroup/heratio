<?php

/**
 * TranslationImportXliffCommand — bootstrap Heratio's lang/*.json files from
 * AtoM's existing XLIFF translation work at apps/qubit/i18n/{lang}/messages.xml.
 *
 * Why: AtoM ships 51 locales with thousands of pre-translated UI strings.
 * Heratio's __() helper reads from lang/{lang}.json. Converting XLIFF → JSON
 * in one pass instantly localises the navbar / buttons / labels into 50
 * languages without contracting a translator.
 *
 * Behaviour: merges into existing lang/{lang}.json (does NOT clobber). Strings
 * already translated in the JSON take precedence over the XLIFF, so any
 * Heratio-specific Afrikaans translations we authored manually survive a
 * re-run. Only writes a key when the XLIFF <target> is non-empty.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationImportXliffCommand extends Command
{
    protected $signature = 'ahg:translation-import-xliff
                            {--source=/usr/share/nginx/archive/apps/qubit/i18n : Directory containing per-locale XLIFF dirs}
                            {--locale=* : Restrict to specific locale code(s); default = all locales found}
                            {--overwrite : Replace existing JSON values instead of merging (existing keys win by default)}';

    protected $description = 'Import AtoM XLIFF translations into Heratio lang/*.json files';

    public function handle(): int
    {
        $sourceDir = (string) $this->option('source');
        $only = (array) $this->option('locale');
        $overwrite = (bool) $this->option('overwrite');

        if (! is_dir($sourceDir)) {
            $this->error("Source dir not found: {$sourceDir}");
            return self::FAILURE;
        }

        $langDir = base_path('lang');
        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        $locales = array_values(array_filter(scandir($sourceDir), function ($e) use ($sourceDir) {
            return $e !== '.' && $e !== '..' && is_dir($sourceDir . '/' . $e);
        }));

        if (! empty($only)) {
            $locales = array_intersect($locales, $only);
        }

        $this->info('Importing ' . count($locales) . ' locales from ' . $sourceDir);
        $this->newLine();

        $headers = ['locale', 'xliff units', 'with target', 'merged into JSON', 'total keys after'];
        $rows = [];

        foreach ($locales as $locale) {
            $xliff = $sourceDir . '/' . $locale . '/messages.xml';
            if (! is_file($xliff)) {
                continue;
            }

            $units = $this->parseXliff($xliff);
            $withTarget = array_filter($units, fn ($t) => $t !== '');

            $jsonPath = $langDir . '/' . $locale . '.json';
            $existing = is_file($jsonPath)
                ? (json_decode(file_get_contents($jsonPath), true) ?? [])
                : [];

            $merged = $existing;
            $newCount = 0;
            foreach ($withTarget as $source => $target) {
                if ($overwrite || ! isset($merged[$source]) || $merged[$source] === $source) {
                    if (($merged[$source] ?? null) !== $target) {
                        $merged[$source] = $target;
                        $newCount++;
                    }
                }
            }

            ksort($merged);
            file_put_contents(
                $jsonPath,
                json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
            );

            $rows[] = [
                $locale,
                count($units),
                count($withTarget),
                $newCount,
                count($merged),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info('Done. Run `php artisan view:clear` if you have cached views.');

        return self::SUCCESS;
    }

    /**
     * Parse XLIFF 1.0/1.2 file into an associative array of source → target.
     * Returns target == '' for entries with empty/missing translations.
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
            $source = (string) ($u->source ?? '');
            $target = (string) ($u->target ?? '');
            $source = trim($source);
            if ($source === '') {
                continue;
            }
            $out[$source] = trim($target);
        }

        return $out;
    }
}
