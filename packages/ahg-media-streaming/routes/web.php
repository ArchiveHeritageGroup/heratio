<?php

use AhgMediaStreaming\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

Route::get('/media/stream/{id}', [MediaStreamController::class, 'stream'])
    ->name('media.stream')
    ->whereNumber('id');

Route::get('/media/thumbnail/{id}', [MediaStreamController::class, 'thumbnail'])
    ->name('media.thumbnail')
    ->whereNumber('id');

Route::get('/media/info/{id}', [MediaStreamController::class, 'info'])
    ->name('media.info')
    ->whereNumber('id');
