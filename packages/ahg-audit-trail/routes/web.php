<?php

use AhgAuditTrail\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/audit', [AuditTrailController::class, 'browse'])->name('audit.browse');
Route::get('/admin/audit/statistics', [AuditTrailController::class, 'statistics'])->name('audit.statistics');
Route::match(['get', 'post'], '/admin/audit/settings', [AuditTrailController::class, 'settings'])->name('audit.settings');
Route::get('/admin/audit/{id}', [AuditTrailController::class, 'show'])->name('audit.show')->whereNumber('id');
