<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchEntityResolutionSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Entity-resolution routes are all auth-gated - an anonymous request must
    // redirect to /login, proving the route resolves to the extracted
    // controller and stays inside the auth group (issue #1269 decomposition).
    public function test_entity_resolution_index_requires_auth()
    {
        $response = $this->get('/research/entityResolution');
        $response->assertRedirect('/login');
    }

    public function test_entity_resolution_hyphenated_index_requires_auth()
    {
        $response = $this->get('/research/entity-resolution');
        $response->assertRedirect('/login');
    }

    public function test_resolve_entity_resolution_requires_auth()
    {
        $response = $this->post('/research/entity-resolution/1/resolve');
        $response->assertRedirect('/login');
    }

    public function test_entity_resolution_conflicts_requires_auth()
    {
        $response = $this->get('/research/entity-resolution/1/conflicts');
        $response->assertRedirect('/login');
    }
}
