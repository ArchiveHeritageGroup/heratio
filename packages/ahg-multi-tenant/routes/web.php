<?php

use AhgMultiTenant\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('tenant')->group(function () {
    Route::get('/', [TenantController::class, 'index'])->name('tenant.index');
    Route::match(['get', 'post'], '/create', [TenantController::class, 'create'])->name('tenant.create');
    Route::match(['get', 'post'], '/{id}/edit', [TenantController::class, 'edit'])->name('tenant.edit');
    Route::post('/{id}/delete', [TenantController::class, 'destroy'])->name('tenant.delete');
    Route::get('/super-users', [TenantController::class, 'superUsers'])->name('tenant.superUsers');
    Route::get('/{tenantId}/users', [TenantController::class, 'users'])->name('tenant.users');
    Route::match(['get', 'post'], '/{tenantId}/branding', [TenantController::class, 'branding'])->name('tenant.branding');
});

Route::get('/tenant-error/unknown-domain', [TenantController::class, 'unknownDomain'])->name('tenant.unknownDomain');
Route::get('/tenant-error/unknown-tenant', [TenantController::class, 'unknownTenant'])->name('tenant.unknownTenant');

Route::middleware('auth')->group(function () {
    Route::get('/tenant/switcher', [TenantController::class, 'switcher'])->name('tenant.switcher');
    Route::post('/tenant/switch', [TenantController::class, 'switchTo'])->name('tenant.switch');
});
