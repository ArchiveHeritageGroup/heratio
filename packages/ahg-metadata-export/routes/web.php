<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/metadata-export')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/bulk', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'bulk'])->name('ahgmetadataexport.bulk');
    Route::get('/index', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'index'])->name('ahgmetadataexport.index');
    Route::get('/preview', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'preview'])->name('ahgmetadataexport.preview');

    // #662 Phase 3 per-standard XML download (dcterms / mods / rad / dacs).
    Route::get('/download/{format}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadStandard'])
        ->whereIn('format', ['dcterms', 'mods', 'rad', 'dacs'])
        ->name('ahgmetadataexport.download');

    // #1197 CIDOC-CRM (ISO 21127) RDF download. ?io=NNN required; serialisation
    // is Turtle by default, RDF/XML when ?rdf=rdf. A .ttl / .rdf path variant
    // selects the format from the extension. Lives under /admin/metadata-export
    // so the IO slug catch-all in ahg-information-object-manage cannot
    // intercept it.
    Route::get('/cidoc-crm', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrm'])
        ->name('ahgmetadataexport.cidoc');
    Route::get('/cidoc-crm.{ext}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrm'])
        ->whereIn('ext', ['ttl', 'rdf'])
        ->name('ahgmetadataexport.cidoc.ext');

    // #1425 A2 RiC-O (Records in Contexts) RDF download, same shape as CIDOC-CRM.
    // ?io=NNN required; Turtle by default, RDF/XML via ?rdf=rdf or the .rdf ext.
    Route::get('/ric', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadRic'])
        ->name('ahgmetadataexport.ric');
    Route::get('/ric.{ext}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadRic'])
        ->whereIn('ext', ['ttl', 'rdf'])
        ->name('ahgmetadataexport.ric.ext');

    // #1197 CIDOC-CRM RDF download for an ACTOR. ?actor=NNN required. Same
    // format negotiation as the record export (Turtle default, RDF/XML via
    // ?rdf=rdf or a .rdf extension). Under /admin/metadata-export so the IO
    // slug catch-all cannot intercept it.
    Route::get('/cidoc-crm-actor', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrmActor'])
        ->name('ahgmetadataexport.cidoc.actor');
    Route::get('/cidoc-crm-actor.{ext}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrmActor'])
        ->whereIn('ext', ['ttl', 'rdf'])
        ->name('ahgmetadataexport.cidoc.actor.ext');

    // #1197 CIDOC-CRM RDF download for a TERM / PLACE. ?term=NNN required.
    // Place taxonomy serialises as E53 Place, every other taxonomy as E55 Type.
    Route::get('/cidoc-crm-term', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrmTerm'])
        ->name('ahgmetadataexport.cidoc.term');
    Route::get('/cidoc-crm-term.{ext}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadCidocCrmTerm'])
        ->whereIn('ext', ['ttl', 'rdf'])
        ->name('ahgmetadataexport.cidoc.term.ext');

    // #1197 / #1244 / #1243 PREMIS 3.0 preservation-metadata download.
    // ?io=NNN required; optional ?culture=. Emits premis:object (fixity /
    // size / format / originalName) per digital_object, premis:event rows for
    // recorded preservation events, premis:agent rows for the responsible
    // systems. A .xml path variant selects the same handler. Under
    // /admin/metadata-export so the IO slug catch-all in
    // ahg-information-object-manage cannot intercept it.
    Route::get('/premis', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadPremis'])
        ->name('ahgmetadataexport.premis');
    Route::get('/premis.{ext}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'downloadPremis'])
        ->whereIn('ext', ['xml'])
        ->name('ahgmetadataexport.premis.ext');

    // #662 Phase 3 RAD / DACS XML importer. POST with `xml_file` upload or
    // `xml` body field. dryRun=1 (default) returns preview JSON; dryRun=0
    // (or commit=1) persists into ahg_io_rad / ahg_io_dacs.
    Route::post('/import/{format}', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'importStandard'])
        ->whereIn('format', ['rad', 'dacs'])
        ->name('ahgmetadataexport.import-standard');
});

// SPARQL 1.1 query endpoint over the PROV-O graph for a single
// information object. Phase 4 of #658. Auth is checked inside the
// controller (session OR Bearer token from ahg_setting.sparql_bearer_token)
// so the route is registered with the `web` middleware group only -
// dropping `auth` so external Linked Data clients can hit it with a
// Bearer token without first acquiring a session cookie.
//
// Path lives under /admin/ to stay clear of the IO slug catch-all
// regex in ahg-information-object-manage (that file is locked - moving
// the route to bare /sparql would need an unlock and a regex update).
Route::match(['get', 'post'], '/admin/sparql', [\AhgMetadataExport\Controllers\SparqlController::class, 'handle'])
    ->middleware('web')
    ->name('ahgmetadataexport.sparql');

// PUBLIC whole-collection CIDOC-CRM (ISO 21127) bulk dump - GET /data/cidoc-crm.ttl
// (#1197 / #1204, the Open Memory Protocol open-data line). Unauthenticated open
// data: published records only, CORS-open, read-only. Streams the most-recent
// pre-built dump from config('heratio.storage_path')/cidoc-graph/cidoc-crm.ttl
// when present, otherwise generates a bounded (hard-capped) graph on the fly.
//
// CATCH-ALL SAFETY: "/data/cidoc-crm.ttl" is a TWO-segment, dotted path. The
// archival-record /{slug} catch-all in ahg-information-object-manage matches a
// SINGLE path segment with no dot (^[a-z0-9][a-z0-9-]*$), so it can never
// capture this URL. Registered at the root with the bare `web` group (no auth)
// so external Linked Data clients reach it without a session cookie.
Route::options('/data/cidoc-crm.ttl', [\AhgMetadataExport\Controllers\CidocGraphController::class, 'options'])
    ->middleware('web')
    ->name('ahgmetadataexport.cidoc.graph.options');

Route::get('/data/cidoc-crm.ttl', [\AhgMetadataExport\Controllers\CidocGraphController::class, 'download'])
    ->middleware('web')
    ->name('ahgmetadataexport.cidoc.graph');

// ---- BEGIN MARCXML import (#663 Phase 2) ------------------------------------
// Upload + preview + commit flow. Kept inside /admin so the IO slug catch-all
// regex in ahg-information-object-manage cannot intercept these URLs.
Route::prefix('admin/marc')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/import', [\AhgMetadataExport\Controllers\MarcImportController::class, 'form'])
        ->name('ahgmetadataexport.marc.import');
    Route::post('/import/preview', [\AhgMetadataExport\Controllers\MarcImportController::class, 'preview'])
        ->name('ahgmetadataexport.marc.import.preview');
    Route::post('/import/commit', [\AhgMetadataExport\Controllers\MarcImportController::class, 'commit'])
        ->name('ahgmetadataexport.marc.import.commit');
});
// ---- END MARCXML import -----------------------------------------------------

// ---- BEGIN EAD import (#657 Phase 1) ----------------------------------------
// Native EAD 2002 + EAD 3 XML upload, parse, preview, commit. Same /admin
// prefix rules as MARCXML import.
Route::prefix('admin/ead')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/import', [\AhgMetadataExport\Controllers\EadImportController::class, 'form'])
        ->name('ahgmetadataexport.ead.import');
    Route::post('/import/preview', [\AhgMetadataExport\Controllers\EadImportController::class, 'preview'])
        ->name('ahgmetadataexport.ead.import.preview');
    Route::post('/import/commit', [\AhgMetadataExport\Controllers\EadImportController::class, 'commit'])
        ->name('ahgmetadataexport.ead.import.commit');
});
// ---- END EAD import ---------------------------------------------------------
