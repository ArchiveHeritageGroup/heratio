<?php

/**
 * CrmGraphSyncCommand - push CIDOC-CRM named graphs into Fuseki
 *
 * Bulk / single-object driver for CrmGraphSyncService. Completes the
 * unified-knowledge-graph loop (#1197 / #1214): the museum CIDOC export
 * already emits RDF/Turtle per object; this command writes that Turtle
 * into the shared Fuseki triple store as per-object named graphs
 * (urn:ahg:crm:<class-or-record>:<id>) so the records are queryable as
 * CIDOC-CRM next to the RiC-native entities.
 *
 *   ahg:crm-graph-sync --id=905228          # one object (museum class)
 *   ahg:crm-graph-sync --type=museum         # every museum object
 *   ahg:crm-graph-sync --type=io --limit=50  # first 50 information objects
 *   ahg:crm-graph-sync --id=905228 --dry-run # print Turtle + graph URI only
 *
 * Idempotent: each object's graph is replaced (DROP + INSERT DATA) per
 * sync via CrmGraphSyncService::syncObject(), so re-runs never duplicate.
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

namespace AhgRic\Console\Commands;

use AhgRic\Services\CrmGraphSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrmGraphSyncCommand extends Command
{
    protected $signature = 'ahg:crm-graph-sync
                            {--id= : Sync a single object by id (overrides --type/--limit selection)}
                            {--type=museum : Object set to sync when --id is absent: museum|io}
                            {--limit=0 : Cap objects processed (0 = no cap; any cap is logged loudly)}
                            {--culture=en : Culture to serialize}
                            {--dry-run : Print the target graph URI + Turtle for each object; write nothing}';

    protected $description = 'Push CIDOC-CRM triples for museum / information-object records into the Fuseki named-graph store (#1197/#1214).';

    public function handle(CrmGraphSyncService $sync): int
    {
        $idOpt   = $this->option('id');
        $type    = strtolower((string) $this->option('type'));
        $limit   = (int) $this->option('limit');
        $culture = (string) ($this->option('culture') ?: 'en');
        $dryRun  = (bool) $this->option('dry-run');

        if ($idOpt !== null && trim((string) $idOpt) !== '') {
            $objectId = (int) $idOpt;
            // A single explicit id always types as the requested set's class
            // (museum -> E22; io -> archival E73 default).
            $recordClass = $this->recordClassForType($type, $sync);
            return $this->processOne($sync, $objectId, $recordClass, $culture, $dryRun)
                ? self::SUCCESS
                : self::FAILURE;
        }

        if (!in_array($type, ['museum', 'io'], true)) {
            $this->error("Unknown --type '{$type}'. Use 'museum' or 'io'.");
            return self::FAILURE;
        }

        $objectIds = $this->collectObjectIds($type, $limit);
        $total = count($objectIds);

        if ($limit > 0) {
            // Loud, deliberate notice - never truncate silently.
            $this->warn(sprintf(
                '--limit=%d in effect: processing %d of the available %s object(s). Re-run without --limit for a full sync.',
                $limit,
                $total,
                $type
            ));
        }

        if ($total === 0) {
            $this->warn("No {$type} objects found to sync.");
            return self::SUCCESS;
        }

        $recordClass = $this->recordClassForType($type, $sync);

        $this->line(sprintf(
            '%s %d %s object(s) into CRM named graphs (prefix-class: %s, culture: %s)%s ...',
            $dryRun ? 'DRY-RUN:' : 'Syncing',
            $total,
            $type,
            $recordClass ?? 'record (E73 default)',
            $culture,
            $dryRun ? '' : ' [replace-graph]'
        ));

        $ok = 0;
        $failed = 0;
        foreach ($objectIds as $objectId) {
            if ($this->processOne($sync, $objectId, $recordClass, $culture, $dryRun)) {
                $ok++;
            } else {
                $failed++;
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d %s graph(s) %s, %d failed/skipped.',
            $dryRun ? 'DRY-RUN:' : 'Done:',
            $ok,
            $type,
            $dryRun ? 'previewed' : 'written',
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sync (or preview) one object. Returns true on success/preview, false
     * on a Fuseki failure or empty serialization.
     */
    private function processOne(CrmGraphSyncService $sync, int $objectId, ?string $recordClass, string $culture, bool $dryRun): bool
    {
        $graphUri = $sync->graphUri($objectId, $recordClass);

        if ($dryRun) {
            $turtle = $sync->buildTurtle($objectId, $recordClass, $culture);
            $this->newLine();
            $this->line("<info>Graph URI:</info> {$graphUri}");
            if (trim($turtle) === '') {
                $this->warn("  (empty serialization for object {$objectId} in culture {$culture} - nothing would be written)");
                return false;
            }
            $this->line('--- Turtle ---');
            $this->line($turtle);
            $this->line('--- end ---');
            return true;
        }

        $result = $sync->syncObject($objectId, $recordClass, $culture);
        if ($result) {
            $this->line("  <info>ok</info>   {$graphUri}");
        } else {
            $this->line("  <error>fail</error> {$graphUri} (see log)");
        }
        return $result;
    }

    /**
     * Record-class CURIE for a type. Museum objects are physical artefacts
     * (crm:E22_Human-Made_Object via rico:Item, same as MuseumController);
     * information objects use the archival rico:Record -> E73 default
     * (recordClass = null).
     */
    private function recordClassForType(string $type, CrmGraphSyncService $sync): ?string
    {
        return $type === 'museum' ? $sync->museumRecordClass() : null;
    }

    /**
     * Object ids for the requested set.
     *   museum -> museum_metadata.object_id
     *   io     -> information_object.id (excluding the root id=1)
     *
     * @return array<int,int>
     */
    private function collectObjectIds(string $type, int $limit): array
    {
        if ($type === 'museum') {
            $q = DB::table('museum_metadata')
                ->whereNotNull('object_id')
                ->orderBy('object_id')
                ->select('object_id');
            if ($limit > 0) {
                $q->limit($limit);
            }
            return $q->pluck('object_id')->map(fn ($v) => (int) $v)->all();
        }

        // io
        $q = DB::table('information_object')
            ->where('id', '!=', 1) // skip the synthetic root
            ->orderBy('id')
            ->select('id');
        if ($limit > 0) {
            $q->limit($limit);
        }
        return $q->pluck('id')->map(fn ($v) => (int) $v)->all();
    }
}
