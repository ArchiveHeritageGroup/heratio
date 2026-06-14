<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchNotificationsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The notifications route is auth-gated - an anonymous request must redirect
    // to /login, proving the route resolves to the extracted controller and
    // stays inside the auth group (extraction, issue #1269).
    public function test_notifications_requires_auth()
    {
        $response = $this->get('/research/notifications');
        $response->assertRedirect('/login');
    }
}
