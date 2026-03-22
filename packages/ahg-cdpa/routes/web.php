<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/cdpa')->middleware(['web'])->group(function () {
    Route::get('/breach-create', [\AhgVendor\Controllers\VendorController::class, 'breachCreate'])->name('ahgcdpa.breach-create');
    Route::get('/breach-view', [\AhgVendor\Controllers\VendorController::class, 'breachView'])->name('ahgcdpa.breach-view');
    Route::get('/breaches', [\AhgVendor\Controllers\VendorController::class, 'breaches'])->name('ahgcdpa.breaches');
    Route::get('/config', [\AhgVendor\Controllers\VendorController::class, 'config'])->name('ahgcdpa.config');
    Route::get('/consent', [\AhgVendor\Controllers\VendorController::class, 'consent'])->name('ahgcdpa.consent');
    Route::get('/dpia-create', [\AhgVendor\Controllers\VendorController::class, 'dpiaCreate'])->name('ahgcdpa.dpia-create');
    Route::get('/dpia', [\AhgVendor\Controllers\VendorController::class, 'dpia'])->name('ahgcdpa.dpia');
    Route::get('/dpia-view', [\AhgVendor\Controllers\VendorController::class, 'dpiaView'])->name('ahgcdpa.dpia-view');
    Route::get('/dpo-edit', [\AhgVendor\Controllers\VendorController::class, 'dpoEdit'])->name('ahgcdpa.dpo-edit');
    Route::get('/dpo', [\AhgVendor\Controllers\VendorController::class, 'dpo'])->name('ahgcdpa.dpo');
    Route::get('/index', [\AhgVendor\Controllers\VendorController::class, 'index'])->name('ahgcdpa.index');
    Route::get('/license-edit', [\AhgVendor\Controllers\VendorController::class, 'licenseEdit'])->name('ahgcdpa.license-edit');
    Route::get('/license', [\AhgVendor\Controllers\VendorController::class, 'license'])->name('ahgcdpa.license');
    Route::get('/processing-create', [\AhgVendor\Controllers\VendorController::class, 'processingCreate'])->name('ahgcdpa.processing-create');
    Route::get('/processing-edit', [\AhgVendor\Controllers\VendorController::class, 'processingEdit'])->name('ahgcdpa.processing-edit');
    Route::get('/processing', [\AhgVendor\Controllers\VendorController::class, 'processing'])->name('ahgcdpa.processing');
    Route::get('/reports', [\AhgVendor\Controllers\VendorController::class, 'reports'])->name('ahgcdpa.reports');
    Route::get('/request-create', [\AhgVendor\Controllers\VendorController::class, 'requestCreate'])->name('ahgcdpa.request-create');
    Route::get('/request-view', [\AhgVendor\Controllers\VendorController::class, 'requestView'])->name('ahgcdpa.request-view');
    Route::get('/requests', [\AhgVendor\Controllers\VendorController::class, 'requests'])->name('ahgcdpa.requests');
});
