<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/metadata-export')->middleware(['web', 'auth'])->group(function () {
    Route::get('/bulk', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'bulk'])->name('ahgmetadataexport.bulk');
    Route::get('/index', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'index'])->name('ahgmetadataexport.index');
    Route::get('/preview', [\AhgMetadataExport\Controllers\MetadataExportController::class, 'preview'])->name('ahgmetadataexport.preview');
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
