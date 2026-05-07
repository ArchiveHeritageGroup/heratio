<?php

/**
 * ahg:translation:import-json-to-db - one-shot import of every lang/{locale}.json
 * file into the ui_string table (issue #57).
 *
 * Idempotent: re-running over an existing ui_string set does an upsert (INSERT
 * ... ON DUPLICATE KEY UPDATE) per row - no duplicates, no truncation. Empty
 * and null values are skipped (the DB representation of "no translation" is
 * "row absent", same as the JSON behaviour where a missing key falls back to
 * the source string).
 *
 * Reports per-culture counts: total rows in the JSON file, rows inserted
 * (didn't exist before), rows updated (key existed with a different value),
 * rows untouched (key existed with the same value), rows skipped (empty value).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing iSystems
 * Licensed under AGPL-3.0-or-later. See LICENSE.
 */

namespace AhgTranslation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportJsonToDbCommand extends Command
{
    protected $signature = 'ahg:translation:import-json-to-db
        {--locale= : Restrict to a single culture (default: every lang/*.json file)}
        {--dry-run : Report what would be imported without writing}
        {--batch=500 : Insert batch size}';

    protected $description = 'Import every lang/{locale}.json into the ui_string table (idempotent upsert)';

    public function handle(): int
    {
        if (!Schema::hasTable('ui_string')) {
            $this->error('ui_string table does not exist - boot the app once to auto-install it, or run: mysql -u root heratio < packages/ahg-translation/database/install.sql');
            return self::FAILURE;
        }

        $only    = (string) ($this->option('locale') ?? '');
        $dry     = (bool) $this->option('dry-run');
        $batch   = max(1, (int) $this->option('batch'));

        $files = glob(base_path('lang/*.json')) ?: [];
        if (empty($files)) {
            $this->warn('No lang/*.json files found at ' . base_path('lang'));
            return self::SUCCESS;
        }

        $totals = [
            'cultures'  => 0,
            'total'     => 0,
            'inserted'  => 0,
            'updated'   => 0,
            'unchanged' => 0,
            'skipped'   => 0,
        ];

        $this->line(sprintf(
            '%sImporting lang/*.json into ui_string (%s mode)',
            $dry ? '[DRY RUN] ' : '',
            $dry ? 'preview only' : 'live upsert'
        ));
        $this->line(str_repeat('-', 78));

        foreach ($files as $path) {
            $culture = pathinfo($path, PATHINFO_FILENAME);
            if ($only !== '' && $culture !== $only) {
                continue;
            }
            if (!preg_match('/^[a-z][a-z0-9_-]{0,15}$/i', $culture)) {
                $this->warn("Skipping non-locale-shaped file: {$path}");
                continue;
            }

            $json = json_decode((string) file_get_contents($path), true);
            if (!is_array($json)) {
                $this->warn("Could not parse: {$path}");
                continue;
            }

            $totals['cultures']++;

            $perCulture = [
                'total'     => count($json),
                'inserted'  => 0,
                'updated'   => 0,
                'unchanged' => 0,
                'skipped'   => 0,
            ];

            // Pre-load the existing ui_string rows for this culture so we
            // can classify each incoming row as inserted/updated/unchanged
            // without N round-trips. Only used for reporting; the actual
            // write is a single batched upsert.
            $existing = [];
            DB::table('ui_string')->where('culture', $culture)
                ->select(['key', 'value'])
                ->orderBy('id')
                ->chunk(2000, function ($rows) use (&$existing) {
                    foreach ($rows as $r) {
                        $existing[(string) $r->key] = (string) ($r->value ?? '');
                    }
                });

            $upsertRows = [];
            $now = now();

            foreach ($json as $key => $value) {
                $key = (string) $key;
                $value = $value === null ? '' : (string) $value;

                if ($key === '' || $value === '') {
                    $perCulture['skipped']++;
                    continue;
                }

                if (array_key_exists($key, $existing)) {
                    if ($existing[$key] === $value) {
                        $perCulture['unchanged']++;
                        continue;
                    }
                    $perCulture['updated']++;
                } else {
                    $perCulture['inserted']++;
                }

                $upsertRows[] = [
                    'key'        => $key,
                    'culture'    => $culture,
                    'value'      => $value,
                    'updated_at' => $now,
                ];
            }

            if (!$dry && !empty($upsertRows)) {
                foreach (array_chunk($upsertRows, $batch) as $chunk) {
                    DB::table('ui_string')->upsert(
                        $chunk,
                        ['key', 'culture'],
                        ['value', 'updated_at']
                    );
                }
            }

            $this->line(sprintf(
                '  %-8s  total=%-5d  inserted=%-5d  updated=%-5d  unchanged=%-5d  skipped=%-4d',
                $culture,
                $perCulture['total'],
                $perCulture['inserted'],
                $perCulture['updated'],
                $perCulture['unchanged'],
                $perCulture['skipped']
            ));

            foreach (['total', 'inserted', 'updated', 'unchanged', 'skipped'] as $k) {
                $totals[$k] += $perCulture[$k];
            }
        }

        $this->line(str_repeat('-', 78));
        $this->info(sprintf(
            '%sCultures=%d  total_rows=%d  inserted=%d  updated=%d  unchanged=%d  skipped(empty)=%d',
            $dry ? '[DRY RUN] ' : '',
            $totals['cultures'],
            $totals['total'],
            $totals['inserted'],
            $totals['updated'],
            $totals['unchanged'],
            $totals['skipped']
        ));

        if ($dry) {
            $this->comment('Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }
}
