<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HeritageBuildGraphCommand extends Command
{
    protected $signature = 'ahg:heritage-build-graph
        {--full : Full rebuild of the heritage_entity_cache (truncate + repopulate)}
        {--link-getty : Link entities to Getty AAT vocab via getty_vocabulary_link}
        {--limit=10000 : Max IO/term entities to walk}';

    protected $description = 'Build heritage knowledge graph: heritage_entity_cache from atom IO + term + relation rows';

    public function handle(): int
    {
        if ($this->option('full')) {
            DB::table('heritage_entity_cache')->truncate();
            $this->info('truncated heritage_entity_cache');
        }

        $limit = max(1, (int) $this->option('limit'));
        // Pull IOs that don't yet have a heritage_entity_cache row.
        $ios = DB::connection('atom')->table('information_object as i')
            ->leftJoin(
                DB::connection('heratio')->getDatabaseName() . '.heritage_entity_cache as hec',
                function ($j) { $j->on('hec.entity_type', '=', DB::raw("'io'"))->on('hec.entity_id', '=', 'i.id'); }
            )
            ->whereNull('hec.id')
            ->limit($limit)
            ->select('i.id', 'i.repository_id', 'i.level_of_description_id')
            ->get();

        $written = 0;
        foreach ($ios->chunk(500) as $chunk) {
            $rows = $chunk->map(fn($r) => [
                'entity_type'       => 'io',
                'entity_id'         => $r->id,
                'repository_id'     => $r->repository_id,
                'level_id'          => $r->level_of_description_id,
                'cached_at'         => now(),
            ])->all();
            DB::table('heritage_entity_cache')->insert($rows);
            $written += count($rows);
        }
        $this->info("populated heritage_entity_cache: {$written} new IO entities");

        if ($this->option('link-getty')) {
            // Best-effort link: term names that exact-match a getty_aat_cache.pref_label.
            $linked = DB::statement("
                INSERT IGNORE INTO getty_vocabulary_link (entity_type, entity_id, aat_id, matched_at)
                SELECT 'term', t.id, gac.aat_id, NOW()
                FROM atom.term t
                JOIN atom.term_i18n ti ON ti.id = t.id AND ti.culture='en'
                JOIN getty_aat_cache gac ON LOWER(gac.pref_label) = LOWER(ti.name)
                LIMIT 5000
            ");
            $this->info('queued Getty AAT links (where exact term name matches pref_label)');
        }
        return self::SUCCESS;
    }
}
