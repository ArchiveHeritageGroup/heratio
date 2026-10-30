<?php

/**
 * RefreshFacetCacheTest - the hourly facet refresh writes the rows the browse
 * sidebar reads (published-only for visitors, _all for staff) and removes the
 * rows the old version wrote under names nothing read. The old version also
 * gave visitors level counts that included unpublished records.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefreshFacetCacheTest extends TestCase
{
    use DatabaseTransactions;

    public function test_level_counts_published_records_for_visitors_and_old_rows_go(): void
    {
        $level = (int) DB::table('object')->insertGetId(['class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('term')->insert(['id' => $level, 'taxonomy_id' => 34, 'source_culture' => 'en']);
        DB::table('term_i18n')->insert(['id' => $level, 'culture' => 'en', 'name' => 'ZZ facet level']);
        foreach ([160, 160, 159] as $status) {
            $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en', 'level_of_description_id' => $level]);
            StatusRow::set($id, 158, $status);
        }
        DB::table('display_facet_cache')->insert(['facet_type' => 'subjects', 'term_id' => 1, 'term_name' => 'old', 'count' => 1, 'created_at' => now()]);

        $this->artisan('ahg:refresh-facet-cache', ['--facet' => 'level'])->assertSuccessful();

        $count = fn (string $type) => DB::table('display_facet_cache')->where('facet_type', $type)->where('term_id', $level)->value('count');
        $this->assertSame(2, (int) $count('level'));
        $this->assertSame(3, (int) $count('level_all'));
        $this->assertSame(0, DB::table('display_facet_cache')->where('facet_type', 'subjects')->count());
    }
}
