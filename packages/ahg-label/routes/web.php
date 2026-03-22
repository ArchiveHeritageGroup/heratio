<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/label')->middleware(['web'])->group(function () {
    Route::get('/index', [\AhgTranslation\Controllers\TranslationController::class, 'index'])->name('ahglabel.index');
});
