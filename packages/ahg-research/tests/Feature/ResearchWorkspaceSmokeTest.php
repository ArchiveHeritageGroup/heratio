<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchWorkspaceSmokeTest extends TestCase
{
    use RefreshDatabase;

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
