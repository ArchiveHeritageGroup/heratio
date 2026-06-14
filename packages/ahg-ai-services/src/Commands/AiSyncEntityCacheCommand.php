<?php

/**
 * AiSyncEntityCacheCommand - sync approved/linked NER entities from
 * ahg_ner_entity into heritage_entity_cache so the heritage discovery
 * filters can use AI-derived access points.
 *
 * Ported from the AtoM ahgAIPlugin ai:sync-entity-cache task. This is a
 * pure database projection - it performs no AI inference, so there is no
 * gateway/node concern here.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgAiServices\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiSyncEntityCacheCommand extends Command
{
    /** NER entity_type -> heritage_entity_cache entity_type. */
    private const TYPE_MAP = [
        'PERSON' => 'person',
        'ORG'    => 'organization',
        'GPE'    => 'place',
        'LOC'    => 'place',
        'DATE'   => 'date',
        'EVENT'  => 'event',
        'WORK'   => 'work',
    ];

    protected $signature = 'ahg:ai-sync-entity-cache
        {--limit=1000        : Maximum objects to process}
        {--object-id=        : Sync a single object only}
        {--since-id=         : Only objects with id > this value}
        {--min-confidence=0.70 : Minimum NER confidence to sync}
        {--clean-orphaned    : Remove cached ner rows whose object no longer exists}
        {--stats             : Show statistics and exit}
        {--dry-run           : Report what would change, write nothing}';

    protected $description = 'Rebuild NER entity search cache';

    public function handle(): int
    {
        if (!Schema::hasTable('heritage_entity_cache')) {
            $this->error('heritage_entity_cache table is missing (ahg-heritage-manage not installed).');
            return self::FAILURE;
        }
        if (!Schema::hasTable('ahg_ner_entity')) {
            $this->error('ahg_ner_entity table is missing (run ahg:ai-install).');
            return self::FAILURE;
        }

        if ($this->option('stats')) {
            return $this->showStats();
        }
        if ($this->option('clean-orphaned')) {
            return $this->cleanOrphaned();
        }

        $minConf = (float) ($this->option('min-confidence') ?: 0.70);
        $limit   = (int) ($this->option('limit') ?: 1000);
        $dryRun  = (bool) $this->option('dry-run');

        $objectsQuery = DB::table('ahg_ner_entity')
            ->whereIn('status', ['linked', 'approved'])
            ->where('confidence', '>=', $minConf)
            ->orderBy('object_id')
            ->select('object_id')
            ->distinct();

        if ($this->option('object-id')) {
            $objectsQuery->where('object_id', (int) $this->option('object-id'));
        }
        if ($this->option('since-id')) {
            $objectsQuery->where('object_id', '>', (int) $this->option('since-id'));
        }
        if ($limit > 0) {
            $objectsQuery->limit($limit);
        }

        $objectIds = $objectsQuery->pluck('object_id');
        $total = $objectIds->count();
        if ($total === 0) {
            $this->info('No approved/linked NER entities above the confidence threshold to sync.');
            return self::SUCCESS;
        }

        $this->info("Syncing NER entities for {$total} object(s)" . ($dryRun ? ' [DRY RUN]' : '') . '...');

        $objectsProcessed = 0;
        $entitiesSynced   = 0;

        foreach ($objectIds as $objectId) {
            $entities = DB::table('ahg_ner_entity')
                ->where('object_id', $objectId)
                ->whereIn('status', ['linked', 'approved'])
                ->where('confidence', '>=', $minConf)
                ->get();

            if ($dryRun) {
                $entitiesSynced += $entities->count();
                $objectsProcessed++;
                continue;
            }

            // Refresh this object's NER-derived cache rows in one pass.
            DB::table('heritage_entity_cache')
                ->where('object_id', $objectId)
                ->where('extraction_method', 'ner')
                ->delete();

            foreach ($entities as $e) {
                $type = self::TYPE_MAP[strtoupper((string) $e->entity_type)] ?? strtolower((string) $e->entity_type);
                DB::table('heritage_entity_cache')->insert([
                    'object_id'         => (int) $objectId,
                    'entity_type'       => $type,
                    'entity_value'      => mb_substr((string) $e->entity_value, 0, 500),
                    'normalized_value'  => mb_substr((string) $e->entity_value, 0, 500),
                    'confidence_score'  => (float) $e->confidence,
                    'source_field'      => 'scope_and_content',
                    'extraction_method' => 'ner',
                    'created_at'        => now(),
                ]);
                $entitiesSynced++;
            }
            $objectsProcessed++;
        }

        $this->info(sprintf('Objects processed: %d, entities synced: %d', $objectsProcessed, $entitiesSynced));
        return self::SUCCESS;
    }

    private function showStats(): int
    {
        $this->info('Entity Cache Sync Statistics');

        $byStatus = DB::table('ahg_ner_entity')
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')->pluck('c', 'status');
        $this->line('NER entities by status:');
        foreach ($byStatus as $status => $count) {
            $this->line(sprintf('  %s: %d', $status, $count));
        }

        $cacheByMethod = DB::table('heritage_entity_cache')
            ->select('extraction_method', DB::raw('COUNT(*) as c'))
            ->groupBy('extraction_method')->pluck('c', 'extraction_method');
        $this->line('Cache entities by method:');
        foreach ($cacheByMethod as $method => $count) {
            $this->line(sprintf('  %s: %d', $method, $count));
        }

        $approvedObjects = (int) DB::table('ahg_ner_entity')
            ->whereIn('status', ['linked', 'approved'])
            ->distinct()->count('object_id');
        $cachedNerObjects = (int) DB::table('heritage_entity_cache')
            ->where('extraction_method', 'ner')
            ->distinct()->count('object_id');

        $this->info(sprintf('Objects with approved NER: %d', $approvedObjects));
        $this->info(sprintf('Objects in NER cache: %d', $cachedNerObjects));
        $this->info(sprintf('Sync gap: %d objects', max(0, $approvedObjects - $cachedNerObjects)));
        return self::SUCCESS;
    }

    private function cleanOrphaned(): int
    {
        $removed = DB::table('heritage_entity_cache as hec')
            ->where('hec.extraction_method', 'ner')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))->from('information_object as io')
                    ->whereColumn('io.id', 'hec.object_id');
            })
            ->delete();

        $this->info($removed > 0 ? "Removed {$removed} orphaned cache entries." : 'No orphaned entries found.');
        return self::SUCCESS;
    }
}
