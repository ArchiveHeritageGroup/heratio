<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchMobileSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Both mobile routes live in the auth-gated `research` group - an anonymous
    // request must redirect to /login, proving the route resolves to the
    // extracted controller and stays inside the auth group (issue #1269).
    //
    // GET /research/mobile -> ResearchMobileController@mobileHome
    public function test_mobile_home_requires_auth()
    {
        $response = $this->get('/research/mobile');
        $response->assertRedirect('/login');
    }

    // POST /research/sync/offline -> ResearchMobileController@offlineSync.
    // The route is registered POST-only; an anonymous POST is caught by the
    // auth middleware and redirected to /login (the controller's own
    // abort(401)/abort(403) guards only fire once auth is satisfied).
    public function test_offline_sync_requires_auth()
    {
        $response = $this->post('/research/sync/offline');
        $response->assertRedirect('/login');
    }

    // The offlineSync route only answers POST. A GET does not resolve to it -
    // there is no GET binding for /research/sync/offline and the `research`
    // prefix is excluded from the IO slug catch-all, so the request 404s rather
    // than redirecting to /login (which only the matched auth route would do).
    // This confirms the verb binding is POST-only and the GET /mobile binding
    // did not bleed across.
    public function test_offline_sync_get_does_not_resolve()
    {
        $response = $this->get('/research/sync/offline');
        $response->assertNotFound();
    }
}
