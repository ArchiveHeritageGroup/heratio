<?php

/**
 * RefreshFederatedDiscoveriesCommand - Heratio
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

namespace AhgSemanticSearch\Console\Commands;

use AhgSemanticSearch\Services\ScholarshipService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1210 - warm / refresh the persisted cross-institutional (federated)
 * discovery cache (ahg_scholarship_federated_discovery). Without this, the cache
 * fills lazily as records are viewed; running it on a schedule keeps the most
 * worthwhile records (those that already have a LOCAL discovery) pre-computed, so
 * a visitor never waits on a live peer + AI round-trip. Fail-soft: it relies on
 * ScholarshipService::discoverFederatedCached(), which never throws.
 */
class RefreshFederatedDiscoveriesCommand extends Command
{
    protected $signature = 'ahg:refresh-federated-discoveries
        {--object= : Refresh just this information_object id}
        {--limit=200 : Max records to refresh in one run (ignored with --object)}
        {--stale-only : Only refresh rows already past the freshness window (skip fresh ones)}';

    protected $description = 'Refresh the persisted federated (cross-institutional) discovery cache (heratio#1210).';

    public function handle(): int
    {
        $service = new ScholarshipService;

        // Force a live re-run unless --stale-only, where the cache decides per row.
        $force = ! $this->option('stale-only');

        $ids = $this->targetIds();
        if (empty($ids)) {
            $this->info('No records to refresh.');

            return self::SUCCESS;
        }

        $this->info('Refreshing federated discoveries for '.count($ids).' record(s)'.($force ? '' : ' (stale only)').'...');

        $withConnections = 0;
        $total = 0;
        foreach ($ids as $id) {
            $result = $service->discoverFederatedCached((int) $id, $force);
            $count = is_array($result['connections'] ?? null) ? count($result['connections']) : 0;
            $total += $count;
            if ($count > 0) {
                $withConnections++;
            }
        }

        $this->info("Done. {$withConnections} record(s) have cross-institutional connections; {$total} connection(s) cached.");

        return self::SUCCESS;
    }

    /**
     * The records to refresh: a single --object, else the records that already
     * carry a LOCAL discovery (the set worth federating), capped by --limit.
     *
     * @return array<int,int>
     */
    private function targetIds(): array
    {
        $object = $this->option('object');
        if ($object !== null && ctype_digit((string) $object)) {
            return [(int) $object];
        }

        if (! Schema::hasTable('ahg_scholarship_discovery')) {
            return [];
        }

        $limit = max(1, (int) $this->option('limit'));

        return DB::table('ahg_scholarship_discovery')
            ->orderByDesc('confidence')
            ->limit($limit)
            ->pluck('information_object_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
