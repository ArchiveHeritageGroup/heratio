<?php

use AhgMediaStreaming\Controllers\CaptionTrackController;
use AhgMediaStreaming\Controllers\LiveEventController;
use AhgMediaStreaming\Controllers\MediaStreamController;
use AhgMediaStreaming\Controllers\PublishPackageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ahg-media-streaming web routes
|--------------------------------------------------------------------------
*/

// Media streaming - public (players use these)
Route::get('/media-streaming/stream/{digitalObjectId}', [MediaStreamController::class, 'stream'])
    ->name('media-streaming.stream');

// HLS segment key (heratio#1771) - public route, access-checked per request
Route::get('/media-streaming/hls-key/{masterId}/{rendition}', [MediaStreamController::class, 'hlsKey'])
    ->name('media-streaming.hls-key')
    ->whereNumber('masterId')->whereNumber('rendition');

// Caption track VTT endpoint - public (video players need direct access)
Route::get('/media-streaming/captions/{trackId}', [CaptionTrackController::class, 'serve'])
    ->name('media-streaming.captions')
    ->whereNumber('trackId');

// Live events (heratio#1743): the live playlist, its segments and the event
// status - public routes, each request checked against the record's media rules.
Route::get('/media-streaming/live/{eventId}/hls/{file}', [LiveEventController::class, 'hls'])
    ->name('media-streaming.live.hls')->whereNumber('eventId')->where('file', 'index\.m3u8|seg\d{5}\.ts');
Route::get('/media-streaming/live/{eventId}/status', [LiveEventController::class, 'status'])
    ->name('media-streaming.live.status')->whereNumber('eventId');

// Admin-capable routes (auth required)
Route::middleware('auth')->group(function () {
    // Live events (heratio#1743): staff with update permission only - every
    // route, reads too, since the event page shows the stream key.
    Route::middleware('acl:update')->group(function () {
        Route::get('/informationobject/{id}/live/create', [LiveEventController::class, 'create'])
            ->name('media-streaming.live.create')->whereNumber('id');
        Route::post('/informationobject/{id}/live', [LiveEventController::class, 'store'])
            ->name('media-streaming.live.store')->whereNumber('id');
        Route::get('/media-streaming/live/{eventId}', [LiveEventController::class, 'show'])
            ->name('media-streaming.live.show')->whereNumber('eventId');
        Route::post('/media-streaming/live/{eventId}/start', [LiveEventController::class, 'start'])
            ->name('media-streaming.live.start')->whereNumber('eventId');
        Route::post('/media-streaming/live/{eventId}/stop', [LiveEventController::class, 'stop'])
            ->name('media-streaming.live.stop')->whereNumber('eventId');
        Route::get('/admin/live-events', [LiveEventController::class, 'index'])
            ->name('media-streaming.live.index');
    });

    // Release clearance for audio/video (heratio#1771; admin or editor, checked in the controller)
    Route::post('/media-streaming/clearance/{masterId}', [MediaStreamController::class, 'clearance'])
        ->name('media-streaming.clearance')->whereNumber('masterId')->middleware('acl:update');

    // Publish to platforms (heratio#1761): packages for posting by hand, and the
    // distribution log. Staff with update permission only - every route, reads too.
    Route::middleware('acl:update')->group(function () {
        Route::get('/informationobject/{id}/publish', [PublishPackageController::class, 'form'])
            ->name('media-streaming.publish.form')->whereNumber('id');
        Route::post('/informationobject/{id}/publish', [PublishPackageController::class, 'store'])
            ->name('media-streaming.publish.store')->whereNumber('id');
        Route::get('/media-streaming/publish/{packageId}/download', [PublishPackageController::class, 'download'])
            ->name('media-streaming.publish.download')->whereNumber('packageId');
        Route::post('/media-streaming/publish/{packageId}/posted', [PublishPackageController::class, 'posted'])
            ->name('media-streaming.publish.posted')->whereNumber('packageId');
        Route::get('/admin/publish-log', [PublishPackageController::class, 'index'])
            ->name('media-streaming.publish.index');
    });

    // Caption track CRUD
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}', [CaptionTrackController::class, 'index'])
        ->name('caption-tracks.index');
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}/create', [CaptionTrackController::class, 'create'])
        ->name('caption-tracks.create');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}', [CaptionTrackController::class, 'store'])
        ->name('caption-tracks.store')->middleware('admin');
    Route::get('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/edit', [CaptionTrackController::class, 'edit'])
        ->name('caption-tracks.edit');
    Route::put('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}', [CaptionTrackController::class, 'update'])
        ->name('caption-tracks.update')->middleware('admin');
    Route::delete('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}', [CaptionTrackController::class, 'destroy'])
        ->name('caption-tracks.destroy')->middleware('admin');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/toggle', [CaptionTrackController::class, 'toggleActive'])
        ->name('caption-tracks.toggle-active')->middleware('admin');
    Route::post('/media-streaming/caption-tracks/{digitalObjectId}/{trackId}/fetch', [CaptionTrackController::class, 'fetchRemote'])
        ->name('caption-tracks.fetch')->middleware('admin');
});
