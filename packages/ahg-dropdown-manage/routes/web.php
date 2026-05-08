<?php

use AhgDropdownManage\Controllers\DropdownController;
use Illuminate\Support\Facades\Route;

// Legacy URL alias: /admin/dropdown → /admin/dropdowns
Route::get('/admin/dropdown', fn () => redirect('/admin/dropdowns', 301));

Route::middleware('admin')->group(function () {
    Route::get('/admin/dropdowns', [DropdownController::class, 'index'])->name('dropdown.index');

    // Issue #59 Phase 3 - source dispatcher pattern. Edit route takes
    // {source} (ahg_dropdown / term / setting) so the Dropdown Manager can
    // edit values from all 3 backends. Legacy URL /admin/dropdowns/{taxonomy}/edit
    // (no source) redirects to the ahg_dropdown source for back-compat.
    Route::get('/admin/dropdowns/{source}/{taxonomy}/edit', [DropdownController::class, 'edit'])
        ->where('source', 'ahg_dropdown|term|setting')
        ->name('dropdown.edit');
    Route::get('/admin/dropdowns/{taxonomy}/edit', function (string $taxonomy) {
        return redirect()->route('dropdown.edit', ['source' => 'ahg_dropdown', 'taxonomy' => $taxonomy], 301);
    })->where('taxonomy', '^(?!ahg_dropdown|term|setting$).+');

    // Issue #59 Phase 3 - per-row save endpoint. Admin auto-applies; editor
    // queues a draft into ahg_translation_draft. Controller dispatches to the
    // right *_i18n table based on {source}.
    Route::post('/admin/dropdowns/{source}/{id}/i18n', [DropdownController::class, 'saveI18n'])
        ->where('source', 'ahg_dropdown|term|setting')
        ->where('id', '[0-9]+')
        ->name('dropdown.save-i18n');

    // AJAX endpoints (existing - kept ahg_dropdown-specific for now;
    // taxonomy/code CRUD on the other 3 sources is out of scope for #59).
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
