<?php

/**
 * EnforceMfaPolicyTest - middleware decision-table tests (#723).
 *
 * The middleware has four decision branches:
 *
 *   - Anonymous request                       -> pass through
 *   - Allowed path (setup / verify / logout)  -> pass through
 *   - Policy does not require MFA             -> pass through
 *   - In grace window                         -> pass through (flash banner)
 *   - Otherwise                               -> 302 to setup-2fa
 *
 * We exercise each branch by stubbing out MfaPolicyService directly inside
 * the test rather than wiring up a full auth flow. The decision logic is
 * the unit under test.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Tests\Unit;

use AhgCore\Models\User;
use AhgSecurityClearance\Http\Middleware\EnforceMfaPolicy;
use AhgSecurityClearance\Services\MfaPolicyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class EnforceMfaPolicyTest extends TestCase
{
    /** @test */
    public function anonymous_requests_pass_through(): void
    {
        Auth::shouldReceive('check')->andReturn(false);
        $mw = new EnforceMfaPolicy($this->stubPolicy(requires: true, inGrace: false));

        $response = $mw->handle(Request::create('/some/url'), fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    /** @test */
    public function allowed_paths_pass_through_even_when_policy_demands_mfa(): void
    {
        $user = $this->fakeUser();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $mw = new EnforceMfaPolicy($this->stubPolicy(requires: true, inGrace: false));

        foreach (['security-clearance/setup-2fa', 'logout', 'security/2fa/otp/add', 'security/2fa/webauthn/verify'] as $allowedPath) {
            $response = $mw->handle(Request::create("/{$allowedPath}"), fn () => response('ok'));
            $this->assertSame(200, $response->getStatusCode(), "Path /{$allowedPath} should pass through");
        }
    }

    /** @test */
    public function policy_not_requiring_mfa_passes_through(): void
    {
        $user = $this->fakeUser();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $mw = new EnforceMfaPolicy($this->stubPolicy(requires: false, inGrace: false));
        $response = $mw->handle(Request::create('/admin/dashboard'), fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    /** @test */
    public function in_grace_passes_through_and_flashes_banner(): void
    {
        $user = $this->fakeUser();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $mw = new EnforceMfaPolicy($this->stubPolicy(requires: true, inGrace: true));

        $request = Request::create('/admin/dashboard');
        $request->setLaravelSession($this->app['session']->driver('array'));

        $response = $mw->handle($request, fn () => response('ok'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotEmpty($request->session()->get('mfa_policy_grace'));
    }

    /** @test */
    public function out_of_grace_redirects_to_setup(): void
    {
        $user = $this->fakeUser();
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        $mw = new EnforceMfaPolicy($this->stubPolicy(requires: true, inGrace: false));

        $request = Request::create('/admin/dashboard');
        $request->setLaravelSession($this->app['session']->driver('array'));

        $response = $mw->handle($request, fn () => response('should-not-run'));

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('setup-2fa', $response->getTargetUrl());
    }

    private function fakeUser(): User
    {
        $u = new class extends User {
            public function __construct() {}
        };
        $u->id = 999000730;

        return $u;
    }

    private function stubPolicy(bool $requires, bool $inGrace): MfaPolicyService
    {
        return new class($requires, $inGrace) extends MfaPolicyService {
            public function __construct(private bool $r, private bool $g) {}

            public function requiresMfa(\AhgCore\Models\User $user): bool
            {
                return $this->r;
            }

            public function inGrace(\AhgCore\Models\User $user): bool
            {
                return $this->g;
            }
        };
    }
}
