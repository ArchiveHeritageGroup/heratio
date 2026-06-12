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
use AhgC2pa\Controllers\AuthenticityReportController;
use AhgC2pa\Controllers\CoverageController;
use AhgC2pa\Controllers\InferenceProvenanceController;
use AhgC2pa\Controllers\ProvenanceController;
use AhgC2pa\Controllers\PreservationTimelineController;
use AhgC2pa\Controllers\TrustDossierController;
use AhgC2pa\Controllers\PublicCheckController;
use AhgC2pa\Controllers\VerifyController;
use AhgC2pa\Controllers\VerifyObjectController;
use AhgC2pa\Controllers\VerifyObjectDownloadController;
use AhgC2pa\Controllers\VerifyRecordTraceController;
use Illuminate\Support\Facades\Route;

// Public per-record INFERENCE-PROVENANCE explorer (issue #1201).
// An honest, read-only view of which AI inferences (descriptions, NER, HTR,
// translations, condition scans, ...) contributed to ONE published record's
// metadata - the model, the gateway, when - and that a human remained
// accountable for the result. It consolidates the existing AI-inference
// provenance foundation (ahg_ai_inference + ahg_ai_override, issue #61 /
// ADR-0002) READ-ONLY via InferenceProvenanceService; it records nothing, runs
// no AI, and re-verifies nothing. A published record with no recorded inference
// degrades to the dignified "no AI inference recorded" state (still HTTP 200);
// an unknown / unpublished record is a clean 404 (HTML + JSON).
//
// All paths are multi-segment (/inference-provenance/...) so the single-segment
// IO slug catch-all (/{slug}) can never intercept them. There is deliberately
// NO bare /inference-provenance route: the explorer needs a record reference.
// The literal '.json' companion is declared BEFORE the {idOrSlug} '.+' matcher
// so it can never be captured as part of a slug. No '.svg' surface here (nginx
// would serve *.svg statically and 404 before Laravel) - JSON only.
Route::prefix('inference-provenance')->group(function () {
    // Machine-readable companion. nginx passes *.json through to Laravel, so
    // it keeps its real extension. Declared before the bare {idOrSlug} page
    // route so the literal '.json' suffix resolves here. Numeric or single-
    // segment slug; a multi-segment slug uses the page route below.
    Route::get('/{idOrSlug}.json', [InferenceProvenanceController::class, 'json'])
        ->name('c2pa.inference.provenance.json')
        ->where('idOrSlug', '[^/]+');

    // The explorer page, addressed by numeric id or (possibly multi-segment)
    // slug. '.+' so /inference-provenance/fonds/series/item resolves to one
    // record. Unknown / unpublished -> 404. Declared LAST so the .json literal
    // wins first.
    Route::get('/{idOrSlug}', [InferenceProvenanceController::class, 'show'])
        ->name('c2pa.inference.provenance')
        ->where('idOrSlug', '.+');
});

// Public per-record PRESERVATION-TIMELINE explorer (issue #1244, building on the
// #1201 provenance epic). An honest, read-only view of the PREMIS-style digital-
// preservation lifecycle of ONE published record's digital objects: ingest,
// fixity checks, format identification, migrations / normalisations, and virus
// scans, in chronological order, each with its outcome and the responsible agent
// or tool. It consolidates the preservation stores owned by the (locked)
// ahg-preservation package READ-ONLY via PreservationTimelineService
// (preservation_event + preservation_fixity_check + preservation_object_format +
// preservation_virus_scan + preservation_format_conversion); it records nothing,
// runs no preservation action, and re-verifies nothing. ahg-preservation remains
// the sole owner / writer of those tables. A published record with no recorded
// preservation event degrades to the dignified "no preservation events recorded
// yet" state (still HTTP 200); an unknown / unpublished record is a clean 404
// (HTML + JSON).
//
// This is DISTINCT from /inference-provenance (the AI-inference layer) and from
// /authenticity (the C2PA content-credentials / signing layer); the page links
// to both for the full trust picture.
//
// All paths are multi-segment (/preservation-timeline/...) so the single-segment
// IO slug catch-all (/{slug}) can never intercept them. There is deliberately NO
// bare /preservation-timeline route: the explorer needs a record reference. The
// literal '.json' companion is declared BEFORE the {idOrSlug} '.+' matcher so it
// can never be captured as part of a slug. No '.svg' surface here (nginx would
// serve *.svg statically and 404 before Laravel) - JSON only.
Route::prefix('preservation-timeline')->group(function () {
    // Machine-readable companion. nginx passes *.json through to Laravel, so it
    // keeps its real extension. Declared before the bare {idOrSlug} page route so
    // the literal '.json' suffix resolves here. Numeric or single-segment slug; a
    // multi-segment slug uses the page route below.
    Route::get('/{idOrSlug}.json', [PreservationTimelineController::class, 'json'])
        ->name('c2pa.preservation.timeline.json')
        ->where('idOrSlug', '[^/]+');

    // The explorer page, addressed by numeric id or (possibly multi-segment)
    // slug. '.+' so /preservation-timeline/fonds/series/item resolves to one
    // record. Unknown / unpublished -> 404. Declared LAST so the .json literal
    // wins first.
    Route::get('/{idOrSlug}', [PreservationTimelineController::class, 'show'])
        ->name('c2pa.preservation.timeline')
        ->where('idOrSlug', '.+');
});

// Public per-record consolidated TRUST DOSSIER (issues #1209 / #1201, next
// slice). The one-stop "defence dossier" for ONE published record: it UNIFIES
// the three per-record trust surfaces - the Authenticity Report (C2PA content
// credentials / signing), the AI Inference Provenance Explorer, and the
// Preservation Timeline (PREMIS lifecycle) - onto one print-friendly page, topped
// by an honest "what can and cannot be verified about this record" statement that
// never overclaims. It consolidates the three existing services READ-ONLY via
// TrustDossierService (AuthenticityReportService + InferenceProvenanceService +
// PreservationTimelineService); it records nothing, signs nothing, runs no AI, runs
// no preservation action, and re-verifies nothing. Each section is guarded so a
// missing / faulting sub-layer degrades only that section, never the page. A
// published record with thin layers shows those layers' dignified empty states
// (still HTTP 200); an unknown / unpublished record is a clean 404 (HTML + JSON).
//
// All paths are multi-segment (/trust-dossier/...) so the single-segment IO slug
// catch-all (/{slug}) can never intercept them. There is deliberately NO bare
// /trust-dossier route: the dossier needs a record reference. The literal '.json'
// companion is declared BEFORE the {idOrSlug} '.+' matcher so it can never be
// captured as part of a slug. No '.svg' surface here (nginx would serve *.svg
// statically and 404 before Laravel) - JSON only.
Route::prefix('trust-dossier')->group(function () {
    // Machine-readable companion. nginx passes *.json through to Laravel, so it
    // keeps its real extension. Declared before the bare {idOrSlug} page route so
    // the literal '.json' suffix resolves here. Numeric or single-segment slug; a
    // multi-segment slug uses the page route below.
    Route::get('/{idOrSlug}.json', [TrustDossierController::class, 'json'])
        ->name('c2pa.trust.dossier.json')
        ->where('idOrSlug', '[^/]+');

    // The dossier page, addressed by numeric id or (possibly multi-segment) slug.
    // '.+' so /trust-dossier/fonds/series/item resolves to one record. Unknown /
    // unpublished -> 404. Declared LAST so the .json literal wins first.
    Route::get('/{idOrSlug}', [TrustDossierController::class, 'show'])
        ->name('c2pa.trust.dossier')
        ->where('idOrSlug', '.+');
});

// Public per-record AUTHENTICITY REPORT surface (issue #1209, north star).
// A single, plain-language report that CONSOLIDATES the verification signals
// that already exist for one published record - content credentials / C2PA
// signing, the whole-record provenance verdict, AI-inference provenance - plus
// an honest "what we can and cannot verify" statement. It reuses the existing
// services read-only (AuthenticityReportService -> ProvenanceTraceService ->
// ProvenanceRecordService); it builds no new verification of its own.
//
// All paths are multi-segment (/authenticity/...) so the single-segment IO
// slug catch-all (/{slug}) can never intercept them. There is deliberately NO
// bare /authenticity route here: the report needs a record reference, and a
// bare single-segment /authenticity would sit in the catch-all's lane. The
// literal '.json' companion and the extensionless 'badge' are declared BEFORE
// the {idOrSlug} '.+' matcher so they can never be captured as part of a slug.
Route::prefix('authenticity')->group(function () {
    // Embeddable record-level trust badge. Extensionless on purpose: nginx
    // serves *.svg as a static file and 404s before Laravel. The response is
    // still image/svg+xml and embeds fine in an <img>. Deeper '/badge' segment
    // declared before the {idOrSlug} '.+' route so 'badge' is never swallowed
    // as a slug fragment. {idOrSlug} pinned to a non-slash run so the badge
    // path stays unambiguous.
    Route::get('/{idOrSlug}/badge', [AuthenticityReportController::class, 'badge'])
        ->name('c2pa.authenticity.report.badge')
        ->where('idOrSlug', '[^/]+');

    // Machine-readable companion. nginx passes *.json through to Laravel, so
    // unlike the SVG badge this keeps its real extension. Declared before the
    // bare {idOrSlug} page route so the literal '.json' suffix resolves here.
    // A purely numeric or single-segment slug reference is supported; a
    // multi-segment slug uses the page route below (its own '.+' matcher).
    Route::get('/{idOrSlug}.json', [AuthenticityReportController::class, 'json'])
        ->name('c2pa.authenticity.report.json')
        ->where('idOrSlug', '[^/]+');

    // The report page itself, addressed by numeric id or (possibly
    // multi-segment) slug. '.+' so /authenticity/fonds/series/item resolves to
    // one record. Unknown / unpublished -> 404. Declared LAST so the badge +
    // .json literals win first.
    Route::get('/{idOrSlug}', [AuthenticityReportController::class, 'show'])
        ->name('c2pa.authenticity.report')
        ->where('idOrSlug', '.+');
});

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

    // Public "check content credentials" file-drop tool (deepens #1209 / #1201):
    // a visitor uploads ANY image - including files that did NOT come from this
    // repository - and gets its C2PA verdict in plain language. No auth, no DB
    // writes, the upload is throwaway. A literal 'check' segment, declared HERE
    // (before the numeric /{digitalObjectId} and the {slug} '.+' matcher below)
    // so it can never be captured as a digital-object id or a record slug. The
    // POST is on the same path; it inherits the 'web' group's CSRF protection.
    Route::get('/check', [PublicCheckController::class, 'form'])
        ->name('c2pa.verify.check');
    Route::post('/check', [PublicCheckController::class, 'check'])
        ->name('c2pa.verify.check.run');

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
    // Authenticity-coverage dashboard (deepens #1201 / #1209). The operator's
    // view of how much of the collection actually carries content credentials:
    // headline coverage %, verified / invalid / unsigned split, and a
    // per-holding-repository gap table. Admin-gated the same way as the
    // provenance admin routes below (the group's 'admin' middleware). Read-only.
    // A literal, multi-segment path so the single-segment IO slug catch-all
    // never intercepts it.
    Route::get('/coverage', [CoverageController::class, 'index'])
        ->name('c2pa.coverage');

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
