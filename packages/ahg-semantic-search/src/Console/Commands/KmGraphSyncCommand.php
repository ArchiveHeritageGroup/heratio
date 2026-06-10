<?php

/**
 * KmGraphSyncCommand - generate + write KM graph-connection docs.
 *
 * Drives GraphKmBridgeService to turn the cross-collection RiC graph into
 * KM-ingestable markdown. With --id it processes one information object;
 * without, it iterates a bounded set of information objects that actually have
 * relations (respecting --limit, logging anything skipped or capped - never a
 * silent truncation). The generated markdown lands under
 * docs/reference/graph-connections/ where the KM inotify watcher auto-ingests
 * it. heratio#1197 / #1214.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

use AhgSemanticSearch\Services\GraphKmBridgeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KmGraphSyncCommand extends Command
{
    protected $signature = 'ahg:km-graph-sync
        {--id= : One information_object id to sync (writes a single doc)}
        {--limit=0 : Cap when syncing many records (0 = no cap, all related records)}
        {--dry-run : Print the summary instead of writing the markdown doc}';

    protected $description = 'Render cross-collection RiC graph connections to KM-ingestable markdown (heratio#1197/#1214)';

    public function handle(GraphKmBridgeService $bridge): int
    {
        $id = $this->option('id');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        if ($id !== null && $id !== '') {
            return $this->syncOne($bridge, (int) $id, $dryRun);
        }

        return $this->syncMany($bridge, $limit, $dryRun);
    }

    /**
     * Sync a single record. Prints the summary; writes the doc unless --dry-run.
     */
    protected function syncOne(GraphKmBridgeService $bridge, int $objectId, bool $dryRun): int
    {
        $summary = $bridge->connectionsSummary($objectId);

        if ($summary === null) {
            $this->warn("Record #{$objectId} has no resolvable cross-collection connections - nothing to sync.");

            return self::SUCCESS;
        }

        $title = $bridge->recordTitle($objectId);
        $this->line('<info>Record:</info> '.($title ?? '#'.$objectId)." (#{$objectId})");
        $this->newLine();
        $this->line($summary);
        $this->newLine();

        if ($dryRun) {
            $this->comment('Dry run - no file written.');

            return self::SUCCESS;
        }

        $path = $bridge->writeKmDoc($objectId);
        if ($path === null) {
            $this->warn('Nothing written (summary became empty between render and write).');

            return self::SUCCESS;
        }

        $this->info('Wrote: '.$path);
        $this->comment('The KM inotify watcher will ingest this in ~2-3 minutes (no manual trigger needed).');

        return self::SUCCESS;
    }

    /**
     * Sync a bounded set of information objects that have relations.
     *
     * Selection: distinct information_object ids that appear in the relation
     * table (as subject or object), ordered ascending for determinism. The
     * --limit caps how many we WRITE; anything beyond the cap is logged as
     * skipped so there is no silent truncation.
     */
    protected function syncMany(GraphKmBridgeService $bridge, int $limit, bool $dryRun): int
    {
        if (! Schema::hasTable('relation') || ! Schema::hasTable('information_object')) {
            $this->error('relation / information_object tables not present - cannot enumerate.');

            return self::FAILURE;
        }

        // Distinct IO ids that participate in at least one relation. We only
        // want information objects (the record domain), so intersect the
        // relation endpoints with information_object.id.
        $relatedIds = DB::table('relation')
            ->select('subject_id as id')
            ->union(DB::table('relation')->select('object_id as id'))
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique();

        $ioIds = DB::table('information_object')
            ->whereIn('id', $relatedIds->all())
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->values();

        $totalCandidates = $ioIds->count();
        if ($totalCandidates === 0) {
            $this->warn('No information objects with relations found - nothing to sync.');

            return self::SUCCESS;
        }

        $capped = $limit > 0 && $totalCandidates > $limit;
        $toProcess = $capped ? $ioIds->take($limit) : $ioIds;

        $this->info(sprintf(
            'Candidates with relations: %d. Processing: %d.%s',
            $totalCandidates,
            $toProcess->count(),
            $dryRun ? ' (dry run)' : ''
        ));

        if ($capped) {
            $skipped = $totalCandidates - $limit;
            $skippedIds = $ioIds->slice($limit)->values();
            $this->warn(sprintf(
                'CAP APPLIED: --limit=%d, so %d candidate record(s) were NOT processed this run.',
                $limit,
                $skipped
            ));
            // No silent truncation: log the skipped ids (trim the echo, keep full list in laravel.log).
            \Illuminate\Support\Facades\Log::info('ahg:km-graph-sync capped run', [
                'limit' => $limit,
                'candidates' => $totalCandidates,
                'processed' => $toProcess->count(),
                'skipped_count' => $skipped,
                'skipped_ids' => $skippedIds->all(),
            ]);
            $preview = $skippedIds->take(20)->implode(', ');
            $this->line('  Skipped ids (first 20): '.$preview.($skipped > 20 ? ', ...' : ''));
        }

        $written = 0;
        $empty = 0;
        $bar = $this->output->createProgressBar($toProcess->count());
        $bar->start();

        foreach ($toProcess as $objectId) {
            if ($dryRun) {
                $summary = $bridge->connectionsSummary($objectId);
                if ($summary === null) {
                    $empty++;
                } else {
                    $written++; // would-write count
                }
            } else {
                $path = $bridge->writeKmDoc($objectId);
                if ($path === null) {
                    $empty++;
                } else {
                    $written++;
                }
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'would write' : 'wrote';
        $this->info(sprintf(
            'Done: %s %d doc(s); %d record(s) had no resolvable connections (skipped).',
            $verb,
            $written,
            $empty
        ));

        if (! $dryRun && $written > 0) {
            $this->comment('The KM inotify watcher will ingest the new docs in ~2-3 minutes.');
        }

        return self::SUCCESS;
    }
}
