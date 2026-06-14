<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchProjectsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Project routes are all auth-gated - an anonymous request must redirect to
    // /login, proving the route resolves to the extracted
    // ResearchProjectsController and stays inside the auth group
    // (project-subsystem extraction, issue #1269).
    public function test_projects_index_requires_auth()
    {
        $response = $this->get('/research/projects');
        $response->assertRedirect('/login');
    }

    public function test_projects_create_requires_auth()
    {
        $response = $this->get('/research/projects/create');
        $response->assertRedirect('/login');
    }

    public function test_view_project_requires_auth()
    {
        $response = $this->get('/research/viewProject/1');
        $response->assertRedirect('/login');
    }
}
