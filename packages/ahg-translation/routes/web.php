<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/translation')->middleware(['web', 'auth'])->group(function () {
    Route::get('/settings', [\AhgTranslation\Controllers\TranslationController::class, 'settings'])->name('ahgtranslation.settings');
    Route::post('/settings', [\AhgTranslation\Controllers\TranslationController::class, 'settings']);

    Route::get('/translate/{slug}', [\AhgTranslation\Controllers\TranslationController::class, 'translate'])->name('ahgtranslation.translate');
    Route::post('/translate/{slug}', [\AhgTranslation\Controllers\TranslationController::class, 'store'])->name('ahgtranslation.store');

    Route::post('/apply', [\AhgTranslation\Controllers\TranslationController::class, 'apply'])->name('ahgtranslation.apply');
    Route::post('/save', [\AhgTranslation\Controllers\TranslationController::class, 'save'])->name('ahgtranslation.save');
    Route::get('/health', [\AhgTranslation\Controllers\TranslationController::class, 'health'])->name('ahgtranslation.health');

    Route::get('/languages', [\AhgTranslation\Controllers\TranslationController::class, 'languages'])->name('ahgtranslation.languages');
    Route::post('/languages', [\AhgTranslation\Controllers\TranslationController::class, 'addLanguage'])->name('ahgtranslation.addLanguage');
});
