<?php

/**
 * IngestDipsCommand - poll the Storage Service for new DIPs and ingest them.
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

namespace AhgArchivematica\Commands;

use AhgArchivematica\Jobs\IngestDipFromSs;
use AhgArchivematica\Services\ArchivematicaSsClient;
use AhgArchivematica\Services\DipIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Direction 1 (pull) scheduler. Lists DIP packages on the Storage Service and,
 * for each one not already linked in am_link, dispatches an IngestDipFromSs
 * job (or runs it inline with --sync). Intended to be wired into the schedule
 * (e.g. every 15 minutes) once the client confirms the pull cadence.
 *
 * Usage:
 *   php artisan am:ingest-dips
 *   php artisan am:ingest-dips --limit=50 --sync
 */
class IngestDipsCommand extends Command
{
    protected $signature = 'am:ingest-dips
        {--limit=100 : Max DIP packages to fetch from the Storage Service}
        {--sync : Ingest inline instead of dispatching queued jobs}';

    protected $description = 'Poll the Archivematica Storage Service for new DIPs and ingest each into Heratio.';

    public function handle(ArchivematicaSsClient $ss, DipIngestService $service): int
    {
        if (! $ss->isConfigured()) {
            $this->error('Archivematica Storage Service is not configured (am_ss_url/username/api_key).');

            return self::FAILURE;
        }

        $limit = (int) $this->option('limit');
        if ($limit <= 0) {
            $limit = 100;
        }

        try {
            $packages = $ss->listDipPackages([], $limit);
        } catch (\Throwable $e) {
            $this->error('Failed to list DIP packages: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d DIP package(s) on the Storage Service.', count($packages)));

        $dispatched = 0;
        $skipped = 0;

        foreach ($packages as $pkg) {
            $uuid = (string) ($pkg['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            if ($this->alreadyLinked($uuid)) {
                $skipped++;
                continue;
            }

            if ($this->option('sync')) {
                try {
                    $summary = $service->ingestFromSs($uuid);
                    $this->line(sprintf('  %s -> %s', $uuid, $summary['status'] ?? 'done'));
                } catch (\Throwable $e) {
                    $this->error(sprintf('  %s -> FAILED: %s', $uuid, $e->getMessage()));
                }
            } else {
                IngestDipFromSs::dispatch($uuid);
            }
            $dispatched++;
        }

        $this->info(sprintf(
            '%s %d DIP(s); %d already linked (skipped).',
            $this->option('sync') ? 'Ingested' : 'Dispatched',
            $dispatched,
            $skipped
        ));

        return self::SUCCESS;
    }

    private function alreadyLinked(string $uuid): bool
    {
        if (! Schema::hasTable('am_link')) {
            return false;
        }

        return DB::table('am_link')
            ->where('dip_uuid', $uuid)
            ->where('status', 'linked')
            ->exists();
    }
}
