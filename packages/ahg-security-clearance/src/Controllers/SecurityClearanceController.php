<?php

/**
 * SecurityClearanceController - Controller for Heratio
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

namespace AhgSecurityClearance\Controllers;

use AhgSecurityClearance\Services\OtpService;
use AhgSecurityClearance\Services\SecurityClearanceService;
use AhgSecurityClearance\Services\TotpService;
use AhgSecurityClearance\Services\WebAuthnService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security Clearance Controller.
 *
 * Covers all 24 AtoM ahgSecurityClearancePlugin actions:
 *
 * securityClearance module:
 *   1. index         — List all users and their clearances
 *   2. view          — View single user clearance details
 *   3. grant         — Grant or update clearance (POST)
 *   4. revoke        — Revoke clearance (POST)
 *   5. bulkGrant     — Bulk grant clearances (POST)
 *   6. revokeAccess  — Revoke object access grant (POST)
 *   7. dashboard     — Security dashboard with stats
 *   8. report        — Security reports
 *   9. compartments  — Compartments management
 *  10. twoFactor     — 2FA verification page
 *  11. verifyTwoFactor — 2FA verify POST
 *  12. setupTwoFactor — 2FA setup page (QR code)
 *  13. confirmTwoFactor — Confirm 2FA setup POST
 *  14. sendEmailCode — Send 2FA code via email (JSON)
 *  15. removeTwoFactor — Admin: remove user 2FA
 *  16. securityCompliance — Security compliance dashboard
 *  17. user          — User clearance management by slug
 *  18. watermarkSettings — Watermark settings
 *
 * security module:
 *  19. accessRequests  — List pending access requests
 *  20. approveRequest  — Approve request (POST)
 *  21. denyRequest     — Deny request (POST)
 *  22. viewRequest     — View single request
 *
 * securityAudit module:
 *  23. auditDashboard  — Audit dashboard
 *  24. auditIndex      — Audit log index with filters
 *  25. auditExport     — Export audit log as CSV
 *  26. auditObjectAccess — Object access audit
 *
 * accessFilter module:
 *  27. denied          — Access denied page
 */
class SecurityClearanceController extends Controller
{
    private SecurityClearanceService $service;
    private TotpService $totp;
    private WebAuthnService $webauthn;
    private OtpService $otp;

    public function __construct(SecurityClearanceService $service, TotpService $totp, WebAuthnService $webauthn, OtpService $otp)
    {
        $this->service = $service;
        $this->totp = $totp;
        $this->webauthn = $webauthn;
        $this->otp = $otp;
    }

    // =========================================================================
    // Clearance Management (securityClearance module)
    // =========================================================================

    /**
     * #1 — List all users and their clearances.
     * AtoM: securityClearance/executeIndex
     */
    public function index()
    {
        $users = $this->service->getAllUsersWithClearances();
        $classifications = $this->service->getClassificationLevels();

        $stats = [
            'total_users' => DB::table('user')->count(),
            'with_clearance' => DB::table('user_security_clearance')->count(),
            'top_secret' => DB::table('user_security_clearance as usc')
                ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
                ->where('sc.level', '>=', 4)
                ->count(),
        ];

        return view('ahg-security-clearance::clearance.index', compact('users', 'classifications', 'stats'));
    }

    /**
     * #2 — View single user's clearance details.
     * AtoM: securityClearance/executeView
     */
    public function view(int $id)
    {
        $targetUser = DB::table('user')->where('id', $id)->first();

        if (! $targetUser) {
            abort(404, 'User not found');
        }

        $clearance = $this->service->getUserClearanceRecord($id);
        $classifications = $this->service->getClassificationLevels();
        $history = $this->service->getClearanceHistory($id);
        $accessGrants = $this->service->getUserAccessGrants($id);

        return view('ahg-security-clearance::clearance.view', compact('targetUser', 'clearance', 'classifications', 'history', 'accessGrants'));
    }

    /**
     * #3 — Grant or update clearance (POST).
     * AtoM: securityClearance/executeGrant
     */
    public function grant(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'classification_id' => 'required|integer',
        ]);

        $userId = (int) $request->input('user_id');
        $classificationId = (int) $request->input('classification_id');
        $expiresAt = $request->input('expires_at');
        $notes = trim($request->input('notes', ''));
        $grantedBy = auth()->id() ?? 1;

        if ($classificationId === 0) {
            $success = $this->service->revokeClearance($userId, $grantedBy, $notes ?: 'Clearance revoked by administrator');
            $message = $success ? 'Clearance revoked successfully.' : 'Failed to revoke clearance.';
        } else {
            $success = $this->service->grantClearance($userId, $classificationId, $grantedBy, $expiresAt ?: null, $notes);
            $message = $success ? 'Clearance granted successfully.' : 'Failed to grant clearance.';
        }

        return redirect()->route('security-clearance.index')
            ->with($success ? 'success' : 'error', $message);
    }

    /**
     * #4 — Revoke clearance (POST).
     * AtoM: securityClearance/executeRevoke
     */
    public function revoke(Request $request, int $id)
    {
        $grantedBy = auth()->id() ?? 1;
        $notes = $request->input('notes', 'Clearance revoked by administrator');

        $success = $this->service->revokeClearance($id, $grantedBy, $notes);

        return redirect()->route('security-clearance.index')
            ->with($success ? 'success' : 'error', $success ? 'Clearance revoked.' : 'Failed to revoke clearance.');
    }

    /**
     * #5 — Bulk grant clearances (POST).
     * AtoM: securityClearance/executeBulkGrant
     */
    public function bulkGrant(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer',
            'classification_id' => 'required|integer',
        ]);

        $grantedBy = auth()->id() ?? 1;
        $notes = trim($request->input('notes', 'Bulk grant by administrator'));

        $count = $this->service->bulkGrant(
            $request->input('user_ids'),
            (int) $request->input('classification_id'),
            $grantedBy,
            $notes
        );

        return redirect()->route('security-clearance.index')
            ->with('success', "Clearance granted to {$count} users.");
    }

    /**
     * #6 — Revoke object access grant (POST).
     * AtoM: securityClearance/executeRevokeAccess
     */
    public function revokeAccess(Request $request, int $id)
    {
        $userId = (int) $request->input('user_id');
        $revokedBy = auth()->id() ?? 1;

        $success = $this->service->revokeObjectAccess($id, $revokedBy);

        return redirect()->route('security-clearance.view', ['id' => $userId])
            ->with($success ? 'success' : 'error', $success ? 'Access revoked.' : 'Failed to revoke access.');
    }

    /**
     * #7 — Security Dashboard.
     * AtoM: securityClearance/executeDashboard
     */
    public function dashboard()
    {
        $statistics = $this->service->getDashboardStatistics();
        $pendingRequests = $this->service->getPendingRequests();
        $expiringClearances = $this->service->getExpiringClearances();
        $dueDeclassifications = $this->service->getDueDeclassifications();

        return view('ahg-security-clearance::clearance.dashboard', compact('statistics', 'pendingRequests', 'expiringClearances', 'dueDeclassifications'));
    }

    /**
     * #8 — Security Reports.
     * AtoM: securityClearance/executeReport
     */
    public function report(Request $request)
    {
        $period = $request->input('period', '30 days');
        $reportData = $this->service->getReportStats($period);

        return view('ahg-security-clearance::clearance.report', array_merge($reportData, ['period' => $period]));
    }

    /**
     * #9 — Compartments management.
     * AtoM: securityClearance/executeCompartments
     */
    public function compartments()
    {
        $compartments = $this->service->getCompartments();
        $userCounts = $this->service->getCompartmentUserCounts();

        return view('ahg-security-clearance::clearance.compartments', compact('compartments', 'userCounts'));
    }

    /**
     * #9b — Compartment access grants.
     */
    public function compartmentAccess()
    {
        $grants = $this->service->getCompartmentAccessGrants();

        return view('ahg-security-clearance::clearance.compartment-access', compact('grants'));
    }

    /**
     * #10 — 2FA verification page.
     * AtoM: securityClearance/executeTwoFactor
     *
     * Updated for issue #721 — if both TOTP + WebAuthn are enrolled the user
     * picks at the chooser; passkey-only goes straight to verifyPage; TOTP-
     * only keeps the original behaviour (this view).
     */
    public function twoFactor(Request $request)
    {
        $returnUrl = $request->input('return', '/');
        $userId = (int) auth()->id();
        $hasTotp = $this->totp->userHasMfa($userId);
        $hasPasskey = $this->webauthn->userHasCredential($userId);
        $hasOtp = $this->otp->userHasOtp($userId);
        $forceTotp = (bool) $request->query('force_totp');

        // Issue #721 / #722 — when more than one factor is enrolled, route
        // through the chooser unless the user explicitly picked TOTP from
        // it (?force_totp=1). Single-factor users go straight to the
        // matching verify page so they never see an irrelevant chooser.
        $enrolledCount = (int) $hasTotp + (int) $hasPasskey + (int) $hasOtp;
        if ($enrolledCount >= 2 && ! $forceTotp) {
            return redirect()->route('security-clearance.two-factor-choose', ['return' => $returnUrl]);
        }

        if ($hasPasskey && ! $hasTotp && ! $hasOtp) {
            return redirect()->route('security-clearance.webauthn.verify', ['return' => $returnUrl]);
        }

        if ($hasOtp && ! $hasTotp && ! $hasPasskey) {
            return redirect()->route('security-clearance.otp.verify', ['return' => $returnUrl]);
        }

        $clearance = $this->service->getUserClearance($userId);

        return view('ahg-security-clearance::twofactor.verify', compact('returnUrl', 'clearance'));
    }

    /**
     * Login-time chooser shown when the user has enrolled two or more of
     * TOTP, WebAuthn passkey, or email/SMS OTP. Any enrolled factor can
     * satisfy the MFA gate.
     */
    public function twoFactorChooser(Request $request)
    {
        $returnUrl = $request->input('return', '/');
        $userId = (int) auth()->id();

        return view('ahg-security-clearance::webauthn.chooser', [
            'returnUrl' => $returnUrl,
            'hasTotp' => $this->totp->userHasMfa($userId),
            'hasPasskey' => $this->webauthn->userHasCredential($userId),
            'hasOtp' => $this->otp->userHasOtp($userId),
        ]);
    }

    /**
     * #11 — 2FA verification POST.
     * Accepts a 6-digit TOTP code OR a single-use recovery code; on success,
     * clears the post-login pending_mfa session flag and creates the
     * security_2fa_session marker so subsequent requests aren't re-prompted.
     */
    public function verifyTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = auth()->id();
        $code = trim($request->input('code'));
        $returnUrl = $request->input('return', $request->session()->pull('mfa_return_url', '/'));

        if (! $this->totp->verifyCode($userId, $code)) {
            \Log::info('mfa.verify.failed', ['user_id' => $userId, 'ip' => $request->ip()]);

            return redirect()->route('security-clearance.two-factor', ['return' => $returnUrl])
                ->with('error', __('Invalid verification code. Please try again.'));
        }

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

        $remaining = $this->totp->unusedRecoveryCodeCount($userId);
        if ($remaining > 0 && $remaining <= 2) {
            $request->session()->flash('warning',
                __('You have :n recovery code(s) remaining. Regenerate them from your security settings.', ['n' => $remaining])
            );
        }

        return redirect($returnUrl)->with('success', __('Two-factor authentication verified.'));
    }

    /**
     * #12 — 2FA setup page (begins enrolment, renders QR + secret).
     * Generates a pending TOTP secret (enabled_at IS NULL) and passes the
     * otpauth QR data-URI + secret to the view. Idempotent: each visit
     * regenerates the pending secret until the user confirms.
     */
    public function setupTwoFactor(Request $request)
    {
        $userId = auth()->id();
        $returnUrl = $request->input('return', '/');

        if ($this->totp->userHasMfa($userId)) {
            return redirect($returnUrl)
                ->with('info', __('Two-factor authentication is already enabled on your account.'));
        }

        $user = DB::table('user')->where('id', $userId)->first();
        $identifier = $user->email ?? $user->username ?? "user-{$userId}";
        $issuer = config('app.name', 'Heratio');

        $enrolment = $this->totp->beginEnrolment($userId, $identifier, $issuer);

        return view('ahg-security-clearance::twofactor.setup', [
            'returnUrl' => $returnUrl,
            'secret' => $enrolment['secret'],
            'qrSvgDataUri' => $enrolment['qr_svg_data_uri'],
            'otpauthUri' => $enrolment['otpauth_uri'],
        ]);
    }

    /**
     * #13 — Confirm 2FA setup POST.
     * Validates the first TOTP code against the pending secret. On success
     * the secret becomes active, a batch of recovery codes is minted, and
     * the codes are flashed to the recovery-codes view for one-time display.
     */
    public function confirmTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string|size:6']);

        $userId = auth()->id();
        $code = trim($request->input('code'));
        $returnUrl = $request->input('return', '/');

        $result = $this->totp->confirmEnrolment($userId, $code);

        if (! $result['ok']) {
            return redirect()->route('security-clearance.setup-2fa', ['return' => $returnUrl])
                ->with('error', __('Invalid code. Re-scan the QR code and enter the current code from your authenticator app.'));
        }

        \Log::info('mfa.enrolled', ['user_id' => $userId]);

        return redirect()->route('security-clearance.recovery-codes', ['return' => $returnUrl])
            ->with('mfa_recovery_codes', $result['recovery_codes'])
            ->with('success', __('Two-factor authentication is now active. Save your recovery codes — they will not be shown again.'));
    }

    /**
     * Render recovery codes once. Reads from flash session only; reloading
     * the page after the flash clears returns an empty list (no leak from DB).
     */
    public function showRecoveryCodes(Request $request)
    {
        $codes = $request->session()->get('mfa_recovery_codes', []);
        $returnUrl = $request->input('return', '/');

        return view('ahg-security-clearance::twofactor.recovery-codes', [
            'codes' => $codes,
            'returnUrl' => $returnUrl,
            'remainingCount' => $this->totp->unusedRecoveryCodeCount(auth()->id()),
        ]);
    }

    /**
     * Regenerate recovery codes (user lost their printed sheet). Mints a
     * fresh batch of 10, invalidates all previous codes, flashes the new
     * plaintext set to the recovery-codes view.
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $userId = auth()->id();
        $returnUrl = $request->input('return', '/');

        $codes = $this->totp->regenerateRecoveryCodes($userId);
        if (empty($codes)) {
            return redirect($returnUrl)
                ->with('error', __('You do not have two-factor authentication enabled.'));
        }

        \Log::info('mfa.recovery_codes_regenerated', ['user_id' => $userId]);

        return redirect()->route('security-clearance.recovery-codes', ['return' => $returnUrl])
            ->with('mfa_recovery_codes', $codes)
            ->with('success', __('New recovery codes generated. Save them — your previous codes are no longer valid.'));
    }

    /**
     * User-initiated MFA disable. Requires a current TOTP code or recovery
     * code to confirm the user still has the second factor — guards against
     * an attacker with a stolen session removing MFA without the factor.
     */
    public function disableTwoFactor(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $userId = auth()->id();

        if (! $this->totp->verifyCode($userId, trim($request->input('code')))) {
            return redirect()->route('security-clearance.disable-2fa')
                ->with('error', __('Invalid verification code. Disable cancelled.'));
        }

        $this->totp->disable($userId);
        if (Schema::hasTable('security_2fa_session')) {
            DB::table('security_2fa_session')->where('user_id', $userId)->delete();
        }

        \Log::info('mfa.disabled.self', ['user_id' => $userId]);

        return redirect('/user/profile')
            ->with('success', __('Two-factor authentication has been disabled.'));
    }

    /** Render the disable-2FA confirmation form (asks for current code). */
    public function showDisableTwoFactor()
    {
        return view('ahg-security-clearance::twofactor.disable');
    }

    /**
     * #14 — Send 2FA code via email (JSON).
     * AtoM: securityClearance/executeSendEmailCode
     */
    public function sendEmailCode(Request $request)
    {
        $userId = auth()->id();
        $email = DB::table('user')->where('id', $userId)->value('email');

        if (! $email) {
            return response()->json(['error' => 'No email address on file'], 400);
        }

        // Generate a simple 6-digit code and store it
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (Schema::hasTable('security_email_code')) {
            DB::table('security_email_code')->where('user_id', $userId)->delete();
            DB::table('security_email_code')->insert([
                'user_id' => $userId,
                'code' => $code,
                'expires_at' => now()->addMinutes(10),
                'created_at' => now(),
            ]);
        }

        $subject = 'Heratio — Your verification code';
        $body = "Your two-factor verification code is: {$code}\n\nThis code expires in 10 minutes.\n\nIf you did not request this code, please ignore this email.";
        $headers = 'From: noreply@'.($request->getHost() ?? 'localhost')."\r\n"
                 ."Content-Type: text/plain; charset=UTF-8\r\n";

        $sent = @mail($email, $subject, $body, $headers);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Verification code sent to your email.' : 'Failed to send email. Please use your authenticator app.',
        ]);
    }

    /**
     * #15 — Admin: Remove 2FA enrollment for a user.
     * Used when a user has lost both their authenticator AND every recovery
     * code. Admin overrides via this endpoint; the audit log records who
     * cleared whom.
     */
    public function removeTwoFactor(int $id)
    {
        $this->totp->disable($id);
        if (Schema::hasTable('security_2fa_session')) {
            DB::table('security_2fa_session')->where('user_id', $id)->delete();
        }

        \Log::info('mfa.disabled.admin', ['target_user_id' => $id, 'admin_user_id' => auth()->id()]);

        return redirect()->route('security-clearance.view', ['id' => $id])
            ->with('success', __('Two-factor authentication removed for user.'));
    }

    /**
     * #16 — Security Compliance Dashboard.
     * AtoM: securityClearance/executeSecurityCompliance
     */
    public function securityCompliance()
    {
        $stats = $this->service->getComplianceStats();
        $recentLogs = $this->service->getRecentComplianceLogs();

        return view('ahg-security-clearance::clearance.security-compliance', compact('stats', 'recentLogs'));
    }

    /**
     * #17 — User clearance management by slug.
     * AtoM: securityClearance/userAction
     */
    public function user(string $slug)
    {
        $targetUser = DB::table('user as u')
            ->join('actor as a', 'a.id', '=', 'u.id')
            ->join('slug', 'slug.object_id', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('ai.id', '=', 'a.id')->where('ai.culture', '=', 'en');
            })
            ->where('slug.slug', $slug)
            ->select('u.*', 'a.entity_type_id', 'ai.authorized_form_of_name', 'slug.slug')
            ->first();

        if (! $targetUser) {
            abort(404, 'User not found');
        }

        $clearance = $this->service->getUserClearanceRecord($targetUser->id);
        $classifications = $this->service->getClassificationLevels();
        $history = $this->service->getClearanceHistory($targetUser->id);

        return view('ahg-security-clearance::clearance.user', compact('targetUser', 'clearance', 'classifications', 'history'));
    }

    /**
     * #17b — User clearance management POST (update/revoke).
     */
    public function userUpdate(Request $request, string $slug)
    {
        $targetUser = DB::table('user as u')
            ->join('slug', 'slug.object_id', '=', 'u.id')
            ->where('slug.slug', $slug)
            ->select('u.id', 'slug.slug')
            ->first();

        if (! $targetUser) {
            abort(404, 'User not found');
        }

        $actionType = $request->input('action_type');
        $currentUserId = auth()->id() ?? 1;

        if ($actionType === 'update') {
            $classificationId = (int) $request->input('classification_id');
            $expiresAt = $request->input('expires_at') ?: null;
            $notes = $request->input('notes');

            if ($classificationId) {
                $this->service->grantClearance($targetUser->id, $classificationId, $currentUserId, $expiresAt, $notes);
            }

            return redirect()->route('security-clearance.user', ['slug' => $slug])
                ->with('success', 'Clearance updated.');
        } elseif ($actionType === 'revoke') {
            $reason = $request->input('revoke_reason', 'Revoked by administrator');
            $this->service->revokeClearance($targetUser->id, $currentUserId, $reason);

            return redirect()->route('security-clearance.user', ['slug' => $slug])
                ->with('success', 'Clearance revoked.');
        }

        return redirect()->route('security-clearance.user', ['slug' => $slug]);
    }

    /**
     * #18 — Watermark Settings.
     * AtoM: securityClearance/watermarkSettingsAction
     */
    public function watermarkSettings()
    {
        $settings = [
            'default_enabled' => '1',
            'default_type' => 'COPYRIGHT',
            'apply_on_view' => '1',
            'apply_on_download' => '1',
            'security_override' => '1',
            'min_size' => '200',
        ];

        // AtoM stores setting values on `setting_i18n.value`, keyed to `setting.id`.
        if (Schema::hasTable('setting')) {
            foreach ($settings as $key => &$value) {
                $dbVal = DB::table('setting as s')
                    ->leftJoin('setting_i18n as si', function ($j) {
                        $j->on('si.id', '=', 's.id')->where('si.culture', '=', app()->getLocale());
                    })
                    ->where('s.name', 'watermark_'.$key)
                    ->value('si.value');
                if ($dbVal !== null) {
                    $value = $dbVal;
                }
            }
        }

        $watermarkTypes = collect();
        if (Schema::hasTable('watermark_type')) {
            $watermarkTypes = DB::table('watermark_type')
                ->where('active', 1)
                ->orderBy('sort_order')
                ->get();
        }

        $customWatermarks = collect();
        if (Schema::hasTable('custom_watermark')) {
            $customWatermarks = DB::table('custom_watermark')
                ->whereNull('object_id')
                ->where('active', 1)
                ->orderBy('name')
                ->get();
        }

        return view('ahg-security-clearance::clearance.watermark-settings', compact('settings', 'watermarkTypes', 'customWatermarks'));
    }

    /**
     * #18b — Watermark Settings POST.
     */
    public function watermarkSettingsStore(Request $request)
    {
        $fields = [
            'default_enabled', 'default_type', 'apply_on_view',
            'apply_on_download', 'security_override', 'min_size',
        ];

        // Value lives on `setting_i18n`, keyed by `setting.id` + `culture`.
        if (Schema::hasTable('setting')) {
            $culture = app()->getLocale();
            foreach ($fields as $field) {
                $name = 'watermark_'.$field;
                $value = $request->input($field, '0');

                $existing = DB::table('setting')->where('name', $name)->first();
                if ($existing) {
                    DB::table('setting_i18n')->updateOrInsert(
                        ['id' => $existing->id, 'culture' => $culture],
                        ['value' => $value]
                    );
                } else {
                    $id = DB::table('setting')->insertGetId([
                        'name' => $name,
                        'scope' => 'global',
                        'source_culture' => $culture,
                    ]);
                    DB::table('setting_i18n')->insert([
                        'id' => $id,
                        'culture' => $culture,
                        'value' => $value,
                    ]);
                }
            }
        }

        return redirect()->route('security-clearance.watermark-settings')
            ->with('success', 'Watermark settings saved.');
    }

    // =========================================================================
    // Object Classification
    // =========================================================================

    public function classify(int $id)
    {
        $object = DB::table('information_object_i18n')
            ->where('id', $id)->where('culture', 'en')
            ->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];

        $classifications = $this->service->getClassificationLevels();
        $currentClassification = $this->service->getObjectClassification($id);
        $compartments = $this->service->getCompartments();

        return view('ahg-security-clearance::clearance.classify', compact('object', 'classifications', 'currentClassification', 'compartments'));
    }

    public function classifyStore(Request $request)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'classification_id' => 'required|integer',
        ]);

        $userId = auth()->id() ?? 1;
        $success = $this->service->classifyObject(
            (int) $request->input('object_id'),
            (int) $request->input('classification_id'),
            $userId,
            $request->input('reason'),
            $request->input('compartment_ids')
        );

        return redirect()->route('security-clearance.dashboard')
            ->with($success ? 'success' : 'error', $success ? 'Classification applied.' : 'Failed to apply classification.');
    }

    public function declassification(int $id)
    {
        $object = DB::table('information_object_i18n')
            ->where('id', $id)->where('culture', 'en')
            ->first() ?? (object) ['id' => $id, 'title' => 'Unknown'];

        $currentClassification = $this->service->getObjectClassification($id);
        $classifications = $this->service->getClassificationLevels();

        return view('ahg-security-clearance::clearance.declassification', compact('object', 'currentClassification', 'classifications'));
    }

    public function declassifyStore(Request $request)
    {
        $request->validate(['object_id' => 'required|integer']);

        $userId = auth()->id() ?? 1;
        $success = $this->service->declassifyObject(
            (int) $request->input('object_id'),
            $userId,
            $request->input('new_classification_id') ? (int) $request->input('new_classification_id') : null,
            $request->input('reason')
        );

        return redirect()->route('security-clearance.dashboard')
            ->with($success ? 'success' : 'error', $success ? 'Object declassified.' : 'Failed to declassify.');
    }

    // =========================================================================
    // Access Requests (security module)
    // =========================================================================

    /**
     * #19 — Access Requests list.
     * AtoM: security/executeAccessRequests
     */
    public function accessRequests(Request $request)
    {
        $status = $request->input('status', 'pending');
        $requests = $this->service->getAccessRequests($status ?: null);

        $stats = [
            'pending' => DB::table('security_access_request')->where('status', 'pending')->count(),
            'approved_today' => DB::table('security_access_request')->where('status', 'approved')->whereDate('reviewed_at', today())->count(),
            'denied_today' => DB::table('security_access_request')->where('status', 'denied')->whereDate('reviewed_at', today())->count(),
            'total_this_month' => DB::table('security_access_request')->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
        ];

        return view('ahg-security-clearance::clearance.access-requests', compact('requests', 'stats', 'status'));
    }

    /**
     * #20 — Approve request (POST).
     * AtoM: security/executeApproveRequest
     */
    public function approveRequest(Request $request, int $id)
    {
        $reviewerId = auth()->id() ?? 1;
        $notes = $request->input('note', '');
        $durationHours = (int) $request->input('duration_hours', 24);

        $this->service->reviewAccessRequest($id, 'approved', $reviewerId, $notes, $durationHours);

        return redirect()->route('security-clearance.access-requests')
            ->with('success', 'Access request approved.');
    }

    /**
     * #21 — Deny request (POST).
     * AtoM: security/executeDenyRequest
     */
    public function denyRequest(Request $request, int $id)
    {
        $reviewerId = auth()->id() ?? 1;
        $notes = $request->input('note', '');

        $this->service->reviewAccessRequest($id, 'denied', $reviewerId, $notes);

        return redirect()->route('security-clearance.access-requests')
            ->with('success', 'Access request denied.');
    }

    /**
     * #22 — View single request.
     * AtoM: security/executeViewRequest
     */
    public function viewRequest(int $id)
    {
        $accessRequest = DB::table('security_access_request as sar')
            ->leftJoin('user as u', 'sar.user_id', '=', 'u.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('u.id', '=', 'ai.id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('sar.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 'sar.object_id', '=', 's.object_id')
            ->where('sar.id', $id)
            ->select(
                'sar.*',
                'u.username',
                DB::raw('COALESCE(ai.authorized_form_of_name, u.username) as user_name'),
                'ioi.title as object_title',
                's.slug'
            )
            ->first();

        if (! $accessRequest) {
            abort(404, 'Access request not found');
        }

        return view('ahg-security-clearance::clearance.view-request', compact('accessRequest'));
    }

    /**
     * Submit access request (POST) — for authenticated users.
     */
    public function submitAccessRequest(Request $request)
    {
        $request->validate([
            'object_id' => 'required|integer',
            'request_type' => 'required|string',
            'justification' => 'required|string|max:1000',
        ]);

        $userId = auth()->id();
        $success = $this->service->submitAccessRequest(
            $userId,
            (int) $request->input('object_id'),
            $request->input('request_type'),
            $request->input('justification'),
            $request->input('priority', 'normal'),
            (int) $request->input('duration_hours', 24)
        );

        return redirect()->route('security-clearance.my-requests')
            ->with($success ? 'success' : 'error', $success ? 'Access request submitted.' : 'Failed to submit request.');
    }

    /**
     * My Requests — user's own requests.
     */
    public function myRequests()
    {
        $userId = auth()->id();
        $currentClearance = $this->service->getUserClearance($userId);

        $requests = DB::table('security_access_request as sar')
            ->leftJoin('security_classification as sc', 'sc.id', '=', 'sar.classification_id')
            ->where('sar.user_id', $userId)
            ->select('sar.*', 'sc.name as requested_classification', 'sc.code as classification_code')
            ->orderByDesc('sar.created_at')
            ->get();

        $accessGrants = collect();
        if (Schema::hasTable('security_object_access')) {
            $accessGrants = DB::table('security_object_access as soa')
                ->leftJoin('information_object_i18n as ioi', function ($j) {
                    $j->on('soa.object_id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
                })
                ->where('soa.user_id', $userId)
                ->select('soa.*', 'ioi.title as object_title')
                ->orderByDesc('soa.granted_at')
                ->get();
        }

        return view('ahg-security-clearance::clearance.my-requests', compact('currentClearance', 'requests', 'accessGrants'));
    }

    /**
     * Access Denied page.
     */
    public function accessDenied(Request $request)
    {
        $objectId = $request->input('id');
        $objectTitle = 'Restricted Resource';
        if ($objectId) {
            $objectTitle = DB::table('information_object_i18n')
                ->where('id', $objectId)->where('culture', 'en')
                ->value('title') ?? 'Restricted Resource';
        }

        return view('ahg-security-clearance::clearance.denied', compact('objectTitle'));
    }

    /**
     * Trace Watermark.
     */
    public function traceWatermark()
    {
        return view('ahg-security-clearance::clearance.trace-watermark', ['watermarkCode' => null, 'traceResult' => null]);
    }

    public function traceWatermarkResult(Request $request)
    {
        $watermarkCode = $request->input('watermark_code');
        $traceResult = null;

        // Look up watermark code in access logs
        if ($watermarkCode && Schema::hasTable('access_log')) {
            $traceResult = DB::table('access_log')
                ->where('watermark_code', $watermarkCode)
                ->orderByDesc('access_date')
                ->first();
        }

        return view('ahg-security-clearance::clearance.trace-watermark', compact('watermarkCode', 'traceResult'));
    }

    // =========================================================================
    // Security Audit (securityAudit module)
    // =========================================================================

    /**
     * #23 — Audit Dashboard.
     * AtoM: securityAudit/executeDashboard
     */
    public function auditDashboard(Request $request)
    {
        $period = $request->input('period', '30 days');
        $since = now()->sub(\DateInterval::createFromDateString($period));

        $stats = [
            'total_events' => 0,
            'security_events' => 0,
            'by_user' => collect(),
            'by_action' => collect(),
            'by_day' => collect(),
            'top_objects' => collect(),
            'since' => $since->format('M j, Y H:i'),
        ];

        if (Schema::hasTable('security_audit_log')) {
            $stats['total_events'] = DB::table('security_audit_log')
                ->where('created_at', '>=', $since)->count();
            $stats['security_events'] = DB::table('security_audit_log')
                ->where('created_at', '>=', $since)
                ->where('action_category', 'security')
                ->count();
            $stats['by_user'] = DB::table('security_audit_log')
                ->where('created_at', '>=', $since)
                ->whereNotNull('user_name')
                ->select('user_name as username', DB::raw('COUNT(*) as count'))
                ->groupBy('user_name')
                ->orderByDesc('count')
                ->limit(10)
                ->get();
            $stats['by_action'] = DB::table('security_audit_log')
                ->where('created_at', '>=', $since)
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->get();
            $stats['by_day'] = DB::table('security_audit_log')
                ->where('created_at', '>=', $since)
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get();
        }

        return view('ahg-security-clearance::audit.dashboard', compact('stats', 'period'));
    }

    /**
     * #24 — Audit Log Index with filters.
     * AtoM: securityAudit/executeIndex
     */
    public function auditIndex(Request $request)
    {
        $filters = [
            'user_name' => $request->input('user'),
            'action' => $request->input('log_action'),
            'category' => $request->input('category'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];
        $filters = array_filter($filters);

        $page = max(1, (int) $request->input('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $result = $this->service->getAuditLog($filters, $limit, $offset);
        $logs = $result['logs'];
        $total = $result['total'];
        $totalPages = $total > 0 ? ceil($total / $limit) : 1;

        $actions = [];
        $categories = [];
        if (Schema::hasTable('security_audit_log')) {
            $actions = DB::table('security_audit_log')->distinct()->pluck('action')->filter()->values()->toArray();
            $categories = DB::table('security_audit_log')->distinct()->pluck('action_category')->filter()->values()->toArray();
        }

        return view('ahg-security-clearance::audit.index', compact('logs', 'total', 'page', 'totalPages', 'filters', 'actions', 'categories'));
    }

    /**
     * #25 — Export audit log as CSV.
     * AtoM: securityAudit/executeExport
     */
    public function auditExport()
    {
        $logs = $this->service->exportAuditLog();
        $filename = 'security_audit_'.date('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date/Time', 'User', 'Action', 'Category', 'Object', 'IP Address']);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log->created_at,
                    $log->user_name,
                    $log->action,
                    $log->action_category,
                    $log->object_title ?? 'N/A',
                    $log->ip_address ?? 'N/A',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * #26 — Object Access Audit.
     * AtoM: securityAudit/executeObjectAccess
     */
    public function auditObjectAccess(Request $request)
    {
        $objectId = (int) $request->input('object_id');
        $period = $request->input('period', '30 days');
        $since = now()->sub(\DateInterval::createFromDateString($period));

        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'ioi.title', 'slug.slug')
            ->first();

        if (! $object && $objectId) {
            return redirect()->route('security-clearance.audit-dashboard')
                ->with('error', 'Object not found.');
        }

        $accessLogs = collect();
        $securityLogs = collect();
        $dailyAccess = collect();
        $totalAccess = 0;

        if ($objectId) {
            if (Schema::hasTable('access_log')) {
                $accessLogs = DB::table('access_log')
                    ->where('object_id', $objectId)
                    ->where('access_date', '>=', $since)
                    ->orderByDesc('access_date')
                    ->limit(100)
                    ->get();

                $dailyAccess = DB::table('access_log')
                    ->where('object_id', $objectId)
                    ->where('access_date', '>=', $since)
                    ->select(DB::raw('DATE(access_date) as date'), DB::raw('COUNT(*) as count'))
                    ->groupBy(DB::raw('DATE(access_date)'))
                    ->orderBy('date')
                    ->get();

                $totalAccess = $accessLogs->count();
            }

            if (Schema::hasTable('security_audit_log')) {
                $securityLogs = DB::table('security_audit_log')
                    ->where('object_id', $objectId)
                    ->where('created_at', '>=', $since)
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get();
            }
        }

        return view('ahg-security-clearance::audit.object-access', compact('object', 'period', 'accessLogs', 'securityLogs', 'dailyAccess', 'totalAccess'));
    }
}
