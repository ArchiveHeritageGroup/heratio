<?php

use AhgReports\Controllers\ReportController;
use AhgReports\Controllers\ReportBuilderController;
use Illuminate\Support\Facades\Route;

// Main dashboard at /reports (matching AtoM URL)
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/index', [ReportController::class, 'index'])->name('reports.index');
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

    // Browse & Publish
    Route::get('/browse', [ReportController::class, 'browse'])->name('reports.browse');
    Route::match(['get', 'post'], '/browse-publish', [ReportController::class, 'browsePublish'])->name('reports.browse-publish');

    // Report Select & Generic Report
    Route::get('/select', [ReportController::class, 'reportSelect'])->name('reports.select');
    Route::get('/report', [ReportController::class, 'report'])->name('reports.report');

    // Individual report types (matching AtoM actions)
    Route::get('/report-access', [ReportController::class, 'reportAccess'])->name('reports.report-access');
    Route::get('/report-accession', [ReportController::class, 'reportAccession'])->name('reports.report-accession');
    Route::get('/report-authority-record', [ReportController::class, 'reportAuthorityRecord'])->name('reports.report-authority-record');
    Route::get('/report-donor', [ReportController::class, 'reportDonor'])->name('reports.report-donor');
    Route::get('/report-information-object', [ReportController::class, 'reportInformationObject'])->name('reports.report-information-object');
    Route::get('/report-physical-storage', [ReportController::class, 'reportPhysicalStorage'])->name('reports.report-physical-storage');
    Route::get('/report-repository', [ReportController::class, 'reportRepository'])->name('reports.report-repository');
    Route::get('/report-spatial-analysis', [ReportController::class, 'reportSpatialAnalysis'])->name('reports.report-spatial-analysis');
    Route::get('/report-taxonomy-audit', [ReportController::class, 'reportTaxonomyAudit'])->name('reports.report-taxonomy-audit');
    Route::get('/report-updates', [ReportController::class, 'reportUpdates'])->name('reports.report-updates');
    Route::get('/report-user', [ReportController::class, 'reportUser'])->name('reports.report-user');

    // Audit reports
    Route::get('/audit/actor', [ReportController::class, 'auditActor'])->name('reports.audit.actor');
    Route::get('/audit/description', [ReportController::class, 'auditDescription'])->name('reports.audit.description');
    Route::get('/audit/donor', [ReportController::class, 'auditDonor'])->name('reports.audit.donor');
    Route::get('/audit/permissions', [ReportController::class, 'auditPermissions'])->name('reports.audit.permissions');
    Route::get('/audit/physical-storage', [ReportController::class, 'auditPhysicalStorage'])->name('reports.audit.physical-storage');
    Route::get('/audit/repository', [ReportController::class, 'auditRepository'])->name('reports.audit.repository');
    Route::get('/audit/taxonomy', [ReportController::class, 'auditTaxonomy'])->name('reports.audit.taxonomy');

    // Report Builder
    Route::prefix('builder')->group(function () {
        Route::get('/', [ReportBuilderController::class, 'index'])->name('reports.builder.index');
        Route::get('/create', [ReportBuilderController::class, 'create'])->name('reports.builder.create');
        Route::post('/store', [ReportBuilderController::class, 'store'])->name('reports.builder.store');
        Route::get('/templates', [ReportBuilderController::class, 'templates'])->name('reports.builder.templates');
        Route::get('/archive', [ReportBuilderController::class, 'archive'])->name('reports.builder.archive');
        Route::get('/{id}/preview', [ReportBuilderController::class, 'preview'])->name('reports.builder.preview');
        Route::get('/{id}/edit', [ReportBuilderController::class, 'edit'])->name('reports.builder.edit');
        Route::put('/{id}', [ReportBuilderController::class, 'update'])->name('reports.builder.update');
        Route::get('/{id}/view', [ReportBuilderController::class, 'view'])->name('reports.builder.view');
        Route::get('/{id}/query', [ReportBuilderController::class, 'query'])->name('reports.builder.query');
        Route::get('/{id}/schedule', [ReportBuilderController::class, 'schedule'])->name('reports.builder.schedule');
        Route::get('/{id}/share', [ReportBuilderController::class, 'share'])->name('reports.builder.share');
        Route::get('/{id}/history', [ReportBuilderController::class, 'history'])->name('reports.builder.history');
        Route::get('/{id}/widget', [ReportBuilderController::class, 'widget'])->name('reports.builder.widget');
        Route::get('/template/{id}/edit', [ReportBuilderController::class, 'editTemplate'])->name('reports.builder.edit-template');
        Route::get('/template/{id}/preview', [ReportBuilderController::class, 'previewTemplate'])->name('reports.builder.preview-template');
        Route::delete('/template/{id}', [ReportBuilderController::class, 'deleteTemplate'])->name('reports.builder.delete-template');
    });

});
