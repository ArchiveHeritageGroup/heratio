<?php

/**
 * EsActorVisibilityTest - #1433.
 *
 * Draft / embargoed authority records must not surface on the ES-backed
 * surfaces (globalSearch + autocomplete). Rather than stand up a live cluster,
 * we fake the HTTP layer and assert the query BODY posted to Elasticsearch
 * carries the publication-status + embargo filter arms - the structural
 * guarantee that hidden actors are excluded at query time (the keep-and-filter
 * model that pairs with the single-actor reindex on save).
 */

namespace AhgSearch\Tests;

use AhgSearch\Services\ElasticsearchService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EsActorVisibilityTest extends TestCase
{
    private function emptyEsResponse(): array
    {
        return ['hits' => ['total' => ['value' => 0], 'hits' => []]];
    }

    /** The publication-status should-arm: published (160) OR the field absent. */
    private function assertHasPublicationFilter(array $body): void
    {
        $json = json_encode($body);
        $this->assertStringContainsString('publicationStatusId', $json,
            'ES query must filter on publicationStatusId so draft actors are excluded.');
        $this->assertStringContainsString('160', $json,
            'ES query must admit only published (160) actors.');
        $this->assertStringContainsString('embargoUntil', $json,
            'ES query must exclude currently-embargoed actors.');
    }

    public function test_global_search_body_carries_draft_and_embargo_filter(): void
    {
        Http::fake(['*' => Http::response($this->emptyEsResponse(), 200)]);

        app(ElasticsearchService::class)->globalSearch('anything');

        Http::assertSent(function ($request) {
            $this->assertHasPublicationFilter($request->data());

            return true;
        });
    }

    public function test_autocomplete_body_carries_draft_and_embargo_filter(): void
    {
        Http::fake(['*' => Http::response($this->emptyEsResponse(), 200)]);

        app(ElasticsearchService::class)->autocomplete('anything');

        Http::assertSent(function ($request) {
            $this->assertHasPublicationFilter($request->data());

            return true;
        });
    }

    public function test_index_actor_puts_publication_status_and_embargo_fields(): void
    {
        // A missing actor short-circuits before any HTTP call, so we index a
        // real row and assert the doc shape. Skip cleanly where the DB / actor
        // table isn't reachable (indexActor is proven end-to-end on the dev box).
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('actor')) {
                $this->markTestSkipped('actor table not present in this install.');
            }
            $id = (int) \Illuminate\Support\Facades\DB::table('actor')->where('id', '!=', 3)->value('id');
        } catch (\Throwable $e) {
            $this->markTestSkipped('actor DB not reachable in this environment: '.$e->getMessage());
        }
        if (empty($id)) {
            $this->markTestSkipped('no actor rows to index.');
        }

        Http::fake(['*' => Http::response(['result' => 'updated'], 200)]);

        $res = app(ElasticsearchService::class)->indexActor($id);
        $this->assertTrue($res['ok']);

        Http::assertSent(function ($request) use ($id) {
            $body = $request->data();
            $this->assertArrayHasKey('publicationStatusId', $body);
            $this->assertArrayHasKey('embargoUntil', $body);
            $this->assertStringContainsString("qubitactor/_doc/{$id}", $request->url());

            return true;
        });
    }
}
