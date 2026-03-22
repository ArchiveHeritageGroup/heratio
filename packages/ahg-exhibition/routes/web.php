<?php

use AhgExhibition\Controllers\ExhibitionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('exhibition')->group(function () {
    Route::get('/', [ExhibitionController::class, 'index'])->name('exhibition.index');
    Route::get('/dashboard', [ExhibitionController::class, 'dashboard'])->name('exhibition.dashboard');
    Route::match(['get', 'post'], '/add', [ExhibitionController::class, 'add'])->name('exhibition.add');
    Route::match(['get', 'post'], '/{id}/edit', [ExhibitionController::class, 'edit'])->name('exhibition.edit');
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
