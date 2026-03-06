<?php

use AhgAuditTrail\Controllers\AuditTrailController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/audit', [AuditTrailController::class, 'browse'])->name('audit.browse');
Route::get('/admin/audit/{id}', [AuditTrailController::class, 'show'])->name('audit.show')->whereNumber('id');
