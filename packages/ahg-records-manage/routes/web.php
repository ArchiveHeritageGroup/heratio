<?php

use AhgRecordsManage\Controllers\RetentionController;
use AhgRecordsManage\Controllers\DisposalController;
use AhgRecordsManage\Controllers\FilePlanController;
use AhgRecordsManage\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    // Dashboard / landing page (single entry point)
    Route::get('/admin/records', [RetentionController::class, 'index'])->name('records.index');

    // Retention Schedules
    Route::get('/admin/records/schedules', [RetentionController::class, 'schedules'])->name('records.schedules.index');
    Route::get('/admin/records/schedules/create', [RetentionController::class, 'scheduleCreate'])->name('records.schedules.create');
    Route::post('/admin/records/schedules', [RetentionController::class, 'scheduleStore'])->name('records.schedules.store');
    Route::get('/admin/records/schedules/{id}', [RetentionController::class, 'scheduleShow'])->name('records.schedules.show')->where('id', '[0-9]+');
    Route::get('/admin/records/schedules/{id}/edit', [RetentionController::class, 'scheduleEdit'])->name('records.schedules.edit')->where('id', '[0-9]+');
    Route::put('/admin/records/schedules/{id}', [RetentionController::class, 'scheduleUpdate'])->name('records.schedules.update')->where('id', '[0-9]+');
    Route::post('/admin/records/schedules/{id}/approve', [RetentionController::class, 'scheduleApprove'])->name('records.schedules.approve')->where('id', '[0-9]+');

    // Disposal Classes
    Route::get('/admin/records/schedules/{id}/classes/create', [RetentionController::class, 'classCreate'])->name('records.classes.create')->where('id', '[0-9]+');
    Route::post('/admin/records/schedules/{id}/classes', [RetentionController::class, 'classStore'])->name('records.classes.store')->where('id', '[0-9]+');
    Route::get('/admin/records/schedules/{id}/classes/{classId}/edit', [RetentionController::class, 'classEdit'])->name('records.classes.edit')->where(['id' => '[0-9]+', 'classId' => '[0-9]+']);
    Route::put('/admin/records/schedules/{id}/classes/{classId}', [RetentionController::class, 'classUpdate'])->name('records.classes.update')->where(['id' => '[0-9]+', 'classId' => '[0-9]+']);
    Route::delete('/admin/records/schedules/{id}/classes/{classId}', [RetentionController::class, 'classDelete'])->name('records.classes.delete')->where(['id' => '[0-9]+', 'classId' => '[0-9]+']);

    // Record Assignment
    Route::post('/admin/records/assign-class', [RetentionController::class, 'assignClass'])->name('records.assign-class');
    Route::get('/admin/records/record-class/{ioId}', [RetentionController::class, 'recordClass'])->name('records.record-class')->where('ioId', '[0-9]+');

    // Disposal Workflow (P2.2-P2.3)
    Route::get('/admin/records/disposal/queue', [DisposalController::class, 'queue'])->name('records.disposal.queue');
    Route::get('/admin/records/disposal/history', [DisposalController::class, 'history'])->name('records.disposal.history');
    Route::get('/admin/records/disposal/initiate/{ioId}', [DisposalController::class, 'initiate'])->name('records.disposal.initiate')->where('ioId', '[0-9]+');
    Route::post('/admin/records/disposal/initiate', [DisposalController::class, 'initiateStore'])->name('records.disposal.initiate.store');
    Route::get('/admin/records/disposal/{id}', [DisposalController::class, 'show'])->name('records.disposal.show')->where('id', '[0-9]+');
    Route::post('/admin/records/disposal/{id}/recommend', [DisposalController::class, 'recommend'])->name('records.disposal.recommend')->where('id', '[0-9]+');
    Route::post('/admin/records/disposal/{id}/approve', [DisposalController::class, 'approve'])->name('records.disposal.approve')->where('id', '[0-9]+');
    Route::post('/admin/records/disposal/{id}/clear-legal', [DisposalController::class, 'clearLegal'])->name('records.disposal.clearLegal')->where('id', '[0-9]+');
    Route::post('/admin/records/disposal/{id}/reject', [DisposalController::class, 'reject'])->name('records.disposal.reject')->where('id', '[0-9]+');
    Route::post('/admin/records/disposal/{id}/execute', [DisposalController::class, 'execute'])->name('records.disposal.execute')->where('id', '[0-9]+');
    Route::get('/admin/records/disposal/{id}/verify', [DisposalController::class, 'verify'])->name('records.disposal.verify')->where('id', '[0-9]+');

    // File Plan (P2.5)
    Route::get('/admin/records/fileplan', [FilePlanController::class, 'index'])->name('records.fileplan.index');
    Route::get('/admin/records/fileplan/tree', [FilePlanController::class, 'treeJson'])->name('records.fileplan.tree');
    Route::get('/admin/records/fileplan/create', [FilePlanController::class, 'create'])->name('records.fileplan.create');
    Route::post('/admin/records/fileplan', [FilePlanController::class, 'store'])->name('records.fileplan.store');
    Route::get('/admin/records/fileplan/import', [FilePlanController::class, 'importForm'])->name('records.fileplan.import');
    Route::post('/admin/records/fileplan/import/upload', [FilePlanController::class, 'importUpload'])->name('records.fileplan.import.upload');
    Route::post('/admin/records/fileplan/import/map', [FilePlanController::class, 'importMap'])->name('records.fileplan.import.map');
    Route::post('/admin/records/fileplan/import/preview', [FilePlanController::class, 'importPreview'])->name('records.fileplan.import.preview');
    Route::post('/admin/records/fileplan/import/commit', [FilePlanController::class, 'importCommit'])->name('records.fileplan.import.commit');
    Route::get('/admin/records/fileplan/import/{sessionId}', [FilePlanController::class, 'importStatus'])->name('records.fileplan.import.status')->where('sessionId', '[0-9]+');
    Route::post('/admin/records/fileplan/import/{sessionId}/link', [FilePlanController::class, 'linkRecords'])->name('records.fileplan.import.link')->where('sessionId', '[0-9]+');
    Route::get('/admin/records/fileplan/{id}', [FilePlanController::class, 'show'])->name('records.fileplan.show')->where('id', '[0-9]+');
    Route::get('/admin/records/fileplan/{id}/edit', [FilePlanController::class, 'edit'])->name('records.fileplan.edit')->where('id', '[0-9]+');
    Route::put('/admin/records/fileplan/{id}', [FilePlanController::class, 'update'])->name('records.fileplan.update')->where('id', '[0-9]+');
    Route::delete('/admin/records/fileplan/{id}', [FilePlanController::class, 'destroy'])->name('records.fileplan.destroy')->where('id', '[0-9]+');
    Route::post('/admin/records/fileplan/{id}/move', [FilePlanController::class, 'move'])->name('records.fileplan.move')->where('id', '[0-9]+');

    // Review queue (P2.4)
    Route::get('/admin/records/reviews', [ReviewController::class, 'index'])->name('records.reviews.index');
    Route::post('/admin/records/reviews', [ReviewController::class, 'store'])->name('records.reviews.store');
    Route::get('/admin/records/reviews/{id}', [ReviewController::class, 'show'])->name('records.reviews.show')->where('id', '[0-9]+');
    Route::post('/admin/records/reviews/{id}/complete', [ReviewController::class, 'complete'])->name('records.reviews.complete')->where('id', '[0-9]+');
    Route::post('/admin/records/reviews/{id}/assign', [ReviewController::class, 'assign'])->name('records.reviews.assign')->where('id', '[0-9]+');
});
