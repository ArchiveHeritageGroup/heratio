<?php

/**
 * IiifChangeBackfillCommand - artisan command for Heratio
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

namespace AhgIiifCollection\Commands;

use AhgIiifCollection\Services\IiifChangeDiscoveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed the IIIF Change Discovery activity stream from existing
 * information_object rows that already have digital objects. Used once
 * at install time so /iiif/discovery/changes isn't empty until the next
 * manifest CRUD happens. Issue #695.
 *
 * Usage:
 *   php artisan iiif:change-backfill          # all eligible IOs
 *   php artisan iiif:change-backfill --limit=500
 *   php artisan iiif:change-backfill --since=2026-01-01
 */
class IiifChangeBackfillCommand extends Command
{
    protected $signature = 'iiif:change-backfill
                            {--limit= : Cap on rows to emit}
                            {--since= : Only emit events for IOs updated_at >= this date}';

    protected $description = 'Backfill the IIIF Change Discovery activity stream from existing manifestable information_object rows.';

    public function handle(IiifChangeDiscoveryService $service): int
    {
        $q = DB::table('information_object as io')
            ->join('digital_object as do', 'io.id', '=', 'do.object_id')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->select('io.id', 's.slug', 'io.updated_at')
            ->distinct()
            ->orderBy('io.id');

        if ($since = $this->option('since')) {
            $q->where('io.updated_at', '>=', $since);
        }
        if ($limit = $this->option('limit')) {
            $q->limit((int) $limit);
        }

        $count = 0;
        foreach ($q->cursor() as $row) {
            $service->recordChange('Create', (int) $row->id, (string) $row->slug, 'system:backfill');
            $count++;
            if ($count % 200 === 0) {
                $this->info("Backfilled {$count} manifest changes ...");
            }
        }
        $this->info("Backfill complete: {$count} manifest changes recorded.");
        return self::SUCCESS;
    }
}
