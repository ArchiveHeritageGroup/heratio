<?php

use AhgIiifCollection\Controllers\IiifAuthFlow2Controller;
use AhgIiifCollection\Controllers\IiifChangeDiscoveryController;
use AhgIiifCollection\Controllers\IiifCollectionController;
use AhgIiifCollection\Controllers\IiifContentSearchController;
use AhgIiifCollection\Controllers\IiifContentStateController;
use AhgIiifCollection\Controllers\IiifNerAnnotationsController;
use AhgIiifCollection\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/manifest-collections', [IiifCollectionController::class, 'index'])->name('iiif-collection.index');
Route::get('/manifest-collection/{id}/view', [IiifCollectionController::class, 'view'])->name('iiif-collection.view');
Route::get('/manifest-collection/{slug}/manifest.json', [IiifCollectionController::class, 'manifest'])->name('iiif-collection.manifest');
Route::get('/iiif-manifest/{slug}', [IiifCollectionController::class, 'objectManifest'])->name('iiif-collection.object-manifest');

// --- BEGIN issue #694: IIIF Content Search 2.0 ---
// /iiif/{manifestId}/search per the spec lives under /iiif-manifest/{slug}/...
// on this host because nginx routes /iiif/ to Cantaloupe (the Image API proxy).
// The service block in the manifest advertises these URLs so harvesters /
// Mirador discover them from the manifest itself.
Route::get('/iiif-manifest/{slug}/search', [IiifContentSearchController::class, 'search'])
    ->name('iiif-content-search.search');
Route::get('/iiif-manifest/{slug}/autocomplete', [IiifContentSearchController::class, 'autocomplete'])
    ->name('iiif-content-search.autocomplete');
// --- END issue #694 ---

// IIIF Auth endpoints (public, for IIIF Auth API)
Route::get('/iiif-auth/access-service-close', function () { return view('ahg-iiif-collection::iiifAuth.access-service-close'); })->name('iiif-auth.access-service-close');
Route::get('/iiif-auth/access-token-iframe', function (\Illuminate\Http\Request $r) { return view('ahg-iiif-collection::iiifAuth.access-token-iframe', ['tokenData' => $r->input('tokenData', []), 'origin' => $r->input('origin', '*')]); })->name('iiif-auth.access-token-iframe');
Route::get('/iiif-auth/auth-failed', function () { return view('ahg-iiif-collection::iiifAuth.auth-failed'); })->name('iiif-auth.auth-failed');
Route::get('/iiif-auth/auth-success', function () { return view('ahg-iiif-collection::iiifAuth.auth-success'); })->name('iiif-auth.auth-success');
Route::get('/iiif-auth/clickthrough', function () { return view('ahg-iiif-collection::iiifAuth.clickthrough', ['terms' => '', 'acceptUrl' => '']); })->name('iiif-auth.clickthrough');
Route::get('/iiif-auth/logout-success', function () { return view('ahg-iiif-collection::iiifAuth.logout-success'); })->name('iiif-auth.logout-success');

// IIIF viewer/compare/validation (public) — use /iiif-viewer prefix to avoid nginx /iiif/ proxy
Route::get('/iiif-viewer/{slug}', [IiifCollectionController::class, 'viewer'])->name('iiif.viewer');
Route::get('/iiif-compare', [IiifCollectionController::class, 'compare'])->name('iiif.compare');

// --- BEGIN issue #695: IIIF Change Discovery 1.0 ---
// Activity-streams OrderedCollection of manifest lifecycle changes.
// nginx routes /iiif/ to Cantaloupe, but /iiif/discovery/ is anchored
// at this Laravel app via a path-prefix exemption in the nginx config.
Route::get('/iiif/discovery/changes', [IiifChangeDiscoveryController::class, 'changes'])
    ->name('iiif.discovery.changes');
// --- END issue #695 ---

// --- BEGIN issue #697 finishing pass: NER -> IIIF annotation surface ---
// Canvas-scoped AnnotationPage of NER-tagged rows from ahg_iiif_annotation.
// Mounted under /iiif-manifest/ so it's picked up by the slug catch-all
// exemption list in ahg-information-object-manage. odrl:use middleware
// honours the same ODRL access policy as the IO show page so private
// records don't leak entity tags. The {n} parameter must be numeric so
// it doesn't shadow the /iiif-manifest/{slug}/search route.
Route::get('/iiif-manifest/{slug}/canvas/{n}/annotations', [IiifNerAnnotationsController::class, 'canvasAnnotations'])
    ->whereNumber('n')
    ->middleware('odrl:use')
    ->name('iiif.canvas.annotations');

// AI -> Heratio ingestion. API-key auth (X-API-Key, Authorization: Bearer)
// or a logged-in session via the api.auth alias from ahg-api.
Route::post('/api/iiif/annotations/from-ner', [IiifNerAnnotationsController::class, 'ingestFromNer'])
    ->middleware('api.auth')
    ->name('iiif.annotations.from-ner');
// --- END issue #697 finishing pass ---

// --- BEGIN issue #696: IIIF Content State + Auth 2.0 ---
Route::post('/iiif/content-state/encode', [IiifContentStateController::class, 'encode'])
    ->name('iiif.content-state.encode');
Route::get('/iiif/content-state/decode', [IiifContentStateController::class, 'decode'])
    ->name('iiif.content-state.decode');
Route::get('/iiif/auth/2/probe', [IiifAuthFlow2Controller::class, 'probe'])
    ->name('iiif.auth2.probe');
Route::get('/iiif/auth/2/access', [IiifAuthFlow2Controller::class, 'access'])
    ->name('iiif.auth2.access');
Route::get('/iiif/auth/2/token', [IiifAuthFlow2Controller::class, 'token'])
    ->name('iiif.auth2.token');
// --- END issue #696 ---

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::get('/manifest-collection/new', [IiifCollectionController::class, 'create'])->name('iiif-collection.create');
    Route::post('/manifest-collection', [IiifCollectionController::class, 'store'])->name('iiif-collection.store')->middleware('acl:create');
    Route::get('/manifest-collection/{id}/edit', [IiifCollectionController::class, 'edit'])->name('iiif-collection.edit');
    Route::put('/manifest-collection/{id}', [IiifCollectionController::class, 'update'])->name('iiif-collection.update')->middleware('acl:update');
    Route::delete('/manifest-collection/{id}', [IiifCollectionController::class, 'destroy'])->name('iiif-collection.destroy')->middleware('acl:delete');
    Route::match(['get', 'post'], '/manifest-collection/{id}/items/add', [IiifCollectionController::class, 'addItems'])->name('iiif-collection.add-items'); // ACL must be checked in controller (Route::match)
    Route::post('/manifest-collection/remove-item', [IiifCollectionController::class, 'removeItem'])->name('iiif-collection.remove-item');
    Route::post('/manifest-collection/reorder', [IiifCollectionController::class, 'reorder'])->name('iiif-collection.reorder')->middleware('acl:update');
    Route::get('/manifest-collections/autocomplete', [IiifCollectionController::class, 'autocomplete'])->name('iiif-collection.autocomplete');

    // IIIF Settings — canonical URL under /admin/ahgSettings/
    Route::get('/admin/ahgSettings/carousel', [IiifCollectionController::class, 'settings'])->name('iiif.settings');
    Route::post('/admin/ahgSettings/carousel', [IiifCollectionController::class, 'settingsUpdate'])->name('iiif.settings.update')->middleware('acl:update');
    Route::get('/admin/iiif-settings', fn () => redirect('/admin/ahgSettings/carousel')); // legacy redirect

    // IIIF Validation
    Route::get('/admin/iiif-validation', [IiifCollectionController::class, 'validationDashboard'])->name('iiif.validation-dashboard');

    // Media Settings
    Route::get('/admin/iiif-media/queue', [IiifCollectionController::class, 'mediaQueue'])->name('iiif.media-settings.queue');
    Route::get('/admin/iiif-media/test', [IiifCollectionController::class, 'mediaTest'])->name('iiif.media-settings.test');
    Route::post('/admin/iiif-media/test', [IiifCollectionController::class, 'mediaTestRun'])->name('iiif.media-settings.test.run')->middleware('acl:update');

    // 3D Reports
    Route::get('/admin/iiif-3d-reports', [IiifCollectionController::class, 'threeDIndex'])->name('iiif.three-d-reports.index');
    Route::get('/admin/iiif-3d-reports/digital-objects', [IiifCollectionController::class, 'threeDDigitalObjects'])->name('iiif.three-d-reports.digital-objects');
    Route::get('/admin/iiif-3d-reports/hotspots', [IiifCollectionController::class, 'threeDHotspots'])->name('iiif.three-d-reports.hotspots');
    Route::get('/admin/iiif-3d-reports/models', [IiifCollectionController::class, 'threeDModels'])->name('iiif.three-d-reports.models');
    Route::get('/admin/iiif-3d-reports/settings', [IiifCollectionController::class, 'threeDSettings'])->name('iiif.three-d-reports.settings');
    Route::get('/admin/iiif-3d-reports/thumbnails', [IiifCollectionController::class, 'threeDThumbnails'])->name('iiif.three-d-reports.thumbnails');

    // Mirador workspace persistence (issue #699) - admin page
    Route::get('/iiif/workspaces', [WorkspaceController::class, 'adminIndex'])->name('iiif.workspaces.index');

    // Mirador workspace persistence (issue #699) - REST API
    // Session-auth gated; each user only ever sees their own rows.
    Route::prefix('api/iiif/workspace')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('iiif.workspace.api.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('iiif.workspace.api.store');
        Route::get('/{id}', [WorkspaceController::class, 'show'])->whereNumber('id')->name('iiif.workspace.api.show');
        Route::put('/{id}', [WorkspaceController::class, 'update'])->whereNumber('id')->name('iiif.workspace.api.update');
        Route::delete('/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id')->name('iiif.workspace.api.destroy');
        Route::post('/{id}/load', [WorkspaceController::class, 'load'])->whereNumber('id')->name('iiif.workspace.api.load');
    });
});
