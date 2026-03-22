<?php

use AhgJobsManage\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/jobs/browse', [JobController::class, 'browse']);
});

Route::middleware('admin')->group(function () {
    Route::get('/admin/jobs', [JobController::class, 'browse'])->name('job.browse');
    Route::get('/admin/jobs/{id}', [JobController::class, 'show'])->name('job.show')->whereNumber('id');
    Route::post('/admin/jobs/delete/{id}', [JobController::class, 'destroy'])->name('job.destroy')->whereNumber('id');
    Route::post('/admin/jobs/clear-inactive', [JobController::class, 'clearInactive'])->name('job.clear-inactive');
    Route::get('/admin/jobs/export-csv', [JobController::class, 'exportCsv'])->name('job.export-csv');
});
    Route::get('/admin/jobs/queue-batches', [JobController::class, 'queueBatches'])->name('job.queue-batches');
    Route::get('/admin/jobs/queue-browse', [JobController::class, 'queueBrowse'])->name('job.queue-browse');
    Route::get('/admin/jobs/queue/{id}', [JobController::class, 'queueDetail'])->name('job.queue-detail')->whereNumber('id');
    Route::get('/admin/jobs/report', [JobController::class, 'report'])->name('job.report');
