<?php

/**
 * EnforceMfaPolicy - tenant MFA enforcement gate (issue #723).
 *
 * Runs after the auth middleware in the web stack. For each authenticated
 * request:
 *
 *   1. If MfaPolicyService::requiresMfa() is false, fall through.
 *   2. If MfaPolicyService::inGrace() is true, attach a flash banner and
 *      let the request through (yellow warning, not a hard block).
 *   3. Otherwise redirect to the enrolment page.
 *
 * Allowed paths (always pass through) include the MFA setup / verify
 * routes themselves, logout, and the post-login MFA challenge endpoints
 * already used by RequireMfaCompletion - we never want to trap the user
 * in a redirect loop on their way TO the enrolment page.
 *
 * The enrolment-page redirect target is /security-clearance/setup-2fa
 * (the existing TOTP enrolment view). The task spec mentions a
 * /profile/mfa/enroll route which does not exist in this codebase; the
 * project's actual canonical enrolment URL is named security-clearance.setup-2fa
 * and is shipped by the same package, so we route there.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * License: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgSecurityClearance\Http\Middleware;

use AhgCore\Models\User;
use AhgSecurityClearance\Services\MfaPolicyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceMfaPolicy
{
    /**
     * Paths the user can always reach even when MFA is mandatory. Keeps
     * the redirect loop tight: the user can enrol, log out, or finish a
     * pending MFA login flow without ever being trapped.
     */
    private const ALLOWED_PATHS = [
        // Enrolment / management surfaces - the destination of our redirect.
        'security-clearance/setup-2fa',
        'security-clearance/confirm-2fa',
        'security-clearance/recovery-codes',
        'security/2fa/webauthn',
        'security/2fa/webauthn/add',
        'security/2fa/webauthn/register/begin',
        'security/2fa/webauthn/register/complete',
        'security/2fa/otp',
        'security/2fa/otp/add',
        'security/2fa/otp/enrol',

        // Post-login MFA challenge (handled by RequireMfaCompletion). Listed
        // here too so the two middlewares don't fight if both decide to act.
        'security-clearance/two-factor',
        'security-clearance/two-factor/choose',
        'security-clearance/verify-2fa',
        'security/2fa/webauthn/verify',
        'security/2fa/otp/verify',

        'logout',
    ];

    /**
     * Path prefixes that are always allowed (so dynamic segments like
     * /security/2fa/otp/{factor}/... don't have to be enumerated above).
     */
    private const ALLOWED_PREFIXES = [
        'security/2fa/',
        'security-clearance/',
    ];

    public function __construct(private readonly MfaPolicyService $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');
        if ($this->isAllowed($path)) {
            return $next($request);
        }

        // Cheap fast-path: only consult the policy service when the user
        // has not already enrolled. We re-use requiresMfa() since it
        // already short-circuits on the "user has factor" branch.
        if (! $this->policy->requiresMfa($user)) {
            return $next($request);
        }

        if ($this->policy->inGrace($user)) {
            // Surface a yellow banner via session flash; the layout reads
            // 'mfa_policy_grace' and renders the warning band.
            $request->session()->flash(
                'mfa_policy_grace',
                __('Your administrator requires multi-factor authentication. Please enrol from your profile - this notice will become mandatory once the grace period expires.')
            );

            return $next($request);
        }

        $return = $request->fullUrl();

        return redirect()->route('security-clearance.setup-2fa', ['return' => $return])
            ->with('mfa_policy_required', __('Multi-factor authentication is required by your administrator. Please enrol a factor to continue.'));
    }

    private function isAllowed(string $path): bool
    {
        foreach (self::ALLOWED_PATHS as $allowed) {
            if ($path === $allowed) {
                return true;
            }
        }
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
