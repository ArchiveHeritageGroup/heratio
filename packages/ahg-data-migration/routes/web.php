<?php

use AhgDataMigration\Controllers\DataMigrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/data-migration', [DataMigrationController::class, 'index'])->name('data-migration.index');
    Route::match(['get', 'post'], '/admin/data-migration/upload', [DataMigrationController::class, 'upload'])->name('data-migration.upload');
    Route::get('/admin/data-migration/map', [DataMigrationController::class, 'map'])->name('data-migration.map');
    Route::post('/admin/data-migration/save-mapping', [DataMigrationController::class, 'saveMapping'])->name('data-migration.save-mapping');
    Route::post('/admin/data-migration/delete-mapping/{id}', [DataMigrationController::class, 'deleteMapping'])->name('data-migration.delete-mapping')->whereNumber('id');
    Route::get('/admin/data-migration/preview', [DataMigrationController::class, 'preview'])->name('data-migration.preview');
    Route::post('/admin/data-migration/execute', [DataMigrationController::class, 'execute'])->name('data-migration.execute');
    Route::get('/admin/data-migration/jobs', [DataMigrationController::class, 'jobs'])->name('data-migration.jobs');
    Route::get('/admin/data-migration/job/{id}', [DataMigrationController::class, 'jobStatus'])->name('data-migration.job')->whereNumber('id');
    Route::get('/admin/data-migration/batch-export', [DataMigrationController::class, 'batchExport'])->name('data-migration.batch-export');
    Route::get('/admin/data-migration/import-results', [DataMigrationController::class, 'importResults'])->name('data-migration.import-results');
});
