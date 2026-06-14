<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchAssessmentsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The assessments route is auth-gated (research prefix + middleware('auth')
    // group) - an anonymous request must redirect to /login, proving the route
    // resolves to the extracted controller and stays inside the auth group
    // (issue #1253 / #1269).
    public function test_assessments_index_requires_auth()
    {
        $response = $this->get('/research/assessments');
        $response->assertRedirect('/login');
    }
}
