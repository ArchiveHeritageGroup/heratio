<?php

/**
 * CacheClearCommand - Console command for Heratio
 *
 * Task 10 (CLI consolidation). Destructive: deletes rows from
 * ahg_authority_lookup_cache. Either targets a single source (--source=NAME)
 * or every source (--all). Defaults to an interactive yes/no confirm; pass
 * --force to skip the prompt (suitable for cron / scripted runs).
 *
 * "Clearing" the cache forces the next lookup-engine call to hit the live
 * external API; the row is then re-inserted with a fresh retrieved_at +
 * ttl_seconds. Useful when an upstream changed its data and a stale row is
 * surfacing wrong values.
 *
 * Usage:
 *   php artisan auth-res:cache-clear --source=viaf
 *   php artisan auth-res:cache-clear --source=viaf --force
 *   php artisan auth-res:cache-clear --all --force
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

namespace AhgAuthorityResolution\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CacheClearCommand extends Command
{
    protected $signature = 'auth-res:cache-clear
                            {--source= : Source to purge (viaf, wikidata, geonames, tgn, gnd, isni, sagnc)}
                            {--all : Purge every source}
                            {--force : Skip the interactive confirm prompt}';

    protected $description = 'Delete rows from ahg_authority_lookup_cache by source (or --all). Re-population happens on next live lookup.';

    public function handle(): int
    {
        $source = $this->option('source');
        $all = (bool) $this->option('all');
        $force = (bool) $this->option('force');

        if ($source === null && ! $all) {
            $this->error('Provide --source=NAME or --all.');

            return self::FAILURE;
        }
        if ($source !== null && $all) {
            $this->error('--source and --all are mutually exclusive.');

            return self::FAILURE;
        }

        $q = DB::table('ahg_authority_lookup_cache');
        if (! $all) {
            $q->where('source', $source);
        }

        $count = (int) (clone $q)->count();
        if ($count === 0) {
            $label = $all ? 'all sources' : "source={$source}";
            $this->info("ahg_authority_lookup_cache: 0 rows match {$label}; nothing to do.");

            return self::SUCCESS;
        }

        $label = $all ? 'ALL sources' : "source={$source}";
        if (! $force) {
            $confirmed = $this->confirm("Delete {$count} row(s) from ahg_authority_lookup_cache ({$label})?", false);
            if (! $confirmed) {
                $this->info('Aborted.');

                return self::SUCCESS;
            }
        }

        $deleted = (int) $q->delete();
        $this->info(sprintf('Deleted %d row(s) from ahg_authority_lookup_cache (%s).', $deleted, $label));

        return self::SUCCESS;
    }
}
