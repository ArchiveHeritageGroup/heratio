<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchEvidenceSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Both evidence routes live in the auth group - an anonymous request must
    // redirect to /login, proving the route resolves to the extracted
    // controller and stays inside the auth group (issue #1269).
    public function test_evidence_viewer_requires_auth()
    {
        $response = $this->get('/research/evidence-viewer?object_id=1');
        $response->assertRedirect('/login');
    }

    public function test_search_items_requires_auth()
    {
        $response = $this->get('/research/searchItems?q=ab');
        $response->assertRedirect('/login');
    }
}
