<?php

use AhgBiblioBf\Controllers\BibframeController;
use AhgBiblioBf\Controllers\GraphEditorController;
use AhgBiblioBf\Services\BibframeSerialisationService;
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
        Route::post('/bibframe/import', [BibframeController::class, 'importRun'])->name('bibframe.import-run')->middleware('acl:create');

        Route::get('/bibframe/validate', [BibframeController::class, 'validate'])->name('bibframe.validate');
        Route::post('/bibframe/validate', [BibframeController::class, 'validateRun'])->name('bibframe.validate-run');

        Route::get('/bibframe/agent', [BibframeController::class, 'agent'])->name('bibframe.agent');

        // #760 graph-aware BIBFRAME editor - Work / Instance / Item + Contribution + Topic
        // inline edit against the live library_item catalogue (not the empty biblio_work scaffold).
        Route::get('/bibframe/editor/{libraryItemId}', [GraphEditorController::class, 'show'])
            ->name('bibframe.editor.show')->whereNumber('libraryItemId');
        Route::post('/bibframe/editor/{libraryItemId}/work', [GraphEditorController::class, 'updateWork'])
            ->name('bibframe.editor.work')->whereNumber('libraryItemId')->middleware('acl:update');
        Route::post('/bibframe/editor/{libraryItemId}/contributor', [GraphEditorController::class, 'addContributor'])
            ->name('bibframe.editor.contributor')->whereNumber('libraryItemId')->middleware('acl:update');
        Route::post('/bibframe/editor/{libraryItemId}/contributor/{creatorId}/delete', [GraphEditorController::class, 'deleteContributor'])
            ->name('bibframe.editor.contributor.delete')->whereNumber('libraryItemId')->whereNumber('creatorId')->middleware('acl:delete');
        Route::post('/bibframe/editor/{libraryItemId}/subject', [GraphEditorController::class, 'addSubject'])
            ->name('bibframe.editor.subject')->whereNumber('libraryItemId')->middleware('acl:update');
        Route::post('/bibframe/editor/{libraryItemId}/subject/{termId}/delete', [GraphEditorController::class, 'deleteSubject'])
            ->name('bibframe.editor.subject.delete')->whereNumber('libraryItemId')->whereNumber('termId')->middleware('acl:delete');
    });

    // LOD/SPARQL surface: BIBFRAME Turtle / JSON-LD downloads
    // These are public (MARC/HATHITrust-style open LOD pattern).
    Route::middleware('web')->group(function () {
        Route::get('/lodsparql/bibframe/{workId}.ttl', function (int $workId) {
            $service = app(BibframeSerialisationService::class);
            if (! $service->supports('turtle')) {
                abort(501, $service->unsupportedReason('turtle'));
            }
            $turtle = $service->toTurtle($workId);
            return response($turtle, 200, [
                'Content-Type' => 'text/turtle; charset=utf-8',
                'Content-Disposition' => "attachment; filename=bibframe-{$workId}.ttl",
            ]);
        })->name('bibframe.turtle')->whereNumber('workId');

        Route::get('/lodsparql/bibframe/{workId}.jsonld', function (int $workId) {
            $service = app(BibframeSerialisationService::class);
            if (! $service->supports('jsonld')) {
                abort(501, $service->unsupportedReason('jsonld'));
            }
            $jsonld = $service->toJsonLd($workId);
            return response($jsonld, 200, [
                'Content-Type' => 'application/ld+json; charset=utf-8',
                'Content-Disposition' => "attachment; filename=bibframe-{$workId}.jsonld",
            ]);
        })->name('bibframe.jsonld')->whereNumber('workId');
    });
});
