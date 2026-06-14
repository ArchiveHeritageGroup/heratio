<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchExportsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The three export routes all sit inside the research auth middleware group
    // - an anonymous request must redirect to /login, proving the route resolves
    // to the extracted ResearchExportsController and stays inside the auth group
    // (monolith decomposition, issue #1269).
    public function test_export_notes_requires_auth()
    {
        $response = $this->get('/research/exportNotes');
        $response->assertRedirect('/login');
    }

    public function test_export_finding_aid_requires_auth()
    {
        $response = $this->get('/research/exportFindingAid');
        $response->assertRedirect('/login');
    }

    public function test_generate_finding_aid_requires_auth()
    {
        $response = $this->get('/research/generateFindingAid');
        $response->assertRedirect('/login');
    }
}
