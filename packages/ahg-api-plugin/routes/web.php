<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/api-plugin')->middleware(['web'])->group(function () {
    Route::get('/search-information-objects', [\AhgRadManage\Controllers\RadManageController::class, 'searchInformationObjects'])->name('ahgapiplugin.search-information-objects');
});
