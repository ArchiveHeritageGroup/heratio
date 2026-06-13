<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchReproductionsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    public function test_reproductions_index_requires_auth()
    {
        $response = $this->get('/research/reproductions');
        $response->assertRedirect('/login');
    }

    public function test_view_reproduction_requires_auth()
    {
        $response = $this->get('/research/viewReproduction/1');
        $response->assertRedirect('/login');
    }
}
