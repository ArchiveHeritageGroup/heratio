<?php

use AhgForms\Controllers\FormsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('forms')->group(function () {
    Route::get('/', [FormsController::class, 'index'])->name('forms.index');
    Route::get('/browse', [FormsController::class, 'browse'])->name('forms.browse');
    Route::get('/templates', [FormsController::class, 'templates'])->name('forms.templates');
    Route::match(['get', 'post'], '/template/create', [FormsController::class, 'templateCreate'])->name('forms.template.create');
    Route::get('/builder/{id}', [FormsController::class, 'builder'])->name('forms.builder');
    Route::get('/preview/{id}', [FormsController::class, 'preview'])->name('forms.preview');
    Route::get('/assignments', [FormsController::class, 'assignments'])->name('forms.assignments');
    Route::match(['get', 'post'], '/assignment/create', [FormsController::class, 'assignmentCreate'])->name('forms.assignment.create');
    Route::get('/library', [FormsController::class, 'library'])->name('forms.library');

    // AJAX
    Route::post('/field/add', [FormsController::class, 'fieldAdd'])->name('forms.field.add');
    Route::post('/field/update', [FormsController::class, 'fieldUpdate'])->name('forms.field.update');
    Route::post('/field/delete', [FormsController::class, 'fieldDelete'])->name('forms.field.delete');
    Route::post('/field/reorder', [FormsController::class, 'fieldReorder'])->name('forms.field.reorder');
});
