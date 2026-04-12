<?php

use AhgExtendedRights\Controllers\RightsAdminController;
use AhgExtendedRights\Controllers\RightsController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Object-level rights routes (authenticated)
// =========================================================================
Route::middleware('auth')->group(function () {
    // View rights for an object
    Route::get('/{slug}/ext-rights', [RightsController::class, 'index'])->name('ext-rights.index');

    // Add new rights record
    Route::get('/{slug}/ext-rights/add', [RightsController::class, 'add'])->name('ext-rights.add');
    Route::post('/{slug}/ext-rights/add', [RightsController::class, 'store'])->name('ext-rights.store')->middleware('acl:create');

    // Edit existing rights record
    Route::get('/{slug}/ext-rights/{id}/edit', [RightsController::class, 'edit'])->name('ext-rights.edit')->where('id', '[0-9]+');
    Route::post('/{slug}/ext-rights/{id}/edit', [RightsController::class, 'store'])->name('ext-rights.update')->where('id', '[0-9]+')->middleware('acl:update');

    // Delete rights record
    Route::post('/{slug}/ext-rights/{id}/delete', [RightsController::class, 'delete'])->name('ext-rights.delete')->where('id', '[0-9]+')->middleware('acl:delete');

    // Embargo per object
    Route::get('/{slug}/ext-rights/embargo', [RightsController::class, 'editEmbargo'])->name('ext-rights.edit-embargo');
    Route::post('/{slug}/ext-rights/embargo', [RightsController::class, 'storeEmbargo'])->name('ext-rights.store-embargo')->middleware('acl:create');
    Route::post('/{slug}/ext-rights/embargo/{id}/release', [RightsController::class, 'releaseEmbargo'])->name('ext-rights.release-embargo')->where('id', '[0-9]+')->middleware('acl:update');

    // TK Labels per object
    Route::get('/{slug}/ext-rights/tk-labels', [RightsController::class, 'tkLabels'])->name('ext-rights.tk-labels');
    Route::post('/{slug}/ext-rights/tk-labels/assign', [RightsController::class, 'assignTkLabel'])->name('ext-rights.assign-tk-label')->middleware('acl:create');

    // Orphan work per object
    Route::get('/{slug}/ext-rights/orphan-work', [RightsController::class, 'orphanWork'])->name('ext-rights.orphan-work');

    // API endpoints
    Route::get('/api/ext-rights/{id}/check', [RightsController::class, 'apiCheck'])->name('ext-rights.api.check')->where('id', '[0-9]+');
    Route::get('/api/ext-rights/{id}/embargo', [RightsController::class, 'apiEmbargo'])->name('ext-rights.api.embargo')->where('id', '[0-9]+');
});

// =========================================================================
// Admin routes (admin middleware)
// =========================================================================
Route::middleware('admin')->prefix('ext-rights-admin')->group(function () {
    // Dashboard
    Route::get('/', [RightsAdminController::class, 'index'])->name('ext-rights-admin.index');

    // Embargoes
    Route::get('/embargoes', [RightsAdminController::class, 'embargoes'])->name('ext-rights-admin.embargoes');
    Route::get('/embargoes/new', [RightsAdminController::class, 'embargoEdit'])->name('ext-rights-admin.embargo-new');
    Route::post('/embargoes/new', [RightsAdminController::class, 'embargoStore'])->name('ext-rights-admin.embargo-create')->middleware('acl:create');
    Route::get('/embargoes/{id}/edit', [RightsAdminController::class, 'embargoEdit'])->name('ext-rights-admin.embargo-edit')->where('id', '[0-9]+');
    Route::post('/embargoes/{id}/edit', [RightsAdminController::class, 'embargoStore'])->name('ext-rights-admin.embargo-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/embargoes/{id}/lift', [RightsAdminController::class, 'embargoLift'])->name('ext-rights-admin.embargo-lift')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/embargoes/{id}/extend', [RightsAdminController::class, 'embargoExtend'])->name('ext-rights-admin.embargo-extend')->where('id', '[0-9]+')->middleware('acl:update');
    Route::get('/embargoes/process-expired', [RightsAdminController::class, 'processExpired'])->name('ext-rights-admin.process-expired');

    // Orphan Works
    Route::get('/orphan-works', [RightsAdminController::class, 'orphanWorks'])->name('ext-rights-admin.orphan-works');
    Route::get('/orphan-works/new', [RightsAdminController::class, 'orphanWorkEdit'])->name('ext-rights-admin.orphan-work-new');
    Route::post('/orphan-works/new', [RightsAdminController::class, 'orphanWorkStore'])->name('ext-rights-admin.orphan-work-create')->middleware('acl:create');
    Route::get('/orphan-works/{id}/edit', [RightsAdminController::class, 'orphanWorkEdit'])->name('ext-rights-admin.orphan-work-edit')->where('id', '[0-9]+');
    Route::post('/orphan-works/{id}/edit', [RightsAdminController::class, 'orphanWorkStore'])->name('ext-rights-admin.orphan-work-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/orphan-works/{id}/search-step', [RightsAdminController::class, 'addSearchStep'])->name('ext-rights-admin.add-search-step')->where('id', '[0-9]+')->middleware('acl:create');
    Route::get('/orphan-works/{id}/complete', [RightsAdminController::class, 'completeOrphanSearch'])->name('ext-rights-admin.complete-orphan-search')->where('id', '[0-9]+');

    // TK Labels
    Route::get('/tk-labels', [RightsAdminController::class, 'tkLabels'])->name('ext-rights-admin.tk-labels');
    Route::post('/tk-labels/assign', [RightsAdminController::class, 'assignTkLabel'])->name('ext-rights-admin.assign-tk-label')->middleware('acl:create');
    Route::get('/tk-labels/remove', [RightsAdminController::class, 'removeTkLabel'])->name('ext-rights-admin.remove-tk-label');

    // Statements & Licenses
    Route::get('/statements', [RightsAdminController::class, 'statements'])->name('ext-rights-admin.statements');

    // Reports
    Route::get('/report', [RightsAdminController::class, 'report'])->name('ext-rights-admin.report');

    // Batch Rights Assignment
    Route::get('/batch', [RightsAdminController::class, 'batch'])->name('ext-rights-admin.batch');
    Route::post('/batch', [RightsAdminController::class, 'batchStore'])->name('ext-rights-admin.batch-store')->middleware('acl:create');

    // Browse Rights
    Route::get('/browse', [RightsAdminController::class, 'browse'])->name('ext-rights-admin.browse');

    // Export Rights
    Route::get('/export', [RightsAdminController::class, 'export'])->name('ext-rights-admin.export');
    Route::get('/export/csv', [RightsAdminController::class, 'exportCsv'])->name('ext-rights-admin.export-csv');
    Route::get('/export/jsonld', [RightsAdminController::class, 'exportJsonld'])->name('ext-rights-admin.export-jsonld');

    // Expiring Embargoes (dashboard "Expiring Soon")
    Route::get('/expiring', [RightsAdminController::class, 'expiringEmbargoes'])->name('ext-rights-admin.expiring');
});

// =========================================================================
// Dashboard URL aliases under /admin/rights/* (matches reports dashboard links)
// =========================================================================
Route::middleware('admin')->prefix('admin/rights')->group(function () {
    Route::get('/', [RightsAdminController::class, 'index']);
    Route::get('/batch', [RightsAdminController::class, 'batch']);
    Route::post('/batch', [RightsAdminController::class, 'batchStore'])->middleware('acl:create');
    Route::get('/browse', [RightsAdminController::class, 'browse']);
    Route::get('/export', [RightsAdminController::class, 'export']);
    Route::get('/export/csv', [RightsAdminController::class, 'exportCsv']);
    Route::get('/export/jsonld', [RightsAdminController::class, 'exportJsonld']);
    Route::get('/embargo', [RightsAdminController::class, 'embargoes']);
    Route::get('/expiring', [RightsAdminController::class, 'expiringEmbargoes']);
    Route::get('/statements', [RightsAdminController::class, 'statements']);
    Route::get('/creative-commons', [RightsAdminController::class, 'statements']);
    Route::get('/tk-labels', [RightsAdminController::class, 'tkLabels']);
});
