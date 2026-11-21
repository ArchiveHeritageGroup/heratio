<?php

/**
 * ahg:translation-review-import - merge a reviewer's corrections back into
 * lang/{locale}.json (heratio#1445).
 *
 * Reads a review spreadsheet in the layout sent to reviewers (id, english,
 * {locale}_machine or {locale}_current, {locale}_corrected,
 * must_keep_unchanged, ...). A filled corrected cell is a correction; a blank
 * one means "fine as is" and accepts the machine text (--no-accept-blank turns
 * that off). The english key is found from the id (the first 10 hex characters
 * of sha1(english)), so an edited english cell cannot redirect a correction. A
 * row whose placeholders do not match the English is refused and reported.
 * Reviewed strings go to ui_string and lang/{locale}.json and are marked in
 * ui_string_review, so the machine fill never overwrites them.
 *
 * --stage loads a held sheet (zu/xh/ts, kept out of git) as pending review
 * rows without making anything live. The work itself is in
 * AhgCore\Services\UiStringReviewService.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Commands;

use AhgCore\Services\UiStringReviewService;
use Illuminate\Console\Command;

class TranslationReviewImportCommand extends Command
{
    protected $signature = 'ahg:translation-review-import
        {locale : The locale the sheet reviews, e.g. zu or ar}
        {file : The reviewed .xlsx or .csv}
        {--reviewer= : Name recorded with each reviewed string}
        {--target=both : Where reviewed strings go: both, db (ui_string only, for production) or file (lang/{locale}.json only)}
        {--no-accept-blank : A blank corrected cell leaves the string unreviewed instead of accepting the machine text}
        {--stage : Load held machine translations as pending review rows; nothing goes live}
        {--dry-run : Report what would change; write nothing}';

    protected $description = 'Import a reviewed translation workbook (or stage a held one) into ui_string, lang/{locale}.json and the review log';

    public function handle(UiStringReviewService $review): int
    {
        $locale = (string) $this->argument('locale');
        $file = (string) $this->argument('file');
        if (! UiStringReviewService::validLocale($locale) || ! is_file($file)) {
            $this->error('Unknown locale or sheet not found.');

            return self::FAILURE;
        }
        $target = (string) $this->option('target');
        if (! in_array($target, ['both', 'db', 'file'], true)) {
            $this->error('--target must be both, db or file.');

            return self::FAILURE;
        }

        try {
            if ($this->option('stage')) {
                if ($this->option('dry-run')) {
                    $this->info(count($review->readWorkbook($locale, $file)).' row(s) in the sheet; nothing staged (dry run).');

                    return self::SUCCESS;
                }
                $n = $review->stageHeld($locale, $file, basename($file));
                $this->info("{$n['staged']} held string(s) staged for review, {$n['skipped']} skipped (no machine text or already reviewed), {$n['unknown']} with an unknown id.");

                return self::SUCCESS;
            }
            $preview = $review->preview($locale, $file, ! $this->option('no-accept-blank'));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $items = $preview['items'];
        $refused = array_filter($items, fn ($i) => $i['problem'] !== null);
        foreach ($refused as $i) {
            $this->warn("Refused ({$i['problem']}): ".mb_substr($i['key'], 0, 70));
        }
        $corrections = count(array_filter($items, fn ($i) => $i['problem'] === null && $i['action'] === UiStringReviewService::CORRECTED));
        $accepts = count($items) - count($refused) - $corrections;
        $this->info("{$corrections} correction(s) and {$accepts} accepted string(s) to apply, ".count($refused)." refused, {$preview['unknown']} with an unknown id, {$preview['unchanged']} unchanged.");
        if ($this->option('dry-run') || count($items) === count($refused)) {
            return self::SUCCESS;
        }

        $reviewer = (string) ($this->option('reviewer') ?: 'unnamed reviewer');
        $done = $review->apply($locale, $items, $reviewer, null, $target !== 'file', $target !== 'db');
        $this->info("Applied: {$done['corrected']} corrected, {$done['accepted']} accepted.");

        return self::SUCCESS;
    }
}
