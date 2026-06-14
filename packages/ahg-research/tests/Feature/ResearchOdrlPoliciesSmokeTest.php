<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchOdrlPoliciesSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // ODRL-policy + autocomplete routes are all auth-gated - an anonymous
    // request must redirect to /login, proving the route resolves to the
    // extracted controller and stays inside the auth group (issue #1269).
    public function test_odrl_policies_requires_auth()
    {
        $response = $this->get('/research/odrlPolicies');
        $response->assertRedirect('/login');
    }

    public function test_odrl_policies_dashed_alias_requires_auth()
    {
        $response = $this->get('/research/odrl-policies');
        $response->assertRedirect('/login');
    }

    public function test_researcher_autocomplete_requires_auth()
    {
        $response = $this->get('/research/researcher-autocomplete');
        $response->assertRedirect('/login');
    }

    public function test_target_autocomplete_requires_auth()
    {
        $response = $this->get('/research/target-autocomplete');
        $response->assertRedirect('/login');
    }
}
