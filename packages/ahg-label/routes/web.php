<?php

use Illuminate\Support\Facades\Route;

// Legacy URL alias: /admin/label → /admin/label/index
Route::get('/admin/label', fn () => redirect('/admin/label/index', 301));

Route::prefix('admin/label')->middleware(['web', 'auth'])->group(function () {
    Route::get('/index', [\AhgLabel\Controllers\LabelController::class, 'index'])->name('ahglabel.index');
});
