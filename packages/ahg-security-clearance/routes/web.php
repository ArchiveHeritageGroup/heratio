<?php

use AhgSecurityClearance\Controllers\SecurityClearanceController;
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
        ->name('security-clearance.grant');

    // Revoke clearance (POST)
    Route::post('/admin/security-clearance/revoke/{id}', [SecurityClearanceController::class, 'revoke'])
        ->name('security-clearance.revoke')
        ->where('id', '[0-9]+');

    // Bulk grant (POST)
    Route::post('/admin/security-clearance/bulk-grant', [SecurityClearanceController::class, 'bulkGrant'])
        ->name('security-clearance.bulk-grant');

    // Revoke object access grant (POST)
    Route::post('/admin/security-clearance/revoke-access/{id}', [SecurityClearanceController::class, 'revokeAccess'])
        ->name('security-clearance.revoke-access')
        ->where('id', '[0-9]+');

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
        ->name('security-clearance.classify-store');

    // Declassification
    Route::get('/admin/security-clearance/declassification/{id}', [SecurityClearanceController::class, 'declassification'])
        ->name('security-clearance.declassification')
        ->where('id', '[0-9]+');
    Route::post('/admin/security-clearance/declassify', [SecurityClearanceController::class, 'declassifyStore'])
        ->name('security-clearance.declassify-store');

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
        ->name('security-clearance.watermark-settings-store');

    // Trace watermark
    Route::get('/admin/security-clearance/trace-watermark', [SecurityClearanceController::class, 'traceWatermark'])
        ->name('security-clearance.trace-watermark');
    Route::post('/admin/security-clearance/trace-watermark', [SecurityClearanceController::class, 'traceWatermarkResult'])
        ->name('security-clearance.trace-watermark-result');

    // User clearance management by slug
    Route::get('/admin/security-clearance/user/{slug}', [SecurityClearanceController::class, 'user'])
        ->name('security-clearance.user')
        ->where('slug', '[a-zA-Z0-9_-]+');
    Route::post('/admin/security-clearance/user/{slug}', [SecurityClearanceController::class, 'userUpdate'])
        ->name('security-clearance.user-update')
        ->where('slug', '[a-zA-Z0-9_-]+');

    // Remove 2FA for user (admin)
    Route::post('/admin/security-clearance/remove-2fa/{id}', [SecurityClearanceController::class, 'removeTwoFactor'])
        ->name('security-clearance.remove-2fa')
        ->where('id', '[0-9]+');

    // Access requests (admin review)
    Route::get('/admin/security-clearance/access-requests', [SecurityClearanceController::class, 'accessRequests'])
        ->name('security-clearance.access-requests');
    Route::post('/admin/security-clearance/access-requests/{id}/approve', [SecurityClearanceController::class, 'approveRequest'])
        ->name('security-clearance.approve-request')
        ->where('id', '[0-9]+');
    Route::post('/admin/security-clearance/access-requests/{id}/deny', [SecurityClearanceController::class, 'denyRequest'])
        ->name('security-clearance.deny-request')
        ->where('id', '[0-9]+');
    Route::get('/admin/security-clearance/access-requests/{id}', [SecurityClearanceController::class, 'viewRequest'])
        ->name('security-clearance.view-request')
        ->where('id', '[0-9]+');

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
        ->name('security-clearance.submit-access-request');

    // Access denied
    Route::get('/security-clearance/denied', [SecurityClearanceController::class, 'accessDenied'])
        ->name('security-clearance.access-denied');

    // 2FA routes
    Route::get('/security-clearance/two-factor', [SecurityClearanceController::class, 'twoFactor'])
        ->name('security-clearance.two-factor');
    Route::post('/security-clearance/verify-2fa', [SecurityClearanceController::class, 'verifyTwoFactor'])
        ->name('security-clearance.verify-2fa');
    Route::get('/security-clearance/setup-2fa', [SecurityClearanceController::class, 'setupTwoFactor'])
        ->name('security-clearance.setup-2fa');
    Route::post('/security-clearance/confirm-2fa', [SecurityClearanceController::class, 'confirmTwoFactor'])
        ->name('security-clearance.confirm-2fa');
    Route::post('/security-clearance/send-email-code', [SecurityClearanceController::class, 'sendEmailCode'])
        ->name('security-clearance.send-email-code');
});

// ── Legacy redirect ─────────────────────────────────────────────────────────
Route::get('/security/clearances', fn () => redirect('/admin/security-clearance', 301));
Route::get('/security/dashboard', fn () => redirect('/admin/security-clearance/dashboard', 301));
Route::get('/security/report', fn () => redirect('/admin/security-clearance/report', 301));
Route::get('/security/compartments', fn () => redirect('/admin/security-clearance/compartments', 301));
