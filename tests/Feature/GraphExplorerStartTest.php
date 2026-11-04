<?php

/**
 * GraphExplorerStartTest - the /graph-explorer landing lists the best-linked
 * published records, never a draft, and caches the list (it took 49.6 s
 * uncached on ANC).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Services\GraphExplorerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GraphExplorerStartTest extends TestCase
{
    use DatabaseTransactions;

    private function record(int $status, int $links): string
    {
        $obj = fn (string $class) => (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
        $id = $obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => "Start $id"]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => "ges-$id"]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $status]);
        $term = $obj('QubitTerm');
        DB::table('term')->insert(['id' => $term, 'taxonomy_id' => 35, 'source_culture' => 'en']);
        $rows = [];
        for ($i = 0; $i < $links; $i++) {
            $rows[] = ['id' => $obj('QubitObjectTermRelation'), 'object_id' => $id, 'term_id' => $term];
        }
        DB::table('object_term_relation')->insert($rows);

        return "ges-$id";
    }

    public function test_best_linked_published_records_lead_and_drafts_stay_out(): void
    {
        $top = $this->record(160, 900);
        $draft = $this->record(159, 1000);
        Cache::forget('graph_explorer_start:en:3');

        $slugs = array_column((new GraphExplorerService)->startingPoints(3), 'slug');

        $this->assertSame($top, $slugs[0] ?? null);
        $this->assertNotContains($draft, $slugs);
        $this->assertNotNull(Cache::get('graph_explorer_start:en:3'), 'the list is cached');
        Cache::forget('graph_explorer_start:en:3');
    }
}
