<?php

use AhgInformationObjectManage\Controllers\InformationObjectController;
use Illuminate\Support\Facades\Route;

Route::get('/informationobject/browse', [InformationObjectController::class, 'browse'])->name('informationobject.browse');
Route::get('/informationobject/add', [InformationObjectController::class, 'create'])->name('informationobject.create');
Route::post('/informationobject/store', [InformationObjectController::class, 'store'])->name('informationobject.store');
Route::get('/informationobject/{slug}/edit', [InformationObjectController::class, 'edit'])->name('informationobject.edit');
Route::put('/informationobject/{slug}', [InformationObjectController::class, 'update'])->name('informationobject.update');
Route::delete('/informationobject/{slug}', [InformationObjectController::class, 'destroy'])->name('informationobject.destroy');
Route::get('/{slug}', [InformationObjectController::class, 'show'])->name('informationobject.show')->where('slug', '^(?!search|login|logout|admin|api|storage|up|about|privacy|terms|pages|contact)[a-z0-9-]+$');
