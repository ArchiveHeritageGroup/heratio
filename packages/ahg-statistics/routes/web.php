<?php

use AhgStatistics\Controllers\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('statistics')->group(function () {
    Route::get('/dashboard', [StatisticsController::class, 'dashboard'])->name('statistics.dashboard');
    Route::get('/views', [StatisticsController::class, 'views'])->name('statistics.views');
    Route::get('/downloads', [StatisticsController::class, 'downloads'])->name('statistics.downloads');
    Route::get('/top-items', [StatisticsController::class, 'topItems'])->name('statistics.topItems');
    Route::get('/geographic', [StatisticsController::class, 'geographic'])->name('statistics.geographic');
    Route::get('/item', [StatisticsController::class, 'item'])->name('statistics.item');
    Route::get('/repository/{id}', [StatisticsController::class, 'repository'])->name('statistics.repository');
    Route::match(['get', 'post'], '/admin', [StatisticsController::class, 'admin'])->name('statistics.admin');
    Route::match(['get', 'post'], '/admin/bots', [StatisticsController::class, 'bots'])->name('statistics.bots');
    Route::get('/export', [StatisticsController::class, 'export'])->name('statistics.export');
});
