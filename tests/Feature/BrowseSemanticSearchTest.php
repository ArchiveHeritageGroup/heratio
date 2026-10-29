<?php

/**
 * BrowseSemanticSearchTest - the GLAM browse Semantic switch matches records by
 * meaning (the vector index), not only by thesaurus synonyms. On the ANC demo
 * (29 Oct 2026) the thesaurus was empty, so the switch changed nothing.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgCore\Support\StatusRow;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BrowseSemanticSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        \AhgCore\Services\AclService::forgetUser();
        Cache::flush();

        $published = $this->record('ZZ Bsst meaning match', 160);
        $draft = $this->record('ZZ Bsst draft match', 159);
        $hits = [
            ['id' => $draft, 'score' => 0.9, 'title' => 'ZZ Bsst draft match', 'slug' => 'bsst-'.$draft],
            ['id' => $published, 'score' => 0.8, 'title' => 'ZZ Bsst meaning match', 'slug' => 'bsst-'.$published],
        ];
        $this->app->instance(VectorSearchService::class, new class($hits) extends VectorSearchService
        {
            public function __construct(private array $hits) {}

            public function embedQuery(string $text): ?array
            {
                return [0.1, 0.2];
            }

            public function qdrantSearch(string $collection, array $vector, int $limit, ?array $filter = null): ?array
            {
                return $this->hits;
            }
        });
    }

    private function record(string $title, int $status): int
    {
        $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitInformationObject', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => 1, 'source_culture' => 'en']);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => 'bsst-'.$id]);
        StatusRow::set($id, 158, $status);

        return $id;
    }

    // One browse request per process: the browse view declares a helper function.
    // The query matches no title, so anything listed came from the vector index.
    public function test_semantic_switch_adds_meaning_matches_for_visitors_published_only(): void
    {
        $this->get('/glam/browse?topLevel=0&view=table&sort=relevance&query=qqzzxx+solidarity&semantic=1')->assertOk()
            ->assertSee('ZZ Bsst meaning match')
            ->assertDontSee('ZZ Bsst draft match');
    }
}
