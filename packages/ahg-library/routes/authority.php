<?php

use AhgLibrary\Controllers\AuthorityHierarchyController;
use Illuminate\Support\Facades\Route;

// heratio#1475 item 3 - authority editing, hierarchy and vocabulary sync.
Route::middleware('auth')->prefix('library-manage/authority')->group(function () {
    Route::get('/{id}/edit', [AuthorityHierarchyController::class, 'edit'])->name('library.authority-edit')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/{id}/relations', [AuthorityHierarchyController::class, 'addRelation'])->name('library.authority-relation-add')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/relations/{relationId}/remove', [AuthorityHierarchyController::class, 'removeRelation'])->name('library.authority-relation-remove')->where('relationId', '[0-9]+')->middleware('acl:update');
    Route::post('/{id}/sync', [AuthorityHierarchyController::class, 'sync'])->name('library.authority-sync')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/{id}/overrides/clear', [AuthorityHierarchyController::class, 'clearOverrides'])->name('library.authority-overrides-clear')->where('id', '[0-9]+')->middleware('acl:update');
});
