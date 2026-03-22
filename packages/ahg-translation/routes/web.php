<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/translation')->middleware(['web'])->group(function () {
    Route::get('/settings', [\AhgGis\Controllers\GisController::class, 'settings'])->name('ahgtranslation.settings');
});
