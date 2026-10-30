<?php

/**
 * BrowseAlphabeticPageTest - the alphabetical browse picks its page of ids off
 * the title sort sidecar. With no type filter that query must not join
 * display_object_config: the join made MySQL materialise the whole subquery,
 * 3.1 s per visitor browse at ANC (454,392 records) instead of 10 ms.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use AhgDisplay\Controllers\DisplayController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrowseAlphabeticPageTest extends TestCase
{
    use DatabaseTransactions;

    private function record(string $title, string $type): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'source_culture' => 'en']);
        StatusRow::set($id, 158, 160);
        DB::table('information_object_title_sort')->insert(['object_id' => $id, 'culture' => 'en', 'title_sort' => $title]);
        DB::table('display_object_config')->insert(['object_id' => $id, 'object_type' => $type]);

        return $id;
    }

    /** @return array{0: ?array, 1: string[]} the page and the SQL of the sidecar query */
    private function page(?string $type): array
    {
        $c = app(DisplayController::class);
        $r = new \ReflectionClass($c);
        $r->getProperty('typeFilter')->setValue($c, $type);
        $sql = [];
        DB::listen(function ($e) use (&$sql) {
            if (str_contains($e->sql, 'from `information_object_title_sort` as `ts`')) {
                $sql[] = $e->sql;
            }
        });

        return [$r->getMethod('alphabeticIdPage')->invoke($c, 'en', 'asc', 1, 100), $sql];
    }

    public function test_unfiltered_page_skips_the_type_join_and_keeps_title_order(): void
    {
        $b = $this->record('!!zz-alpha-b', 'archive');
        $a = $this->record('!!zz-alpha-a', 'museum');

        [$ids, $sql] = $this->page(null);

        $this->assertNotNull($ids);
        $this->assertSame([$a, $b], array_values(array_intersect($ids, [$a, $b])));
        $this->assertStringNotContainsString('display_object_config', $sql[0]);
    }

    public function test_type_filter_still_joins_and_filters(): void
    {
        $this->record('!!zz-alpha-b', 'archive');
        $a = $this->record('!!zz-alpha-a', 'museum');

        [$ids, $sql] = $this->page('museum');

        $this->assertContains($a, $ids);
        $this->assertSame(0, DB::table('display_object_config')->whereIn('object_id', $ids)->where('object_type', '!=', 'museum')->count());
        $this->assertStringContainsString('display_object_config', $sql[0]);
    }
}
