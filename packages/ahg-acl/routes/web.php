<?php

use AhgAcl\Controllers\AclController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/acl', [AclController::class, 'groups'])->name('acl.groups');
    Route::match(['get', 'post'], '/admin/acl/group/{id}', [AclController::class, 'editGroup'])->name('acl.edit-group')->where('id', '[0-9]+');
    // Per-entity ACL editor tabs (issue #50)
    Route::match(['get', 'post'], '/admin/acl/group/{id}/information-object-acl', [AclController::class, 'editInformationObjectAcl'])->name('acl.editInformationObjectAcl')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/admin/acl/group/{id}/actor-acl',              [AclController::class, 'editActorAcl'])->name('acl.editActorAcl')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/admin/acl/group/{id}/repository-acl',         [AclController::class, 'editRepositoryAcl'])->name('acl.editRepositoryAcl')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/admin/acl/group/{id}/term-acl',               [AclController::class, 'editTermAcl'])->name('acl.editTermAcl')->where('id', '[0-9]+');
    Route::post('/admin/acl/group/{groupId}/add-member', [AclController::class, 'addMember'])->name('acl.add-member')->where('groupId', '[0-9]+');
    Route::post('/admin/acl/group/{groupId}/remove-member/{userId}', [AclController::class, 'removeMember'])->name('acl.remove-member')->where(['groupId' => '[0-9]+', 'userId' => '[0-9]+']);
    Route::get('/admin/acl/classifications', [AclController::class, 'classifications'])->name('acl.classifications');
    Route::get('/admin/acl/clearances', [AclController::class, 'clearances'])->name('acl.clearances');
    Route::post('/admin/acl/clearances', [AclController::class, 'setClearance'])->name('acl.set-clearance');
    Route::post('/admin/acl/clearances/bulk-grant', [AclController::class, 'bulkGrantClearance'])->name('acl.bulk-grant-clearance');
    Route::get('/admin/acl/access-requests', [AclController::class, 'accessRequests'])->name('acl.access-requests');
    Route::post('/admin/acl/access-requests/{id}/review', [AclController::class, 'reviewRequest'])->name('acl.review-request')->where('id', '[0-9]+');
    Route::get('/admin/acl/audit-log', [AclController::class, 'auditLog'])->name('acl.audit-log');
    Route::get('/admin/acl/approvers', [AclController::class, 'approvers'])->name('acl.approvers');
    Route::post('/admin/acl/approvers/add', [AclController::class, 'addApprover'])->name('acl.add-approver');
    Route::post('/admin/acl/approvers/{id}/remove', [AclController::class, 'removeApprover'])->name('acl.remove-approver')->where('id', '[0-9]+');
    // Dashboard URL alias under /admin/approvers (matches reports dashboard link)
    Route::get('/admin/approvers', [AclController::class, 'approvers'])->name('admin.approvers');
    Route::post('/admin/approvers/add', [AclController::class, 'addApprover'])->name('admin.add-approver');
    Route::post('/admin/approvers/{id}/remove', [AclController::class, 'removeApprover'])->name('admin.remove-approver')->where('id', '[0-9]+');

    // Alias: AtoM DB menu path → Heratio groups page
    // AtoM-legacy URL aliases — same controller as /admin/acl, just under
    // the historical /aclGroup/* path so old bookmarks / sidebar links land
    // somewhere useful instead of the slug catch-all returning a white page.
    Route::get('/aclGroup',         [AclController::class, 'groups']);
    Route::get('/aclGroup/browse',  [AclController::class, 'groups']);
    Route::get('/aclGroup/list',    [AclController::class, 'groups']);   // ← was 200/empty
    Route::get('/aclGroup/index/id/{id}',                    [AclController::class, 'editGroup'])->where('id', '[0-9]+');
    Route::get('/aclGroup/editInformationObjectAcl/id/{id}', [AclController::class, 'editInformationObjectAcl'])->where('id', '[0-9]+');
    Route::get('/aclGroup/editActorAcl/id/{id}',             [AclController::class, 'editActorAcl'])->where('id', '[0-9]+');
    Route::get('/aclGroup/editRepositoryAcl/id/{id}',        [AclController::class, 'editRepositoryAcl'])->where('id', '[0-9]+');
    Route::get('/aclGroup/editTermAcl/id/{id}',              [AclController::class, 'editTermAcl'])->where('id', '[0-9]+');

    // Security audit routes
    Route::get('/admin/acl/security-audit', [AclController::class, 'securityAuditIndex'])->name('acl.security-audit');
    Route::get('/admin/acl/security-audit/dashboard', [AclController::class, 'securityAuditDashboard'])->name('acl.security-audit-dashboard');
    Route::get('/admin/acl/security-audit/object-access', [AclController::class, 'securityAuditObjectAccess'])->name('acl.security-audit-object-access');

    // Security clearance management routes
    Route::get('/admin/acl/security-dashboard', [AclController::class, 'securityDashboard'])->name('acl.security-dashboard');
    Route::get('/admin/acl/security-index', [AclController::class, 'securityIndex'])->name('acl.security-index');
    Route::get('/admin/acl/compartments', [AclController::class, 'compartments'])->name('acl.compartments');
    Route::get('/admin/acl/compartment-access', [AclController::class, 'compartmentAccess'])->name('acl.compartment-access');
    Route::get('/admin/acl/classify/{id}', [AclController::class, 'classify'])->name('acl.classify')->where('id', '[0-9]+');
    Route::post('/admin/acl/classify', [AclController::class, 'classifyStore'])->name('acl.classify-store');
    Route::get('/admin/acl/declassification/{id}', [AclController::class, 'declassification'])->name('acl.declassification')->where('id', '[0-9]+');
    Route::post('/admin/acl/declassify', [AclController::class, 'declassifyStore'])->name('acl.declassify-store');
    Route::get('/admin/acl/security-report', [AclController::class, 'securityReport'])->name('acl.security-report');
    Route::get('/admin/acl/security-compliance', [AclController::class, 'securityCompliance'])->name('acl.security-compliance');
    Route::get('/admin/acl/watermark-settings', [AclController::class, 'watermarkSettings'])->name('acl.watermark-settings');
    Route::post('/admin/acl/watermark-settings', [AclController::class, 'watermarkSettingsStore'])->name('acl.watermark-settings-store');
    Route::get('/admin/acl/trace-watermark', [AclController::class, 'traceWatermark'])->name('acl.trace-watermark');
    Route::post('/admin/acl/trace-watermark', [AclController::class, 'traceWatermarkResult'])->name('acl.trace-watermark-result');
    Route::get('/admin/acl/object/{id}', [AclController::class, 'objectView'])->name('acl.object-view')->where('id', '[0-9]+');
    Route::get('/admin/acl/user/{id}/clearance', [AclController::class, 'userClearance'])->name('acl.user-clearance')->where('id', '[0-9]+');
    Route::get('/admin/acl/user/{id}/security', [AclController::class, 'userSecurity'])->name('acl.user-security')->where('id', '[0-9]+');
    Route::get('/admin/acl/view/{id}', [AclController::class, 'viewClassification'])->name('acl.view-classification')->where('id', '[0-9]+');
    Route::get('/admin/acl/security-audit-trail', [AclController::class, 'securityAudit'])->name('acl.security-audit-trail');
});

// Legacy URL aliases — redirect to real Heratio routes
Route::get('/security/audit', fn () => redirect('/admin/acl/security-audit', 301));

// AtoM-style security routes (authenticated users, not admin-only)
Route::middleware('auth')->group(function () {
    Route::get('/security/my-requests', [AclController::class, 'myRequests'])->name('security.my-requests');
    Route::get('/security/access-requests', [AclController::class, 'pendingRequests'])->name('security.pending-requests');
    Route::get('/security/access-request/{id}', [AclController::class, 'accessRequest'])->name('acl.access-request')->where('id', '[0-9]+');
    Route::post('/security/access-request', [AclController::class, 'submitAccessRequest'])->name('acl.submit-access-request');
    Route::get('/security/denied', [AclController::class, 'accessDenied'])->name('acl.access-denied');
    Route::get('/security/setup-2fa', [AclController::class, 'setupTwoFactor'])->name('acl.setup-2fa');
    Route::post('/security/setup-2fa', [AclController::class, 'setupTwoFactorStore'])->name('acl.setup-2fa-store');
    Route::get('/security/two-factor', [AclController::class, 'twoFactor'])->name('acl.two-factor');
    Route::post('/security/verify-2fa', [AclController::class, 'verifyTwoFactor'])->name('acl.verify-2fa');
    Route::get('/security/review/{id}', [AclController::class, 'reviewAccessRequest'])->name('acl.review-access-request')->where('id', '[0-9]+');
});
