<?php

use AhgPreservation\Controllers\PreservationController;
use AhgPreservation\Controllers\TiffPdfMergeController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/preservation', [PreservationController::class, 'index'])->name('preservation.index');
    Route::get('/admin/preservation/fixity-log', [PreservationController::class, 'fixityLog'])->name('preservation.fixity-log');
    Route::get('/admin/preservation/events', [PreservationController::class, 'events'])->name('preservation.events');
    Route::get('/admin/preservation/formats', [PreservationController::class, 'formats'])->name('preservation.formats');
    Route::get('/admin/preservation/virus-scan', [PreservationController::class, 'virusScan'])->name('preservation.virus-scan');
    Route::get('/admin/preservation/policies', [PreservationController::class, 'policies'])->name('preservation.policies');
    Route::get('/admin/preservation/packages', [PreservationController::class, 'packages'])->name('preservation.packages');
    Route::get('/admin/preservation/package/{id}', [PreservationController::class, 'packageView'])->name('preservation.package-view');
    Route::get('/admin/preservation/package/{id}/edit', [PreservationController::class, 'packageEdit'])->name('preservation.package-edit');
    Route::get('/admin/preservation/scheduler', [PreservationController::class, 'scheduler'])->name('preservation.scheduler');
    Route::get('/admin/preservation/schedule/{id}/edit', [PreservationController::class, 'scheduleEdit'])->name('preservation.schedule-edit');
    Route::get('/admin/preservation/backup', [PreservationController::class, 'backup'])->name('preservation.backup');
    Route::get('/admin/preservation/reports', [PreservationController::class, 'reports'])->name('preservation.reports');
    Route::get('/admin/preservation/conversion', [PreservationController::class, 'conversion'])->name('preservation.conversion');
    Route::get('/admin/preservation/identification', [PreservationController::class, 'identification'])->name('preservation.identification');
    Route::get('/admin/preservation/object/{id}', [PreservationController::class, 'object'])->name('preservation.object');

    Route::post('/admin/preservation/api/checksum/{id}/generate', [PreservationController::class, 'apiGenerateChecksum'])->name('preservation.api.checksum.generate');
    Route::post('/admin/preservation/api/fixity/{id}/verify', [PreservationController::class, 'apiVerifyFixity'])->name('preservation.api.fixity.verify');
    Route::get('/admin/preservation/api/stats', [PreservationController::class, 'apiStats'])->name('preservation.api.stats');

    // TIFF/PDF Merge
    Route::prefix('admin/preservation/tiffpdfmerge')->group(function () {
        Route::get('/', [TiffPdfMergeController::class, 'index'])->name('preservation.tiffpdfmerge.index');
        Route::get('/browse', [TiffPdfMergeController::class, 'browse'])->name('preservation.tiffpdfmerge.browse');
        Route::post('/store', [TiffPdfMergeController::class, 'store'])->name('preservation.tiffpdfmerge.store');
        Route::get('/{id}', [TiffPdfMergeController::class, 'view'])->name('preservation.tiffpdfmerge.view');
    });
});
