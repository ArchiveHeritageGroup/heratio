<?php

use AhgHeritageManage\Controllers\HeritageController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/heritage/admin', [HeritageController::class, 'adminDashboard'])->name('heritage.admin');
    Route::get('/heritage/analytics', [HeritageController::class, 'analyticsDashboard'])->name('heritage.analytics');
    Route::get('/heritage/custodian', [HeritageController::class, 'custodianDashboard'])->name('heritage.custodian');
});
