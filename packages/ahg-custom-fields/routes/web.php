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

// Duplicate stub routes removed — the auth-protected group above handles all custom-fields admin routes
