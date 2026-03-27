<?php

use AhgAuditTrail\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/audit', [AuditTrailController::class, 'browse'])->name('audit.browse');
    Route::get('/admin/audit/statistics', [AuditTrailController::class, 'statistics'])->name('audit.statistics');
    Route::match(['get', 'post'], '/admin/audit/settings', [AuditTrailController::class, 'settings'])->name('audit.settings');
    Route::get('/admin/audit/authentication', [AuditTrailController::class, 'authentication'])->name('audit.authentication');
    Route::get('/admin/audit/entity-history/{id}', [AuditTrailController::class, 'entityHistory'])->name('audit.entity-history')->whereNumber('id');
    Route::match(['get','post'], '/admin/audit/export', [AuditTrailController::class, 'export'])->name('audit.export');
    Route::get('/admin/audit/security-access', [AuditTrailController::class, 'securityAccess'])->name('audit.security-access');
    Route::get('/admin/audit/user-activity', [AuditTrailController::class, 'userActivity'])->name('audit.user-activity');
    Route::get('/admin/audit/{id}', [AuditTrailController::class, 'show'])->name('audit.show')->whereNumber('id');
});
