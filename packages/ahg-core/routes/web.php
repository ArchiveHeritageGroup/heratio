<?php

use AhgCore\Controllers\ClipboardController;
use AhgCore\Controllers\IiifController;
use AhgCore\Controllers\TtsController;
use AhgCore\Controllers\VoiceController;
use Illuminate\Support\Facades\Route;

// Client-side error logging — captures JS errors to Laravel log
Route::post('/api/log-error', function (\Illuminate\Http\Request $request) {
    \Log::warning('[JS Error] '.($request->input('message', 'Unknown JS error')), [
        'url' => $request->input('url', ''),
        'line' => $request->input('line', ''),
        'col' => $request->input('col', ''),
        'stack' => $request->input('stack', ''),
        'ua' => $request->userAgent(),
    ]);

    return response()->json(['logged' => true]);
})->name('api.log-error');

// TTS (Text-to-Speech) API endpoints — AJAX, used by TTS widget
Route::get('/tts/settings', [TtsController::class, 'settings'])->name('tts.settings');
Route::get('/tts/pdfText', [TtsController::class, 'pdfText'])->name('tts.pdfText');
// Legacy AtoM URL aliases (JS widgets may use /index.php/tts/...)
Route::get('/index.php/tts/settings', [TtsController::class, 'settings'])->name('tts.settings.legacy');
Route::get('/index.php/tts/pdfText', [TtsController::class, 'pdfText'])->name('tts.pdfText.legacy');

// Voice settings endpoint — voiceCommands.js fetches this at init.
// Path matches the legacy AtoM URL hard-coded into the bundled JS.
Route::get('/index.php/ahgVoice/getSettings', [VoiceController::class, 'getSettings'])->name('voice.settings');
Route::get('/ahgVoice/getSettings', [VoiceController::class, 'getSettings'])->name('voice.settings.modern');

// IIIF viewer settings endpoint (closes audit issue #81). The master
// layout injects window.AHG_IIIF synchronously so the viewer init can
// read it without a fetch; this endpoint exists for any future
// fetch-based consumer (SPA shells, harvesters, etc.).
//
// Path is /api/iiif-settings rather than /iiif/settings because nginx
// routes the entire /iiif/ prefix to the Cantaloupe image server (see
// /etc/nginx ^~ /iiif/ block) — colliding here would 404 from Java.
Route::get('/api/iiif-settings', [IiifController::class, 'getSettings'])->name('iiif.viewer.settings');

// heratio#1202 - storytelling engine (admin): theme -> AI narrative from catalogue objects
Route::middleware('auth')->group(function () {
    Route::get('/admin/stories', [\AhgCore\Controllers\StorytellingController::class, 'index'])->name('stories.index');
    Route::post('/admin/stories/generate', [\AhgCore\Controllers\StorytellingController::class, 'generateAjax'])->name('stories.generate');
    Route::post('/admin/stories/save', [\AhgCore\Controllers\StorytellingController::class, 'saveAjax'])->name('stories.save');
    Route::get('/admin/stories/search', [\AhgCore\Controllers\StorytellingController::class, 'searchAjax'])->name('stories.search');
    Route::post('/admin/stories/on-this-day', [\AhgCore\Controllers\StorytellingController::class, 'onThisDayAjax'])->name('stories.on-this-day');
});
// Public, shareable story page (two-segment path - safe from the single-segment /{slug} catch-all).
Route::get('/stories/{slug}', [\AhgCore\Controllers\StorytellingController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('stories.show');

// heratio#1183 - point clouds: admin manager + public Potree viewer
Route::middleware('auth')->group(function () {
    Route::get('/admin/pointclouds', [\AhgCore\Controllers\PointCloudController::class, 'index'])->name('pointclouds.index');
    Route::post('/admin/pointclouds', [\AhgCore\Controllers\PointCloudController::class, 'store'])->name('pointclouds.store');
});
Route::get('/pointcloud/{slug}', [\AhgCore\Controllers\PointCloudController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('pointclouds.show');
Route::get('/pointcloud/{slug}/status', [\AhgCore\Controllers\PointCloudController::class, 'status'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('pointclouds.status');

// heratio#1193 - Gaussian splats: admin manager + public photoreal viewer
Route::middleware('auth')->group(function () {
    Route::get('/admin/splats', [\AhgCore\Controllers\GaussianSplatController::class, 'index'])->name('splats.index');
    Route::post('/admin/splats', [\AhgCore\Controllers\GaussianSplatController::class, 'store'])->name('splats.store');
});
Route::get('/splat/{slug}', [\AhgCore\Controllers\GaussianSplatController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('splats.show');

// Clipboard routes
Route::prefix('clipboard')->name('clipboard.')->group(function () {
    Route::match(['get', 'post'], '/', [ClipboardController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/view', [ClipboardController::class, 'index'])->name('view');
    Route::post('/add', [ClipboardController::class, 'add'])->name('add');
    Route::delete('/remove', [ClipboardController::class, 'remove'])->name('remove');
    Route::post('/clear', [ClipboardController::class, 'clear'])->name('clear');
    Route::post('/sync', [ClipboardController::class, 'sync'])->name('sync');
    Route::post('/save', [ClipboardController::class, 'save'])->name('save');
    Route::get('/load', [ClipboardController::class, 'loadForm'])->name('load');
    Route::post('/load', [ClipboardController::class, 'load'])->name('load.post');
    Route::get('/export/csv', [ClipboardController::class, 'exportCsv'])->name('export.csv');
    Route::get('/count', [ClipboardController::class, 'count'])->name('count');
    Route::post('/exportCheck', [ClipboardController::class, 'exportCheck'])->name('exportCheck');
});

// Object import select (auth required). TIFF/PDF merge routes live in ahg-preservation.
Route::middleware('auth')->group(function () {
    Route::get('/object/{slug}/import-select', fn ($slug) => view('ahg-core::object-import-select', ['slug' => $slug]))->name('object.importSelect');
});
