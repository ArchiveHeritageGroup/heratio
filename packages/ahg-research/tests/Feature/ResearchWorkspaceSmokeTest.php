<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchWorkspaceSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    public function test_workspace_index_requires_auth()
    {
        $response = $this->get('/research/workspace');
        $response->assertRedirect('/login');
    }

    public function test_workspaces_page_requires_auth()
    {
        $response = $this->get('/research/workspaces');
        $response->assertRedirect('/login');
    }
}
