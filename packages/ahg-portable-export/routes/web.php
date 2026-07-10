<?php

use AhgPortableExport\Controllers\PortableExportController;
use Illuminate\Support\Facades\Route;

// #1357 — anonymous, token-gated public download of a shared bundle. Deliberately
// OUTSIDE the admin group (a share link is meant to be opened by a recipient who
// isn't logged in). Safety: a 128-bit token in the path, expiry + optional
// download cap + a published-only gate enforced in share(), plus a throttle to
// blunt enumeration. ICIP/TK, ODRL and PII are already excluded at bundle-build.
Route::get('/portable-export/share/{token}', [PortableExportController::class, 'share'])
    ->where('token', '[a-f0-9]{32}')
    ->middleware('throttle:30,1')
    ->name('portable-export.share');

Route::middleware('admin')->group(function () {
    // AtoM-canonical URLs
    Route::get('/portable-export', [PortableExportController::class, 'index'])->name('portable-export.index');
    Route::get('/portable-export/index', [PortableExportController::class, 'index']);
    Route::match(['get', 'post'], '/portable-export/import', [PortableExportController::class, 'import'])->name('portable-export.import');
    Route::get('/portable-export/download', [PortableExportController::class, 'download'])->name('portable-export.download');

    // API endpoints used by the wizard JS
    Route::post('/portable-export/api/start', [PortableExportController::class, 'apiStart'])->name('portable-export.api.start');
    Route::get('/portable-export/api/progress', [PortableExportController::class, 'apiProgress'])->name('portable-export.api.progress');
    Route::get('/portable-export/api/estimate', [PortableExportController::class, 'apiEstimate'])->name('portable-export.api.estimate');
    Route::get('/portable-export/api/fonds-search', [PortableExportController::class, 'apiFondsSearch'])->name('portable-export.api.fonds-search');
    Route::post('/portable-export/api/delete', [PortableExportController::class, 'apiDelete'])->name('portable-export.api.delete');
    Route::post('/portable-export/api/token', [PortableExportController::class, 'apiToken'])->name('portable-export.api.token');

    // #1357 tidy — legacy AtoM-style URLs (/portableExport/*) now 301-redirect to
    // the canonical /portable-export/* handlers, so old bookmarks and the seeded
    // admin menu item ('portableExport/index') keep working without a duplicate
    // handler. The old POST /portableExport/export alias (a passthrough to
    // apiStart) is dropped — the wizard posts to /portable-export/api/start.
    Route::redirect('/portableExport/index', '/portable-export', 301);
    Route::redirect('/portableExport/import', '/portable-export/import', 301);
});
