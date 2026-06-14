<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchEquipmentSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Equipment routes are in the admin group (RequireAdmin middleware), which
    // abort(403) for anonymous requests. A 403 proves the route resolves to the
    // extracted controller and stays inside the admin group (issue #1269).
    public function test_equipment_index_requires_admin()
    {
        $response = $this->get('/research/equipment');
        $response->assertStatus(403);
    }

    public function test_equipment_history_requires_admin()
    {
        $response = $this->get('/research/equipment-history/1');
        $response->assertStatus(403);
    }
}
