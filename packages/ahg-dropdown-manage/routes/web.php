<?php

use AhgDropdownManage\Controllers\DropdownController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/dropdowns', [DropdownController::class, 'index'])->name('dropdown.index');
    Route::get('/admin/dropdowns/{taxonomy}/edit', [DropdownController::class, 'edit'])->name('dropdown.edit');

    // AJAX endpoints
    Route::post('/admin/dropdowns/create', [DropdownController::class, 'createTaxonomy'])->name('dropdown.create');
    Route::post('/admin/dropdowns/rename', [DropdownController::class, 'renameTaxonomy'])->name('dropdown.rename');
    Route::post('/admin/dropdowns/delete-taxonomy', [DropdownController::class, 'deleteTaxonomy'])->name('dropdown.delete-taxonomy');
    Route::post('/admin/dropdowns/move-section', [DropdownController::class, 'moveSection'])->name('dropdown.move-section');
    Route::post('/admin/dropdowns/add-term', [DropdownController::class, 'addTerm'])->name('dropdown.add-term');
    Route::post('/admin/dropdowns/update-term', [DropdownController::class, 'updateTerm'])->name('dropdown.update-term');
    Route::post('/admin/dropdowns/delete-term', [DropdownController::class, 'deleteTerm'])->name('dropdown.delete-term');
    Route::post('/admin/dropdowns/reorder', [DropdownController::class, 'reorder'])->name('dropdown.reorder');
    Route::post('/admin/dropdowns/set-default', [DropdownController::class, 'setDefault'])->name('dropdown.set-default');
});
