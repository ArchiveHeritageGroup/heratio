<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Start every test with no cached identity (heratio#1515).
     *
     * AclService keeps the signed-in user and their ACL groups in statics,
     * which outlive a test: one case's actingAs() admin was still the answer
     * for every later case in the same process, so tests asserting what a
     * GUEST may see were quietly run as an administrator.
     * ActorVisibilityTest caught it - a guest seeing draft and embargoed
     * authority records - but only when something signed an admin in first,
     * which is why it passed alone and failed in the full suite.
     *
     * forgetUser() was written for exactly this and had no callers.
     */
    protected function setUp(): void
    {
        parent::setUp();

        \AhgCore\Services\AclService::forgetUser();
    }

    protected function tearDown(): void
    {
        \AhgCore\Services\AclService::forgetUser();

        parent::tearDown();
    }

    /**
     * Assert an anonymous request was stopped by the admin gate before it
     * reached the controller.
     *
     * Assert the property these tests actually care about - the gate ran -
     * rather than the particular status code it uses today. RequireAdmin sent
     * a logged-out visitor a bare 403 until v1.154.451, which changed it to a
     * redirect to login so every admin page behaves like the 'auth' middleware
     * (JSON callers still get 401, and an authenticated non-admin still gets
     * 403). Twenty-four smoke tests across eight classes asserted the old 403
     * literally, so the Package CI job went red on 2026-07-31 and stayed red.
     *
     * A 302 is only accepted when it points at login. Accepting any redirect
     * would let a route that quietly stopped being admin-gated pass, which is
     * the one thing these tests exist to catch.
     */
    protected function assertAdminGated(TestResponse $response): void
    {
        $status = $response->getStatusCode();

        if ($status === 302) {
            $this->assertStringContainsString(
                '/login',
                (string) $response->headers->get('Location'),
                'Admin gate redirected an anonymous visitor somewhere other than login.'
            );

            return;
        }

        // 419 is CSRF rejecting a POST before the gate is reached, which is
        // still proof the request never touched the controller.
        $this->assertContains(
            $status,
            [401, 403, 419],
            "Anonymous request was not stopped by the admin gate (got {$status})."
        );
    }
}
