<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/graphql')->middleware(['web'])->group(function () {
    Route::get('/playground', [\AhgLabel\Controllers\LabelController::class, 'playground'])->name('ahggraphql.playground');
});
