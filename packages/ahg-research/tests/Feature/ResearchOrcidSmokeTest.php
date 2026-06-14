<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchOrcidSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Auth-gated ORCID routes - an anonymous request must redirect to /login,
    // proving the route resolves to the extracted controller and stays inside
    // the auth group (monolith decomposition, issue #1269).
    public function test_orcid_link_requires_auth()
    {
        $response = $this->get('/research/orcid');
        $response->assertRedirect('/login');
    }

    public function test_orcid_authorize_requires_auth()
    {
        $response = $this->get('/research/orcid/authorize');
        $response->assertRedirect('/login');
    }

    public function test_orcid_callback_requires_auth()
    {
        $response = $this->get('/research/orcid/callback');
        $response->assertRedirect('/login');
    }

    public function test_orcid_sync_requires_auth()
    {
        $response = $this->post('/research/orcid/sync');
        $response->assertRedirect('/login');
    }

    public function test_orcid_pull_profile_requires_auth()
    {
        $response = $this->post('/research/orcid/pull-profile');
        $response->assertRedirect('/login');
    }

    public function test_orcid_save_credentials_requires_auth()
    {
        $response = $this->post('/research/orcid/credentials');
        $response->assertRedirect('/login');
    }

    public function test_orcid_clear_credentials_requires_auth()
    {
        $response = $this->post('/research/orcid/credentials/clear');
        $response->assertRedirect('/login');
    }

    public function test_orcid_unlink_requires_auth()
    {
        $response = $this->post('/research/orcid/unlink');
        $response->assertRedirect('/login');
    }

    // orcidFetchPublic is in the PUBLIC route group (no auth). An anonymous POST
    // must NOT redirect to /login; it should reach the controller and fail
    // validation (422) for the missing orcid_id, proving the public wiring.
    public function test_orcid_fetch_public_is_public_not_auth_gated()
    {
        $response = $this->postJson('/research/orcid/fetch-public', []);
        $this->assertNotEquals(302, $response->getStatusCode(), 'orcidFetchPublic must not redirect to login - it is a public endpoint');
        $response->assertStatus(422);
    }
}
