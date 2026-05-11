<?php

use AhgVersionControl\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('version-control')->group(function () {
    Route::post('/{entity}/{id}/{number}/restore', [VersionController::class, 'restore'])
        ->where(['entity' => 'information_object|actor', 'id' => '[0-9]+', 'number' => '[0-9]+'])
        ->name('version-control.restore');

    Route::get('/{entity}/{id}/diff/{v1}/{v2}', [VersionController::class, 'diff'])
        ->where(['entity' => 'information_object|actor', 'id' => '[0-9]+', 'v1' => '[0-9]+', 'v2' => '[0-9]+'])
        ->name('version-control.diff');

    Route::get('/{entity}/{id}/{number}', [VersionController::class, 'show'])
        ->where(['entity' => 'information_object|actor', 'id' => '[0-9]+', 'number' => '[0-9]+'])
        ->name('version-control.show');

    Route::get('/{entity}/{id}', [VersionController::class, 'list'])
        ->where(['entity' => 'information_object|actor', 'id' => '[0-9]+'])
        ->name('version-control.list');
});
