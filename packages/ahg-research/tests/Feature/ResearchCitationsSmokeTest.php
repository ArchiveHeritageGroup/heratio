<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchCitationsSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Citations are PUBLIC routes (no auth) - an unknown slug must 404 rather
    // than redirect to login, proving the route resolves to the extracted
    // controller and stays outside the auth group.
    public function test_cite_unknown_slug_returns_404()
    {
        $response = $this->get('/research/cite/__nonexistent-slug-' . uniqid());
        $response->assertNotFound();
    }

    public function test_cite_export_unknown_slug_returns_404()
    {
        $response = $this->get('/research/cite/__nonexistent-slug-' . uniqid() . '/export/ris');
        $response->assertNotFound();
    }
}
