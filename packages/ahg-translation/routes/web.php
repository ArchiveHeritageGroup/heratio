<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/translation')->middleware(['web', 'auth'])->group(function () {
    Route::get('/settings', [\AhgTranslation\Controllers\TranslationController::class, 'settings'])->name('ahgtranslation.settings');
});
