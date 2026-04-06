<?php

use AhgRecordsManage\Controllers\RetentionController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
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
});
