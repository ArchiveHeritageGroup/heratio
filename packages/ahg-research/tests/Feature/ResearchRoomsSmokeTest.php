<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchRoomsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Reading-room routes live in the admin route group (RequireAdmin
    // middleware), so an anonymous request is rejected with 403 before the
    // controller runs - proving the route resolves to the extracted controller
    // and stays inside the admin group (issue #1269).
    public function test_rooms_index_requires_admin()
    {
        $response = $this->get('/research/rooms');
        $response->assertStatus(403);
    }

    public function test_edit_room_requires_admin()
    {
        $response = $this->get('/research/editRoom');
        $response->assertStatus(403);
    }
}
