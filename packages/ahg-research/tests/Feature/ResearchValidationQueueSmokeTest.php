<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchValidationQueueSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // Validation-queue routes are all auth-gated. The GET index must redirect
    // an anonymous request to /login, proving the route resolves to the
    // extracted controller and stays inside the auth group (issue #1269).
    public function test_validation_queue_index_requires_auth()
    {
        $response = $this->get('/research/validationQueue');
        $response->assertRedirect('/login');
    }

    // validateResult / bulkValidate are POST-only endpoints. An anonymous POST
    // is short-circuited by the web group's CSRF middleware (302) before the
    // controller body runs, so we only assert the route resolves to a POST
    // verb (not 404/405) - proving it is wired to the extracted controller.
    public function test_validate_result_route_resolves()
    {
        $response = $this->post('/research/validate/1');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }

    public function test_bulk_validate_route_resolves()
    {
        $response = $this->post('/research/bulk-validate');
        $this->assertNotContains($response->getStatusCode(), [404, 405]);
    }
}
