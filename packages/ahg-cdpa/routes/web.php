<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/cdpa')->middleware(['web', 'admin'])->group(function () {
    Route::get('/breach-create', [\AhgCdpa\Controllers\CdpaController::class, 'breachCreate'])->name('ahgcdpa.breach-create');
    Route::get('/breach-view', [\AhgCdpa\Controllers\CdpaController::class, 'breachView'])->name('ahgcdpa.breach-view');
    Route::get('/breaches', [\AhgCdpa\Controllers\CdpaController::class, 'breaches'])->name('ahgcdpa.breaches');
    Route::get('/config', [\AhgCdpa\Controllers\CdpaController::class, 'config'])->name('ahgcdpa.config');
    Route::get('/consent', [\AhgCdpa\Controllers\CdpaController::class, 'consent'])->name('ahgcdpa.consent');
    Route::get('/dpia-create', [\AhgCdpa\Controllers\CdpaController::class, 'dpiaCreate'])->name('ahgcdpa.dpia-create');
    Route::get('/dpia', [\AhgCdpa\Controllers\CdpaController::class, 'dpia'])->name('ahgcdpa.dpia');
    Route::get('/dpia-view', [\AhgCdpa\Controllers\CdpaController::class, 'dpiaView'])->name('ahgcdpa.dpia-view');
    Route::get('/dpo-edit', [\AhgCdpa\Controllers\CdpaController::class, 'dpoEdit'])->name('ahgcdpa.dpo-edit');
    Route::get('/dpo', [\AhgCdpa\Controllers\CdpaController::class, 'dpo'])->name('ahgcdpa.dpo');
    Route::get('/index', [\AhgCdpa\Controllers\CdpaController::class, 'index'])->name('ahgcdpa.index');
    Route::get('/license-edit', [\AhgCdpa\Controllers\CdpaController::class, 'licenseEdit'])->name('ahgcdpa.license-edit');
    Route::get('/license', [\AhgCdpa\Controllers\CdpaController::class, 'license'])->name('ahgcdpa.license');
    Route::get('/processing-create', [\AhgCdpa\Controllers\CdpaController::class, 'processingCreate'])->name('ahgcdpa.processing-create');
    Route::get('/processing-edit', [\AhgCdpa\Controllers\CdpaController::class, 'processingEdit'])->name('ahgcdpa.processing-edit');
    Route::get('/processing', [\AhgCdpa\Controllers\CdpaController::class, 'processing'])->name('ahgcdpa.processing');
    Route::get('/reports', [\AhgCdpa\Controllers\CdpaController::class, 'reports'])->name('ahgcdpa.reports');
    Route::get('/request-create', [\AhgCdpa\Controllers\CdpaController::class, 'requestCreate'])->name('ahgcdpa.request-create');
    Route::get('/request-view', [\AhgCdpa\Controllers\CdpaController::class, 'requestView'])->name('ahgcdpa.request-view');
    Route::get('/requests', [\AhgCdpa\Controllers\CdpaController::class, 'requests'])->name('ahgcdpa.requests');
});
