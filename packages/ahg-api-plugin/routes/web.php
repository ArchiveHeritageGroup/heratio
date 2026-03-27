<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/api-plugin')->middleware(['web', 'auth'])->group(function () {
    Route::get('/search-information-objects', [\AhgApiPlugin\Controllers\ApiPluginController::class, 'searchInformationObjects'])->name('ahgapiplugin.search-information-objects');
});
