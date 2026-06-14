<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchAdminSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The researchers-admin ACL cluster (researchers, viewResearcher,
    // approveResearcher, verifyResearcher, rejectResearcher, resetPassword,
    // suspendResearcher) was extracted from the ResearchController monolith to
    // ResearchAdminController (issue #1269). All seven routes live in the admin
    // route group (RequireAdmin middleware), so an anonymous request is
    // rejected before the controller runs - proving the route resolves to the
    // extracted controller and stays inside the admin group. Crucially this
    // includes verifyResearcher: there is NO public verify link - the manual
    // verify flag flip is admin-only.

    public function test_researchers_index_requires_admin()
    {
        $response = $this->get('/research/researchers');
        $response->assertStatus(403);
    }

    public function test_admin_researchers_alias_requires_admin()
    {
        $response = $this->get('/research/admin/researchers');
        $response->assertStatus(403);
    }

    public function test_view_researcher_requires_admin()
    {
        $response = $this->get('/research/viewResearcher/1');
        $response->assertStatus(403);
    }

    public function test_approve_researcher_requires_admin()
    {
        $response = $this->post('/research/approveResearcher/1');
        // Admin gate runs before CSRF/controller logic; anonymous -> 403.
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }

    public function test_reject_researcher_requires_admin()
    {
        $response = $this->post('/research/rejectResearcher/1');
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }

    public function test_suspend_researcher_requires_admin()
    {
        $response = $this->post('/research/researchers/1/suspend');
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }

    public function test_verify_researcher_requires_admin()
    {
        // verifyResearcher is admin-gated - there is no public verify link.
        $response = $this->post('/research/researchers/1/verify', ['verified' => '1']);
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }

    public function test_reset_password_requires_admin()
    {
        $response = $this->post('/research/researchers/1/reset-password');
        $this->assertContains($response->getStatusCode(), [403, 419]);
    }
}
