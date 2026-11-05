<?php

/**
 * GlamLiveFacetCacheTest - a search page's sidebar counts are cached and run
 * under a statement time limit (on ANC, 5 Nov 2026, eight uncached 4-5 s counts
 * per search held every PHP worker). The library dropdown seed no longer
 * writes on every boot.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgLibrary\Providers\AhgLibraryServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlamLiveFacetCacheTest extends TestCase
{
    use DatabaseTransactions;

    private function sql(callable $run): array
    {
        $sql = [];
        DB::listen(function ($e) use (&$sql) {
            $sql[] = $e->sql;
        });
        $run();

        return $sql;
    }

    public function test_a_repeated_search_reuses_its_facet_counts_under_a_time_limit(): void
    {
        $url = route('glam.browse', ['query' => 'Zqfacet'.random_int(1000, 9999), 'topLevel' => '0']);
        $first = $this->sql(fn () => $this->get($url)->assertOk());
        $facets = fn (array $sql) => array_filter($sql, fn ($s) => str_contains($s, 'as facet_id'));

        $this->assertNotEmpty($facets($first));
        $this->assertContains('SET SESSION max_execution_time = 3000', $first);
        $this->assertContains('SET SESSION max_execution_time = DEFAULT', $first);
        $this->assertEmpty($facets($this->sql(fn () => $this->get($url)->assertOk())), 'second visit served from cache');
    }

    public function test_library_dropdown_seed_writes_nothing_once_its_options_exist(): void
    {
        $boot = fn () => (new AhgLibraryServiceProvider($this->app))->boot();
        $boot();
        DB::table('ahg_dropdown')->where(['taxonomy' => 'library_order_type', 'code' => 'exchange'])->update(['label' => 'Swap']);
        cache()->flush();

        $writes = array_filter($this->sql($boot), fn ($s) => preg_match('/^(insert|update)/i', $s) && str_contains($s, 'ahg_dropdown'));

        $this->assertEmpty($writes);
        $this->assertSame('Swap', DB::table('ahg_dropdown')->where(['taxonomy' => 'library_order_type', 'code' => 'exchange'])->value('label'), 'an edit made in the Dropdown Manager stays');
        $left = array_filter($this->sql($boot), fn ($s) => str_contains($s, '`ahg_dropdown`'));
        $this->assertEmpty($left, 'remembered once complete: '.implode(' / ', $left));
    }
}
