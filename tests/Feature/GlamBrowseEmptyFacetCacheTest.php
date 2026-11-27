<?php

/**
 * GlamBrowseEmptyFacetCacheTest - the unfiltered GLAM browse still shows its
 * facets when display_facet_cache is empty (27 Nov 2026). The unfiltered page
 * read only that cache, which a scheduled job fills; on a new or Docker install
 * it is empty, so clicking "All" made the GLAM type, creator and other facets
 * disappear.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlamBrowseEmptyFacetCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unfiltered_browse_counts_facets_live_when_the_cache_is_empty(): void
    {
        // One published archive record, so there is something to count.
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'oai_local_identifier' => $id, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => 'Facet cache test '.$id]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'facet-cache-test-'.$id]);
        $sid = (int) DB::table('object')->insertGetId(['class_name' => 'QubitStatus', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('status')->insert(['id' => $sid, 'object_id' => $id, 'type_id' => 158, 'status_id' => 160]);
        DB::table('display_object_config')->insert(['object_id' => $id, 'object_type' => 'archive']);
        DB::table('display_facet_cache')->delete();
        Cache::flush();

        $html = $this->get('/glam/browse?topLevel=0')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="facet-link[^"]*" title="Archive"/', $html, 'the GLAM type facet lists Archive');
    }
}
