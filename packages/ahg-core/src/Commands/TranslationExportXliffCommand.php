<?php

/**
 * TranslationExportXliffCommand — export Heratio's lang/{culture}.json as
 * XLIFF 1.2 for offline-translator workflows. Round-trips with the
 * ahg:translation-import-xliff command.
 *
 * Examples:
 *   php artisan ahg:translation-export-xliff zu
 *   php artisan ahg:translation-export-xliff af --output=/tmp/heratio-af.xml
 *   php artisan ahg:translation-export-xliff fr --untranslated-only
 *   php artisan ahg:translation-export-xliff zu --to=/usr/share/nginx/archive/apps/qubit/i18n  # writes apps/.../zu/messages.xml structure
 *
 * Output XLIFF contains every key from lang/en.json (the canonical source set)
 * with the matching <target> from lang/{culture}.json. Empty <target/> for
 * untranslated keys — translators fill these in then return the file for
 * re-import.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use Illuminate\Console\Command;

class TranslationExportXliffCommand extends Command
{
    protected $signature = 'ahg:translation-export-xliff
                            {locale : Target locale code, e.g. zu xh af}
                            {--output= : Output path; defaults to lang slash locale.xml}
                            {--to= : AtoM-style parent dir; writes to dir slash locale slash messages.xml. Overrides --output}
                            {--source-locale=en : Source-language locale code, default en}
                            {--untranslated-only : Include only keys missing from the target}';

    protected $description = 'Export Heratio lang/{culture}.json to XLIFF 1.2 for offline translation';

    public function handle(): int
    {
        $culture = (string) $this->argument('locale');
        $sourceLocale = (string) $this->option('source-locale');
        $untranslatedOnly = (bool) $this->option('untranslated-only');

        $langDir = base_path('lang');
        $sourcePath = $langDir . '/' . $sourceLocale . '.json';
        $targetPath = $langDir . '/' . $culture . '.json';

        if (! is_file($sourcePath)) {
            $this->error("Source locale file not found: {$sourcePath}");
            return self::FAILURE;
        }

        $source = json_decode(file_get_contents($sourcePath), true) ?? [];
        $target = is_file($targetPath) ? (json_decode(file_get_contents($targetPath), true) ?? []) : [];

        // Build trans-units: every key from source. The XLIFF <source> is the
        // EN canonical text; <target> is the translation if present. AtoM uses
        // a deterministic SHA1 of source text as the trans-unit id — we do the
        // same so re-exports stay stable.
        $units = [];
        $translatedCount = 0;
        $untranslatedCount = 0;

        foreach ($source as $key => $sourceText) {
            $sourceText = (string) $sourceText;
            $targetText = isset($target[$key]) && is_string($target[$key]) ? trim($target[$key]) : '';
            $isTranslated = $targetText !== '' && $targetText !== $key;

            if ($untranslatedOnly && $isTranslated) {
                continue;
            }

            $units[] = [
                'id'     => sha1($key),
                'source' => $sourceText,
                'target' => $isTranslated ? $targetText : '',
            ];

            if ($isTranslated) {
                $translatedCount++;
            } else {
                $untranslatedCount++;
            }
        }

        // Render XLIFF 1.2 (matches AtoM's i18n format).
        $xliff = $this->renderXliff($units, $sourceLocale, $culture);

        // Resolve output path
        if ($to = $this->option('to')) {
            $outDir = rtrim($to, '/') . '/' . $culture;
            if (! is_dir($outDir)) {
                if (! @mkdir($outDir, 0755, true)) {
                    $this->error("Could not create directory: {$outDir}");
                    return self::FAILURE;
                }
            }
            $outPath = $outDir . '/messages.xml';
        } else {
            $outPath = (string) ($this->option('output') ?: ($langDir . '/' . $culture . '.xml'));
        }

        file_put_contents($outPath, $xliff);

        $this->info(sprintf(
            'Exported %d trans-units to %s (%d translated, %d untranslated)',
            count($units),
            $outPath,
            $translatedCount,
            $untranslatedCount
        ));

        return self::SUCCESS;
    }

    private function renderXliff(array $units, string $sourceLocale, string $targetLocale): string
    {
        $date = gmdate('Y-m-d\TH:i:s\Z');
        $sourceLocale = htmlspecialchars($sourceLocale, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $targetLocale = htmlspecialchars($targetLocale, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $body = '';
        foreach ($units as $u) {
            $id = htmlspecialchars($u['id'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($u['source'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $tgt = htmlspecialchars($u['target'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $body .= "      <trans-unit id=\"{$id}\">\n";
            $body .= "        <source xml:space=\"preserve\">{$src}</source>\n";
            $body .= "        <target xml:space=\"preserve\">{$tgt}</target>\n";
            $body .= "      </trans-unit>\n";
        }

        return <<<XML
<?xml version="1.0"?>
<!DOCTYPE xliff PUBLIC "-//XLIFF//DTD XLIFF//EN" "http://www.oasis-open.org/committees/xliff/documents/xliff.dtd">
<xliff version="1.0">
  <file source-language="{$sourceLocale}" target-language="{$targetLocale}" datatype="plaintext" original="messages" date="{$date}" product-name="messages">
    <header/>
    <body>
{$body}    </body>
  </file>
</xliff>

XML;
    }
}
