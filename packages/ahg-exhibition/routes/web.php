<?php

use AhgExhibition\Controllers\ExhibitionController;
use AhgExhibition\Controllers\ExhibitionSpaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('exhibition')->group(function () {
    Route::get('/', [ExhibitionController::class, 'index'])->name('exhibition.index');
    Route::get('/dashboard', [ExhibitionController::class, 'dashboard'])->name('exhibition.dashboard');
});

// Dashboard URL alias under /museum/exhibitions (matches reports dashboard link)
Route::middleware('auth')->group(function () {
    Route::get('/museum/exhibitions', [ExhibitionController::class, 'index'])->name('museum.exhibitions');
});

Route::middleware('auth')->prefix('exhibition')->group(function () {
    Route::match(['get', 'post'], '/add', [ExhibitionController::class, 'add'])->name('exhibition.add'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/{id}/edit', [ExhibitionController::class, 'edit'])->name('exhibition.edit'); // ACL must be checked in controller (Route::match)
    Route::get('/{id}/objects', [ExhibitionController::class, 'objects'])->name('exhibition.objects');
    Route::get('/{id}/object-list', [ExhibitionController::class, 'objectList'])->name('exhibition.objectList');
    Route::get('/{id}/object-list/csv', [ExhibitionController::class, 'objectListCsv'])->name('exhibition.objectListCsv');
    Route::get('/{id}/storylines', [ExhibitionController::class, 'storylines'])->name('exhibition.storylines');
    Route::get('/{exhibitionId}/storyline/{storylineId}', [ExhibitionController::class, 'storyline'])->name('exhibition.storyline');
    Route::get('/{id}/sections', [ExhibitionController::class, 'sections'])->name('exhibition.sections');
    Route::get('/{id}/events', [ExhibitionController::class, 'events'])->name('exhibition.events');
    Route::get('/{id}/checklists', [ExhibitionController::class, 'checklists'])->name('exhibition.checklists');
    Route::get('/{id}', [ExhibitionController::class, 'show'])->name('exhibition.show');
});

// heratio#146 — exhibition space (front-of-house space allocation, sibling of strongroom)
Route::get('/exhibition-space/browse', [ExhibitionSpaceController::class, 'browse'])->name('exhibition-space.browse');

Route::middleware('auth')->group(function () {
    Route::get('/exhibition-space/add', [ExhibitionSpaceController::class, 'create'])->name('exhibition-space.create');
    Route::post('/exhibition-space/add', [ExhibitionSpaceController::class, 'store'])->name('exhibition-space.store')->middleware('acl:create');
    Route::get('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'edit'])->name('exhibition-space.edit');
    Route::post('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'update'])->name('exhibition-space.update')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/place', [ExhibitionSpaceController::class, 'placePlacement'])->name('exhibition-space.place')->middleware('acl:update');
    Route::post('/exhibition-space/placement/{placementId}/remove', [ExhibitionSpaceController::class, 'removePlacement'])->name('exhibition-space.placement.remove')->middleware('acl:update')->whereNumber('placementId');
});

Route::middleware('admin')->group(function () {
    Route::get('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'confirmDelete'])->name('exhibition-space.confirmDelete');
    Route::delete('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'destroy'])->name('exhibition-space.destroy')->middleware('acl:delete');
});

Route::get('/exhibition-space/{slug}', [ExhibitionSpaceController::class, 'show'])
    ->name('exhibition-space.show')
    ->where('slug', '(?!browse|add|placement)[a-z0-9][a-z0-9-]*');
