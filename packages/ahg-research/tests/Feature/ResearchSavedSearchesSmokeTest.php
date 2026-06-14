<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchSavedSearchesSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Saved-search routes are all auth-gated (inside the research auth group) -
    // an anonymous request must redirect to /login, proving the route resolves
    // to the extracted ResearchSavedSearchesController and stays inside the auth
    // group (issue #1269).
    public function test_saved_searches_index_requires_auth()
    {
        $response = $this->get('/research/savedSearches');
        $response->assertRedirect('/login');
    }

    public function test_store_saved_search_requires_auth()
    {
        $response = $this->post('/research/saved-searches');
        $response->assertRedirect('/login');
    }

    public function test_run_saved_search_requires_auth()
    {
        $response = $this->get('/research/saved-searches/1/run');
        $response->assertRedirect('/login');
    }

    public function test_destroy_saved_search_requires_auth()
    {
        $response = $this->delete('/research/saved-searches/1');
        $response->assertRedirect('/login');
    }
}
