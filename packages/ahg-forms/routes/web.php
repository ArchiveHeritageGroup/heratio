<?php

use AhgForms\Controllers\FormsController;
use AhgForms\Controllers\TemplateEditController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('forms')->group(function () {
    Route::get('/', [FormsController::class, 'index'])->name('forms.index');
    Route::post('/', [FormsController::class, 'post'])->name('forms.post');
    Route::get('/browse', [FormsController::class, 'browse'])->name('forms.browse');
    Route::get('/templates', [FormsController::class, 'templates'])->name('forms.templates');
    Route::match(['get', 'post'], '/template/create', [FormsController::class, 'templateCreate'])->name('forms.template.create');
    Route::get('/template/{id}/export', [FormsController::class, 'templateExport'])->name('forms.template.export')->whereNumber('id');
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

// Aliases for /admin/formTemplates (menu + reports links)
Route::middleware(['auth', 'admin'])->prefix('admin/formTemplates')->group(function () {
    Route::get('/', [FormsController::class, 'index']);
    Route::post('/', [FormsController::class, 'post']);
    Route::get('/browse', [FormsController::class, 'browse']);
    Route::match(['get', 'post'], '/create', [FormsController::class, 'templateCreate']);
    Route::get('/assignments', [FormsController::class, 'assignments']);
});

// API: Forms autosave + template resolution (AJAX, used by JS widgets)
Route::middleware('auth')->group(function () {
    Route::post('/api/forms/autosave', [FormsController::class, 'apiAutosave'])->name('forms.api.autosave');
    Route::get('/api/forms/template', [FormsController::class, 'apiGetForm'])->name('forms.api.template');
});

// Entity edit driven by a form template (dispatcher + renderer + submit)
Route::middleware(['auth', 'acl:update'])->prefix('forms/edit')->group(function () {
    Route::get('/{entityType}/{entityId}/{templateId?}', [TemplateEditController::class, 'edit'])
        ->name('forms.template.edit')
        ->whereNumber(['entityId', 'templateId'])
        ->where('entityType', 'information_object|actor|repository|accession');

    Route::post('/{entityType}/{entityId}/submit/{templateId}', [TemplateEditController::class, 'submit'])
        ->name('forms.template.submit')
        ->whereNumber(['entityId', 'templateId'])
        ->where('entityType', 'information_object|actor|repository|accession');
});
