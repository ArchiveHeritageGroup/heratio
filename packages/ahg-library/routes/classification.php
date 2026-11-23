<?php

use AhgLibrary\Controllers\ClassificationScheduleController as C;
use Illuminate\Support\Facades\Route;

// Classification schedules (#1475 item 6).
Route::middleware('auth')->prefix('library-manage/classification')->name('library.classification-')->group(function () {
    Route::middleware('acl:read')->group(function () {
        Route::get('/', [C::class, 'index'])->name('index');
        Route::get('/lookup', [C::class, 'lookup'])->name('lookup');
        Route::get('/check', [C::class, 'check'])->name('check');
        Route::get('/{id}', [C::class, 'show'])->name('show')->whereNumber('id');
    });
    Route::post('/', [C::class, 'store'])->name('store')->middleware('acl:create');
    Route::put('/{id}', [C::class, 'update'])->name('update')->whereNumber('id')->middleware('acl:update');
    Route::delete('/{id}', [C::class, 'destroy'])->name('destroy')->whereNumber('id')->middleware('acl:delete');
    Route::post('/{id}/import', [C::class, 'import'])->name('import')->whereNumber('id')->middleware('acl:create');
    Route::post('/{id}/entries', [C::class, 'storeEntry'])->name('entry-store')->whereNumber('id')->middleware('acl:create');
    Route::put('/entries/{entryId}', [C::class, 'updateEntry'])->name('entry-update')->whereNumber('entryId')->middleware('acl:update');
    Route::delete('/entries/{entryId}', [C::class, 'destroyEntry'])->name('entry-destroy')->whereNumber('entryId')->middleware('acl:delete');
});
