<?php

use AhgBiblioBf\Controllers\BibframeController;
use Illuminate\Support\Facades\Route;

// BIBFRAME integration routes.
// Converts Heratio bibliographic catalogue records to/from BIBFRAME 2.0 RDF
// via the OpenRiC RiC-O service layer.

Route::middleware('web')->group(function () {
    // Public read
    Route::get('/bibframe', [BibframeController::class, 'index'])->name('bibframe.index');
    Route::get('/bibframe/{workId}', [BibframeController::class, 'show'])->name('bibframe.show')
        ->where('workId', '[0-9]+');

    // Auth-gated management
    Route::middleware('auth')->group(function () {
        Route::get('/bibframe/export', [BibframeController::class, 'export'])->name('bibframe.export');
        Route::post('/bibframe/export', [BibframeController::class, 'exportRun'])->name('bibframe.export-run');

        Route::get('/bibframe/import', [BibframeController::class, 'import'])->name('bibframe.import');
        Route::post('/bibframe/import', [BibframeController::class, 'importRun'])->name('bibframe.import-run');

        Route::get('/bibframe/validate', [BibframeController::class, 'validate'])->name('bibframe.validate');
        Route::post('/bibframe/validate', [BibframeController::class, 'validateRun'])->name('bibframe.validate-run');

        Route::get('/bibframe/agent', [BibframeController::class, 'agent'])->name('bibframe.agent');
    });
});
