<?php

use AhgMuseum\Controllers\MuseumController;
use Illuminate\Support\Facades\Route;

Route::get('/museum/browse', [MuseumController::class, 'browse'])->name('museum.browse');

Route::middleware('auth')->group(function () {
    Route::get('/museum/add', [MuseumController::class, 'create'])->name('museum.create');
    Route::post('/museum/store', [MuseumController::class, 'store'])->name('museum.store');
    Route::get('/museum/{slug}/edit', [MuseumController::class, 'edit'])->name('museum.edit')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::put('/museum/{slug}', [MuseumController::class, 'update'])->name('museum.update')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::post('/museum/{slug}/delete', [MuseumController::class, 'destroy'])->name('museum.destroy')->where('slug', '[a-z0-9][a-z0-9-]*');
});

Route::get('/museum/{slug}', [MuseumController::class, 'show'])->name('museum.show')->where('slug', '[a-z0-9][a-z0-9-]*');

// Museum dashboard, reports, and special views
Route::middleware('auth')->group(function () {
    Route::get('/museum/dashboard', [MuseumController::class, 'dashboard'])->name('museum.dashboard');
    Route::get('/museum/reports', [MuseumController::class, 'reports'])->name('museum.reports');
    Route::get('/museum/reports/objects', [MuseumController::class, 'reportObjects'])->name('museum.report-objects');
    Route::get('/museum/reports/creators', [MuseumController::class, 'reportCreators'])->name('museum.report-creators');
    Route::get('/museum/reports/condition', [MuseumController::class, 'reportCondition'])->name('museum.report-condition');
    Route::get('/museum/reports/provenance', [MuseumController::class, 'reportProvenance'])->name('museum.report-provenance');
    Route::get('/museum/reports/style-period', [MuseumController::class, 'reportStylePeriod'])->name('museum.report-style-period');
    Route::get('/museum/reports/materials', [MuseumController::class, 'reportMaterials'])->name('museum.report-materials');
    Route::get('/museum/{slug}/condition-report', [MuseumController::class, 'conditionReport'])->name('museum.condition-report')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/getty-links', [MuseumController::class, 'gettyLinks'])->name('museum.getty-links')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/grap-dashboard', [MuseumController::class, 'grapDashboard'])->name('museum.grap-dashboard')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/loan-dashboard', [MuseumController::class, 'loanDashboard'])->name('museum.loan-dashboard')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/multi-upload', [MuseumController::class, 'multiFileUpload'])->name('museum.multi-upload')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::post('/museum/{slug}/multi-upload', [MuseumController::class, 'multiUploadStore'])->name('museum.multi-upload-store')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/provenance', [MuseumController::class, 'provenance'])->name('museum.provenance')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/{slug}/object-comparison', [MuseumController::class, 'objectComparison'])->name('museum.object-comparison')->where('slug', '[a-z0-9][a-z0-9-]*');
    Route::get('/museum/quality-dashboard', [MuseumController::class, 'qualityDashboard'])->name('museum.quality-dashboard');
    Route::get('/museum/quality-dashboard/missing/{field}', [MuseumController::class, 'missingField'])->name('museum.missing-field');
    Route::get('/museum/cidoc-export', [MuseumController::class, 'cidocExport'])->name('museum.cidoc-export');
    Route::post('/museum/cidoc-export', [MuseumController::class, 'cidocExportDownload'])->name('museum.cidoc-export-download');
    Route::get('/museum/authority/{slug}/link', [MuseumController::class, 'authorityLink'])->name('museum.authority-link');
    Route::post('/museum/authority/{slug}/link', [MuseumController::class, 'authorityLinkStore'])->name('museum.authority-link-store');
    Route::post('/museum/authority/{slug}/unlink', [MuseumController::class, 'authorityUnlink'])->name('museum.authority-unlink');
});
