<?php

/**
 * ahg:translation-review-export - write a native-speaker review workbook for a
 * locale (heratio#1445).
 *
 * Same layout the import reads: id (sha1(english)[:10]), english,
 * {locale}_machine, {locale}_corrected (blank), must_keep_unchanged, context
 * (a file where the string is used), note. By default only strings waiting for
 * review are exported: live translations not yet reviewed plus held ones
 * staged with ahg:translation-review-import --stage.
 *
 *   php artisan ahg:translation-review-export zu --out=/path/heratio-zu-review.xlsx
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Commands;

use AhgCore\Services\UiStringReviewService;
use Illuminate\Console\Command;

class TranslationReviewExportCommand extends Command
{
    protected $signature = 'ahg:translation-review-export
        {locale* : One or more locales, e.g. zu xh ss nso tn ts st}
        {--out= : Output directory or .xlsx path (default storage/app/translation-review)}
        {--all : Include strings already reviewed}
        {--no-context : Skip the source scan for the context column}';

    protected $description = 'Write a review workbook per locale for native-speaker review of machine translations';

    public function handle(UiStringReviewService $review): int
    {
        $locales = (array) $this->argument('locale');
        $out = (string) ($this->option('out') ?: storage_path('app/translation-review'));
        $single = str_ends_with($out, '.xlsx');
        if ($single && count($locales) > 1) {
            $this->error('Give a directory for --out when exporting more than one locale.');

            return self::FAILURE;
        }
        $dir = $single ? dirname($out) : $out;
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            $this->error("Cannot create {$dir}.");

            return self::FAILURE;
        }
        foreach ($locales as $locale) {
            if (! UiStringReviewService::validLocale($locale) || ! in_array($locale, $review->locales(), true)) {
                $this->warn("{$locale}: no lang file, skipped.");

                continue;
            }
            $rows = $review->exportRows($locale, (bool) $this->option('all'), ! $this->option('no-context'));
            $path = $single ? $out : $dir."/heratio-{$locale}-review-".date('Y-m-d').'.xlsx';
            $review->writeWorkbook($locale, $rows, $path);
            $this->info("{$locale}: ".count($rows)." string(s) -> {$path}");
        }

        return self::SUCCESS;
    }
}
