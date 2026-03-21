<?php

use AhgReports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// Main dashboard at /reports (matching AtoM URL)
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/index', [ReportController::class, 'dashboard']);
    Route::get('/admin/reports', [ReportController::class, 'dashboard']); // legacy alias
});

Route::middleware('admin')->prefix('admin/reports')->group(function () {
    Route::get('/accessions', [ReportController::class, 'accessions'])->name('reports.accessions');
    Route::get('/descriptions', [ReportController::class, 'descriptions'])->name('reports.descriptions');
    Route::get('/authorities', [ReportController::class, 'authorities'])->name('reports.authorities');
    Route::get('/donors', [ReportController::class, 'donors'])->name('reports.donors');
    Route::get('/repositories', [ReportController::class, 'repositories'])->name('reports.repositories');
    Route::get('/storage', [ReportController::class, 'storage'])->name('reports.storage');
    Route::get('/activity', [ReportController::class, 'activity'])->name('reports.activity');
    Route::get('/recent', [ReportController::class, 'recent'])->name('reports.recent');
    Route::get('/taxonomy', [ReportController::class, 'taxonomy'])->name('reports.taxonomy');
    Route::match(['get', 'post'], '/spatial-analysis', [ReportController::class, 'spatialAnalysis'])->name('reports.spatial');
});
