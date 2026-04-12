<?php

use AhgLibrary\Controllers\LibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/library', [LibraryController::class, 'browse'])->name('library.browse');
// Dashboard URL alias under /library/browse (matches reports dashboard link)
Route::get('/library/browse', [LibraryController::class, 'browse'])->name('library.browse.alias');

Route::middleware('auth')->group(function () {
    Route::get('/library/add', [LibraryController::class, 'create'])->name('library.create');
    Route::post('/library/store', [LibraryController::class, 'store'])->name('library.store')->middleware('acl:create');
    Route::get('/library/{slug}/edit', [LibraryController::class, 'edit'])->name('library.edit');
    Route::put('/library/{slug}', [LibraryController::class, 'update'])->name('library.update')->middleware('acl:update');
    Route::post('/library/{slug}/delete', [LibraryController::class, 'destroy'])->name('library.destroy')->middleware('acl:delete');
});

Route::get('/library/{slug}', [LibraryController::class, 'show'])->name('library.show')
    ->where('slug', '(?!browse|add|store)[a-z0-9\-]+');

// Library management routes
Route::middleware('auth')->group(function () {
    Route::get('/library-manage/index', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library-manage/{slug}/rename', [LibraryController::class, 'rename'])->name('library.rename');
    Route::post('/library-manage/{slug}/rename', [LibraryController::class, 'renameStore'])->name('library.rename-store')->middleware('acl:update');
    Route::get('/library-manage/isbn-providers', [LibraryController::class, 'isbnProviders'])->name('library.isbn-providers');
    Route::get('/library-manage/isbn-provider/{id}/edit', [LibraryController::class, 'isbnProviderEdit'])->name('library.isbn-provider-edit')->where('id', '[0-9]+');
    Route::post('/library-manage/isbn-provider/{id}', [LibraryController::class, 'isbnProviderStore'])->name('library.isbn-provider-store')->where('id', '[0-9]+')->middleware('acl:update');

    // Acquisition
    Route::get('/library-manage/acquisitions', [LibraryController::class, 'acquisitions'])->name('library.acquisitions');
    Route::get('/library-manage/acquisition/batch-capture', [LibraryController::class, 'batchCapture'])->name('library.batch-capture');
    Route::post('/library-manage/acquisition/batch-capture', [LibraryController::class, 'batchCaptureLookup'])->name('library.batch-capture-lookup')->middleware('acl:create');
    Route::get('/library-manage/acquisition/budgets', [LibraryController::class, 'budgets'])->name('library.budgets');
    Route::get('/library-manage/acquisition/order/{id}', [LibraryController::class, 'acquisitionOrder'])->name('library.acquisition-order')->where('id', '[0-9]+');
    Route::get('/library-manage/acquisition/order/{id}/edit', [LibraryController::class, 'acquisitionOrderEdit'])->name('library.acquisition-order-edit')->where('id', '[0-9]+');
    Route::post('/library-manage/acquisition/order/{id}', [LibraryController::class, 'acquisitionOrderStore'])->name('library.acquisition-order-store')->where('id', '[0-9]+')->middleware('acl:update');

    // Circulation
    Route::get('/library-manage/circulation', [LibraryController::class, 'circulation'])->name('library.circulation');
    Route::get('/library-manage/circulation/loan-rules', [LibraryController::class, 'loanRules'])->name('library.loan-rules');
    Route::get('/library-manage/circulation/overdue', [LibraryController::class, 'overdue'])->name('library.overdue');

    // ILL
    Route::get('/library-manage/ill', [LibraryController::class, 'ill'])->name('library.ill');
    Route::get('/library-manage/ill/{id}', [LibraryController::class, 'illView'])->name('library.ill-view')->where('id', '[0-9]+');

    // ISBN
    Route::get('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookup'])->name('library.isbn-lookup');
    Route::post('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookupSearch'])->name('library.isbn-lookup-search')->middleware('acl:create');

    // Patrons
    Route::get('/library-manage/patrons', [LibraryController::class, 'patrons'])->name('library.patrons');
    Route::get('/library-manage/patron/{id}', [LibraryController::class, 'patronView'])->name('library.patron-view')->where('id', '[0-9]+');

    // Serials
    Route::get('/library-manage/serials', [LibraryController::class, 'serials'])->name('library.serials');
    Route::get('/library-manage/serial/{id}', [LibraryController::class, 'serialView'])->name('library.serial-view')->where('id', '[0-9]+');

    // Reports
    Route::get('/library-manage/reports', [LibraryController::class, 'libraryReports'])->name('library.reports');
    Route::get('/library-manage/reports/catalogue', [LibraryController::class, 'reportCatalogue'])->name('library.report-catalogue');
    Route::get('/library-manage/reports/creators', [LibraryController::class, 'reportCreators'])->name('library.report-creators');
    Route::get('/library-manage/reports/publishers', [LibraryController::class, 'reportPublishers'])->name('library.report-publishers');
    Route::get('/library-manage/reports/subjects', [LibraryController::class, 'reportSubjects'])->name('library.report-subjects');
    Route::get('/library-manage/reports/call-numbers', [LibraryController::class, 'reportCallNumbers'])->name('library.report-call-numbers');
});

// OPAC (public)
Route::get('/opac', [LibraryController::class, 'opac'])->name('library.opac');
Route::get('/opac/view/{slug}', [LibraryController::class, 'opacView'])->name('library.opac-view');
Route::middleware('auth')->group(function () {
    Route::get('/opac/account', [LibraryController::class, 'opacAccount'])->name('library.opac-account');
    Route::get('/opac/hold/{slug}', [LibraryController::class, 'opacHold'])->name('library.opac-hold');
    Route::post('/opac/hold/{slug}', [LibraryController::class, 'opacHoldStore'])->name('library.opac-hold-store')->middleware('acl:create');
    Route::post('/opac/renew/{id}', [LibraryController::class, 'opacRenew'])->name('library.opac-renew')->where('id', '[0-9]+')->middleware('acl:update');
});
