<?php

use AhgJobsManage\Controllers\JobController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/jobs', [JobController::class, 'browse'])->name('job.browse');
    Route::get('/admin/jobs/{id}', [JobController::class, 'show'])->name('job.show')->whereNumber('id');
});
