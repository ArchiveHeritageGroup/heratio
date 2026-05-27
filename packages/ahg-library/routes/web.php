<?php

use AhgLibrary\Controllers\LibraryController;
use AhgLibrary\Controllers\KbartoController;
use AhgLibrary\Controllers\KbartAdminController;
use AhgLibrary\Controllers\LibraryUsageController;
use AhgLibrary\Controllers\MarcEditorController;
use AhgLibrary\Controllers\SushiServerController;
use Illuminate\Support\Facades\Route;

// #766 SUSHI 5.0 server endpoint - publishes COUNTER R5 reports to consortium
// consumers. Mounted on api/sushi/r5; no `web` middleware so machine consumers
// don't trip CSRF.
Route::prefix('api/sushi/r5')->group(function () {
    Route::get('/status', [SushiServerController::class, 'status'])->name('library.sushi-status');
    Route::get('/members', [SushiServerController::class, 'members'])->name('library.sushi-members');
    Route::get('/reports', [SushiServerController::class, 'reports'])->name('library.sushi-reports');
    Route::get('/reports/{report_id}', [SushiServerController::class, 'report'])
        ->name('library.sushi-report')
        ->where('report_id', '[a-zA-Z0-9_]+');
});

Route::get('/library', [LibraryController::class, 'browse'])->name('library.browse');
// Dashboard URL alias under /library/browse (matches reports dashboard link)
Route::get('/library/browse', [LibraryController::class, 'browse'])->name('library.browse.alias');

// Issue #734 - PSIS-parity AJAX/proxy endpoints.
// Cover proxy: server-side fetch + local disk cache so the OPAC and admin
// thumbnails don't leak the patron's IP to the upstream cover service.
Route::get('/library/cover-image/{isbn}', [LibraryController::class, 'coverImage'])
    ->name('library.cover-image')
    ->where('isbn', '[0-9Xx\-]{9,20}');

// Slug preview: returns {slug: "kebab-case"} for the rename / edit form.
Route::get('/library/slug-preview', [LibraryController::class, 'slugPreview'])
    ->name('library.slug-preview')
    ->middleware('auth');

// AI subject suggestions (LlmService::complete behind the cloud-mode override).
Route::post('/library/suggest-subjects', [LibraryController::class, 'suggestSubjects'])
    ->name('library.suggest-subjects')
    ->middleware('auth');

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
    // Issue #734 - PSIS-parity alias requested in the brief. Same target as
    // /library-manage/isbn-providers so existing deep links keep working.
    Route::get('/admin/library/isbn-providers', [LibraryController::class, 'isbnProviders'])->name('library.isbn-providers.admin');
    Route::get('/library-manage/isbn-provider/{id}/edit', [LibraryController::class, 'isbnProviderEdit'])->name('library.isbn-provider-edit')->where('id', '[0-9]+');
    Route::post('/library-manage/isbn-provider/{id}', [LibraryController::class, 'isbnProviderStore'])->name('library.isbn-provider-store')->where('id', '[0-9]+')->middleware('acl:update');
    // Issue #734 - toggle (enable/disable) + delete admin actions
    Route::post('/library-manage/isbn-provider/{id}/toggle', [LibraryController::class, 'isbnProviderToggle'])->name('library.isbn-provider-toggle')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/isbn-provider/{id}/delete', [LibraryController::class, 'isbnProviderDelete'])->name('library.isbn-provider-delete')->where('id', '[0-9]+')->middleware('acl:delete');

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
    Route::get('/library-manage/ill',  [LibraryController::class, 'ill'])->name('library.ill');
    Route::get('/library-manage/ill/create',  [LibraryController::class, 'illCreate'])->name('library.ill-create');
    Route::post('/library-manage/ill/create',  [LibraryController::class, 'illStore'])->name('library.ill-store');
    Route::get('/library-manage/ill/{id}',    [LibraryController::class, 'illView'])->name('library.ill-view')->where('id', '[0-9]+');
    Route::patch('/library-manage/ill/{id}',   [LibraryController::class, 'illUpdate'])->name('library.ill-update')->where('id', '[0-9]+');
    Route::post('/library-manage/ill/{id}/transition', [LibraryController::class, 'illTransition'])->name('library.ill-transition')->where('id', '[0-9]+');
    Route::delete('/library-manage/ill/{id}',  [LibraryController::class, 'illDelete'])->name('library.ill-delete')->where('id', '[0-9]+');
    Route::patch('/library-manage/ill/{id}/suppress', [LibraryController::class, 'illOpacSuppress'])->name('library.ill-opac-suppress')->where('id', '[0-9]+');
    Route::get('/library-manage/ill/settings',  [LibraryController::class, 'illSettings'])->name('library.ill-settings');
    Route::post('/library-manage/ill/settings', [LibraryController::class, 'illSettingsStore'])->name('library.ill-settings-store');

    // Patron-facing ILL (OPAC)
    Route::get('/opac/ill/create',  [LibraryController::class, 'opacIllCreate'])->name('library.opac-ill-create');
    Route::post('/opac/ill/create',  [LibraryController::class, 'opacIllStore'])->name('library.ill-opac-store');

    // ISBN
    Route::get('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookup'])->name('library.isbn-lookup');
    Route::post('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookupSearch'])->name('library.isbn-lookup-search')->middleware('acl:create');

    // Patrons
    Route::get('/library-manage/patrons', [LibraryController::class, 'patrons'])->name('library.patrons');
    Route::get('/library-manage/patron/{id}', [LibraryController::class, 'patronView'])->name('library.patron-view')->where('id', '[0-9]+');
    // Issue #734 - patron reactivate (PSIS parity twin: ahgLibraryPlugin patron/reactivateAction)
    Route::post('/library/patron/{id}/reactivate', [LibraryController::class, 'patronReactivate'])->name('library.patron-reactivate')->where('id', '[0-9]+')->middleware('acl:update');

    // Serials
    Route::get('/library-manage/serials', [LibraryController::class, 'serials'])->name('library.serials');
    Route::get('/library-manage/serial/{id}', [LibraryController::class, 'serialView'])->name('library.serial-view')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/add',  [LibraryController::class,'serialCreate'])->name('library.serial-create');
    Route::post('/library-manage/serial/add', [LibraryController::class,'serialStore'])->name('library.serial-store');
    Route::get('/library-manage/serial/{id}/edit',  [LibraryController::class,'serialEdit'])->name('library.serial-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/serial/{id}',  [LibraryController::class,'serialUpdate'])->name('library.serial-update')->where('id', '[0-9]+');
    Route::delete('/library-manage/serial/{id}', [LibraryController::class,'serialDelete'])->name('library.serial-delete')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/issue', [LibraryController::class,'serialAddIssue'])->name('library.serial-add-issue')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/subscription', [LibraryController::class,'serialSubscription'])->name('library.serial-subscription')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/subscription', [LibraryController::class,'serialSubscriptionStore'])->name('library.serial-subscription-store')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/predict', [LibraryController::class,'serialPredict'])->name('library.serial-predict')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/coverage', [LibraryController::class,'serialCoverage'])->name('library.serial-coverage')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/clone', [LibraryController::class,'serialClone'])->name('library.serial-clone')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/overdue-claims', [LibraryController::class,'serialOverdueClaims'])->name('library.serial-overdue-claims');
    Route::post('/library-manage/serial/{serialId}/claim/{issueId}',[LibraryController::class,'serialClaimIssue'])->name('library.serial-claim-issue')->where(['serialId'=>'[0-9]+','issueId'=>'[0-9]+']);

    // Reports
    Route::get('/library-manage/reports', [LibraryController::class, 'libraryReports'])->name('library.reports');
    Route::get('/library-manage/reports/catalogue', [LibraryController::class, 'reportCatalogue'])->name('library.report-catalogue');
    Route::get('/library-manage/reports/creators', [LibraryController::class, 'reportCreators'])->name('library.report-creators');
    Route::get('/library-manage/reports/publishers', [LibraryController::class, 'reportPublishers'])->name('library.report-publishers');
    Route::get('/library-manage/reports/subjects', [LibraryController::class, 'reportSubjects'])->name('library.report-subjects');
    Route::get('/library-manage/reports/call-numbers', [LibraryController::class, 'reportCallNumbers'])->name('library.report-call-numbers');

    // MARC Editor - batch import + in-place field editor
    Route::get('/library-manage/marc', [MarcEditorController::class, 'index'])->name('library.marc-index');
    Route::get('/library-manage/marc/import', [MarcEditorController::class, 'import'])->name('library.marc-import');
    Route::post('/library-manage/marc/import/preview', [MarcEditorController::class, 'formImportPreview'])->name('library.marc-import-preview');
    Route::post('/library-manage/marc/import/commit', [MarcEditorController::class, 'formImportCommit'])->name('library.marc-import-commit');
    Route::get('/library-manage/marc/{id}/edit', [MarcEditorController::class, 'edit'])->name('library.marc-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/marc/{id}', [MarcEditorController::class, 'update'])->name('library.marc-update')->where('id', '[0-9]+');
    Route::get('/library-manage/marc/{id}/download', [MarcEditorController::class, 'download'])->name('library.marc-download')->where('id', '[0-9]+');
    Route::get('/library-manage/marc/{id}/download-binary', [MarcEditorController::class, 'downloadBinary'])->name('library.marc-download-binary')->where('id', '[0-9]+');

    // KBART knowledge-base exchange (issue #765)
    Route::get('/library-manage/kbart',              [KbartoController::class, 'index'])->name('library.kbart');
    Route::get('/library-manage/kbart/export',        [KbartoController::class, 'export'])->name('library.kbart-export');
    Route::get('/library-manage/kbart/export-csv',    [KbartoController::class, 'exportCsv'])->name('library.kbart-export-csv');
    Route::get('/library-manage/kbart/import',        [KbartoController::class, 'import'])->name('library.kbart-import');
    Route::post('/library-manage/kbart/preview',      [KbartoController::class, 'preview'])->name('library.kbart-preview');
    Route::post('/library-manage/kbart/commit',       [KbartoController::class, 'commit'])->name('library.kbart-commit');
    Route::get('/library-manage/kbart/template',      [KbartoController::class, 'template'])->name('library.kbart-template');

    // KBART remote feeds — automated scheduled import (issue #768)
    Route::get('/library-manage/kbart/remote',               [KbartAdminController::class, 'index'])->name('library.kbart-remote');
    Route::get('/library-manage/kbart/remote/log',           [KbartAdminController::class, 'log'])->name('library.kbart-remote-log');
    Route::get('/library-manage/kbart/remote/create',        [KbartAdminController::class, 'create'])->name('library.kbart-remote-create');
    Route::post('/library-manage/kbart/remote',              [KbartAdminController::class, 'store'])->name('library.kbart-remote-store');
    Route::get('/library-manage/kbart/remote/{feed}/edit',  [KbartAdminController::class, 'edit'])->name('library.kbart-remote-edit')->where('feed', '[0-9]+');
    Route::put('/library-manage/kbart/remote/{feed}',        [KbartAdminController::class, 'update'])->name('library.kbart-remote-update')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/{feed}/refresh', [KbartAdminController::class, 'refresh'])->name('library.kbart-remote-refresh')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/{feed}/toggle',  [KbartAdminController::class, 'toggle'])->name('library.kbart-remote-toggle')->where('feed', '[0-9]+');
    Route::delete('/library-manage/kbart/remote/{feed}',     [KbartAdminController::class, 'destroy'])->name('library.kbart-remote-destroy')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/test-url',      [KbartAdminController::class, 'testUrl'])->name('library.kbart-remote-test-url');

    // COUNTER 5 / SUSHI usage statistics (issue #766)
    Route::get('/library-manage/usage',                    [LibraryUsageController::class, 'index'])->name('library.usage');
    Route::get('/library-manage/usage/tr',                 [LibraryUsageController::class, 'titleReport'])->name('library.usage-tr');
    Route::get('/library-manage/usage/dr',                 [LibraryUsageController::class, 'databaseReport'])->name('library.usage-dr');
    Route::get('/library-manage/usage/harvest',            [LibraryUsageController::class, 'harvest'])->name('library.usage-harvest');
    Route::get('/library-manage/usage/subscriptions',      [LibraryUsageController::class, 'subscriptions'])->name('library.usage-subscriptions');
    Route::post('/library-manage/usage/subscriptions',     [LibraryUsageController::class, 'subscriptionsStore'])->name('library.usage-subscriptions-store');
    Route::get('/library-manage/usage/subscriptions/test', [LibraryUsageController::class, 'testConnection'])->name('library.usage-subscriptions-test');
    Route::get('/library-manage/usage/export/{type}',      [LibraryUsageController::class, 'export'])->name('library.usage-export')->where('type', 'PR|TR|DR');
});

// OPAC (public). Master gate: library_opac_enabled (404s the whole surface when off).
Route::middleware('opac.enabled')->group(function () {
    Route::get('/opac', [LibraryController::class, 'opac'])->name('library.opac');
    Route::get('/opac/view/{slug}', [LibraryController::class, 'opacView'])->name('library.opac-view');
    Route::middleware('auth')->group(function () {
        Route::get('/opac/account', [LibraryController::class, 'opacAccount'])->name('library.opac-account');
        Route::get('/opac/hold/{slug}', [LibraryController::class, 'opacHold'])->name('library.opac-hold');
        Route::post('/opac/hold/{slug}', [LibraryController::class, 'opacHoldStore'])->name('library.opac-hold-store')->middleware('acl:create');
        Route::post('/opac/renew/{id}', [LibraryController::class, 'opacRenew'])->name('library.opac-renew')->where('id', '[0-9]+')->middleware('acl:update');
    });
});
