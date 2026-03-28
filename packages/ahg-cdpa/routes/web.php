<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/cdpa')->middleware(['web', 'admin'])->group(function () {
    // Dashboard
    Route::get('/', [\AhgCdpa\Controllers\CdpaController::class, 'index'])->name('ahgcdpa.index');

    // Config (GET + POST)
    Route::match(['get', 'post'], '/config', [\AhgCdpa\Controllers\CdpaController::class, 'config'])->name('ahgcdpa.config');

    // DPO management
    Route::get('/dpo', [\AhgCdpa\Controllers\CdpaController::class, 'dpo'])->name('ahgcdpa.dpo');
    Route::match(['get', 'post'], '/dpo/edit', [\AhgCdpa\Controllers\CdpaController::class, 'dpoEdit'])->name('ahgcdpa.dpo-edit');

    // Processing activities CRUD
    Route::get('/processing', [\AhgCdpa\Controllers\CdpaController::class, 'processing'])->name('ahgcdpa.processing');
    Route::match(['get', 'post'], '/processing/create', [\AhgCdpa\Controllers\CdpaController::class, 'processingCreate'])->name('ahgcdpa.processing-create');
    Route::match(['get', 'post'], '/processing/edit', [\AhgCdpa\Controllers\CdpaController::class, 'processingEdit'])->name('ahgcdpa.processing-edit');

    // Consent records
    Route::get('/consent', [\AhgCdpa\Controllers\CdpaController::class, 'consent'])->name('ahgcdpa.consent');

    // Data subject requests CRUD
    Route::get('/requests', [\AhgCdpa\Controllers\CdpaController::class, 'requests'])->name('ahgcdpa.requests');
    Route::match(['get', 'post'], '/requests/create', [\AhgCdpa\Controllers\CdpaController::class, 'requestCreate'])->name('ahgcdpa.request-create');
    Route::match(['get', 'post'], '/requests/view', [\AhgCdpa\Controllers\CdpaController::class, 'requestView'])->name('ahgcdpa.request-view');

    // DPIA CRUD
    Route::get('/dpia', [\AhgCdpa\Controllers\CdpaController::class, 'dpia'])->name('ahgcdpa.dpia');
    Route::match(['get', 'post'], '/dpia/create', [\AhgCdpa\Controllers\CdpaController::class, 'dpiaCreate'])->name('ahgcdpa.dpia-create');
    Route::match(['get', 'post'], '/dpia/view', [\AhgCdpa\Controllers\CdpaController::class, 'dpiaView'])->name('ahgcdpa.dpia-view');

    // Breach notifications CRUD
    Route::get('/breaches', [\AhgCdpa\Controllers\CdpaController::class, 'breaches'])->name('ahgcdpa.breaches');
    Route::match(['get', 'post'], '/breaches/create', [\AhgCdpa\Controllers\CdpaController::class, 'breachCreate'])->name('ahgcdpa.breach-create');
    Route::match(['get', 'post'], '/breaches/view', [\AhgCdpa\Controllers\CdpaController::class, 'breachView'])->name('ahgcdpa.breach-view');

    // Controller/processor licenses
    Route::get('/license', [\AhgCdpa\Controllers\CdpaController::class, 'license'])->name('ahgcdpa.license');
    Route::match(['get', 'post'], '/license/edit', [\AhgCdpa\Controllers\CdpaController::class, 'licenseEdit'])->name('ahgcdpa.license-edit');

    // Reports
    Route::get('/reports', [\AhgCdpa\Controllers\CdpaController::class, 'reports'])->name('ahgcdpa.reports');
});
