<?php

/**
 * RequireMfaCompletion - Middleware for Heratio
 *
 * Gates every authenticated route behind the post-login MFA step when the
 * user has opt-in TOTP enabled. Reads the `pending_mfa` session flag set
 * by LoginController; if present, every request other than the MFA verify
 * flow itself is redirected to /security-clearance/two-factor.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireMfaCompletion
{
    /**
     * Paths the user is allowed to hit while pending_mfa is set. Anything
     * else under the auth.required middleware group bounces back to the
     * verify page until they complete the second factor.
     *
     * Issue #721 added the chooser + WebAuthn assertion endpoints so users
     * with a passkey can clear the gate without an authenticator-app code.
     * Issue #722 added the email / SMS OTP assertion endpoints so users with
     * only an enrolled OTP destination can complete sign-in.
     */
    private const ALLOWED_PATHS = [
        'security-clearance/two-factor',
        'security-clearance/two-factor/choose',
        'security-clearance/verify-2fa',
        'security/2fa/webauthn/verify',
        'security/2fa/webauthn/assert/begin',
        'security/2fa/webauthn/assert/complete',
        'security/2fa/otp/verify',
        'security/2fa/otp/assert/begin',
        'security/2fa/otp/assert/complete',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        if (! $request->session()->has('pending_mfa')) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');
        foreach (self::ALLOWED_PATHS as $allowed) {
            if ($path === $allowed) {
                return $next($request);
            }
        }

        return redirect()->route('security-clearance.two-factor', [
            'return' => $request->session()->get('mfa_return_url', '/'),
        ]);
    }
}
