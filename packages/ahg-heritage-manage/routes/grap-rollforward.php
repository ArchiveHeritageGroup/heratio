<?php

use AhgHeritageManage\Controllers\HeritageRollForwardController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/heritage/reports/roll-forward', [HeritageRollForwardController::class, 'index'])->name('heritage.hreport.roll-forward');
});
