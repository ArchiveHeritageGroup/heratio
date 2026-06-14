<?php

namespace AhgResearch\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ResearchRegistrationSmokeTest extends TestCase
{
    // RefreshDatabase would wipe the pre-built heratio_test schema, which is
    // loaded from the AtoM core SQL dumps + package install.sql (the ~995 base
    // tables are NOT created by Laravel migrations - see issue #1136). Use
    // transactional rollback against that pre-built DB instead.
    use DatabaseTransactions;

    // The researcher registration cluster (register, registrationComplete,
    // publicRegister, storePublicRegistration, renewal) was extracted from the
    // ResearchController monolith to ResearchRegistrationController (issue
    // #1269). This is the public-facing entry path: publicRegister +
    // registrationComplete are PUBLIC (no-auth `research.` group), while
    // register + renewal stay in the auth `research.` group. These smokes prove
    // each route resolves to the extracted controller AND stays in its original
    // middleware group.

    // ---- PUBLIC routes: must NOT redirect to /login ----

    public function test_public_register_get_is_public()
    {
        $response = $this->get('/research/publicRegister');
        // Public: anonymous request renders the form (200), it must NOT be
        // bounced to the login screen the way an auth-gated route would be.
        $this->assertFalse(
            $this->redirectsToLogin($response),
            'publicRegister GET must stay public (no redirect to /login)'
        );
    }

    public function test_public_register_post_is_public()
    {
        // No CSRF token -> 419 from the VerifyCsrfToken middleware, or a
        // validation 302-back. Either way it must NOT redirect to /login,
        // proving the POST stays in the public group and reaches the stack.
        $response = $this->post('/research/public-register', []);
        $this->assertFalse(
            $this->redirectsToLogin($response),
            'public-register POST must stay public (no redirect to /login)'
        );
    }

    public function test_registration_complete_is_public()
    {
        $response = $this->get('/research/registrationComplete');
        $this->assertFalse(
            $this->redirectsToLogin($response),
            'registrationComplete must stay public (no redirect to /login)'
        );
    }

    // ---- AUTH routes: anonymous request must redirect to /login ----

    // NB: the URL /research/register is NOT exercised here. The package's
    // auth-gated `research.register` route (now -> ResearchRegistrationController)
    // shares its URI with an app-level route in /usr/share/nginx/heratio/routes/web.php
    // (researcher.register -> LoginController@showResearcherRegister, the public
    // self-service register form). The app route is registered first, so it wins
    // URI matching and GET /research/register renders the public form (200). The
    // package route still resolves auth-gated by NAME (verified via route:list).
    // The genuinely URL-reachable auth route on the extracted controller is
    // renewal, asserted below.

    public function test_renewal_requires_auth()
    {
        $response = $this->get('/research/renewal');
        $response->assertRedirect('/login');
    }

    // ---- #1271: /research/register name collision fix ----
    //
    // The package's research.register route is dropped at runtime (the app
    // LoginController route owns the /research/register URI by method+URI and
    // the duplicate name is discarded), so route('research.register') throws
    // RouteNotFoundException. The workspace page rendered that helper for the
    // expired / expiring-soon / rejected researcher states and 500'd. Option B
    // repoints those buttons at research.renewal, which now also carries the
    // rejected re-apply path that previously lived only in register(). These
    // assertions lock in: (1) research.renewal still resolves by name, and
    // (2) the workspace route is reachable (auth bounce, not a 500) for an
    // anonymous request - it never even reaches the blade that would have
    // thrown, but this guards the public entry path regardless.

    public function test_research_renewal_route_resolves_by_name()
    {
        // route() would throw RouteNotFoundException if the name were dropped.
        $url = route('research.renewal');
        $this->assertStringContainsString('/research/renewal', $url);
    }

    public function test_research_register_name_is_not_registered()
    {
        // The package name was discarded by the app route collision; asserting
        // it is absent documents why workspace must use research.renewal.
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('research.register'),
            'research.register name is expected to be dropped by the app route collision (#1271)'
        );
    }

    public function test_workspace_does_not_500_for_anonymous()
    {
        // Pre-fix this 500'd for expired/rejected researchers via the blade's
        // route('research.register') call. Anonymous hits the auth bounce
        // first, so it must be a redirect to /login - never a 500.
        $response = $this->get('/research/workspace');
        $response->assertRedirect('/login');
        $this->assertTrue(
            $response->isRedirect(),
            'workspace must redirect (auth bounce), not 500'
        );
    }

    /**
     * True only when the response is a redirect whose Location path is /login
     * (the auth-middleware bounce). A 200 render or a 419 CSRF rejection is not
     * a login redirect, so a public route returns false here.
     */
    private function redirectsToLogin($response): bool
    {
        if (!$response->isRedirect()) {
            return false;
        }

        $location = (string) $response->headers->get('Location');

        return rtrim(parse_url($location, PHP_URL_PATH) ?? '', '/') === '/login';
    }
}
