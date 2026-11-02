<?php

/**
 * TermBrowseAlphabeticTest - the index-ordered alphabetical term list returns
 * the same pages, in the same order, as the ordinary query (heratio#1573).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgTermTaxonomy\Services\TermBrowseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TermBrowseAlphabeticTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TermBrowseService::$idFirstMin = 5000;
        parent::tearDown();
    }

    private function page(int $parent, int $page, string $dir, int $min): array
    {
        TermBrowseService::$idFirstMin = $min;
        $r = (new TermBrowseService('en'))->browse(['sort' => 'alphabetic', 'sortDir' => $dir, 'page' => $page, 'limit' => 2, 'parent' => $parent]);

        return [array_column($r['hits'], 'id'), $r['total']];
    }

    public function test_index_order_matches_the_ordinary_query(): void
    {
        $obj = fn () => (int) DB::table('object')->insertGetId(['class_name' => 'QubitTerm', 'created_at' => now(), 'updated_at' => now()]);
        $parent = $obj();
        DB::table('term')->insert(['id' => $parent, 'taxonomy_id' => 35, 'source_culture' => 'en']);
        foreach (['Delta', 'alpha', 'Charlie', 'bravo', 'Echo'] as $name) {
            $id = $obj();
            DB::table('term')->insert(['id' => $id, 'taxonomy_id' => 35, 'parent_id' => $parent, 'source_culture' => 'en']);
            DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => 'tba-'.$id]);
        }

        $indexScans = 0;
        DB::listen(function ($e) use (&$indexScans) {
            if (str_contains($e->sql, 'from `term_i18n` as `ns`')) {
                $indexScans++;
            }
        });

        foreach (['asc', 'desc'] as $dir) {
            foreach ([1, 2, 3] as $page) {
                [$ordinary, $total] = $this->page($parent, $page, $dir, PHP_INT_MAX);
                [$indexed, $total2] = $this->page($parent, $page, $dir, 0);
                $this->assertSame($ordinary, $indexed, "page {$page} {$dir}");
                $this->assertSame(5, $total);
                $this->assertSame(5, $total2);
            }
        }
        $this->assertSame(6, $indexScans, 'each of the six indexed pages used name_sort');
    }
}
