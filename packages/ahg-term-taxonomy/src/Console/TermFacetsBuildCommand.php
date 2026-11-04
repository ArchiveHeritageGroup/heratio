<?php

/**
 * TermFacetsBuildCommand - precompute the term page's facet panels for terms
 * on at least --min descriptions into term_facet_top (heratio#1573). Smaller
 * terms aggregate live in well under a second; big ones took up to 21 s.
 * Scheduled nightly; terms that fall below --min are removed from the table.
 * It also refreshes the cached totals of the alphabetical term list.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgTermTaxonomy\Console;

use AhgTermTaxonomy\Services\TermBrowseService;
use AhgTermTaxonomy\Services\TermService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TermFacetsBuildCommand extends Command
{
    protected $signature = 'ahg:term-facets-build
        {--min=2000 : Precompute terms tagged on at least this many descriptions}
        {--totals-only : Only refresh the cached term list totals (quick; run after a deploy)}';

    protected $description = 'Precompute term page facet panels for large terms';

    public function handle(TermService $terms): int
    {
        if (Schema::hasColumn('term_i18n', 'name_sort')) {
            $this->info('term list totals refreshed: '.TermBrowseService::warmAlphabeticTotals());
        }
        if ($this->option('totals-only')) {
            return self::SUCCESS;
        }
        if (! Schema::hasTable('term_facet_top')) {
            $this->error('term_facet_top missing - run the migrations.');

            return self::FAILURE;
        }
        $min = max(1, (int) $this->option('min'));
        // A server-wide statement cap (30 s at ANC) killed the build on the first
        // term that took longer, every night; this run is meant to be slow.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET SESSION max_execution_time = 0');
        }
        $big = DB::table('object_term_relation')->select('term_id')
            ->groupBy('term_id')->havingRaw('COUNT(*) >= ?', [$min])->pluck('term_id')->map(fn ($v) => (int) $v)->all();

        DB::table('term_facet_top')->whereNotIn('term_id', $big ?: [0])->delete();
        $start = microtime(true);
        $failed = 0;
        foreach ($big as $termId) {
            $rows = [];
            $now = now();
            try {
                $top = $terms->computeFacetCounts($termId);
            } catch (\Throwable $e) {
                // One bad term must not leave the rest unbuilt.
                $failed++;
                $this->warn("term {$termId}: ".$e->getMessage());

                continue;
            }
            foreach ($top as $taxonomyId => $top) {
                foreach ($top as $r) {
                    $rows[] = ['term_id' => $termId, 'taxonomy_id' => $taxonomyId, 'other_term_id' => $r['term_id'], 'cnt' => $r['cnt'], 'built_at' => $now];
                }
            }
            DB::transaction(function () use ($termId, $rows) {
                DB::table('term_facet_top')->where('term_id', $termId)->delete();
                if ($rows) {
                    DB::table('term_facet_top')->insert($rows);
                }
            });
            Cache::forget("term_facet_counts:{$termId}");
        }
        $this->info(sprintf('built %d terms (min %d descriptions) in %.0f s', count($big) - $failed, $min, microtime(true) - $start));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
