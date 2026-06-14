<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchAdminReferenceSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The admin reference/config cluster (adminTypes, adminStatistics,
    // institutions) was extracted from the ResearchController monolith to
    // ResearchAdminReferenceController (issue #1269). All three routes live in
    // the admin route group (RequireAdmin middleware), so an anonymous request
    // is rejected before the controller runs - proving the route resolves to
    // the extracted controller and stays inside the admin group.

    public function test_admin_types_requires_admin()
    {
        $response = $this->get('/research/adminTypes');
        $response->assertStatus(403);
    }

    public function test_admin_statistics_requires_admin()
    {
        $response = $this->get('/research/adminStatistics');
        $response->assertStatus(403);
    }

    public function test_admin_statistics_alias_requires_admin()
    {
        $response = $this->get('/research/admin/statistics');
        $response->assertStatus(403);
    }

    public function test_institutions_requires_admin()
    {
        $response = $this->get('/research/institutions');
        $response->assertStatus(403);
    }
}
