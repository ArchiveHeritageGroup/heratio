<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/discovery')->middleware(['web'])->group(function () {
    Route::get('/index', [\AhgGraphql\Controllers\GraphqlController::class, 'index'])->name('ahgdiscovery.index');
});
