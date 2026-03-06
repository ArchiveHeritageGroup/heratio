<?php

use AhgReports\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/reports', [ReportController::class, 'dashboard'])->name('reports.dashboard');
