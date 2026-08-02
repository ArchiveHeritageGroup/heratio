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
        {--sync : Ingest inline instead of dispatching queued jobs}
        {--retry-unmatched : Re-evaluate DIPs previously recorded as unmatched (use after adding matching records)}';

    protected $description = 'Poll the Archivematica Storage Service for new DIPs and ingest each into Heratio.';

    public function handle(ArchivematicaSsClient $ss, DipIngestService $service): int
    {
        if (! $ss->isConfigured()) {
            // No-op cleanly on an instance not wired to Archivematica (#1429) so a
            // managed cron_schedule row on e.g. the demo does nothing instead of
            // logging a failure every run.
            $this->info('Archivematica Storage Service is not configured (am_ss_url/username/api_key) - nothing to do.');

            return self::SUCCESS;
        }

        $retryUnmatched = (bool) $this->option('retry-unmatched');

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
        $skippedLinked = 0;
        $skippedUnmatched = 0;

        foreach ($packages as $pkg) {
            $uuid = (string) ($pkg['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            // Skip anything we've already resolved. A DIP that matched a record is
            // always skipped; a DIP we saw and could not match is skipped too
            // (#1430 - it used to be re-dispatched on every sweep, thrashing the
            // 15-minute cron) unless --retry-unmatched asks us to re-evaluate it
            // (e.g. after a matching record was added).
            $seen = $this->linkStatus($uuid);
            if ($seen === 'linked') {
                $skippedLinked++;
                continue;
            }
            if ($seen === 'unmatched' && ! $retryUnmatched) {
                $skippedUnmatched++;
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
            '%s %d DIP(s); %d already linked, %d unmatched (skipped)%s.',
            $this->option('sync') ? 'Ingested' : 'Dispatched',
            $dispatched,
            $skippedLinked,
            $skippedUnmatched,
            $retryUnmatched ? ' [--retry-unmatched: unmatched re-evaluated]' : ''
        ));

        return self::SUCCESS;
    }

    /**
     * The recorded am_link outcome for a DIP UUID: 'linked', 'unmatched', or null
     * if we have never seen it. DipIngestService records BOTH outcomes, so this
     * is the durable "already seen" marker (#1430).
     */
    private function linkStatus(string $uuid): ?string
    {
        if (! Schema::hasTable('am_link')) {
            return null;
        }

        return DB::table('am_link')
            ->where('dip_uuid', $uuid)
            ->orderByDesc('id')
            ->value('status');
    }
}
