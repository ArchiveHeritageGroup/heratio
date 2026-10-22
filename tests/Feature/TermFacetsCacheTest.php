<?php

/**
 * TermFacetsCacheTest - the term page's related-term facet panels come from one
 * cached query in TermService, so paging or re-sorting a term page does not
 * re-run the aggregation (five uncached self-joins per view overloaded MySQL on
 * the ANC host, 22 Oct 2026).
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

namespace Tests\Feature;

use AhgTermTaxonomy\Services\TermService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TermFacetsCacheTest extends TestCase
{
    use DatabaseTransactions;

    /** A subject term and a place term, both tagged on two records. Returns [termId, slug]. */
    private function fixture(): array
    {
        $obj = fn (string $class) => (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
        $term = function (int $taxonomy, string $name) use ($obj) {
            $id = $obj('QubitTerm');
            DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'source_culture' => 'en']);
            DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => 'tfc-'.$id]);

            return $id;
        };
        $subject = $term(35, 'Facet test subject');
        $place = $term(42, 'Facet test place');
        foreach ([1, 2] as $i) {
            $record = $obj('QubitInformationObject');
            foreach ([$subject, $place] as $t) {
                DB::table('object_term_relation')->insert(['id' => $obj('QubitObjectTermRelation'), 'object_id' => $record, 'term_id' => $t]);
            }
        }

        return [$subject, 'tfc-'.$subject];
    }

    public function test_the_term_page_renders_and_repeat_views_skip_the_facet_query(): void
    {
        [$termId, $slug] = $this->fixture();
        Cache::forget("term_facets:{$termId}:en");

        $this->assertSame('Facet test place', app(TermService::class)->relatedFacets($termId, 'en')[42][0]->name ?? null);
        $this->assertSame(2, app(TermService::class)->relatedFacets($termId, 'en')[42][0]->cnt ?? null);
        $this->get('/term/'.$slug)->assertOk()->assertSee('Facet test place');

        DB::enableQueryLog();
        $this->get('/term/'.$slug.'?sort=alphabetic&page=2')->assertOk();
        $facetQueries = array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'object_term_relation` as `tagged`'));
        $this->assertCount(0, $facetQueries, 'a re-sorted or paged view reuses the cached facets');
    }

    public function test_facets_cover_the_five_taxonomies_and_come_back_from_cache(): void
    {
        $this->assertSame([12, 42, 35, 78, 54], array_keys(TermService::FACET_TAXONOMIES));
        $svc = app(TermService::class);
        Cache::forget('term_facets:0:en');
        $this->assertSame([], $svc->relatedFacets(0, 'en'), 'an untagged term has no facets');
        $this->assertTrue(Cache::has('term_facets:0:en'));
    }
}
