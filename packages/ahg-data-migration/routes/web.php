<?php

use AhgDataMigration\Controllers\DataMigrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {

    // ── Main admin UI pages ──────────────────────────────────
    Route::get('/admin/data-migration',
        [DataMigrationController::class, 'index'])->name('data-migration.index');

    Route::match(['get','post'], '/admin/data-migration/import',
        [DataMigrationController::class, 'import'])->name('data-migration.import');

    Route::match(['get','post'], '/admin/data-migration/export',
        [DataMigrationController::class, 'export'])->name('data-migration.export');

    Route::match(['get','post'], '/admin/data-migration/upload',
        [DataMigrationController::class, 'upload'])->name('data-migration.upload');

    Route::get('/admin/data-migration/map',
        [DataMigrationController::class, 'map'])->name('data-migration.map');

    Route::post('/admin/data-migration/save-mapping',
        [DataMigrationController::class, 'saveMapping'])->name('data-migration.save-mapping');

    Route::post('/admin/data-migration/delete-mapping/{id}',
        [DataMigrationController::class, 'deleteMapping'])->name('data-migration.delete-mapping')
        ->whereNumber('id');

    Route::get('/admin/data-migration/preview',
        [DataMigrationController::class, 'preview'])->name('data-migration.preview');

    Route::post('/admin/data-migration/execute',
        [DataMigrationController::class, 'execute'])->name('data-migration.execute');

    Route::get('/admin/data-migration/jobs',
        [DataMigrationController::class, 'jobs'])->name('data-migration.jobs');

    Route::get('/admin/data-migration/job/{id}',
        [DataMigrationController::class, 'jobStatus'])->name('data-migration.job')
        ->whereNumber('id');

    Route::match(['get', 'post'], '/admin/data-migration/batch-export',
        [DataMigrationController::class, 'batchExport'])->name('data-migration.batch-export');

    Route::get('/admin/data-migration/import-results',
        [DataMigrationController::class, 'importResults'])->name('data-migration.import-results');

    Route::get('/admin/data-migration/download',
        [DataMigrationController::class, 'download'])->name('data-migration.download');

    Route::get('/admin/data-migration/mapping',
        [DataMigrationController::class, 'getMapping'])->name('data-migration.get-mapping');

    Route::get('/admin/data-migration/export-mapping/{id}',
        [DataMigrationController::class, 'exportMapping'])->name('data-migration.export-mapping')
        ->whereNumber('id');

    Route::post('/admin/data-migration/import-mapping',
        [DataMigrationController::class, 'importMapping'])->name('data-migration.import-mapping');

    // ── Preservica ───────────────────────────────────────────
    Route::match(['get','post'], '/admin/data-migration/preservica/import',
        [DataMigrationController::class, 'preservicaImport'])->name('data-migration.preservica-import');

    Route::match(['get','post'], '/admin/data-migration/preservica/export',
        [DataMigrationController::class, 'preservicaExport'])->name('data-migration.preservica-export');

    Route::match(['get','post'], '/admin/data-migration/preservica/export/{id}',
        [DataMigrationController::class, 'preservicaExport'])->name('data-migration.preservica-export-id')
        ->whereNumber('id');

    // ── AJAX / legacy camelCase routes (gap inventory matches) ─
    Route::get('/dataMigration/job/progress',
        [DataMigrationController::class, 'jobProgress'])->name('data-migration.job-progress');

    Route::post('/dataMigration/queue',
        [DataMigrationController::class, 'queueJob'])->name('data-migration.queue-job');

    Route::post('/dataMigration/job/cancel',
        [DataMigrationController::class, 'cancelJob'])->name('data-migration.cancel-job');

    Route::get('/dataMigration/exportCsv',
        [DataMigrationController::class, 'exportCsv'])->name('data-migration.export-csv');

    Route::get('/dataMigration/loadMapping',
        [DataMigrationController::class, 'loadMapping'])->name('data-migration.load-mapping');

    Route::post('/dataMigration/previewValidation',
        [DataMigrationController::class, 'previewValidation'])->name('data-migration.preview-validation');

    Route::get('/dataMigration/exportMapping/{id}',
        [DataMigrationController::class, 'exportMapping'])->name('data-migration.export-mapping-legacy')
        ->whereNumber('id');

    Route::post('/dataMigration/importMapping',
        [DataMigrationController::class, 'importMapping'])->name('data-migration.import-mapping-legacy');

    Route::post('/dataMigration/validate',
        [DataMigrationController::class, 'validate'])->name('data-migration.validate');

    Route::post('/dataMigration/executeAhgImport',
        [DataMigrationController::class, 'executeAhgImport'])->name('data-migration.execute-ahg');

    Route::get('/dataMigration/ahgImportResults',
        [DataMigrationController::class, 'ahgImportResults'])->name('data-migration.ahg-results');

    // ── Legacy camelCase aliases for existing routes ─────────
    Route::get('/dataMigration',
        [DataMigrationController::class, 'index'])->name('data-migration.index-legacy');

    Route::get('/dataMigration/jobs',
        [DataMigrationController::class, 'jobs'])->name('data-migration.jobs-legacy');

    Route::get('/dataMigration/job/{id}',
        [DataMigrationController::class, 'jobStatus'])->name('data-migration.job-status-legacy')
        ->whereNumber('id');

    Route::match(['get','post'], '/dataMigration/upload',
        [DataMigrationController::class, 'upload'])->name('data-migration.upload-legacy');

    Route::get('/dataMigration/map',
        [DataMigrationController::class, 'map'])->name('data-migration.map-legacy');

    Route::get('/dataMigration/preview',
        [DataMigrationController::class, 'preview'])->name('data-migration.preview-legacy');

    Route::post('/dataMigration/execute',
        [DataMigrationController::class, 'execute'])->name('data-migration.execute-legacy');

    Route::post('/dataMigration/saveMapping',
        [DataMigrationController::class, 'saveMapping'])->name('data-migration.save-mapping-legacy');

    Route::get('/dataMigration/batchExport',
        [DataMigrationController::class, 'batchExport'])->name('data-migration.batch-export-legacy');

    Route::get('/dataMigration/exportCsvLegacy',
        [DataMigrationController::class, 'exportCsv'])->name('data-migration.export-csv-legacy2');

    Route::match(['get','post'], '/dataMigration/export/{sector?}',
        [DataMigrationController::class, 'export'])->name('data-migration.sector-export');

    Route::get('/admin/data-migration/preview-data', [DataMigrationController::class, 'previewData'])->name('data-migration.preview-data');
});
