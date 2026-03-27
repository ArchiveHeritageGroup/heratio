<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/label')->middleware(['web', 'auth'])->group(function () {
    Route::get('/index', [\AhgLabel\Controllers\LabelController::class, 'index'])->name('ahglabel.index');
});
