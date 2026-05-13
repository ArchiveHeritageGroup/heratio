<?php

/**
 * BackfillLibraryAuthorsCommand - promote pre-existing library_item_creator rows
 * into proper Authority Records by upserting an actor for each creator name and
 * populating library_item_creator.actor_id.
 *
 * Idempotent: rows with actor_id already set are skipped. Re-running after new
 * library imports is safe.
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

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\LibraryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillLibraryAuthorsCommand extends Command
{
    protected $signature = 'ahg:library-backfill-authors
                            {--dry-run : Report what would change without writing}
                            {--culture= : Force a specific culture (default: app locale)}';

    protected $description = 'Upsert an Authority Record (actor) for every library_item_creator row whose actor_id is NULL and link them.';

    public function handle(LibraryService $service): int
    {
        $culture = (string) ($this->option('culture') ?: app()->getLocale());
        $fallback = (string) config('app.fallback_locale', 'en');
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('library_item_creator')
            ->whereNull('actor_id')
            ->orderBy('id')
            ->get(['id', 'name']);

        if ($rows->isEmpty()) {
            $this->info('Nothing to do: every library_item_creator row already has actor_id set.');
            return self::SUCCESS;
        }

        $this->info(sprintf('Found %d unlinked creator rows (culture=%s%s)', $rows->count(), $culture, $dryRun ? ', DRY RUN' : ''));

        $bar = $this->output->createProgressBar($rows->count());
        $bar->start();

        // Resolve each unique name once so repeated authors don't create
        // duplicate actor records inside this run.
        $cache = [];
        $created = 0;
        $reused = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                $skipped++;
                $bar->advance();
                continue;
            }

            if (!array_key_exists($name, $cache)) {
                // Check whether an actor already exists for this name (so we can
                // report "reused" vs "created") before delegating to the service.
                $existing = DB::table('actor_i18n')
                    ->whereIn('culture', array_unique([$culture, $fallback]))
                    ->whereRaw('LOWER(TRIM(authorized_form_of_name)) = ?', [mb_strtolower($name)])
                    ->orderByRaw('FIELD(culture, ?, ?)', [$culture, $fallback])
                    ->value('id');

                if ($dryRun) {
                    $cache[$name] = $existing ?: 0;
                    if ($existing) {
                        $reused++;
                    } else {
                        $created++;
                    }
                } else {
                    $cache[$name] = $service->resolveOrCreateActor($name, $culture, $fallback);
                    if ($existing) {
                        $reused++;
                    } else {
                        $created++;
                    }
                }
            }

            if (!$dryRun && $cache[$name]) {
                DB::table('library_item_creator')
                    ->where('id', $row->id)
                    ->update(['actor_id' => $cache[$name]]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info(sprintf(
            '%s%d rows processed: %d reused existing actors, %d new actors %s, %d skipped (empty names).',
            $dryRun ? '[DRY RUN] ' : '',
            $rows->count(),
            $reused,
            $created,
            $dryRun ? 'would be created' : 'created',
            $skipped
        ));

        if (!$dryRun) {
            $this->line('Run `php artisan ahg:es-reindex --index=informationobject` to refresh the search index so creators now resolve to authority records.');
        }

        return self::SUCCESS;
    }
}
