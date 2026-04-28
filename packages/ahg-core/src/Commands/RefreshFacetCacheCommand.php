<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefreshFacetCacheCommand extends Command
{
    protected $signature = 'ahg:refresh-facet-cache
        {--facet= : Only rebuild specified facet_type (subjects, places, level, repository, etc.)}
        {--connection=atom : Source DB connection}';

    protected $description = 'Rebuild display_facet_cache from atom (or chosen) DB — counts per term per facet_type';

    public function handle(): int
    {
        if (! Schema::hasTable('display_facet_cache')) {
            $this->error('display_facet_cache table missing.');
            return self::FAILURE;
        }
        $conn = (string) $this->option('connection');
        $only = $this->option('facet');

        // Map facet_type → (taxonomy_id, ahg_io_facet_denorm.taxonomy_id) per ADR-0001.
        $sourceMap = Schema::connection($conn)->hasTable('ahg_io_facet_denorm') ? 'denorm' : 'fallback';
        $this->info("source: {$sourceMap}");

        $facets = [
            'subjects'   => 35,
            'places'     => 42,
            'genres'     => 78,
            'level'      => 34,
            'repository' => null,  // distinct repositories
        ];
        if ($only) $facets = array_intersect_key($facets, [$only => true]);

        $totalRows = 0;
        DB::beginTransaction();
        try {
            DB::table('display_facet_cache')->whereIn('facet_type', array_keys($facets))->delete();
            foreach ($facets as $facetType => $taxId) {
                if ($facetType === 'repository') {
                    // Distinct repository_ids from atom IO.
                    $rows = DB::connection($conn)->table('information_object as io')
                        ->join('actor_i18n as ai', function ($j) {
                            $j->on('ai.id', '=', 'io.repository_id')->where('ai.culture', '=', 'en');
                        })
                        ->whereNotNull('io.repository_id')
                        ->groupBy('io.repository_id', 'ai.authorized_form_of_name')
                        ->selectRaw('io.repository_id AS term_id, ai.authorized_form_of_name AS term_name, COUNT(*) AS cnt')
                        ->get();
                } elseif ($sourceMap === 'denorm') {
                    $rows = DB::connection($conn)->table('ahg_io_facet_denorm as d')
                        ->join('term_i18n as ti', function ($j) {
                            $j->on('ti.id', '=', 'd.term_id')->where('ti.culture', '=', 'en');
                        })
                        ->where('d.taxonomy_id', $taxId)
                        ->selectRaw('d.term_id, ti.name AS term_name, COUNT(*) AS cnt')
                        ->groupBy('d.term_id', 'ti.name')
                        ->get();
                } else {
                    // Fallback: object_term_relation
                    $rows = DB::connection($conn)->table('object_term_relation as otr')
                        ->join('term as t', 't.id', '=', 'otr.term_id')
                        ->join('term_i18n as ti', function ($j) {
                            $j->on('ti.id', '=', 't.id')->where('ti.culture', '=', 'en');
                        })
                        ->where('t.taxonomy_id', $taxId)
                        ->selectRaw('otr.term_id, ti.name AS term_name, COUNT(*) AS cnt')
                        ->groupBy('otr.term_id', 'ti.name')
                        ->get();
                }

                $batch = [];
                foreach ($rows as $r) {
                    $batch[] = [
                        'facet_type' => $facetType,
                        'term_id'    => (int) $r->term_id,
                        'term_name'  => mb_strimwidth((string) ($r->term_name ?? ''), 0, 255),
                        'count'      => (int) $r->cnt,
                        'created_at' => now(),
                    ];
                }
                foreach (array_chunk($batch, 1000) as $chunk) {
                    DB::table('display_facet_cache')->insert($chunk);
                }
                $this->info("  {$facetType}: " . count($batch) . " rows");
                $totalRows += count($batch);
            }
            DB::commit();
            $this->info("done; total_rows={$totalRows}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('refresh failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
