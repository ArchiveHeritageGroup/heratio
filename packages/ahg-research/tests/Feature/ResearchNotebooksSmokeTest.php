<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchNotebooksSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Notebook routes are all auth-gated - an anonymous request must redirect
    // to /login, proving the route resolves to the extracted controller and
    // stays inside the auth group (stage 4 extraction, issue #1253 / #1269).
    public function test_notebooks_index_requires_auth()
    {
        $response = $this->get('/research/notebooks');
        $response->assertRedirect('/login');
    }

    public function test_notebook_show_requires_auth()
    {
        $response = $this->get('/research/notebooks/1');
        $response->assertRedirect('/login');
    }

    public function test_notebook_delete_requires_auth()
    {
        $response = $this->delete('/research/notebooks/1');
        $response->assertRedirect('/login');
    }

    public function test_notebook_promote_requires_auth()
    {
        $response = $this->post('/research/notebooks/1/promote');
        $response->assertRedirect('/login');
    }
}
