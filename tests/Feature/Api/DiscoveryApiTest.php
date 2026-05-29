<?php

/**
 * DiscoveryApiTest - feature coverage for the issue #1095 discovery API.
 *
 * Asserts the JSON contract of POST /api/discovery/search and
 * POST /api/discovery/recommend. Both endpoints degrade gracefully when their
 * backing services (Elasticsearch / Qdrant) are unreachable in the test
 * environment, so these tests validate the response shape and status without
 * requiring a live search backend.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DiscoveryApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Route::has('discovery.search')) {
            $this->markTestSkipped('Discovery API routes not registered');
        }
    }

    public function test_discovery_search_returns_contract_shape(): void
    {
        $response = $this->postJson('/api/discovery/search', [
            'q'      => 'railway',
            'limit'  => 10,
            'offset' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'results',
            'total',
            'facets',
            'time_ms',
            'meta' => ['source', 'limit', 'offset', 'page', 'query_expanded', 'history_reranked'],
        ]);

        $this->assertIsArray($response->json('results'));
        $this->assertIsInt($response->json('total'));
        $this->assertSame(10, $response->json('meta.limit'));
    }

    public function test_discovery_search_accepts_empty_query(): void
    {
        // An empty q is a valid "browse everything" request.
        $response = $this->postJson('/api/discovery/search', [
            'filters' => ['hasDigitalObject' => true],
            'limit'   => 5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['results', 'total', 'facets', 'time_ms']);
    }

    public function test_discovery_search_offset_maps_to_page(): void
    {
        $response = $this->postJson('/api/discovery/search', [
            'q'      => 'test',
            'limit'  => 20,
            'offset' => 40,
        ]);

        $response->assertStatus(200);
        // offset 40 / limit 20 => page 3
        $this->assertSame(3, $response->json('meta.page'));
    }

    public function test_discovery_search_validates_limit_bounds(): void
    {
        $response = $this->postJson('/api/discovery/search', [
            'q'     => 'test',
            'limit' => 9999, // over the max:100 rule
        ]);

        $response->assertStatus(422);
    }

    public function test_discovery_recommend_requires_io_id(): void
    {
        $response = $this->postJson('/api/discovery/recommend', [
            'limit' => 5,
        ]);

        $response->assertStatus(422);
    }

    public function test_discovery_recommend_returns_contract_shape(): void
    {
        $response = $this->postJson('/api/discovery/recommend', [
            'io_id' => 1,
            'limit' => 5,
        ]);

        // Either a live Qdrant result or a graceful degraded payload; both 200.
        $response->assertStatus(200);
        $response->assertJsonStructure(['items', 'reason', 'source']);
        $this->assertIsArray($response->json('items'));
    }
}
