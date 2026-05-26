<?php

/**
 * WebAuthnController - HTTP layer for FIDO2 / passkey MFA (issue #721).
 *
 * Sister controller to the TOTP slice on SecurityClearanceController. Routes
 * live in security-clearance/routes/web.php under /security/2fa/webauthn/* so
 * the URL prefix stays consistent with the existing 2FA admin surface.
 *
 * Two contexts:
 *
 *   1. Enrolled-user management (auth required, post-MFA): setupPage, list,
 *      registerBegin, registerComplete, delete.
 *
 *   2. Login-time assertion (auth required but pending_mfa session flag set):
 *      assertBegin, assertComplete. The chooser/verify views are rendered
 *      from SecurityClearanceController so the user-facing /two-factor URL
 *      stays the canonical entry point.
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

namespace AhgSecurityClearance\Controllers;

use AhgSecurityClearance\Services\WebAuthnService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WebAuthnController extends Controller
{
    private WebAuthnService $webauthn;

    public function __construct(WebAuthnService $webauthn)
    {
        $this->webauthn = $webauthn;
    }

    /** List enrolled credentials and offer the "Add new" entry point. */
    public function setupPage(Request $request)
    {
        $userId = (int) auth()->id();
        $credentials = $this->webauthn->listForUser($userId);

        return view('ahg-security-clearance::webauthn.list', [
            'credentials' => $credentials,
            'returnUrl' => $request->input('return', '/user/profile'),
        ]);
    }

    /** Render the per-credential enrolment form (label + JS trigger). */
    public function addPage(Request $request)
    {
        return view('ahg-security-clearance::webauthn.setup', [
            'returnUrl' => $request->input('return', '/user/profile'),
            'rpId' => $this->rpId($request),
            'rpName' => config('app.name', 'Heratio'),
        ]);
    }

    /**
     * POST /security/2fa/webauthn/register/begin
     *
     * Returns the PublicKeyCredentialCreationOptions JSON. Browser feeds it
     * to navigator.credentials.create().
     */
    public function registerBegin(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        $user = DB::table('user')->where('id', $userId)->first();

        $username = $user->email ?? $user->username ?? "user-{$userId}";
        $displayName = trim(($user->username ?? '').' ('.($user->email ?? '').')', ' ()');
        if ($displayName === '') {
            $displayName = $username;
        }

        $options = $this->webauthn->beginRegistration(
            $userId,
            $username,
            $displayName,
            $this->rpId($request),
            config('app.name', 'Heratio'),
        );

        return response()->json($options);
    }

    /**
     * POST /security/2fa/webauthn/register/complete
     *
     * Body: { credential: <PublicKeyCredential JSON>, label: <string> }
     */
    public function registerComplete(Request $request): JsonResponse
    {
        $credential = $request->input('credential');
        if (! is_array($credential)) {
            return response()->json(['ok' => false, 'error' => 'missing credential'], 400);
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $label = 'Passkey '.now()->format('Y-m-d');
        }

        $ok = $this->webauthn->completeRegistration(
            (int) auth()->id(),
            $credential,
            $label,
            $this->rpId($request),
        );

        if (! $ok) {
            return response()->json(['ok' => false, 'error' => 'attestation verification failed'], 400);
        }

        return response()->json([
            'ok' => true,
            'redirect' => route('security-clearance.webauthn.list'),
        ]);
    }

    /** POST /security/2fa/webauthn/{id}/delete */
    public function delete(Request $request, int $id)
    {
        $userId = (int) auth()->id();
        $this->webauthn->deleteCredential($userId, $id);

        return redirect()->route('security-clearance.webauthn.list')
            ->with('success', __('Passkey removed.'));
    }

    /**
     * POST /security/2fa/webauthn/assert/begin
     *
     * Login-time challenge. Requires the user to already be authenticated
     * (pending_mfa session flag set by LoginController).
     */
    public function assertBegin(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        if (! $userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $options = $this->webauthn->beginAssertion($userId, $this->rpId($request));

        return response()->json($options);
    }

    /**
     * POST /security/2fa/webauthn/assert/complete
     *
     * Body: { credential: <PublicKeyCredential JSON> }
     */
    public function assertComplete(Request $request): JsonResponse
    {
        $userId = (int) auth()->id();
        if (! $userId) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $credential = $request->input('credential');
        if (! is_array($credential)) {
            return response()->json(['ok' => false, 'error' => 'missing credential'], 400);
        }

        $ok = $this->webauthn->completeAssertion($userId, $credential, $this->rpId($request));
        if (! $ok) {
            return response()->json(['ok' => false, 'error' => 'assertion failed'], 400);
        }

        // Clear the MFA gate (mirror TOTP verifyTwoFactor flow).
        $returnUrl = $request->session()->pull('mfa_return_url', '/');
        $request->session()->forget('pending_mfa');

        if (Schema::hasTable('security_2fa_session')) {
            DB::table('security_2fa_session')->where('user_id', $userId)->delete();
            DB::table('security_2fa_session')->insert([
                'user_id' => $userId,
                'session_id' => session()->getId(),
                'verified_at' => now(),
                'expires_at' => now()->addHours(8),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        }

        \Log::info('webauthn.assert.ok', ['user_id' => $userId, 'ip' => $request->ip()]);

        return response()->json([
            'ok' => true,
            'redirect' => $returnUrl,
        ]);
    }

    /** Render the login-time passkey challenge page (JS triggers assertBegin). */
    public function verifyPage(Request $request)
    {
        return view('ahg-security-clearance::webauthn.verify', [
            'returnUrl' => $request->input('return', '/'),
        ]);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    /**
     * Resolve the RP ID for the current request. WebAuthn requires this to
     * be the host (no scheme, no port). For self-hosted Heratio behind a
     * reverse proxy this is whatever the user typed in the address bar.
     */
    private function rpId(Request $request): string
    {
        return $request->getHost();
    }
}
