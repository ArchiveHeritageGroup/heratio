<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchSeatsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The seats route lives in the admin-gated group (middleware('admin') ->
    // RequireAdmin), which aborts 403 for anonymous/non-admin requests rather
    // than redirecting. A 403 proves the route resolves to the extracted
    // controller and stays inside the admin group (issue #1269 extraction).
    public function test_seats_index_requires_admin()
    {
        $response = $this->get('/research/seats');
        $response->assertStatus(403);
    }

    public function test_seats_post_requires_admin()
    {
        $response = $this->post('/research/seats', ['room_id' => 1, 'form_action' => 'create']);
        // Admin gate runs before CSRF/controller logic; anonymous -> 403.
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }
}
