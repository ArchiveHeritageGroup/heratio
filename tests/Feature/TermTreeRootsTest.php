<?php

/**
 * TermTreeRootsTest - the browse sidebar's top-level terms come off the name
 * index for a large taxonomy and match the ordinary query (heratio#1573).
 * The SKOS export refuses a vocabulary too large to build in one request.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgTermTaxonomy\Controllers\TermController;
use AhgTermTaxonomy\Services\TermBrowseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TermTreeRootsTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TermBrowseService::$idFirstMin = 5000;
        TermController::$skosMax = 20000;
        parent::tearDown();
    }

    public function test_index_walk_matches_the_ordinary_query(): void
    {
        if (! Schema::hasIndex('term_i18n', 'idx_term_i18n_name_sort')) {
            $this->markTestSkipped('name_sort index not installed.');
        }
        $obj = fn (string $class) => (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
        $taxonomy = $obj('QubitTaxonomy');
        DB::table('taxonomy')->insert(['id' => $taxonomy, 'source_culture' => 'en']);
        $term = function (string $name, ?int $parent = null) use ($obj, $taxonomy) {
            $id = $obj('QubitTerm');
            DB::table('term')->insert(['id' => $id, 'taxonomy_id' => $taxonomy, 'parent_id' => $parent, 'source_culture' => 'en']);
            DB::table('term_i18n')->insert(['id' => $id, 'culture' => 'en', 'name' => $name]);
            DB::table('slug')->insert(['object_id' => $id, 'slug' => 'ttr-'.$id]);

            return $id;
        };
        $delta = $term('Delta');
        foreach (['alpha', 'Charlie', 'bravo', 'Echo'] as $name) {
            $term($name);
        }
        $term('Child of delta', $delta);

        $sql = [];
        DB::listen(function ($e) use (&$sql) {
            $sql[] = $e->sql;
        });

        TermBrowseService::$idFirstMin = PHP_INT_MAX;
        $ordinary = TermController::treeRoots($taxonomy, 'en')->pluck('name')->all();
        TermBrowseService::$idFirstMin = 0;
        $indexed = TermController::treeRoots($taxonomy, 'en')->pluck('name')->all();

        $this->assertSame(['alpha', 'bravo', 'Charlie', 'Delta', 'Echo'], $ordinary);
        $this->assertSame($ordinary, $indexed);
        $this->assertCount(1, array_filter($sql, fn ($s) => str_contains($s, 'FORCE INDEX (idx_term_i18n_name_sort)')), 'only the large-taxonomy call walks the index');
    }

    public function test_skos_export_refuses_a_vocabulary_too_big_for_a_request(): void
    {
        TermController::$skosMax = -1;
        $this->get('/term/export/skos?taxonomy=35&format=turtle')->assertStatus(413)->assertSee('too many to export');
        TermController::$skosMax = PHP_INT_MAX;
        $this->get('/term/export/skos?taxonomy=35&format=turtle')->assertOk();
    }
}
