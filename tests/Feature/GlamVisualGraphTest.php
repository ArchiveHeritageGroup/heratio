<?php

/**
 * GlamVisualGraphTest - "Visual graph" on a GLAM search serves the
 * relationship explorer for the first 100 results, with the live controls,
 * and leaves out what the list would hide from a guest. Print and CSV export
 * of a search keep its filters (they used to export the whole catalogue).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlamVisualGraphTest extends TestCase
{
    use DatabaseTransactions;

    private function record(string $title, int $status): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en', 'identifier' => "VG-$id"]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => "vg-rec-$id"]);
        DB::table('status')->insert(['id' => \AhgCore\Support\StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $status]);

        return $id;
    }

    private function graph(array $params): array
    {
        $html = $this->get(route('glam.browse', $params + ['view' => 'graph']))->assertOk()->getContent();
        $this->assertTrue((bool) preg_match('/window\.DATA = (.*?); window\.LIVE = (.*?);<\/script>/s', $html, $m), 'data and live controls inlined');

        return [json_decode($m[1], true), json_decode($m[2], true)];
    }

    public function test_graph_of_a_search_lists_only_what_a_guest_may_see(): void
    {
        $word = 'Zqvgraph'.random_int(1000, 9999);
        $shown = $this->record("$word letter", 160);
        $draft = $this->record("$word draft", 159);

        [$data, $live] = $this->graph(['query' => $word, 'topLevel' => '0']);
        $ids = array_column($data['records'], 'id');

        $this->assertContains((string) $shown, $ids);
        $this->assertNotContains((string) $draft, $ids);
        $this->assertSame($word, $live['query']);
        $this->assertStringNotContainsString('view=graph', $live['list']);
    }

    public function test_print_and_csv_keep_the_search(): void
    {
        $word = 'Zqvprint'.random_int(1000, 9999);
        $this->record("$word letter", 160);
        $this->record("$word draft", 159);

        $print = $this->get(route('glam.print', ['query' => $word, 'topLevel' => '0', 'sort' => 'startdate']))->assertOk()->getContent();
        $this->assertStringContainsString("$word letter", $print);
        $this->assertStringNotContainsString("$word draft", $print);

        $csv = $this->get(route('glam.export.csv', ['query' => $word, 'topLevel' => '0', 'sort' => 'alphabetic']))->assertOk()->streamedContent();
        $this->assertSame(2, substr_count($csv, "\n"), 'header plus the one matching published record');
        $this->assertStringContainsString("$word letter", $csv);
    }

    public function test_the_graph_starts_at_100_records_and_loads_more(): void
    {
        [$data, $live] = $this->graph([]);
        $this->assertLessThanOrEqual(100, count($data['records']));
        if ($live['total'] > 100) {
            $this->assertStringContainsString('graphLimit=200', $live['more']);
        }
    }
}
