<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchStudioSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Studio routes are all auth-gated - an anonymous request must redirect to
    // /login, proving the route resolves to the extracted controller and stays
    // inside the auth group (monolith decomposition, issue #1253 / #1269).
    public function test_studio_index_requires_auth()
    {
        $response = $this->get('/research/studio/1');
        $response->assertRedirect('/login');
    }

    public function test_studio_show_requires_auth()
    {
        $response = $this->get('/research/studio/1/artefact/1');
        $response->assertRedirect('/login');
    }

    public function test_studio_download_requires_auth()
    {
        $response = $this->get('/research/studio/1/artefact/1/download');
        $response->assertRedirect('/login');
    }

    public function test_studio_generate_requires_auth()
    {
        $response = $this->post('/research/studio/1/generate');
        $response->assertRedirect('/login');
    }

    public function test_studio_delete_requires_auth()
    {
        $response = $this->delete('/research/studio/1/artefact/1');
        $response->assertRedirect('/login');
    }
}
