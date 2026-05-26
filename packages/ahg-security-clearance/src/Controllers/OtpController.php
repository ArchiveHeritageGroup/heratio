<?php

/**
 * OtpController — HTTP layer for email + SMS OTP MFA (issue #722).
 *
 * Sister controller to WebAuthnController (#721) and the TOTP slice on
 * SecurityClearanceController (#690). Routes live under
 * /security/2fa/otp/* to match the existing 2FA URL prefix.
 *
 * Two flows are surfaced here:
 *
 *   1. Enrolment (post-MFA, fully signed-in):
 *      - setupPage  : pick channel + enter destination + label.
 *      - enrol      : POST creates the factor and sends the first code.
 *      - verifyEnrolmentPage / verifyEnrolment : confirm ownership.
 *      - list / delete : management UI.
 *
 *   2. Login-time assertion (pending_mfa session flag set):
 *      - verifyPage   : pick a verified factor + send code.
 *      - assertBegin  : POST resends a code to the chosen factor.
 *      - assertComplete : POST validates the code, clears MFA gate.
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

use AhgSecurityClearance\Services\OtpService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class OtpController extends Controller
{
    private OtpService $otp;

    public function __construct(OtpService $otp)
    {
        $this->otp = $otp;
    }

    // ─── enrolment ───────────────────────────────────────────────────────────

    /** Render the channel-picker / destination form. */
    public function setupPage(Request $request)
    {
        return view('ahg-security-clearance::otp.setup', [
            'returnUrl' => $request->input('return', '/user/profile'),
            'defaultEmail' => DB::table('user')->where('id', auth()->id())->value('email'),
        ]);
    }

    /** POST — create a pending factor and send the first code. */
    public function enrol(Request $request)
    {
        $request->validate([
            'factor_type' => ['required', Rule::in([OtpService::TYPE_EMAIL, OtpService::TYPE_SMS])],
            'destination' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        $type = $request->input('factor_type');
        $destination = (string) $request->input('destination');
        $label = (string) $request->input('label', '');

        if ($type === OtpService::TYPE_EMAIL) {
            if (! filter_var($destination, FILTER_VALIDATE_EMAIL)) {
                return back()->withErrors(['destination' => __('Enter a valid email address.')])->withInput();
            }
        } else {
            if (! preg_match('/^\+?[0-9 ()\-]{7,20}$/', $destination)) {
                return back()->withErrors(['destination' => __('Enter a valid phone number (E.164 preferred, e.g. +27821234567).')])->withInput();
            }
        }

        $factor = $this->otp->enrol((int) auth()->id(), $type, $destination, $label);

        return redirect()->route('security-clearance.otp.verify-enrolment', [
            'factor' => $factor->id,
            'return' => $request->input('return', '/user/profile'),
        ])->with('success', __('We have sent a verification code. Enter it below to finish enrolment.'));
    }

    /** Render the "enter the first code" page after enrol(). */
    public function verifyEnrolmentPage(Request $request, int $factor)
    {
        $row = $this->ownedFactor((int) auth()->id(), $factor);
        if (! $row || $row->verified_at !== null) {
            return redirect()->route('security-clearance.otp.list')
                ->with('error', __('Factor not found or already verified.'));
        }

        return view('ahg-security-clearance::otp.verify-enrolment', [
            'factor' => $row,
            'returnUrl' => $request->input('return', '/user/profile'),
        ]);
    }

    /** POST — validate the first code. */
    public function verifyEnrolment(Request $request, int $factor)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = (int) auth()->id();
        $ok = $this->otp->verifyEnrolment($userId, $factor, (string) $request->input('code'));

        if (! $ok) {
            return redirect()->route('security-clearance.otp.verify-enrolment', [
                'factor' => $factor,
                'return' => $request->input('return', '/user/profile'),
            ])->with('error', __('Invalid or expired code. Try again, or resend a fresh code.'));
        }

        return redirect()->route('security-clearance.otp.list')
            ->with('success', __('Factor verified and ready to use.'));
    }

    /** Resend the enrolment code (rate-limited inside the service). */
    public function resendEnrolment(Request $request, int $factor)
    {
        $userId = (int) auth()->id();
        $row = $this->ownedFactor($userId, $factor);
        if (! $row || $row->verified_at !== null) {
            return redirect()->route('security-clearance.otp.list');
        }

        $this->otp->sendChallenge($userId, $row);

        return redirect()->route('security-clearance.otp.verify-enrolment', [
            'factor' => $factor,
            'return' => $request->input('return', '/user/profile'),
        ])->with('info', __('If 60 seconds have passed since the last send, a new code is on its way.'));
    }

    /** Management UI — enrolled factors with delete + verify timestamp. */
    public function list(Request $request)
    {
        $factors = $this->otp->factorsFor((int) auth()->id());

        return view('ahg-security-clearance::otp.list', [
            'factors' => $factors,
            'returnUrl' => $request->input('return', '/user/profile'),
        ]);
    }

    /** POST — delete a single factor (ownership-checked inside the service). */
    public function delete(Request $request, int $factor)
    {
        $this->otp->deleteFactor((int) auth()->id(), $factor);

        return redirect()->route('security-clearance.otp.list')
            ->with('success', __('Factor removed.'));
    }

    // ─── login-time assertion ────────────────────────────────────────────────

    /** Render the login-time channel-picker. */
    public function verifyPage(Request $request)
    {
        $factors = $this->otp->factorsFor((int) auth()->id())
            ->filter(fn ($f) => $f->verified_at !== null)
            ->values();

        return view('ahg-security-clearance::otp.verify', [
            'factors' => $factors,
            'returnUrl' => $request->input('return', $request->session()->get('mfa_return_url', '/')),
            'selectedFactorId' => (int) $request->input('factor', $factors->first()->id ?? 0),
            'codeSent' => (bool) $request->session()->get('otp_code_sent'),
        ]);
    }

    /**
     * POST /assert/begin — send a code to the chosen verified factor.
     * Renders the same verify page with a flag so the JS surface knows
     * the code has been dispatched.
     */
    public function assertBegin(Request $request)
    {
        $request->validate(['factor' => 'required|integer']);

        $userId = (int) auth()->id();
        $factorId = (int) $request->input('factor');
        $factor = $this->ownedFactor($userId, $factorId);

        if (! $factor || $factor->verified_at === null) {
            return back()->with('error', __('Factor not found.'));
        }

        $this->otp->sendChallenge($userId, $factor);

        $request->session()->flash('otp_code_sent', true);

        return redirect()->route('security-clearance.otp.verify', [
            'factor' => $factorId,
            'return' => $request->input('return', $request->session()->get('mfa_return_url', '/')),
        ])->with('info', __('A verification code has been sent.'));
    }

    /** POST /assert/complete — validate the code and clear the MFA gate. */
    public function assertComplete(Request $request)
    {
        $request->validate([
            'factor' => 'required|integer',
            'code' => 'required|string|size:6',
        ]);

        $userId = (int) auth()->id();
        $factorId = (int) $request->input('factor');
        $returnUrl = $request->input('return', $request->session()->pull('mfa_return_url', '/'));

        if (! $this->otp->verify($userId, $factorId, (string) $request->input('code'))) {
            return redirect()->route('security-clearance.otp.verify', [
                'factor' => $factorId,
                'return' => $returnUrl,
            ])->with('error', __('Invalid or expired verification code.'));
        }

        // Mirror TOTP / WebAuthn post-verify session bookkeeping.
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

        \Log::info('otp.assert.ok', [
            'user_id' => $userId,
            'factor_id' => $factorId,
            'ip' => $request->ip(),
        ]);

        return redirect($returnUrl)
            ->with('success', __('Two-factor authentication verified.'));
    }

    // ─── JSON variants for cross-factor pickers ──────────────────────────────

    /** JSON list of the user's verified factors (used by the chooser JS). */
    public function listJson(): JsonResponse
    {
        $factors = $this->otp->factorsFor((int) auth()->id())
            ->filter(fn ($f) => $f->verified_at !== null)
            ->map(fn ($f) => [
                'id' => $f->id,
                'factor_type' => $f->factor_type,
                'label' => $f->label,
                'destination_mask' => $this->maskDestination($f),
            ])
            ->values();

        return response()->json(['factors' => $factors]);
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function ownedFactor(int $userId, int $factorId): ?object
    {
        if (! Schema::hasTable('ahg_otp_factor')) {
            return null;
        }

        $row = DB::table('ahg_otp_factor')
            ->where('id', $factorId)
            ->where('user_id', $userId)
            ->first();

        return $row ?: null;
    }

    /**
     * Render a destination string for the UI without leaking the full
     * value. Email "alice@example.com" -> "a***@example.com"; phone
     * "+27821234567" -> "+278***4567".
     */
    private function maskDestination(object $factor): string
    {
        $dest = (string) $factor->destination;
        if ($factor->factor_type === OtpService::TYPE_EMAIL && str_contains($dest, '@')) {
            [$local, $domain] = explode('@', $dest, 2);
            $masked = mb_substr($local, 0, 1).'***';

            return $masked.'@'.$domain;
        }

        if (mb_strlen($dest) <= 4) {
            return '***';
        }

        return mb_substr($dest, 0, 4).str_repeat('*', max(0, mb_strlen($dest) - 8)).mb_substr($dest, -4);
    }
}
