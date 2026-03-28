<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/graphql')->middleware(['web', 'auth'])->group(function () {
    Route::get('/playground', [\AhgGraphql\Controllers\GraphqlController::class, 'playground'])->name('ahggraphql.playground');
    Route::post('/execute', [\AhgGraphql\Controllers\GraphqlController::class, 'execute'])->name('ahggraphql.execute');
});
