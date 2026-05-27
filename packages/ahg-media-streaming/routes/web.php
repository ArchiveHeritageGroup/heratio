<?php

use AhgMediaStreaming\Controllers\CaptionTrackController;
use AhgMediaStreaming\Controllers\MediaStreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ahg-media-streaming web routes
|--------------------------------------------------------------------------
*/

// Media streaming — public (players use these)
Route::get('/media-streaming/stream/{digitalObjectId}', [MediaStreamController::class, 'stream'])
    ->name('media-streaming.stream');

// Caption track VTT endpoint — public (video players need direct access)
Route::get('/media-streaming/captions/{trackId}', [MediaStreamController::class, 'captionTrack'])
    ->name('media-streaming.captions');

// Admin-capable routes (auth required)
Route::middleware('auth')->group(function () {
    // Caption track CRUD
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}', [CaptionTrackController::class, 'index'])
        ->name('caption-tracks.index');
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}/create', [CaptionTrackController::class, 'create'])
        ->name('caption-tracks.create');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}', [CaptionTrackController::class, 'store'])
        ->name('caption-tracks.store');
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/edit', [CaptionTrackController::class, 'edit'])
        ->name('caption-tracks.edit');
    Route::put('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}', [CaptionTrackController::class, 'update'])
        ->name('caption-tracks.update');
    Route::delete('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}', [CaptionTrackController::class, 'destroy'])
        ->name('caption-tracks.destroy');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/toggle', [CaptionTrackController::class, 'toggleActive'])
        ->name('caption-tracks.toggle-active');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/fetch', [CaptionTrackController::class, 'fetchRemote'])
        ->name('caption-tracks.fetch');
});
