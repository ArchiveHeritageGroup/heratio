<?php

use AhgAnnotations\Controllers\AnnotationsController;
use Illuminate\Support\Facades\Route;

// IIIF Web Annotations REST endpoint (Annotot-shaped). Used by the
// mirador-annotations plugin in the Heratio Mirador bundle. Closes #100.
//
// Anonymous reads, authenticated writes. The auth.required middleware
// alias is registered in bootstrap/app.php and matches App\Http\Middleware
// \RequireAuth (the existing AtoM-derived auth gate).

// Public reads — anyone who can see a digital object can read its
// annotations. This matches the IO show page's existing visibility model.
Route::get('/api/annotations/search', [AnnotationsController::class, 'search'])->name('annotations.search');
Route::get('/api/annotations/{uuid}', [AnnotationsController::class, 'show'])->name('annotations.show');

// Writes — auth gate is enforced inside the controller methods (they
// return JSON 401 when not authenticated). Putting auth.required as
// route middleware would redirect-to-/login (302 -> HTML), which the
// mirador-annotation-editor's fetch-based adapter can't parse and
// silently fails on. JSON-401 lets the editor surface a real error.
Route::post('/api/annotations', [AnnotationsController::class, 'store'])->name('annotations.store');
Route::put('/api/annotations/{uuid}', [AnnotationsController::class, 'update'])->name('annotations.update');
Route::delete('/api/annotations/{uuid}', [AnnotationsController::class, 'destroy'])->name('annotations.destroy');
