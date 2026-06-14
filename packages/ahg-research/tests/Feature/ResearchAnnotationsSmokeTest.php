<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchAnnotationsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    public function test_annotations_index_requires_auth()
    {
        $response = $this->get('/research/annotations');
        $response->assertRedirect('/login');
    }

    public function test_store_annotation_requires_auth()
    {
        $response = $this->post('/research/annotations', []);
        $response->assertRedirect('/login');
    }
}
