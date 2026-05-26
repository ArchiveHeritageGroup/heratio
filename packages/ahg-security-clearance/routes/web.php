<?php

use AhgSecurityClearance\Controllers\MfaPolicyController;
use AhgSecurityClearance\Controllers\OtpController;
use AhgSecurityClearance\Controllers\SecurityClearanceController;
use AhgSecurityClearance\Controllers\WebAuthnController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Security Clearance Routes
|--------------------------------------------------------------------------
|
| Migrated from: ahgSecurityClearancePlugin (routing.yml + 4 modules)
|
| securityClearance module:  dashboard, index, view, grant, revoke, bulkGrant,
|                            revokeAccess, compartments, compartment-access,
|                            classify, declassify, report, compliance,
|                            watermark-settings, trace-watermark, user (slug)
| security module:           access-requests, approve, deny, view-request
| securityAudit module:      audit-dashboard, audit-index, audit-export, audit-object-access
| accessFilter module:       denied
| 2FA:                       two-factor, verify-2fa, setup-2fa, confirm-2fa,
|                            send-email-code, remove-2fa
|
*/

// ── Admin-only routes ────────────────────────────────────────────────────────
Route::middleware('admin')->group(function () {

    // Dashboard
    Route::get('/admin/security-clearance/dashboard', [SecurityClearanceController::class, 'dashboard'])
        ->name('security-clearance.dashboard');

    // Clearance index (list all users + clearances)
    Route::get('/admin/security-clearance', [SecurityClearanceController::class, 'index'])
        ->name('security-clearance.index');

    // View single user clearance
    Route::get('/admin/security-clearance/view/{id}', [SecurityClearanceController::class, 'view'])
        ->name('security-clearance.view')
        ->where('id', '[0-9]+');

    // Grant clearance (POST)
    Route::post('/admin/security-clearance/grant', [SecurityClearanceController::class, 'grant'])
        ->name('security-clearance.grant')
        ->middleware('acl:create');

    // Revoke clearance (POST)
    Route::post('/admin/security-clearance/revoke/{id}', [SecurityClearanceController::class, 'revoke'])
        ->name('security-clearance.revoke')
        ->where('id', '[0-9]+')
        ->middleware('acl:delete');

    // Bulk grant (POST)
    Route::post('/admin/security-clearance/bulk-grant', [SecurityClearanceController::class, 'bulkGrant'])
        ->name('security-clearance.bulk-grant')
        ->middleware('acl:create');

    // Revoke object access grant (POST)
    Route::post('/admin/security-clearance/revoke-access/{id}', [SecurityClearanceController::class, 'revokeAccess'])
        ->name('security-clearance.revoke-access')
        ->where('id', '[0-9]+')
        ->middleware('acl:delete');

    // Compartments
    Route::get('/admin/security-clearance/compartments', [SecurityClearanceController::class, 'compartments'])
        ->name('security-clearance.compartments');
    Route::get('/admin/security-clearance/compartment-access', [SecurityClearanceController::class, 'compartmentAccess'])
        ->name('security-clearance.compartment-access');

    // Object classification
    Route::get('/admin/security-clearance/classify/{id}', [SecurityClearanceController::class, 'classify'])
        ->name('security-clearance.classify')
        ->where('id', '[0-9]+');
    Route::post('/admin/security-clearance/classify', [SecurityClearanceController::class, 'classifyStore'])
        ->name('security-clearance.classify-store')
        ->middleware('acl:create');

    // Declassification
    Route::get('/admin/security-clearance/declassification/{id}', [SecurityClearanceController::class, 'declassification'])
        ->name('security-clearance.declassification')
        ->where('id', '[0-9]+');
    Route::post('/admin/security-clearance/declassify', [SecurityClearanceController::class, 'declassifyStore'])
        ->name('security-clearance.declassify-store')
        ->middleware('acl:update');

    // Reports
    Route::get('/admin/security-clearance/report', [SecurityClearanceController::class, 'report'])
        ->name('security-clearance.report');

    // Security Compliance
    Route::get('/admin/security-clearance/compliance', [SecurityClearanceController::class, 'securityCompliance'])
        ->name('security-clearance.compliance');

    // Watermark settings
    Route::get('/admin/security-clearance/watermark-settings', [SecurityClearanceController::class, 'watermarkSettings'])
        ->name('security-clearance.watermark-settings');
    Route::post('/admin/security-clearance/watermark-settings', [SecurityClearanceController::class, 'watermarkSettingsStore'])
        ->name('security-clearance.watermark-settings-store')
        ->middleware('acl:update');

    // Trace watermark
    Route::get('/admin/security-clearance/trace-watermark', [SecurityClearanceController::class, 'traceWatermark'])
        ->name('security-clearance.trace-watermark');
    Route::post('/admin/security-clearance/trace-watermark', [SecurityClearanceController::class, 'traceWatermarkResult'])
        ->name('security-clearance.trace-watermark-result')
        ->middleware('acl:update');

    // User clearance management by slug
    Route::get('/admin/security-clearance/user/{slug}', [SecurityClearanceController::class, 'user'])
        ->name('security-clearance.user')
        ->where('slug', '[a-zA-Z0-9_-]+');
    Route::post('/admin/security-clearance/user/{slug}', [SecurityClearanceController::class, 'userUpdate'])
        ->name('security-clearance.user-update')
        ->where('slug', '[a-zA-Z0-9_-]+')
        ->middleware('acl:update');

    // Remove 2FA for user (admin)
    Route::post('/admin/security-clearance/remove-2fa/{id}', [SecurityClearanceController::class, 'removeTwoFactor'])
        ->name('security-clearance.remove-2fa')
        ->where('id', '[0-9]+')
        ->middleware('acl:delete');

    // Access requests (admin review)
    Route::get('/admin/security-clearance/access-requests', [SecurityClearanceController::class, 'accessRequests'])
        ->name('security-clearance.access-requests');
    Route::post('/admin/security-clearance/access-requests/{id}/approve', [SecurityClearanceController::class, 'approveRequest'])
        ->name('security-clearance.approve-request')
        ->where('id', '[0-9]+')
        ->middleware('acl:update');
    Route::post('/admin/security-clearance/access-requests/{id}/deny', [SecurityClearanceController::class, 'denyRequest'])
        ->name('security-clearance.deny-request')
        ->where('id', '[0-9]+')
        ->middleware('acl:update');
    Route::get('/admin/security-clearance/access-requests/{id}', [SecurityClearanceController::class, 'viewRequest'])
        ->name('security-clearance.view-request')
        ->where('id', '[0-9]+');

    // Per-tenant MFA enforcement policy (issue #723)
    Route::get('/admin/security/mfa-policy', [MfaPolicyController::class, 'index'])
        ->name('security-clearance.mfa-policy.index');
    Route::get('/admin/security/mfa-policy/{tenantId}/edit', [MfaPolicyController::class, 'edit'])
        ->name('security-clearance.mfa-policy.edit')
        ->where('tenantId', '[0-9]+');
    Route::post('/admin/security/mfa-policy/{tenantId}', [MfaPolicyController::class, 'update'])
        ->name('security-clearance.mfa-policy.update')
        ->where('tenantId', '[0-9]+')
        ->middleware('acl:update');
    Route::post('/admin/security/mfa-policy/{tenantId}/reset', [MfaPolicyController::class, 'reset'])
        ->name('security-clearance.mfa-policy.reset')
        ->where('tenantId', '[0-9]+')
        ->middleware('acl:update');

    // Security Audit
    Route::get('/admin/security-clearance/audit/dashboard', [SecurityClearanceController::class, 'auditDashboard'])
        ->name('security-clearance.audit-dashboard');
    Route::get('/admin/security-clearance/audit', [SecurityClearanceController::class, 'auditIndex'])
        ->name('security-clearance.audit-index');
    Route::get('/admin/security-clearance/audit/export', [SecurityClearanceController::class, 'auditExport'])
        ->name('security-clearance.audit-export');
    Route::get('/admin/security-clearance/audit/object-access', [SecurityClearanceController::class, 'auditObjectAccess'])
        ->name('security-clearance.audit-object-access');
});

// ── Authenticated user routes ────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    // My requests
    Route::get('/security-clearance/my-requests', [SecurityClearanceController::class, 'myRequests'])
        ->name('security-clearance.my-requests');

    // Submit access request
    Route::post('/security-clearance/access-request', [SecurityClearanceController::class, 'submitAccessRequest'])
        ->name('security-clearance.submit-access-request')
        ->middleware('acl:create');

    // Access denied
    Route::get('/security-clearance/denied', [SecurityClearanceController::class, 'accessDenied'])
        ->name('security-clearance.access-denied');

    // 2FA / TOTP MFA routes (issue #690)
    Route::get('/security-clearance/two-factor', [SecurityClearanceController::class, 'twoFactor'])
        ->name('security-clearance.two-factor');
    Route::post('/security-clearance/verify-2fa', [SecurityClearanceController::class, 'verifyTwoFactor'])
        ->name('security-clearance.verify-2fa');
    Route::get('/security-clearance/setup-2fa', [SecurityClearanceController::class, 'setupTwoFactor'])
        ->name('security-clearance.setup-2fa');
    Route::post('/security-clearance/confirm-2fa', [SecurityClearanceController::class, 'confirmTwoFactor'])
        ->name('security-clearance.confirm-2fa');
    Route::post('/security-clearance/send-email-code', [SecurityClearanceController::class, 'sendEmailCode'])
        ->name('security-clearance.send-email-code')
        ->middleware('acl:create');

    Route::get('/security-clearance/recovery-codes', [SecurityClearanceController::class, 'showRecoveryCodes'])
        ->name('security-clearance.recovery-codes');
    Route::post('/security-clearance/recovery-codes/regenerate', [SecurityClearanceController::class, 'regenerateRecoveryCodes'])
        ->name('security-clearance.regenerate-recovery-codes');

    Route::get('/security-clearance/disable-2fa', [SecurityClearanceController::class, 'showDisableTwoFactor'])
        ->name('security-clearance.disable-2fa');
    Route::post('/security-clearance/disable-2fa', [SecurityClearanceController::class, 'disableTwoFactor'])
        ->name('security-clearance.disable-2fa.confirm');

    // WebAuthn / FIDO2 / passkey MFA routes (issue #721)
    // /security/2fa/webauthn/* — sibling factor to TOTP. A user with TOTP
    // enrolled can also enrol a passkey; login flow asks which to use when
    // both are present.
    Route::get('/security/2fa/webauthn', [WebAuthnController::class, 'setupPage'])
        ->name('security-clearance.webauthn.list');
    Route::get('/security/2fa/webauthn/add', [WebAuthnController::class, 'addPage'])
        ->name('security-clearance.webauthn.add');
    Route::post('/security/2fa/webauthn/register/begin', [WebAuthnController::class, 'registerBegin'])
        ->name('security-clearance.webauthn.register-begin');
    Route::post('/security/2fa/webauthn/register/complete', [WebAuthnController::class, 'registerComplete'])
        ->name('security-clearance.webauthn.register-complete');
    Route::post('/security/2fa/webauthn/{id}/delete', [WebAuthnController::class, 'delete'])
        ->name('security-clearance.webauthn.delete')
        ->where('id', '[0-9]+');

    // Login-time assertion endpoints (pending_mfa session flag set). These
    // intentionally sit outside the admin-only block so the user can hit
    // them while gated by RequireMfaCompletion.
    Route::get('/security/2fa/webauthn/verify', [WebAuthnController::class, 'verifyPage'])
        ->name('security-clearance.webauthn.verify');
    Route::post('/security/2fa/webauthn/assert/begin', [WebAuthnController::class, 'assertBegin'])
        ->name('security-clearance.webauthn.assert-begin');
    Route::post('/security/2fa/webauthn/assert/complete', [WebAuthnController::class, 'assertComplete'])
        ->name('security-clearance.webauthn.assert-complete');

    // Chooser shown when both TOTP + WebAuthn are enrolled.
    Route::get('/security-clearance/two-factor/choose', [SecurityClearanceController::class, 'twoFactorChooser'])
        ->name('security-clearance.two-factor-choose');

    // Email / SMS OTP MFA routes (issue #722).
    // /security/2fa/otp/* — third sibling factor next to TOTP + WebAuthn.
    // Enrolment management.
    Route::get('/security/2fa/otp', [OtpController::class, 'list'])
        ->name('security-clearance.otp.list');
    Route::get('/security/2fa/otp/add', [OtpController::class, 'setupPage'])
        ->name('security-clearance.otp.setup');
    Route::post('/security/2fa/otp/enrol', [OtpController::class, 'enrol'])
        ->name('security-clearance.otp.enrol');
    Route::get('/security/2fa/otp/{factor}/verify-enrolment', [OtpController::class, 'verifyEnrolmentPage'])
        ->name('security-clearance.otp.verify-enrolment')
        ->where('factor', '[0-9]+');
    Route::post('/security/2fa/otp/{factor}/verify-enrolment', [OtpController::class, 'verifyEnrolment'])
        ->name('security-clearance.otp.verify-enrolment-submit')
        ->where('factor', '[0-9]+');
    Route::post('/security/2fa/otp/{factor}/resend-enrolment', [OtpController::class, 'resendEnrolment'])
        ->name('security-clearance.otp.resend-enrolment')
        ->where('factor', '[0-9]+');
    Route::post('/security/2fa/otp/{factor}/delete', [OtpController::class, 'delete'])
        ->name('security-clearance.otp.delete')
        ->where('factor', '[0-9]+');
    Route::get('/security/2fa/otp/factors.json', [OtpController::class, 'listJson'])
        ->name('security-clearance.otp.list-json');

    // Login-time assertion endpoints (pending_mfa session flag set).
    Route::get('/security/2fa/otp/verify', [OtpController::class, 'verifyPage'])
        ->name('security-clearance.otp.verify');
    Route::post('/security/2fa/otp/assert/begin', [OtpController::class, 'assertBegin'])
        ->name('security-clearance.otp.assert-begin');
    Route::post('/security/2fa/otp/assert/complete', [OtpController::class, 'assertComplete'])
        ->name('security-clearance.otp.assert-complete');
});

// ── Legacy redirects (ahgSecurityClearancePlugin compatibility) ─────────────
Route::get('/security/clearances', fn () => redirect('/admin/security-clearance', 301));
Route::get('/security/dashboard', fn () => redirect('/admin/security-clearance/dashboard', 301));
Route::get('/security/report', fn () => redirect('/admin/security-clearance/report', 301));
Route::get('/security/compartments', fn () => redirect('/admin/security-clearance/compartments', 301));
Route::get('/admin/security/compliance', fn () => redirect('/admin/security-clearance/compliance', 301));
Route::get('/security/clearance/{id}', fn ($id) => redirect("/admin/security-clearance/view/{$id}", 301))->where('id', '[0-9]+');
Route::post('/security/clearance/grant', fn () => redirect()->route('security-clearance.grant', [], 307));
Route::post('/security/clearance/{id}/revoke', fn ($id) => redirect("/admin/security-clearance/revoke/{$id}", 307))->where('id', '[0-9]+');
Route::post('/security/clearance/bulk-grant', fn () => redirect()->route('security-clearance.bulk-grant', [], 307));
Route::post('/security/access/{id}/revoke', fn ($id) => redirect("/admin/security-clearance/revoke-access/{$id}", 307))->where('id', '[0-9]+');
Route::get('/security/clearance/user/{slug}', fn ($slug) => redirect("/admin/security-clearance/user/{$slug}", 301));
Route::post('/security/request/submit', fn () => redirect()->route('security-clearance.submit-access-request', [], 307));
