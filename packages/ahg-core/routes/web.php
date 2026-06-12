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
    // Read-only CSV export of the current queue (respects the active ?status= filter); streamed.
    Route::get('/admin/capture-priority/queue/export', [\AhgCore\Controllers\CaptureQueueController::class, 'export'])->name('capture-priority.queue.export');
    Route::post('/admin/capture-priority/queue/add', [\AhgCore\Controllers\CaptureQueueController::class, 'store'])->name('capture-priority.queue.add');
    Route::post('/admin/capture-priority/queue/status', [\AhgCore\Controllers\CaptureQueueController::class, 'setStatus'])->name('capture-priority.queue.status');
    Route::post('/admin/capture-priority/queue/assign', [\AhgCore\Controllers\CaptureQueueController::class, 'assign'])->name('capture-priority.queue.assign');
    Route::post('/admin/capture-priority/queue/remove', [\AhgCore\Controllers\CaptureQueueController::class, 'remove'])->name('capture-priority.queue.remove');
});
// Metadata completeness / data-quality dashboard (admin): a read-only audit that surfaces PUBLISHED
// archival descriptions missing key descriptive fields (title, scope/abstract, level of description,
// creation date, creator, subjects, digital object / master surrogate) so cataloguers can close the
// gaps. DISTINCT from the capture-priority register above (that is about at-risk physical capture;
// this is about the QUALITY of the metadata already recorded). Read-only, no writes, no AI calls.
// Multi-segment path keeps it clear of the single-segment /{slug} archival-record catch-all.
Route::middleware('auth')->group(function () {
    Route::get('/admin/data-quality', [\AhgCore\Controllers\DataQualityController::class, 'index'])->name('data-quality.index');
});

// heratio#1244 (first slice) - preservation maturity self-assessment (admin): a read-only dashboard
// that scores the running instance, evidence-based, against the five functional areas of the NDSA
// Levels of Digital Preservation (storage, integrity, information security, metadata, file formats).
// Each area gets an achieved level (Not yet, Level 1..4), a short evidence string, and the next gap
// to close. Honest, conservative scoring from concrete records; absence lowers the level, never a 500.
// Read-only - no writes, no ALTER, no AI calls. The two-segment /admin/preservation-maturity path
// keeps it clear of the single-segment /{slug} archival-record catch-all.
Route::middleware('auth')->group(function () {
    Route::get('/admin/preservation-maturity', [\AhgCore\Controllers\PreservationMaturityController::class, 'index'])->name('preservation-maturity.index');
});

// heratio#1244 (fixity slice) - fixity / integrity verification report (admin): a read-only
// dashboard that shows how many digital objects carry a verifiable checksum baseline, how many
// have never been verified, and the result roll-up of the most recent verification sweep
// (match / mismatch / missing_file / ...). The actual verification is done out-of-band by the
// bounded "php artisan ahg:fixity-sweep" command (default 100, hard-capped) + its daily schedule;
// the page never runs a sweep itself. This is the actionable "Integrity" functional area of the
// NDSA Levels of Digital Preservation. Read-only over digital_object - the only writes anywhere
// are append-only rows in the NEW core_fixity_check_log table; no ALTER, no AI calls. The
// two-segment /admin/fixity path keeps it clear of the single-segment /{slug} archival-record
// catch-all (that route only ever matches ONE path segment). A zero-object / missing-table state
// renders a calm empty card, never a 500.
Route::middleware('auth')->group(function () {
    Route::get('/admin/fixity', [\AhgCore\Controllers\FixityController::class, 'index'])->name('fixity.index');
});

// heratio#1211 (accessibility slice) - digital accessibility coverage report (admin): a read-only
// HEURISTIC coverage report (NOT a WCAG conformance audit) over the accessibility-relevant metadata
// Heratio stores - image text descriptions, captions / subtitles and transcripts for audio-visual
// surrogates, 3D-model alternative text, and how much of the catalogue is readable in more than one
// language. Each area gets a coverage level + an honest gap recommendation, with WCAG 2.1 AA success
// criteria cited as an international reference. Where the schema has no place to record a signal
// (e.g. no dedicated image alt-text column) the area is reported as Not measured, never invented.
// Cheap aggregate COUNTs only, every query Schema::hasTable / hasColumn-guarded - read-only over the
// catalogue, no writes, no ALTER, no AI calls. The two-segment /admin/accessibility path keeps it
// clear of the single-segment /{slug} archival-record catch-all (that route only ever matches ONE
// path segment). A zero-content / missing-table state renders a calm empty card, never a 500.
Route::middleware('auth')->group(function () {
    Route::get('/admin/accessibility', [\AhgCore\Controllers\AccessibilityReportController::class, 'index'])->name('accessibility.index');
});

// heratio#1211 (alt-text curation slice) - the actionable companion to the read-only accessibility
// report above. The report surfaced that published image surrogates carry essentially no genuine
// alternative text (the catalogue has no dedicated alt-text column). This admin surface lets
// cataloguers and contributors CURATE real, human-authored alt text into the NEW image_alt_text
// side table: a bounded, paginated worklist of PUBLISHED image digital objects MISSING alt text in
// the working language, an inline add/edit form (POST), and a live coverage figure. Lang-aware
// (international; Afrikaans is a first-class working language). The ONLY write is the upsert in
// store(), confined to image_alt_text; no AtoM base table is written, no ALTER, no AI call (alt text
// is human-authored here). The two-segment /admin/alt-text paths keep this clear of the
// single-segment /{slug} archival-record catch-all (that route only ever matches ONE path segment).
// A zero-content / missing-table state renders a calm empty state, never a 500.
Route::middleware('auth')->group(function () {
    Route::get('/admin/alt-text', [\AhgCore\Controllers\AltTextController::class, 'index'])->name('alt-text.index');
    Route::post('/admin/alt-text/save', [\AhgCore\Controllers\AltTextController::class, 'store'])->name('alt-text.save');
    // OPTIONAL AI assist: a DRAFT alt-text suggestion for one image, via the sanctioned
    // AHG AI gateway vision model (never a node port). Returns JSON; the draft is shown
    // labelled "AI-suggested - review and edit before saving" and is NEVER auto-saved -
    // the curator saves through alt-text.save above. Degrades cleanly (JSON ok:false,
    // HTTP 200) when the gateway is unavailable. Multi-segment path keeps it clear of
    // the single-segment /{slug} archival-record catch-all.
    Route::post('/admin/alt-text/suggest', [\AhgCore\Controllers\AltTextController::class, 'suggest'])->name('alt-text.suggest');
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

// heratio#1211 - reading-language PREFERENCE (deepening universal multilingual access): a visitor
// sets a preferred reading language ONCE and it is remembered (1-year cookie + session) and applied
// to the translate page so they need not re-choose each visit. CSRF-protected POST. Validated against
// the SAME supported set the picker is built from; an unsupported value is ignored/cleared. No DB
// writes. Progressive enhancement: a normal form post (no JS) redirects back with the new ?lang=
// applied; a nonce'd fetch gets JSON. Single safe public path; never collides with the /{slug}
// archival-record catch-all (that route only matches a single segment AND excludes known prefixes).
Route::post('/reading-language', [\AhgCore\Controllers\ReadingLanguageController::class, 'set'])->name('reading-language.set');

// heratio#1211 - universal multilingual access (north-star NEXT slice): a public,
// read-only LANGUAGE-COVERAGE dashboard + an on-demand metadata-translation endpoint.
//
//  - GET /language-coverage shows, per language, how much of the PUBLISHED catalogue
//    (descriptions, authority records, vocabulary terms) can be read in it, as counts
//    + simple CSS bars, framed as a "help us reach more readers" invitation. All
//    figures are cheap aggregate COUNTs from the read-only LanguageCoverageService;
//    a zero total renders a calm empty-state, never a 500.
//  - POST /language-coverage/translate machine-translates ONE published record's key
//    metadata into a target language for DISPLAY ONLY, via the SANCTIONED gateway
//    client (MultilingualRecordService -> AhgAiServices\Services\LlmService::translate
//    -> https://ai.theahg.co.za/ai/v1), labelled "machine translation via the AHG
//    gateway - not an official translation". The original stays authoritative and is
//    never overwritten; drafts 404 for the public.
//
// /language-coverage is a SINGLE-segment public path, like /explore and
// /collection-overview. ahg-core boots early, so it is registered before the
// single-segment /{slug} archival-record catch-all in ahg-information-object-manage
// and wins the match (first-registered route wins). The POST translate route is
// MULTI-SEGMENT, so it can never be captured as a /{slug} value either way.
Route::get('/language-coverage', [\AhgCore\Controllers\LanguageCoverageController::class, 'index'])->name('language-coverage.index');
Route::post('/language-coverage/translate', [\AhgCore\Controllers\LanguageCoverageController::class, 'translate'])->name('language-coverage.translate');

// heratio#1211 - universal multilingual access (north-star NEXT slice): a standalone,
// shareable public "READ THIS RECORD IN YOUR LANGUAGE" page. It shows a PUBLISHED
// record's title + descriptive metadata and a language picker, and PREFERS a real,
// human-authored translation over machine translation:
//
//   - When the catalogue already holds an information_object_i18n row for the chosen
//     culture, that text is shown labelled "official translation" (authoritative, no
//     gateway call).
//   - Otherwise the metadata is translated ON DEMAND via the SANCTIONED AHG AI gateway
//     client (MultilingualRecordService::translate -> AhgAiServices\Services\LlmService
//     ::translate -> https://ai.theahg.co.za/ai/v1, never a node port) and labelled
//     "machine translation via the AHG gateway - not an official translation".
//
// The original is always shown + authoritative; nothing is written back to the
// catalogue; drafts 404 for the public; gateway failure degrades to the original with
// a calm notice (never a 500).
//
// GET  /read/{idOrSlug}            is TWO-segment, so it can never collide with the
//                                  single-segment /{slug} archival-record catch-all in
//                                  ahg-information-object-manage (that route only ever
//                                  matches ONE path segment).
// POST /read/{idOrSlug}/translate  is MULTI-segment (catch-all-safe either way) and is
//                                  registered BEFORE the GET show route so a literal
//                                  '/read/translate'-style path can never be captured as
//                                  an {idOrSlug} value.
Route::post('/read/{idOrSlug}/translate', [\AhgCore\Controllers\ReadInLanguageController::class, 'translate'])
    ->where('idOrSlug', '[A-Za-z0-9][A-Za-z0-9_-]*')->name('read-in-language.translate');
Route::get('/read/{idOrSlug}', [\AhgCore\Controllers\ReadInLanguageController::class, 'show'])
    ->where('idOrSlug', '[A-Za-z0-9][A-Za-z0-9_-]*')->name('read-in-language.show');

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

// Public "Collection at a glance" overview: a positive, visitor-facing snapshot of
// the PUBLISHED collection's size and shape (total descriptions, by level, top
// holding repositories, by century, digital/IIIF/3D coverage, and the actors,
// repositories, subjects and places it connects). All figures come from the
// read-only CollectionOverviewService as cheap aggregate COUNTs; breakdown rows
// deep-link into the GLAM browse with the matching filter only when that route
// exists (Route::has-gated in the controller). Zero records / missing tables render
// a calm "still being catalogued" empty-state, never a 500. This is the welcoming
// outward counterpart to the admin data-quality dashboard (which shows gaps).
//
// /collection-overview is a SINGLE-segment public path, like /explore and
// /open-data. ahg-core boots early, so this route is registered before the
// single-segment /{slug} archival-record catch-all in ahg-information-object-manage
// and wins the match. (First-registered route wins.)
Route::get('/collection-overview', [\AhgCore\Controllers\CollectionOverviewController::class, 'index'])->name('collection.overview');

// Public "Open Data & APIs" hub: one landing page that surfaces every open-data
// endpoint this platform exposes for researchers and developers (the linked-data
// graph, bulk dataset dumps, OAI-PMH, the VoID discovery document, the API
// reference, the content-credentials API, the RiC SPARQL endpoint, ResourceSync).
// Each card is gated by Route::has() in the controller, so a card only shows when
// its feature's package is installed and at least one endpoint resolves - the hub
// never 500s and never shows a dead link.
//
// /open-data is a SINGLE-segment public path, like /explore and /reconstructions.
// ahg-core boots early, so this route is registered before the single-segment
// /{slug} archival-record catch-all in ahg-information-object-manage and wins the
// match. (First-registered route wins.)
Route::get('/open-data', [\AhgCore\Controllers\OpenDataController::class, 'index'])->name('open-data.index');

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
