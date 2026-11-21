<?php

/**
 * ahg:translation-db-reconcile - clear ui_string rows that hide or freeze the
 * lang files (heratio#1416, #1510).
 *
 * ui_string wins over lang/{locale}.json (DbAwareLoader). Most of its rows are
 * a one-off import of the files taken on 7 May 2026, so every translation the
 * fill or a review has put into the files since is hidden wherever that old
 * row exists. Per locale this reports, for rows nobody signed off (no matching
 * approved ui_string_change, no ui_string_review row):
 *
 *   - stub rows: the value is the English key while the file has a translation
 *     - the site shows English although a translation ships;
 *   - same-as-file rows: identical to the file today, so they change nothing
 *     now but freeze the text when the file is corrected later;
 *   - differing rows: a different text from the file's translation. Nobody can
 *     tell from the row who wrote it, so these are listed, never removed.
 *
 * With --apply the first two kinds are deleted, so the file shows through.
 * Signed-off rows and rows for keys the file does not translate are kept.
 * Dry run by default; run it on each install after its lang files are
 * deployed.
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

use AhgCore\Services\UiCoverageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TranslationDbReconcileCommand extends Command
{
    protected $signature = 'ahg:translation-db-reconcile
        {--locale=* : Restrict to these locales; default = every lang/*.json}
        {--examples=3 : Differing rows to print per locale}
        {--lang-dir= : Compare with the locale files in this directory instead of lang/}
        {--apply : Delete the unreviewed stub and same-as-file rows (default: report only)}';

    protected $description = 'Report, and with --apply clear, ui_string rows that hide or freeze the lang file translations';

    public function handle(): int
    {
        if (! Schema::hasTable('ui_string')) {
            $this->info('No ui_string table: the lang files are served as they are.');

            return self::SUCCESS;
        }
        $svc = new UiCoverageService(trim((string) $this->option('lang-dir')) ?: null);
        $only = (array) $this->option('locale');
        $apply = (bool) $this->option('apply');
        $rows = [];
        $deleted = 0;
        foreach ($svc->locales() as $locale) {
            if ($only && ! in_array($locale, $only, true)) {
                continue;
            }
            $file = $svc->fileMap($locale) ?? [];
            $db = $svc->dbMap($locale);
            if (! $db) {
                continue;
            }
            $drift = $svc->dbDrift($file, $db, $svc->protectedKeys($locale, $db));
            $rows[] = [$locale, count($db), count($drift['stub_hides_file']), count($drift['same_as_file']), count($drift['differs_from_file'])];
            foreach (array_slice($drift['differs_from_file'], 0, max(0, (int) $this->option('examples'))) as $k) {
                $this->line(sprintf('  %s differs: "%s" - ui_string "%s", file "%s"', $locale, mb_substr($k, 0, 50), mb_substr($db[$k], 0, 50), mb_substr((string) $file[$k], 0, 50)));
            }
            if ($apply) {
                foreach (array_chunk(array_merge($drift['stub_hides_file'], $drift['same_as_file']), 500) as $keys) {
                    $deleted += DB::table('ui_string')->where('culture', $locale)->whereIn('key', $keys)->delete();
                }
            }
        }
        $this->table(['locale', 'ui_string rows', 'stub hides file', 'same as file', 'differs (kept)'], $rows);
        $this->info($apply
            ? "{$deleted} ui_string row(s) deleted; the lang file now shows for those keys."
            : 'Dry run. --apply deletes the stub and same-as-file rows; differing and signed-off rows are kept.');

        return self::SUCCESS;
    }
}
