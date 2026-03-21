<?php

use AhgHeritageManage\Controllers\HeritageController;
use Illuminate\Support\Facades\Route;

// Public heritage landing page
Route::get('/heritage', [HeritageController::class, 'landing'])->name('heritage.landing');

// Heritage sub-pages (match AtoM URL structure)
Route::get('/heritage/search', [HeritageController::class, 'search'])->name('heritage.search');
Route::get('/heritage/timeline', [HeritageController::class, 'timeline'])->name('heritage.timeline');
Route::get('/heritage/creators', [HeritageController::class, 'creators'])->name('heritage.creators');
Route::get('/heritage/explore', [HeritageController::class, 'explore'])->name('heritage.explore');
Route::get('/heritage/graph', [HeritageController::class, 'graph'])->name('heritage.graph');
Route::get('/heritage/trending', [HeritageController::class, 'trending'])->name('heritage.trending');
Route::get('/heritage/login', [HeritageController::class, 'login'])->name('heritage.login');

Route::middleware('admin')->group(function () {
    Route::get('/heritage/admin', [HeritageController::class, 'adminDashboard'])->name('heritage.admin');
    Route::get('/heritage/analytics', [HeritageController::class, 'analyticsDashboard'])->name('heritage.analytics');
    Route::get('/heritage/custodian', [HeritageController::class, 'custodianDashboard'])->name('heritage.custodian');
});
