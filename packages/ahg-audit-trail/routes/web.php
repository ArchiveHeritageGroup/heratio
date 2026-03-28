<?php

use AhgAuditTrail\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/audit', [AuditTrailController::class, 'browse'])->name('audit.browse');
    Route::get('/admin/audit/statistics', [AuditTrailController::class, 'statistics'])->name('audit.statistics');
    Route::match(['get', 'post'], '/admin/audit/settings', [AuditTrailController::class, 'settings'])->name('audit.settings');
    Route::get('/admin/audit/authentication', [AuditTrailController::class, 'authentication'])->name('audit.authentication');
    Route::match(['get','post'], '/admin/audit/export', [AuditTrailController::class, 'export'])->name('audit.export');
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/audit/entity-history/{id}', [AuditTrailController::class, 'entityHistory'])->name('audit.entity-history')->whereNumber('id');
    Route::get('/admin/audit/security-access', [AuditTrailController::class, 'securityAccess'])->name('audit.security-access');
    Route::get('/admin/audit/user-activity', [AuditTrailController::class, 'userActivity'])->name('audit.user-activity');
    Route::get('/admin/audit/compare/{id}', [AuditTrailController::class, 'compareData'])->name('audit.compare')->whereNumber('id');
    Route::get('/admin/audit/{id}', [AuditTrailController::class, 'show'])->name('audit.show')->whereNumber('id');
});

// ── Legacy URL aliases (ahgAuditTrailPlugin compatibility) ──────────────────
Route::middleware('admin')->group(function () {
    Route::get('/admin/audit/view/{id}', [AuditTrailController::class, 'show'])->name('audit.view-legacy')->whereNumber('id');
    Route::get('/admin/audit/user/{user_id}', [AuditTrailController::class, 'userActivityById'])->name('audit.user-activity-by-id')->whereNumber('user_id');
    Route::get('/admin/audit/entity/{entity_type}/{entity_id}', [AuditTrailController::class, 'entityHistoryByType'])->name('audit.entity-history-by-type')->whereNumber('entity_id');
});
