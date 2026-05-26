<?php

/**
 * TesseractListLanguagesCommand - persist the installed Tesseract packs
 * into ahg_ai_settings.ocr_languages.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

declare(strict_types=1);

namespace AhgAiServices\Commands;

use AhgAiServices\Services\OcrService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Probe the host's Tesseract installation, list every trained-data pack
 * present (eng, afr, nld, osd, ...) and pin the result in
 * ahg_ai_settings.feature=ocr/setting_key=ocr_languages so the rest of
 * the application can render dropdowns / sanity-check operator-supplied
 * lang specs.
 *
 * Usage:
 *   php artisan ahg:tesseract:list-languages
 *   php artisan ahg:tesseract:list-languages --json
 *
 * Exit codes:
 *   0  on success
 *   2  if the tesseract binary is missing
 */
class TesseractListLanguagesCommand extends Command
{
    protected $signature = 'ahg:tesseract:list-languages
        {--json : Emit JSON instead of a human-readable table}
        {--no-persist : Do not write the result to ahg_ai_settings}';

    protected $description = 'Probe tesseract --list-langs, cache the packs into ahg_ai_settings.ocr_languages';

    public function handle(OcrService $ocr): int
    {
        $version = $ocr->tesseractVersion();
        $langs = $ocr->listInstalledLanguages();

        if (empty($langs)) {
            $this->error('No Tesseract languages found. Is the `tesseract` binary on $PATH?');
            $this->line('Run `apt install tesseract-ocr tesseract-ocr-eng tesseract-ocr-afr` on Debian/Ubuntu.');
            return 2;
        }

        if ($this->option('json')) {
            $this->line(json_encode([
                'version'   => $version,
                'languages' => $langs,
                'count'     => count($langs),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Tesseract version: '.($version !== '' ? $version : 'unknown'));
            $this->info('Installed language packs ('.count($langs).'):');
            foreach (array_chunk($langs, 6) as $chunk) {
                $this->line('  '.implode('  ', $chunk));
            }
        }

        if (! $this->option('no-persist')) {
            $this->persist($version, $langs);
            $this->info('Cached into ahg_ai_settings.ocr_languages.');
        }

        return 0;
    }

    private function persist(string $version, array $langs): void
    {
        if (! Schema::hasTable('ahg_ai_settings')) {
            return;
        }
        $rows = [
            'ocr_languages' => implode(',', $langs),
            'ocr_languages_json' => json_encode($langs, JSON_UNESCAPED_SLASHES),
            'ocr_tesseract_version' => $version,
            'ocr_languages_probed_at' => now()->toIso8601String(),
        ];
        foreach ($rows as $k => $v) {
            $exists = DB::table('ahg_ai_settings')
                ->where('feature', 'ocr')
                ->where('setting_key', $k)
                ->exists();
            if ($exists) {
                DB::table('ahg_ai_settings')
                    ->where('feature', 'ocr')
                    ->where('setting_key', $k)
                    ->update(['setting_value' => $v, 'updated_at' => now()]);
            } else {
                DB::table('ahg_ai_settings')->insert([
                    'feature'       => 'ocr',
                    'setting_key'   => $k,
                    'setting_value' => $v,
                    'updated_at'    => now(),
                ]);
            }
        }
    }
}
