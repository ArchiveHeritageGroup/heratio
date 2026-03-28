<?php

use AhgRightsHolderManage\Controllers\RightsHolderController;
use AhgRightsHolderManage\Controllers\EmbargoController;
use AhgRightsHolderManage\Controllers\ExtendedRightsController;
use AhgRightsHolderManage\Controllers\RightsController;
use AhgRightsHolderManage\Controllers\RightsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/rightsholder/browse', [RightsHolderController::class, 'browse'])->name('rightsholder.browse');

// Redirect hyphenated variant to canonical URL
Route::redirect('/rights-holder/browse', '/rightsholder/browse', 301);

Route::middleware('auth')->group(function () {
    Route::get('/rightsholder/add', [RightsHolderController::class, 'create'])->name('rightsholder.create');
    Route::post('/rightsholder/add', [RightsHolderController::class, 'store'])->name('rightsholder.store')->middleware('acl:create');
    Route::get('/rightsholder/{slug}/edit', [RightsHolderController::class, 'edit'])->name('rightsholder.edit');
    Route::post('/rightsholder/{slug}/edit', [RightsHolderController::class, 'update'])->name('rightsholder.update')->middleware('acl:update');

    // Embargo routes
    Route::get('/embargo', [EmbargoController::class, 'index'])->name('embargo.index');
    Route::get('/embargo/{objectId}/add', [EmbargoController::class, 'create'])->name('embargo.create')->where('objectId', '[0-9]+');
    Route::post('/embargo/{objectId}/add', [EmbargoController::class, 'store'])->name('embargo.store')->middleware('acl:create')->where('objectId', '[0-9]+');
    Route::get('/embargo/{id}', [EmbargoController::class, 'show'])->name('embargo.show')->where('id', '[0-9]+');
    Route::get('/embargo/{id}/lift', [EmbargoController::class, 'liftForm'])->name('embargo.liftForm')->where('id', '[0-9]+');
    Route::post('/embargo/{id}/lift', [EmbargoController::class, 'lift'])->name('embargo.lift')->middleware('acl:update')->where('id', '[0-9]+');

    // Extended Rights routes
    Route::get('/extended-rights', [ExtendedRightsController::class, 'index'])->name('extended-rights.index');
    Route::get('/extended-rights/dashboard', [ExtendedRightsController::class, 'dashboard'])->name('extended-rights.dashboard');
    Route::get('/extended-rights/batch', [ExtendedRightsController::class, 'batch'])->name('extended-rights.batch');
    Route::post('/extended-rights/batch', [ExtendedRightsController::class, 'batchStore'])->name('extended-rights.batch.store')->middleware('acl:create');
    Route::get('/extended-rights/embargoes', [ExtendedRightsController::class, 'embargoes'])->name('extended-rights.embargoes');
    Route::get('/extended-rights/expiring-embargoes', [ExtendedRightsController::class, 'expiringEmbargoes'])->name('extended-rights.expiring-embargoes');
    Route::get('/extended-rights/export', [ExtendedRightsController::class, 'export'])->name('extended-rights.export');
    Route::get('/extended-rights/embargo-status', [ExtendedRightsController::class, 'embargoStatus'])->name('extended-rights.embargo-status');
    Route::get('/extended-rights/embargo-blocked', [ExtendedRightsController::class, 'embargoBlocked'])->name('extended-rights.embargo-blocked');
    Route::get('/extended-rights/{slug}/view', [ExtendedRightsController::class, 'view'])->name('extended-rights.view');
    Route::get('/extended-rights/{slug}/edit', [ExtendedRightsController::class, 'view'])->name('extended-rights.edit');
    Route::get('/extended-rights/{slug}/clear', [ExtendedRightsController::class, 'clear'])->name('extended-rights.clear');
    Route::post('/extended-rights/{slug}/clear', [ExtendedRightsController::class, 'clearStore'])->name('extended-rights.clear.store')->middleware('acl:delete');
    Route::get('/extended-rights/lift-embargo/{id}', [ExtendedRightsController::class, 'liftEmbargo'])->name('extended-rights.lift-embargo')->where('id', '[0-9]+');

    // Rights routes (PREMIS)
    Route::get('/{slug}/rights', [RightsController::class, 'index'])->name('rights.index');
    Route::get('/{slug}/rights/add', [RightsController::class, 'index'])->name('rights.add');
});

Route::middleware('admin')->group(function () {
    Route::get('/rightsholder/{slug}/delete', [RightsHolderController::class, 'confirmDelete'])->name('rightsholder.confirmDelete');
    Route::delete('/rightsholder/{slug}/delete', [RightsHolderController::class, 'destroy'])->name('rightsholder.destroy')->middleware('acl:delete');

    // Rights Admin routes
    Route::get('/rights-admin', [RightsAdminController::class, 'index'])->name('rights-admin.index');
    Route::get('/rights-admin/embargoes', [RightsAdminController::class, 'embargoes'])->name('rights-admin.embargoes');
    Route::get('/rights-admin/embargoes/{id}/edit', [RightsAdminController::class, 'embargoEdit'])->name('rights-admin.embargo-edit')->where('id', '[0-9]+');
    Route::put('/rights-admin/embargoes/{id}', [RightsAdminController::class, 'embargoUpdate'])->name('rights-admin.embargo-update')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/rights-admin/orphan-works', [RightsAdminController::class, 'orphanWorks'])->name('rights-admin.orphan-works');
    Route::get('/rights-admin/orphan-works/{id}/edit', [RightsAdminController::class, 'orphanWorkEdit'])->name('rights-admin.orphan-work-edit')->where('id', '[0-9]+');
    Route::put('/rights-admin/orphan-works/{id}', [RightsAdminController::class, 'orphanWorkUpdate'])->name('rights-admin.orphan-work-update')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/rights-admin/report', [RightsAdminController::class, 'report'])->name('rights-admin.report');
    Route::get('/rights-admin/statements', [RightsAdminController::class, 'statements'])->name('rights-admin.statements');
    Route::get('/rights-admin/tk-labels', [RightsAdminController::class, 'tkLabels'])->name('rights-admin.tk-labels');
});

Route::get('/rightsholder/{slug}', [RightsHolderController::class, 'show'])->name('rightsholder.show');
