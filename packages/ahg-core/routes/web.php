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

// heratio#1205 - capture / at-risk register (admin): the records most in need of digitisation or
// most at risk of loss, ranked by transparent catalogue signals (no master surrogate, poor/fragile
// condition, endangerment flags). The detection-and-triage foundation of the capture network.
// Multi-segment path keeps it clear of the single-segment /{slug} archival-record catch-all.
Route::middleware('auth')->group(function () {
    Route::get('/admin/capture-priority', [\AhgCore\Controllers\CapturePriorityController::class, 'index'])->name('capture-priority.index');

    // heratio#1205 - capture queue workflow (admin): the actionable layer on top of the at-risk
    // register. Operators pull a record into a working queue, track its status (queued -> in
    // progress -> captured / deferred), assign it, note it, and pull it back out. Status values come
    // from the Dropdown Manager group `capture_queue_status` (CaptureQueueService). All writes are
    // confined to ahg_capture_queue; no AtoM base tables are touched. Multi-segment paths keep this
    // clear of the single-segment /{slug} archival-record catch-all.
    Route::get('/admin/capture-priority/queue', [\AhgCore\Controllers\CaptureQueueController::class, 'index'])->name('capture-priority.queue');
    Route::post('/admin/capture-priority/queue/add', [\AhgCore\Controllers\CaptureQueueController::class, 'store'])->name('capture-priority.queue.add');
    Route::post('/admin/capture-priority/queue/status', [\AhgCore\Controllers\CaptureQueueController::class, 'setStatus'])->name('capture-priority.queue.status');
    Route::post('/admin/capture-priority/queue/assign', [\AhgCore\Controllers\CaptureQueueController::class, 'assign'])->name('capture-priority.queue.assign');
    Route::post('/admin/capture-priority/queue/remove', [\AhgCore\Controllers\CaptureQueueController::class, 'remove'])->name('capture-priority.queue.remove');
});
// Public "race against loss" awareness board: a dignified, anonymous, read-only top-N of the records
// most at risk of being lost, drawn from the same CapturePriorityService. /race-against-loss is a
// SINGLE-segment public path (like /explore and /reconstructions). ahg-core boots early, so this is
// registered before the single-segment /{slug} archival-record catch-all in
// ahg-information-object-manage and wins the match (first-registered route wins).
Route::get('/race-against-loss', [\AhgCore\Controllers\CapturePriorityController::class, 'publicBoard'])->name('capture-priority.public');

// heratio#1208 - public "Ask the collection": plain-language question -> answer grounded in the
// institution's own corpus via the KM (knowledge-management RAG) service, with cited sources, and
// honest when the corpus does not cover the question. The collection-wide, anonymous cousin of the
// room-scoped exhibition docent. Multi-segment paths keep this clear of the single-segment /{slug}
// archival-record catch-all (which only ever matches ONE path segment). Public - no auth, one cheap
// KM call per ask.
Route::get('/ask-the-collection', [\AhgCore\Controllers\AskCollectionController::class, 'index'])->name('ask.collection');
Route::match(['get', 'post'], '/ask-the-collection/ask', [\AhgCore\Controllers\AskCollectionController::class, 'ask'])->name('ask.collection.ask');

// heratio#1211 - universal multilingual access (north-star first slice): a SEPARATE public surface
// that lets any visitor read a catalogue record's key metadata translated into their language, on
// demand, grounded in the real record text (never invented), via the SANCTIONED gateway translate
// path (AhgAiServices\Services\LlmService::translate -> https://ai.theahg.co.za/ai/v1). The original
// is always shown + authoritative; the translation is labelled "machine translation" and is never
// written back to the catalogue. Publication status is enforced (drafts 404 for the public).
//
// Both routes are MULTI-SEGMENT so they can never collide with the single-segment /{slug}
// archival-record catch-all in ahg-information-object-manage (that route only ever matches ONE path
// segment via its '[a-z0-9][a-z0-9-]*$' constraint). The POST ajax route is registered BEFORE the
// GET show route so '/record/translate' is never captured as an {idOrSlug} value.
Route::post('/record/translate', [\AhgCore\Controllers\MultilingualController::class, 'translateAjax'])->name('record.translate.ajax');
Route::get('/record/{idOrSlug}/translate', [\AhgCore\Controllers\MultilingualController::class, 'show'])
    ->where('idOrSlug', '[A-Za-z0-9][A-Za-z0-9_-]*')->name('record.translate');

// heratio#1183 - point clouds: admin manager + public Potree viewer
Route::middleware('auth')->group(function () {
    Route::get('/admin/pointclouds', [\AhgCore\Controllers\PointCloudController::class, 'index'])->name('pointclouds.index');
    Route::post('/admin/pointclouds', [\AhgCore\Controllers\PointCloudController::class, 'store'])->name('pointclouds.store');
});
Route::get('/pointcloud/{slug}', [\AhgCore\Controllers\PointCloudController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('pointclouds.show');
Route::get('/pointcloud/{slug}/status', [\AhgCore\Controllers\PointCloudController::class, 'status'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('pointclouds.status');

// heratio#1193 - Gaussian splats are uploaded as normal digital objects (the "Link digital
// object" path) and auto-rendered on the record by InjectSplatViewer. No separate manager.
// /splat/{slug} = legacy standalone viewer (existing ahg_gaussian_splat rows, e.g. the demo).
Route::get('/splat/{slug}', [\AhgCore\Controllers\GaussianSplatController::class, 'show'])
    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('splats.show');
// Render a splat uploaded as a digital object on a record. Two-segment, catch-all-safe.
Route::get('/splat/do/{id}', [\AhgCore\Controllers\GaussianSplatController::class, 'showDigitalObject'])
    ->whereNumber('id')->name('splats.do');

// Public "Explore" hub: one landing page that makes this collection's public
// capabilities discoverable in one place (ask the collection, read a record in
// your language, reconstructions gallery, content credentials, system map, open
// data graph). Each card is gated by Route::has() in the controller, so a card
// only shows when its feature's package is installed - the hub never 500s or
// shows a dead link.
//
// /explore is a SINGLE-segment public path, like /reconstructions and /verify.
// ahg-core boots early, so this route is registered before the single-segment
// /{slug} archival-record catch-all in ahg-information-object-manage and wins the
// match. (First-registered route wins; the IO catch-all's exclusion list is a
// belt-and-braces for late-registered packages, not the mechanism relied on here.)
Route::get('/explore', [\AhgCore\Controllers\ExploreController::class, 'index'])->name('explore.index');

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
