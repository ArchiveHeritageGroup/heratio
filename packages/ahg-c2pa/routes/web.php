<?php
/**
 * Heratio - C2PA provenance / content-credentials routes (issue #1201).
 *
 * Mounted under /admin/c2pa. The IO slug catch-all only matches single-segment
 * paths, so these multi-segment admin routes are never intercepted by it.
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

use AhgC2pa\Controllers\AuthenticityController;
use AhgC2pa\Controllers\ProvenanceController;
use AhgC2pa\Controllers\VerifyController;
use AhgC2pa\Controllers\VerifyObjectController;
use AhgC2pa\Controllers\VerifyObjectDownloadController;
use AhgC2pa\Controllers\VerifyRecordTraceController;
use Illuminate\Support\Facades\Route;

// Public "verify authenticity" trust-anchor surface (issue #1209).
// Multi-segment /verify/... paths so the single-segment IO slug catch-all
// (/{slug}) never intercepts them. No auth: this is the page society turns to
// when "is this real?" matters.
Route::prefix('verify')->group(function () {
    // Public "Authenticity" front door (issue #1209, north star): the bare
    // /verify path - the institution-level dashboard for the content-
    // credentials layer, sitting above the per-record /verify/{slug} pages.
    // Declared FIRST so the bare path is matched here and never falls through
    // to the {slug} '.+' matcher below. /verify is two segments to the locked
    // single-segment IO catch-all (/{slug}), so that never intercepts it.
    Route::get('/', [AuthenticityController::class, 'index'])
        ->name('c2pa.authenticity');

    // Numeric id, namespaced under /verify/id/ so a purely numeric slug can
    // never collide with the id route.
    Route::get('/id/{informationObjectId}', [VerifyController::class, 'byId'])
        ->name('c2pa.verify.id')
        ->where('informationObjectId', '[0-9]+');

    // Per-digital-object content-credentials surface (issue #1209 truth anchor
    // / #1201). A single FILE's authenticity, made human-readable and
    // embeddable so it travels with the object. Declared BEFORE the {slug}
    // '.+' matcher so a purely numeric path resolves to the digital object
    // rather than being treated as a numeric slug. {digitalObjectId} is pinned
    // to [0-9]+ and the badge endpoints are deeper segments, so none of these
    // can shadow the bare /verify landing (declared first, above) or the
    // existing /verify/id/{io} route.
    //
    // Embeddable badge (deeper segments first so the bare /verify/{id} detail
    // route does not capture "badge" as a fragment of the id).
    Route::get('/{digitalObjectId}/badge.json', [VerifyObjectController::class, 'badgeJson'])
        ->name('c2pa.verify.object.badge.json')
        ->where('digitalObjectId', '[0-9]+');

    // Extensionless on purpose: nginx serves *.svg as a static file and 404s
    // before Laravel ever sees it. The response is still image/svg+xml and embeds
    // fine in an <img>. Keeping the .svg name on the route would never resolve.
    Route::get('/{digitalObjectId}/badge', [VerifyObjectController::class, 'badgeSvg'])
        ->name('c2pa.verify.object.badge.svg')
        ->where('digitalObjectId', '[0-9]+');

    // "Download with content credentials" (issue #1201: credentials travel with
    // the object on export). A NEW, additive, parallel download path - it never
    // touches the locked IO/media download route. Streams the digital object's
    // master either as a C2PA-embedded copy (when c2patool + an embeddable
    // format) or as the original plus a sidecar manifest advertised in the
    // headers. Deeper '/download' + '/credentials.c2pa' segments so the bare
    // /verify/{id} detail route never captures them as part of the id. nginx has
    // no static rule for the .c2pa extension, so it passes through to Laravel.
    Route::get('/{digitalObjectId}/download', [VerifyObjectDownloadController::class, 'download'])
        ->name('c2pa.verify.object.download')
        ->where('digitalObjectId', '[0-9]+');

    Route::get('/{digitalObjectId}/credentials.c2pa', [VerifyObjectDownloadController::class, 'credentials'])
        ->name('c2pa.verify.object.credentials')
        ->where('digitalObjectId', '[0-9]+');

    // Provenance-chain detail page for one digital object.
    Route::get('/{digitalObjectId}', [VerifyObjectController::class, 'detail'])
        ->name('c2pa.verify.object')
        ->where('digitalObjectId', '[0-9]+');

    // Record-level provenance TRACE (provenance roadmap trace-endpoint slice,
    // building on #1201 / #1209): aggregate every digital object's provenance
    // on one archival record into a single chronological trace + a whole-record
    // authenticity summary. Multi-segment under /verify/record/{ioId}/... so it
    // can never collide with the per-digital-object /verify/{digitalObjectId}
    // detail page, the bare /verify landing, or the /verify/id/{io} route.
    // {ioId} is pinned to [0-9]+. Declared BEFORE the {slug} '.+' matcher below
    // so /verify/record/... resolves here rather than being treated as a slug.
    //
    // The machine-readable companion (.json) is declared FIRST so the literal
    // 'trace.json' segment is matched here and never captured by the bare
    // 'trace' page route. nginx passes *.json through to Laravel, so unlike the
    // SVG badge this can keep its real extension.
    Route::get('/record/{ioId}/trace.json', [VerifyRecordTraceController::class, 'json'])
        ->name('c2pa.verify.record.trace.json')
        ->where('ioId', '[0-9]+');

    Route::get('/record/{ioId}/trace', [VerifyRecordTraceController::class, 'page'])
        ->name('c2pa.verify.record.trace')
        ->where('ioId', '[0-9]+');

    // Slug, including multi-segment slugs (e.g. /verify/fonds/series/item).
    Route::get('/{slug}', [VerifyController::class, 'bySlug'])
        ->name('c2pa.verify.slug')
        ->where('slug', '.+');
});

Route::middleware('admin')->prefix('admin/c2pa')->group(function () {
    Route::get('/object/{informationObjectId}', [ProvenanceController::class, 'index'])
        ->name('c2pa.provenance.index')
        ->where('informationObjectId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record', [ProvenanceController::class, 'create'])
        ->name('c2pa.provenance.create')
        ->where('informationObjectId', '[0-9]+');

    Route::post('/object/{informationObjectId}/record', [ProvenanceController::class, 'store'])
        ->name('c2pa.provenance.store')
        ->where('informationObjectId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record/{provenanceId}', [ProvenanceController::class, 'show'])
        ->name('c2pa.provenance.show')
        ->where('informationObjectId', '[0-9]+')
        ->where('provenanceId', '[0-9]+');

    Route::get('/object/{informationObjectId}/record/{provenanceId}/manifest.json', [ProvenanceController::class, 'manifestJson'])
        ->name('c2pa.provenance.manifest')
        ->where('informationObjectId', '[0-9]+')
        ->where('provenanceId', '[0-9]+');
});
