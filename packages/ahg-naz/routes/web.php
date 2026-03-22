<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/naz')->middleware(['web'])->group(function () {
    Route::get('/closure-create', [\AhgCdpa\Controllers\CdpaController::class, 'closureCreate'])->name('ahgnaz.closure-create');
    Route::get('/closure-edit', [\AhgCdpa\Controllers\CdpaController::class, 'closureEdit'])->name('ahgnaz.closure-edit');
    Route::get('/closures', [\AhgCdpa\Controllers\CdpaController::class, 'closures'])->name('ahgnaz.closures');
    Route::get('/config', [\AhgCdpa\Controllers\CdpaController::class, 'config'])->name('ahgnaz.config');
    Route::get('/index', [\AhgCdpa\Controllers\CdpaController::class, 'index'])->name('ahgnaz.index');
    Route::get('/permit-create', [\AhgCdpa\Controllers\CdpaController::class, 'permitCreate'])->name('ahgnaz.permit-create');
    Route::get('/permit-view', [\AhgCdpa\Controllers\CdpaController::class, 'permitView'])->name('ahgnaz.permit-view');
    Route::get('/permits', [\AhgCdpa\Controllers\CdpaController::class, 'permits'])->name('ahgnaz.permits');
    Route::get('/protected-records', [\AhgCdpa\Controllers\CdpaController::class, 'protectedRecords'])->name('ahgnaz.protected-records');
    Route::get('/reports', [\AhgCdpa\Controllers\CdpaController::class, 'reports'])->name('ahgnaz.reports');
    Route::get('/researcher-create', [\AhgCdpa\Controllers\CdpaController::class, 'researcherCreate'])->name('ahgnaz.researcher-create');
    Route::get('/researcher-view', [\AhgCdpa\Controllers\CdpaController::class, 'researcherView'])->name('ahgnaz.researcher-view');
    Route::get('/researchers', [\AhgCdpa\Controllers\CdpaController::class, 'researchers'])->name('ahgnaz.researchers');
    Route::get('/schedule-create', [\AhgCdpa\Controllers\CdpaController::class, 'scheduleCreate'])->name('ahgnaz.schedule-create');
    Route::get('/schedule-view', [\AhgCdpa\Controllers\CdpaController::class, 'scheduleView'])->name('ahgnaz.schedule-view');
    Route::get('/schedules', [\AhgCdpa\Controllers\CdpaController::class, 'schedules'])->name('ahgnaz.schedules');
    Route::get('/transfer-create', [\AhgCdpa\Controllers\CdpaController::class, 'transferCreate'])->name('ahgnaz.transfer-create');
    Route::get('/transfer-view', [\AhgCdpa\Controllers\CdpaController::class, 'transferView'])->name('ahgnaz.transfer-view');
    Route::get('/transfers', [\AhgCdpa\Controllers\CdpaController::class, 'transfers'])->name('ahgnaz.transfers');
});
