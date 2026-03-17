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
