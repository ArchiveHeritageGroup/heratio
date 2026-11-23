<?php

use AhgPortableExport\Controllers\PortableReleaseController as C;
use Illuminate\Support\Facades\Route;

// #1586 - public citation pages. A DOI must resolve without a login; the
// controller shows released versions only and re-checks disclosure on delivery.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/portable-release/{key}', [C::class, 'concept'])->where('key', '[a-f0-9]{12}')->name('portable-release.concept');
    Route::get('/portable-release/{key}/v{version}', [C::class, 'landing'])
        ->where(['key' => '[a-f0-9]{12}', 'version' => '[1-9][0-9]{0,5}'])->name('portable-release.landing');
    Route::get('/portable-release/{key}/v{version}/{file}', [C::class, 'file'])
        ->where(['key' => '[a-f0-9]{12}', 'version' => '[1-9][0-9]{0,5}', 'file' => 'package|manifest|changelog'])->name('portable-release.file');
});

Route::middleware('admin')->prefix('portable-export/releases')->name('portable-export.releases.')->group(function () {
    Route::get('/', [C::class, 'index'])->name('index');
    Route::post('/series', [C::class, 'storeSeries'])->name('series.store');
    Route::get('/series/{id}', [C::class, 'series'])->whereNumber('id')->name('series');
    Route::post('/series/{id}/freeze', [C::class, 'freeze'])->whereNumber('id')->name('freeze');
    Route::post('/series/{id}/corrections', [C::class, 'storeCorrection'])->whereNumber('id')->name('corrections.store');
    Route::post('/{id}/approve', [C::class, 'approve'])->whereNumber('id')->name('approve');
    Route::post('/{id}/retry', [C::class, 'retry'])->whereNumber('id')->name('retry');
    Route::post('/{id}/abandon', [C::class, 'abandon'])->whereNumber('id')->name('abandon');
    Route::post('/targets', [C::class, 'storeTarget'])->name('targets.store');
    Route::post('/targets/{id}/toggle', [C::class, 'toggleTarget'])->whereNumber('id')->name('targets.toggle');
});
