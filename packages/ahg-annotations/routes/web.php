<?php

use AhgAnnotations\Controllers\AnnotationsController;
use AhgAnnotations\Http\Middleware\AnnotationContentTypeMiddleware;
use Illuminate\Support\Facades\Route;

// IIIF Web Annotations REST endpoint (Annotot-shaped). Used by the
// mirador-annotations plugin in the Heratio Mirador bundle. Closes #100.
//
// Anonymous reads, authenticated writes. The auth gate is enforced inside
// each controller method so a JSON-401 lands instead of the default
// auth.required redirect-to-/login (the mirador-annotation-editor's
// fetch-based adapter can't parse HTML responses and would silently fail).
//
// Phase 1 of #648 wraps every annotation route in AnnotationContentTypeMiddleware
// which adds the W3C Web Annotation Protocol headers (Content-Type,
// Link, Accept-Post, Vary, Allow) without altering the response body.
// The legacy Annotot-shaped HeratioAnnotationAdapter is unaffected because
// it parses JSON regardless of the Content-Type label.

Route::middleware([AnnotationContentTypeMiddleware::class])->group(function () {
    // Public reads - anyone who can see a digital object can read its
    // annotations. This matches the IO show page's existing visibility model.
    Route::get('/api/annotations/search', [AnnotationsController::class, 'search'])->name('annotations.search');
    Route::get('/api/annotations/{uuid}', [AnnotationsController::class, 'show'])->name('annotations.show');

    // Writes - auth gate is enforced inside the controller methods (they
    // return JSON 401 when not authenticated). Putting auth.required as
    // route middleware would redirect-to-/login (302 -> HTML), which the
    // mirador-annotation-editor's fetch-based adapter can't parse and
    // silently fails on. JSON-401 lets the editor surface a real error.
    Route::post('/api/annotations', [AnnotationsController::class, 'store'])->name('annotations.store');
    Route::put('/api/annotations/{uuid}', [AnnotationsController::class, 'update'])->name('annotations.update');
    Route::delete('/api/annotations/{uuid}', [AnnotationsController::class, 'destroy'])->name('annotations.destroy');
});
