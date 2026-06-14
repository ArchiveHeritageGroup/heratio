<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchDocumentTemplatesSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Document-template routes are auth-gated - an anonymous request must
    // redirect to /login, proving the route resolves to the extracted
    // controller and stays inside the auth group (issue #1269).
    public function test_document_templates_camel_requires_auth()
    {
        $response = $this->get('/research/documentTemplates');
        $response->assertRedirect('/login');
    }

    public function test_document_templates_kebab_requires_auth()
    {
        $response = $this->get('/research/document-templates');
        $response->assertRedirect('/login');
    }
}
