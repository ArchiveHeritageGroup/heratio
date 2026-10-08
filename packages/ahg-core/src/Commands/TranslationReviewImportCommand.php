<?php

/**
 * ahg:translation-review-import - merge a reviewer's corrections back into
 * lang/{locale}.json (heratio#1445).
 *
 * Reads a review spreadsheet in the layout sent to reviewers (id, english,
 * {locale}_current, {locale}_corrected, must_keep_unchanged, notes). A row is
 * applied when its corrected cell is filled; a blank corrected cell means "fine
 * as is". The english key is found from the id (the first 10 hex characters of
 * sha1(english)), so an edited english cell cannot redirect a correction. A
 * correction that drops a token from must_keep_unchanged is refused and
 * reported. Applied locales are marked reviewed in lang/_meta.json.
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

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TranslationReviewImportCommand extends Command
{
    protected $signature = 'ahg:translation-review-import
        {locale : The locale the sheet reviews, e.g. zu or ar}
        {file : The reviewed .xlsx or .csv}
        {--reviewer= : Name recorded in lang/_meta.json}
        {--dry-run : Report what would change; write nothing}';

    protected $description = 'Merge a reviewer\'s corrected translations from a review spreadsheet into lang/{locale}.json';

    public function handle(): int
    {
        $locale = (string) $this->argument('locale');
        $file = (string) $this->argument('file');
        $langPath = base_path("lang/{$locale}.json");
        if (! is_file($file) || ! is_file($langPath)) {
            $this->error('Sheet or locale file not found.');

            return self::FAILURE;
        }

        $byId = [];
        foreach (array_keys(json_decode((string) file_get_contents(base_path('lang/en.json')), true) ?: []) as $en) {
            $byId[substr(sha1((string) $en), 0, 10)] = (string) $en;
        }
        $rows = IOFactory::load($file)->getActiveSheet()->toArray(null, false, false, false);
        $head = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $col = fn (string $name) => array_search($name, $head, true);
        [$cId, $cFix, $cKeep] = [$col('id'), $col("{$locale}_corrected"), $col('must_keep_unchanged')];
        if ($cId === false || $cFix === false) {
            $this->error("The sheet needs id and {$locale}_corrected columns.");

            return self::FAILURE;
        }

        $target = json_decode((string) file_get_contents($langPath), true) ?: [];
        $applied = $refused = $unknown = 0;
        foreach ($rows as $r) {
            $fix = trim((string) ($r[$cFix] ?? ''));
            if ($fix === '') {
                continue;
            }
            $en = $byId[trim((string) ($r[$cId] ?? ''))] ?? null;
            if ($en === null) {
                $unknown++;

                continue;
            }
            $keep = $cKeep !== false ? preg_split('/\s+/', trim((string) ($r[$cKeep] ?? '')), -1, PREG_SPLIT_NO_EMPTY) : [];
            $missing = array_filter($keep, fn ($t) => ! str_contains($fix, $t));
            if ($missing) {
                $refused++;
                $this->warn('Refused (drops '.implode(' ', $missing).'): '.mb_substr($en, 0, 70));

                continue;
            }
            if (($target[$en] ?? null) !== $fix) {
                $target[$en] = $fix;
                $applied++;
            }
        }

        $this->info("{$applied} correction(s) to apply, {$refused} refused, {$unknown} with an unknown id.");
        if ($this->option('dry-run') || $applied === 0) {
            return self::SUCCESS;
        }
        file_put_contents($langPath, json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        $metaPath = base_path('lang/_meta.json');
        $meta = json_decode((string) @file_get_contents($metaPath), true) ?: ['locales' => []];
        $meta['locales'][$locale]['reviewed_at'] = date('Y-m-d');
        $meta['locales'][$locale]['reviewed_count'] = (int) ($meta['locales'][$locale]['reviewed_count'] ?? 0) + $applied;
        if ($reviewer = $this->option('reviewer')) {
            $meta['locales'][$locale]['reviewed_by'] = (string) $reviewer;
        }
        file_put_contents($metaPath, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

        return self::SUCCESS;
    }
}
