<?php

use AhgInformationObjectManage\Controllers\InformationObjectController;
use AhgInformationObjectManage\Controllers\ExportController;
use AhgInformationObjectManage\Controllers\ImportController;
use AhgInformationObjectManage\Controllers\FindingAidController;
use Illuminate\Support\Facades\Route;

Route::get('/informationobject/browse', [InformationObjectController::class, 'browse'])->name('informationobject.browse');
Route::get('/informationobject/add', [InformationObjectController::class, 'create'])->name('informationobject.create');
Route::post('/informationobject/store', [InformationObjectController::class, 'store'])->name('informationobject.store');
Route::get('/informationobject/{slug}/edit', [InformationObjectController::class, 'edit'])->name('informationobject.edit');
Route::put('/informationobject/{slug}', [InformationObjectController::class, 'update'])->name('informationobject.update');
Route::delete('/informationobject/{slug}', [InformationObjectController::class, 'destroy'])->name('informationobject.destroy');

// Export
Route::get('/informationobject/{slug}/export/dc', [ExportController::class, 'dc'])->name('informationobject.export.dc');
Route::get('/informationobject/{slug}/export/ead', [ExportController::class, 'ead'])->name('informationobject.export.ead');

// Import (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/informationobject/import/xml/{slug?}', [ImportController::class, 'xml'])->name('informationobject.import.xml');
    Route::get('/informationobject/import/csv/{slug?}', [ImportController::class, 'csv'])->name('informationobject.import.csv');
    Route::post('/informationobject/import/process', [ImportController::class, 'process'])->name('informationobject.import.process');

    // Finding aid
    Route::get('/informationobject/{slug}/findingaid/generate', [FindingAidController::class, 'generate'])->name('informationobject.findingaid.generate');
    Route::get('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'uploadForm'])->name('informationobject.findingaid.upload.form');
    Route::post('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'upload'])->name('informationobject.findingaid.upload');
    Route::get('/informationobject/{slug}/findingaid/download', [FindingAidController::class, 'download'])->name('informationobject.findingaid.download');
});

Route::get('/{slug}', [InformationObjectController::class, 'show'])->name('informationobject.show')->where('slug', '^(?!search|login|logout|admin|api|storage|up|about|privacy|terms|pages|contact)[a-z0-9-]+$');
