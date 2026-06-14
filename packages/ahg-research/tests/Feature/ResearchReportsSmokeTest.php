<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchReportsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Report routes are all auth-gated - an anonymous request must redirect to
    // /login, proving the route resolves to the extracted controller and stays
    // inside the auth group (stage 5 extraction, issue #1253 / #1269).
    public function test_reports_index_requires_auth()
    {
        $response = $this->get('/research/reports');
        $response->assertRedirect('/login');
    }

    public function test_report_templates_requires_auth()
    {
        $response = $this->get('/research/report-templates');
        $response->assertRedirect('/login');
    }

    public function test_view_report_requires_auth()
    {
        $response = $this->get('/research/viewReport/1');
        $response->assertRedirect('/login');
    }
}
