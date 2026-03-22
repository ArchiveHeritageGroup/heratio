<?php

use AhgCustomFields\Controllers\CustomFieldAdminController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin/custom-fields')->group(function () {
    Route::get('/', [CustomFieldAdminController::class, 'index'])->name('customFields.index');
    Route::get('/add', [CustomFieldAdminController::class, 'edit'])->name('customFields.add');
    Route::get('/{id}/edit', [CustomFieldAdminController::class, 'edit'])->name('customFields.edit');
    Route::post('/save', [CustomFieldAdminController::class, 'save'])->name('customFields.save');
    Route::delete('/{id}', [CustomFieldAdminController::class, 'delete'])->name('customFields.delete');
    Route::post('/reorder', [CustomFieldAdminController::class, 'reorder'])->name('customFields.reorder');
    Route::get('/export', [CustomFieldAdminController::class, 'export'])->name('customFields.export');
    Route::post('/import', [CustomFieldAdminController::class, 'import'])->name('customFields.import');
});

// Custom Fields routes
Route::middleware(['web'])->prefix('admin/custom-fields')->group(function () {
    Route::get('/', fn() => view('custom-fields::index'))->name('customFields.index');
    Route::get('/add', fn() => view('custom-fields::add'))->name('customFields.add');
    Route::get('/{id}/edit', fn($id) => view('custom-fields::edit', ['id' => $id]))->name('customFields.edit');
    Route::post('/save', fn() => redirect()->back()->with('success', 'Saved'))->name('customFields.save');
    Route::delete('/{id}', fn($id) => redirect()->back()->with('success', 'Deleted'))->name('customFields.delete');
    Route::get('/export', fn() => response('CSV export', 200, ['Content-Type' => 'text/csv']))->name('customFields.export');
});
