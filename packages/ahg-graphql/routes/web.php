<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/graphql')->middleware(['web'])->group(function () {
    Route::get('/playground', [\AhgGraphql\Controllers\GraphqlController::class, 'playground'])->name('ahggraphql.playground');
});
