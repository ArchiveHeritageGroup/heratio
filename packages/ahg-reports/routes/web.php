<?php

use AhgReports\Controllers\AiUsageController;
use AhgReports\Controllers\CatalogueGrowthController;
use AhgReports\Controllers\CollectionsHealthController;
use AhgReports\Controllers\DataQualityController;
use AhgReports\Controllers\NorthStarCockpitController;
use AhgReports\Controllers\PreservationHealthController;
use AhgReports\Controllers\ReportController;
use AhgReports\Controllers\ReportBuilderController;
use AhgReports\Controllers\TrustConsoleController;
use Illuminate\Support\Facades\Route;

// North Star Cockpit - single demo-ready overview of the platform's vision
// capabilities. Admin-gated (same 'admin' middleware as the report admin
// routes below). Read-only; resolves each capability's public link/metric
// defensively at request time.
Route::middleware('admin')->group(function () {
    Route::get('/admin/north-stars', [NorthStarCockpitController::class, 'index'])->name('north-stars.cockpit');
});

// Trust and Transparency Console - a single read-only operator console that
// ties together the scattered trust, preservation, accessibility and open-data
// surfaces shipped across recent releases. This is a HUB: it LINKS to each
// surface (Route::has-gated, no dead links) and re-implements none of them.
// Admin-gated, same as the North Star Cockpit above. The two-segment
// /admin/trust-console path keeps it clear of the single-segment /{slug}
// archival-record catch-all. Read-only; never 500s.
Route::middleware('admin')->group(function () {
    Route::get('/admin/trust-console', [TrustConsoleController::class, 'index'])->name('trust.console');
});

// Collection Data-Quality report - a read-only, archivist-facing dashboard of
// ISAD(G) descriptive completeness across the published catalogue. For each core
// ISAD(G) element it shows how many published records are missing it, plus an
// overall completeness gauge. Admin-gated, same as the consoles above. The
// two-segment /admin/data-quality path keeps it clear of the single-segment
// /{slug} archival-record catch-all. Read-only; bounded aggregate COUNTs only;
// never 500s; empty-state safe on a fresh install.
Route::middleware('admin')->group(function () {
    Route::get('/admin/data-quality', [DataQualityController::class, 'index'])->name('reports.data-quality');
});

// AI Usage transparency report - a read-only aggregate of how much AI has
// assisted the catalogue: total inferences logged, distinct records touched, the
// breakdown by inference type and by model, the human-reviewed share (from the
// override log, framed as accountability), and a per-month over-time trend. Reads
// the ahg_ai_inference + ahg_ai_override provenance logs and writes to neither.
// Admin-gated, same as the consoles above. The two-segment /admin/ai-usage path
// keeps it clear of the single-segment /{slug} archival-record catch-all.
// Read-only; bounded aggregate COUNTs only; never 500s; empty-state safe.
Route::middleware('admin')->group(function () {
    Route::get('/admin/ai-usage', [AiUsageController::class, 'index'])->name('reports.ai-usage');
});

// Catalogue Growth report - a read-only, management-facing view of how the
// catalogue has grown and how it is composed: headline totals (records, published
// vs unpublished, digital objects, actors, repositories), a records-created-per-
// month time series (shown ONLY when a real creation timestamp exists on the
// schema - here object.created_at via Class-Table-Inheritance - and omitted with
// an honest note otherwise; no publication-time signal exists so no published-per-
// month series is fabricated), and the composition by level of description, by
// repository and by digital-surrogate presence. Distinct from the data-quality
// (completeness) and ai-usage reports. Admin-gated, same as the consoles above.
// The two-segment /admin/catalogue-growth path keeps it clear of the single-
// segment /{slug} archival-record catch-all. Read-only; bounded aggregate COUNTs
// only; never 500s; empty-state safe.
Route::middleware('admin')->group(function () {
    Route::get('/admin/catalogue-growth', [CatalogueGrowthController::class, 'index'])->name('reports.catalogue-growth');
});

// Preservation Health report - a read-only, operator-facing view of the
// operational state of the digital collection's integrity. It surfaces what
// needs attention from the canonical preservation stores owned by the
// ahg-preservation package: fixity pass vs fail and objects never checked
// (preservation_fixity_check, latest per object, aligned with ahg-core's
// FixityService); objects flagged with a missing file (preservation_event
// file_missing); format-identification coverage (preservation_object_format
// PUID/format_name); virus-scan posture (preservation_virus_scan); and a small
// recent failures/warnings list (preservation_event outcome failure/warning).
// READ ONLY - no writes, no ALTER. Admin-gated, same as the consoles above. The
// two-segment /admin/preservation-health path keeps it clear of the single-
// segment /{slug} archival-record catch-all. Bounded aggregate COUNTs + one
// LIMITed recent list only; never 500s; empty-state safe.
Route::middleware('admin')->group(function () {
    Route::get('/admin/preservation-health', [PreservationHealthController::class, 'index'])->name('reports.preservation-health');
});

// Main dashboard at /reports (matching AtoM URL)
Route::middleware('auth')->group(function () {
    Route::get('/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
    Route::get('/reports/index', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/checksums-integrity', [ReportController::class, 'checksumsIntegrity'])->name('reports.checksums-integrity');
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

    // Collections health dashboard (cross-collection KPIs) - issue #1215
    Route::get('/collections-health', [CollectionsHealthController::class, 'index'])->name('reports.collections-health');

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
        Route::post('/{id}/schedule', [ReportBuilderController::class, 'scheduleStore'])->name('reports.builder.schedule-store');
        Route::get('/{id}/share', [ReportBuilderController::class, 'share'])->name('reports.builder.share');
        Route::get('/{id}/history', [ReportBuilderController::class, 'history'])->name('reports.builder.history');
        Route::get('/{id}/widget', [ReportBuilderController::class, 'widget'])->name('reports.builder.widget');
        Route::get('/{id}/export/{format}', [ReportBuilderController::class, 'export'])->name('reports.builder.export');
        Route::get('/{id}/clone', [ReportBuilderController::class, 'cloneReport'])->name('reports.builder.clone');
        Route::post('/{id}/delete', [ReportBuilderController::class, 'apiDelete'])->name('reports.builder.delete');
        Route::get('/template/{id}/edit', [ReportBuilderController::class, 'editTemplate'])->name('reports.builder.edit-template');
        Route::get('/template/{id}/preview', [ReportBuilderController::class, 'previewTemplate'])->name('reports.builder.preview-template');
        Route::delete('/template/{id}', [ReportBuilderController::class, 'deleteTemplate'])->name('reports.builder.delete-template');
    });

});

// Public custom report view
Route::middleware('auth')->group(function () {
    Route::get('/reports/custom/{id}', [ReportBuilderController::class, 'view'])->name('reports.custom.view')->where('id', '[0-9]+');
});

// Report Builder API routes
Route::middleware('admin')->group(function () {
    Route::post('/api/report-builder/save', [ReportBuilderController::class, 'apiSave'])->name('reports.api.save');
    Route::post('/api/report-builder/data', [ReportBuilderController::class, 'apiData'])->name('reports.api.data');
    Route::get('/api/report-builder/columns/{source}', [ReportBuilderController::class, 'apiColumns'])->name('reports.api.columns');
    Route::post('/api/report-builder/delete/{id}', [ReportBuilderController::class, 'apiDelete'])->name('reports.api.delete')->where('id', '[0-9]+');
});

// Legacy camelCase aliases
Route::middleware('admin')->group(function () {
    Route::get('/admin/report-builder', fn () => redirect('/admin/reports/builder', 301));
    Route::get('/admin/report-builder/create', fn () => redirect('/admin/reports/builder/create', 301));
    Route::get('/admin/report-builder/{id}/edit', fn ($id) => redirect("/admin/reports/builder/{$id}/edit", 301))->where('id', '[0-9]+');
    Route::get('/admin/report-builder/{id}/preview', fn ($id) => redirect("/admin/reports/builder/{$id}/preview", 301))->where('id', '[0-9]+');
    Route::get('/admin/report-builder/{id}/export/{format}', fn ($id, $format) => redirect("/admin/reports/builder/{$id}/export/{$format}", 301))->where('id', '[0-9]+');
    Route::get('/admin/report-builder/{id}/clone', fn ($id) => redirect("/admin/reports/builder/{$id}/clone", 301))->where('id', '[0-9]+');
    Route::get('/admin/report-builder/archive', fn () => redirect('/admin/reports/builder/archive', 301));
    Route::post('/admin/report-builder/{id}/schedule', [ReportBuilderController::class, 'scheduleStore'])->where('id', '[0-9]+');
    Route::post('/admin/report-builder/{id}/delete', [ReportBuilderController::class, 'destroy'])->where('id', '[0-9]+');
});
