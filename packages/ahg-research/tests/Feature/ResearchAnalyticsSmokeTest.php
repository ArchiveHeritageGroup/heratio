<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchAnalyticsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Both analytics routes sit inside the research 'auth' middleware group,
    // so an anonymous request must redirect to /login - proving the route
    // resolves to the extracted controller and stays inside the auth group
    // (issue #1269 decomposition).
    public function test_analytics_requires_auth()
    {
        $response = $this->get('/research/analytics');
        $response->assertRedirect('/login');
    }

    public function test_cross_fonds_query_requires_auth()
    {
        $response = $this->get('/research/cross-fonds-query');
        $response->assertRedirect('/login');
    }
}
