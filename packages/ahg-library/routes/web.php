<?php





use AhgLibrary\Controllers\TradingPartnerController;
use AhgLibrary\Controllers\IllRequestController;
use AhgLibrary\Controllers\LibraryController;
use AhgLibrary\Controllers\KbartoController;
use AhgLibrary\Controllers\KbartAdminController;
use AhgLibrary\Controllers\LibraryAcquisitionController;
use AhgLibrary\Controllers\LibraryUsageController;
use AhgLibrary\Controllers\MarcEditorController;
use AhgLibrary\Controllers\SushiServerController;
use AhgLibrary\Controllers\UsageEventController;
use AhgLibrary\Controllers\AuthorityControlController;
use AhgLibrary\Controllers\CirculationDeskController;
use AhgLibrary\Controllers\OpacPatronController;
use AhgLibrary\Controllers\CopyCataloguingController;
use AhgLibrary\Controllers\OnixIngestController;
use Illuminate\Support\Facades\Route;

// #766 per-event JS instrumentation beacon
Route::post('/api/library/usage-event', [UsageEventController::class, 'record'])
    ->name('library.usage-event')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// SUSHI 5.0 server
Route::prefix('api/sushi/r5')->group(function () {
    Route::get('/status', [SushiServerController::class, 'status'])->name('library.sushi-status');
    Route::get('/members', [SushiServerController::class, 'members'])->name('library.sushi-members');
    Route::get('/reports', [SushiServerController::class, 'reports'])->name('library.sushi-reports');
    Route::get('/reports/{report_id}', [SushiServerController::class, 'report'])
        ->name('library.sushi-report')
        ->where('report_id', '[a-zA-Z0-9_]+');
});

Route::get('/library', [LibraryController::class, 'browse'])->name('library.browse');
Route::get('/library/browse', [LibraryController::class, 'browse'])->name('library.browse.alias');

Route::get('/library/cover-image/{isbn}', [LibraryController::class, 'coverImage'])
    ->name('library.cover-image')
    ->where('isbn', '[0-9Xx\-]{9,20}');

Route::get('/library/slug-preview', [LibraryController::class, 'slugPreview'])
    ->name('library.slug-preview')
    ->middleware('auth');

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

Route::middleware('auth')->group(function () {
    // ── Library Management ─────────────────────────────────────────────────
    Route::get('/library-manage/index', [LibraryController::class, 'index'])->name('library.index');
    Route::get('/library-manage/{slug}/rename', [LibraryController::class, 'rename'])->name('library.rename');
    Route::post('/library-manage/{slug}/rename', [LibraryController::class, 'renameStore'])->name('library.rename-store')->middleware('acl:update');
    Route::get('/library-manage/isbn-providers', [LibraryController::class, 'isbnProviders'])->name('library.isbn-providers');
    Route::get('/admin/library/isbn-providers', [LibraryController::class, 'isbnProviders'])->name('library.isbn-providers.admin');
    Route::get('/library-manage/isbn-provider/{id}/edit', [LibraryController::class, 'isbnProviderEdit'])->name('library.isbn-provider-edit')->where('id', '[0-9]+');
    Route::post('/library-manage/isbn-provider/{id}', [LibraryController::class, 'isbnProviderStore'])->name('library.isbn-provider-store')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/isbn-provider/{id}/toggle', [LibraryController::class, 'isbnProviderToggle'])->name('library.isbn-provider-toggle')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/isbn-provider/{id}/delete', [LibraryController::class, 'isbnProviderDelete'])->name('library.isbn-provider-delete')->where('id', '[0-9]+')->middleware('acl:delete');

    // ── MARC Editor ────────────────────────────────────────────────────────
    Route::get('/library-manage/marc', [MarcEditorController::class, 'index'])->name('library.marc-index');
    Route::get('/library-manage/marc/edit', [MarcEditorController::class, 'editRedirect'])->name('library.marc-edit-redirect');
    Route::get('/library-manage/marc/import', [MarcEditorController::class, 'import'])->name('library.marc-import');
    Route::post('/library-manage/marc/import/preview', [MarcEditorController::class, 'formImportPreview'])->name('library.marc-import-preview');
    Route::post('/library-manage/marc/import/commit', [MarcEditorController::class, 'formImportCommit'])->name('library.marc-import-commit');

    // MARC Binary import (ISO 2709)
    Route::get('/library-manage/marc/import/binary', [MarcEditorController::class, 'importBinary'])->name('library.marc-binary');
    Route::post('/library-manage/marc/import/binary/preview', [MarcEditorController::class, 'formBinaryPreview'])->name('library.marc-binary-preview');
    Route::post('/library-manage/marc/import/binary', [MarcEditorController::class, 'formBinaryCommit'])->name('library.marc-binary-commit');

    Route::get('/library-manage/marc/{id}/edit', [MarcEditorController::class, 'edit'])->name('library.marc-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/marc/{id}', [MarcEditorController::class, 'update'])->name('library.marc-update')->where('id', '[0-9]+');
    Route::get('/library-manage/marc/{id}/download', [MarcEditorController::class, 'download'])->name('library.marc-download')->where('id', '[0-9]+');
    Route::get('/library-manage/marc/{id}/download-binary', [MarcEditorController::class, 'downloadBinary'])->name('library.marc-download-binary')->where('id', '[0-9]+');

    // ── ONIX Ingestion (heratio#1094) ────────────────────────────────────────
    Route::get('/library-manage/onix', [OnixIngestController::class, 'index'])->name('library.onix-index');
    Route::post('/library-manage/onix', [OnixIngestController::class, 'store'])->name('library.onix-store')->middleware('acl:create');
    Route::get('/library-manage/onix/{id}', [OnixIngestController::class, 'show'])->name('library.onix-show')->where('id', '[0-9]+');
    Route::post('/library-manage/onix/{id}/commit', [OnixIngestController::class, 'commit'])->name('library.onix-commit')->where('id', '[0-9]+')->middleware('acl:create');
    Route::delete('/library-manage/onix/{id}', [OnixIngestController::class, 'destroy'])->name('library.onix-destroy')->where('id', '[0-9]+')->middleware('acl:delete');
    Route::post('/library-manage/onix/line/{lineId}/status', [OnixIngestController::class, 'lineStatus'])->name('library.onix-line-status')->where('lineId', '[0-9]+')->middleware('acl:update');
    // API: POST /api/library/ingest/onix (raw XML body, onix field, or onix_file). ?commit=1 to commit in one call.
    Route::post('/api/library/ingest/onix', [OnixIngestController::class, 'apiIngest'])
        ->name('library.onix-api-ingest')
        ->middleware('acl:create')
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    // ── Authority Control ──────────────────────────────────────────────────
    Route::get('/library-manage/authority', [AuthorityControlController::class, 'index'])->name('library.authority-index');
    Route::get('/library-manage/authority/create', [AuthorityControlController::class, 'create'])->name('library.authority-create');
    Route::post('/library-manage/authority', [AuthorityControlController::class, 'store'])->name('library.authority-store')->middleware('acl:create');
    Route::get('/library-manage/authority/{id}', [AuthorityControlController::class, 'view'])->name('library.authority-view')->where('id', '[0-9]+');
    Route::put('/library-manage/authority/{id}', [AuthorityControlController::class, 'update'])->name('library.authority-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::delete('/library-manage/authority/{id}', [AuthorityControlController::class, 'destroy'])->name('library.authority-destroy')->where('id', '[0-9]+')->middleware('acl:delete');
    Route::get('/library-manage/authority/{id}/link', [AuthorityControlController::class, 'link'])->name('library.authority-link')->where('id', '[0-9]+');
    Route::post('/library-manage/authority/link', [AuthorityControlController::class, 'storeLink'])->name('library.authority-store-link')->middleware('acl:update');
    Route::post('/library-manage/authority/unlink/{linkId}', [AuthorityControlController::class, 'unlink'])->name('library.authority-unlink')->where('linkId', '[0-9]+')->middleware('acl:update');

    // ── Copy Cataloguing (Z39.50) ──────────────────────────────────────────
    Route::get('/library-manage/copy-cataloguing', [CopyCataloguingController::class, 'index'])->name('library.copy-cataloguing-index');
    Route::get('/library-manage/copy-cataloguing/search', [CopyCataloguingController::class, 'search'])->name('library.copy-cataloguing-search');
    Route::post('/library-manage/copy-cataloguing/import', [CopyCataloguingController::class, 'import'])->name('library.copy-cataloguing-import')->middleware('acl:create');
    Route::get('/library-manage/copy-cataloguing/targets', [CopyCataloguingController::class, 'targets'])->name('library.copy-cataloguing-targets');
    Route::post('/library-manage/copy-cataloguing/targets', [CopyCataloguingController::class, 'storeTarget'])->name('library.copy-cataloguing-store-target')->middleware('acl:create');
    Route::put('/library-manage/copy-cataloguing/targets/{id}', [CopyCataloguingController::class, 'updateTarget'])->name('library.copy-cataloguing-update-target')->where('id', '[0-9]+')->middleware('acl:update');
    Route::delete('/library-manage/copy-cataloguing/targets/{id}', [CopyCataloguingController::class, 'destroyTarget'])->name('library.copy-cataloguing-destroy-target')->where('id', '[0-9]+')->middleware('acl:delete');

    // ── Acquisition ────────────────────────────────────────────────────────
    // Quick-restore: list page served by the stable LibraryController@acquisitions
    // (renders acquisition.index) while Phase-1 LibraryAcquisitionController views
    // (order-list, order-create, order-show, _order-lines, budget-*) are still being built.
    Route::get('/library-manage/acquisitions', [LibraryController::class, 'acquisitions'])->name('library.acquisitions');
    Route::get('/library-manage/acquisition/order/create', [LibraryAcquisitionController::class, 'create'])->name('library.acquisition-order-create');
    Route::post('/library-manage/acquisition/order', [LibraryAcquisitionController::class, 'store'])->name('library.acquisition-order-store')->middleware('acl:create');
    // Quick-restore: order detail served by stable LibraryController@acquisitionOrder
    // (renders acquisition.order) so the restored list's click-through works until the
    // Phase-1 order-show view exists.
    Route::get('/library-manage/acquisition/order/{id}', [LibraryController::class, 'acquisitionOrder'])->name('library.acquisition-order')->where('id', '[0-9]+');
    Route::get('/library-manage/acquisition/order/{id}/edit', [LibraryAcquisitionController::class, 'edit'])->name('library.acquisition-order-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/acquisition/order/{id}', [LibraryAcquisitionController::class, 'update'])->name('library.acquisition-order-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/acquisition/order/{id}/status', [LibraryAcquisitionController::class, 'transition'])->name('library.acquisition-order-transition')->where('id', '[0-9]+')->middleware('acl:update');
    Route::get('/library-manage/acquisition/order/{id}/lines', [LibraryAcquisitionController::class, 'lines'])->name('library.acquisition-order-lines')->where('id', '[0-9]+');
    Route::post('/library-manage/acquisition/order/{id}/lines', [LibraryAcquisitionController::class, 'addLine'])->name('library.acquisition-order-add-line')->where('id', '[0-9]+')->middleware('acl:update');
    Route::put('/library-manage/acquisition/order/{id}/line/{lineId}', [LibraryAcquisitionController::class, 'updateLine'])->name('library.acquisition-order-update-line')->where(['id' => '[0-9]+', 'lineId' => '[0-9]+'])->middleware('acl:update');
    Route::delete('/library-manage/acquisition/order/{id}/line/{lineId}', [LibraryAcquisitionController::class, 'removeLine'])->name('library.acquisition-order-remove-line')->where(['id' => '[0-9]+', 'lineId' => '[0-9]+'])->middleware('acl:delete');
    Route::post('/library-manage/acquisition/order/{id}/receive-all', [LibraryAcquisitionController::class, 'receiveAll'])->name('library.acquisition-order-receive-all')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/acquisition/order/{id}/receive-all-ajax', [LibraryAcquisitionController::class, 'receiveAllAjax'])->name('library.acquisition-order-receive-all-ajax')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/acquisition/order/{id}/status-ajax', [LibraryAcquisitionController::class, 'transitionAjax'])->name('library.acquisition-order-transition-ajax')->where('id', '[0-9]+')->middleware('acl:update');
    Route::get('/library-manage/acquisition/budgets', [LibraryAcquisitionController::class, 'budgets'])->name('library.acquisition-budgets');
    Route::get('/library-manage/acquisition/budget/create', [LibraryAcquisitionController::class, 'budgetCreate'])->name('library.acquisition-budget-create');
    Route::post('/library-manage/acquisition/budget', [LibraryAcquisitionController::class, 'budgetStore'])->name('library.acquisition-budget-store')->middleware('acl:create');
    Route::get('/library-manage/acquisition/budget/{id}', [LibraryAcquisitionController::class, 'budgetShow'])->name('library.acquisition-budget')->where('id', '[0-9]+');
    Route::get('/library-manage/acquisition/budget/{id}/edit', [LibraryAcquisitionController::class, 'budgetEdit'])->name('library.acquisition-budget-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/acquisition/budget/{id}', [LibraryAcquisitionController::class, 'budgetUpdate'])->name('library.acquisition-budget-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::delete('/library-manage/acquisition/budget/{id}', [LibraryAcquisitionController::class, 'budgetDestroy'])->name('library.acquisition-budget-destroy')->where('id', '[0-9]+')->middleware('acl:delete');

    // ── Loan Rules & Overdue (read-only management views) ─────────────────
    Route::get('/library-manage/circulation/loan-rules', [LibraryController::class, 'loanRules'])->name('library.loan-rules');
    Route::get('/library-manage/circulation/overdue', [LibraryController::class, 'overdue'])->name('library.overdue');
    // Standalone index + bare checkout form (no copyId) -- needed by sidebar nav links
    Route::get('/library-manage/circulation', [LibraryController::class, 'circulation'])->name('library.circulation');
    Route::get('/library-manage/circulation/checkout', [LibraryController::class, 'checkoutForm'])->name('library.checkout-form');

    // ── ILL ───────────────────────────────────────────────────────────────
    Route::get('/library-manage/ill', [LibraryController::class, 'ill'])->name('library.ill');
    Route::get('/library-manage/ill/create', [LibraryController::class, 'illCreate'])->name('library.ill-create');
    Route::post('/library-manage/ill/create', [LibraryController::class, 'illStore'])->name('library.ill-store');
    Route::get('/library-manage/ill/{id}', [LibraryController::class, 'illView'])->name('library.ill-view')->where('id', '[0-9]+');
    Route::patch('/library-manage/ill/{id}', [LibraryController::class, 'illUpdate'])->name('library.ill-update')->where('id', '[0-9]+');
    Route::post('/library-manage/ill/{id}/transition', [LibraryController::class, 'illTransition'])->name('library.ill-transition')->where('id', '[0-9]+');
    Route::delete('/library-manage/ill/{id}', [LibraryController::class, 'illDelete'])->name('library.ill-delete')->where('id', '[0-9]+');
    Route::patch('/library-manage/ill/{id}/suppress', [LibraryController::class, 'illOpacSuppress'])->name('library.ill-opac-suppress')->where('id', '[0-9]+');
    Route::get('/library-manage/ill/settings', [LibraryController::class, 'illSettings'])->name('library.ill-settings');
    Route::post('/library-manage/ill/settings', [LibraryController::class, 'illSettingsStore'])->name('library.ill-settings-store');
    Route::get('/opac/ill/create', [LibraryController::class, 'opacIllCreate'])->name('library.opac-ill-create');
    Route::post('/opac/ill/create', [LibraryController::class, 'opacIllStore'])->name('library.ill-opac-store');

    // ── ISBN ───────────────────────────────────────────────────────────────
    Route::get('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookup'])->name('library.isbn-lookup');
    Route::post('/library-manage/isbn-lookup', [LibraryController::class, 'isbnLookupSearch'])->name('library.isbn-lookup-search')->middleware('acl:create');

    // ── Patrons ────────────────────────────────────────────────────────────
    Route::get('/library-manage/patrons', [LibraryController::class, 'patrons'])->name('library.patrons');
    Route::get('/library-manage/patron/create', [LibraryController::class, 'patronCreate'])->name('library.patron-create');
    Route::post('/library-manage/patron', [LibraryController::class, 'patronStore'])->name('library.patron-store')->middleware('acl:create');
    Route::get('/library-manage/patron/{id}', [LibraryController::class, 'patronView'])->name('library.patron-view')->where('id', '[0-9]+');
    Route::get('/library-manage/patron/{id}/edit', [LibraryController::class, 'patronEdit'])->name('library.patron-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/patron/{id}', [LibraryController::class, 'patronUpdate'])->name('library.patron-update')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library-manage/patron/{id}/suspend', [LibraryController::class, 'patronSuspend'])->name('library.patron-suspend')->where('id', '[0-9]+')->middleware('acl:update');
    Route::post('/library/patron/{id}/reactivate', [LibraryController::class, 'patronReactivate'])->name('library.patron-reactivate')->where('id', '[0-9]+')->middleware('acl:update');

    // ── Serials ────────────────────────────────────────────────────────────
    Route::get('/library-manage/serials', [LibraryController::class, 'serials'])->name('library.serials');
    Route::get('/library-manage/serial/{id}', [LibraryController::class, 'serialView'])->name('library.serial-view')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/add', [LibraryController::class, 'serialCreate'])->name('library.serial-create');
    Route::post('/library-manage/serial/add', [LibraryController::class, 'serialStore'])->name('library.serial-store');
    Route::get('/library-manage/serial/{id}/edit', [LibraryController::class, 'serialEdit'])->name('library.serial-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/serial/{id}', [LibraryController::class, 'serialUpdate'])->name('library.serial-update')->where('id', '[0-9]+');
    Route::delete('/library-manage/serial/{id}', [LibraryController::class, 'serialDelete'])->name('library.serial-delete')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/issue', [LibraryController::class, 'serialAddIssue'])->name('library.serial-add-issue')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/subscription', [LibraryController::class, 'serialSubscription'])->name('library.serial-subscription')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/subscription', [LibraryController::class, 'serialSubscriptionStore'])->name('library.serial-subscription-store')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/predict', [LibraryController::class, 'serialPredict'])->name('library.serial-predict')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/coverage', [LibraryController::class, 'serialCoverage'])->name('library.serial-coverage')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/clone', [LibraryController::class, 'serialClone'])->name('library.serial-clone')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/overdue-claims', [LibraryController::class, 'serialOverdueClaims'])->name('library.serial-overdue-claims');
    Route::post('/library-manage/serial/{serialId}/claim/{issueId}', [LibraryController::class, 'serialClaimIssue'])->name('library.serial-claim-issue')->where(['serialId' => '[0-9]+', 'issueId' => '[0-9]+']);

    // ── Reports ────────────────────────────────────────────────────────────
    Route::get('/library-manage/reports', [LibraryController::class, 'libraryReports'])->name('library.reports');
    Route::get('/library-manage/reports/catalogue', [LibraryController::class, 'reportCatalogue'])->name('library.report-catalogue');
    Route::get('/library-manage/reports/creators', [LibraryController::class, 'reportCreators'])->name('library.report-creators');
    Route::get('/library-manage/reports/publishers', [LibraryController::class, 'reportPublishers'])->name('library.report-publishers');
    Route::get('/library-manage/reports/subjects', [LibraryController::class, 'reportSubjects'])->name('library.report-subjects');
    Route::get('/library-manage/reports/call-numbers', [LibraryController::class, 'reportCallNumbers'])->name('library.report-call-numbers');

    // ── KBART ─────────────────────────────────────────────────────────────
    Route::get('/library-manage/kbart', [KbartoController::class, 'index'])->name('library.kbart');
    Route::get('/library-manage/kbart/export', [KbartoController::class, 'export'])->name('library.kbart-export');
    Route::get('/library-manage/kbart/export-csv', [KbartoController::class, 'exportCsv'])->name('library.kbart-export-csv');
    Route::get('/library-manage/kbart/import', [KbartoController::class, 'import'])->name('library.kbart-import');
    Route::post('/library-manage/kbart/preview', [KbartoController::class, 'preview'])->name('library.kbart-preview');
    Route::post('/library-manage/kbart/commit', [KbartoController::class, 'commit'])->name('library.kbart-commit');
    Route::get('/library-manage/kbart/template', [KbartoController::class, 'template'])->name('library.kbart-template');
    Route::get('/library-manage/kbart/remote', [KbartAdminController::class, 'index'])->name('library.kbart-remote');
    Route::get('/library-manage/kbart/remote/log', [KbartAdminController::class, 'log'])->name('library.kbart-remote-log');
    Route::get('/library-manage/kbart/remote/create', [KbartAdminController::class, 'create'])->name('library.kbart-remote-create');
    Route::post('/library-manage/kbart/remote', [KbartAdminController::class, 'store'])->name('library.kbart-remote-store');
    Route::get('/library-manage/kbart/remote/{feed}/edit', [KbartAdminController::class, 'edit'])->name('library.kbart-remote-edit')->where('feed', '[0-9]+');
    Route::put('/library-manage/kbart/remote/{feed}', [KbartAdminController::class, 'update'])->name('library.kbart-remote-update')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/{feed}/refresh', [KbartAdminController::class, 'refresh'])->name('library.kbart-remote-refresh')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/{feed}/toggle', [KbartAdminController::class, 'toggle'])->name('library.kbart-remote-toggle')->where('feed', '[0-9]+');
    Route::delete('/library-manage/kbart/remote/{feed}', [KbartAdminController::class, 'destroy'])->name('library.kbart-remote-destroy')->where('feed', '[0-9]+');
    Route::post('/library-manage/kbart/remote/test-url', [KbartAdminController::class, 'testUrl'])->name('library.kbart-remote-test-url');

    // ── Usage / SUSHI ─────────────────────────────────────────────────────
    Route::get('/library-manage/usage', [LibraryUsageController::class, 'index'])->name('library.usage');
    Route::get('/library-manage/usage/tr', [LibraryUsageController::class, 'titleReport'])->name('library.usage-tr');
    Route::get('/library-manage/usage/dr', [LibraryUsageController::class, 'databaseReport'])->name('library.usage-dr');
    Route::get('/library-manage/usage/harvest', [LibraryUsageController::class, 'harvest'])->name('library.usage-harvest');
    Route::get('/library-manage/usage/subscriptions', [LibraryUsageController::class, 'subscriptions'])->name('library.usage-subscriptions');
    Route::post('/library-manage/usage/subscriptions', [LibraryUsageController::class, 'subscriptionsStore'])->name('library.usage-subscriptions-store');
    Route::get('/library-manage/usage/subscriptions/test', [LibraryUsageController::class, 'testConnection'])->name('library.usage-subscriptions-test');
    Route::get('/library-manage/usage/export/{type}', [LibraryUsageController::class, 'export'])->name('library.usage-export')->where('type', 'PR|TR|DR');
});

// OPAC
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

// ── Circulation Desk (Phase 2) ─────────────────────────────────────────────
// Replaces the old LibraryController-based circulation routes for checkout/return/renew/scan.
Route::middleware('auth')->group(function () {
    Route::get('/library-manage/circulation', [CirculationDeskController::class, 'index'])->name('library.circulation.index');
    Route::post('/library-manage/circulation/scan', [CirculationDeskController::class, 'scan'])->name('library.circulation.scan');
    Route::get('/library-manage/circulation/checkout/{copyId}', [CirculationDeskController::class, 'checkoutForm'])
        ->name('library.circulation.checkout')->where('copyId', '[0-9]+');
    Route::post('/library-manage/circulation/checkout', [CirculationDeskController::class, 'doCheckout'])
        ->name('library.circulation.do-checkout');
    Route::get('/library-manage/circulation/return/{checkoutId}', [CirculationDeskController::class, 'returnForm'])
        ->name('library.circulation.return')->where('checkoutId', '[0-9]+');
    Route::post('/library-manage/circulation/return', [CirculationDeskController::class, 'doReturn'])
        ->name('library.circulation.do-return');
    Route::post('/library-manage/circulation/renew', [CirculationDeskController::class, 'renew'])
        ->name('library.circulation.renew');
    Route::get('/library-manage/circulation/patron/{patronId}', [CirculationDeskController::class, 'patronHistory'])
        ->name('library.circulation.patron')->where('patronId', '[0-9]+');
    Route::get('/library-manage/circulation/loans', [CirculationDeskController::class, 'getLoans'])
        ->name('library.circulation.loans');

    // Holds
    Route::post('/library-manage/circulation/hold', [CirculationDeskController::class, 'placeHold'])
        ->name('library.hold-place');
    Route::post('/library-manage/circulation/hold/{hold}/cancel', [CirculationDeskController::class, 'cancelHold'])
        ->name('library.hold-cancel')->where('hold', '[0-9]+');
});

// ── OPAC Patron Self-Service (Phase 2) ─────────────────────────────────────
Route::get('/opac/patron/login', [OpacPatronController::class, 'login'])->name('opac.patron.login');
Route::post('/opac/patron/authenticate', [OpacPatronController::class, 'authenticate'])->name('opac.patron.authenticate');

Route::middleware(\AhgLibrary\Middleware\EnsurePatronAuthenticated::class)->group(function () {
    Route::get('/opac/patron/account', [OpacPatronController::class, 'account'])->name('opac.patron.account');
    Route::get('/opac/patron/loans', [OpacPatronController::class, 'myLoans'])->name('opac.patron.loans');
    Route::get('/opac/patron/holds', [OpacPatronController::class, 'myHolds'])->name('opac.patron.holds');
    Route::post('/opac/patron/holds/cancel', [OpacPatronController::class, 'cancelHold'])->name('opac.patron.holds.cancel');
    Route::get('/opac/patron/fines', [OpacPatronController::class, 'myFines'])->name('opac.patron.fines');
    Route::post('/opac/patron/renew-all', [OpacPatronController::class, 'renewAll'])->name('opac.patron.renew-all');
    Route::post('/opac/patron/renew-one/{checkoutId}', [OpacPatronController::class, 'renewOne'])
        ->name('opac.patron.renew-one')->where('checkoutId', '[0-9]+');
    Route::get('/opac/patron/logout', [OpacPatronController::class, 'logout'])->name('opac.patron.logout');
});
// ── Phase 2.5: EDI Trading Partners ────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/library-manage/trading-partners', [TradingPartnerController::class, 'index'])
        ->name('library.trading-partners.index');
    Route::get('/library-manage/trading-partners/create', [TradingPartnerController::class, 'create'])
        ->name('library.trading-partners.create');
    Route::post('/library-manage/trading-partners', [TradingPartnerController::class, 'store'])
        ->name('library.trading-partners.store');
    Route::get('/library-manage/trading-partners/{partner}/edit', [TradingPartnerController::class, 'edit'])
        ->name('library.trading-partners.edit');
    Route::match(['PATCH', 'PUT'], '/library-manage/trading-partners/{partner}', [TradingPartnerController::class, 'update'])
        ->name('library.trading-partners.update');
    Route::delete('/library-manage/trading-partners/{partner}', [TradingPartnerController::class, 'destroy'])
        ->name('library.trading-partners.destroy');
    Route::patch('/library-manage/trading-partners/{partner}/toggle', [TradingPartnerController::class, 'toggle'])
        ->name('library.trading-partners.toggle');
    Route::post('/library-manage/trading-partners/{partner}/test', [TradingPartnerController::class, 'test'])
        ->name('library.trading-partners.test');
    Route::post('/library-manage/trading-partners/{partner}/preview-message', [TradingPartnerController::class, 'previewMessage'])
        ->name('library.trading-partners.preview-message');
});

// ── Phase 2.5: ILL Request Management ───────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/library-manage/ill-requests', [IllRequestController::class, 'index'])
        ->name('library.ill-requests.index');
    Route::get('/library-manage/ill-requests/create', [IllRequestController::class, 'create'])
        ->name('library.ill-requests.create');
    Route::post('/library-manage/ill-requests', [IllRequestController::class, 'store'])
        ->name('library.ill-requests.store');
    Route::get('/library-manage/ill-requests/{id}', [IllRequestController::class, 'show'])
        ->name('library.ill-requests.show')->where('id', '[0-9]+');
    Route::put('/library-manage/ill-requests/{id}', [IllRequestController::class, 'update'])
        ->name('library.ill-requests.update')->where('id', '[0-9]+');
    Route::patch('/library-manage/ill-requests/{id}', [IllRequestController::class, 'update'])
        ->name('library.ill-requests.patch')->where('id', '[0-9]+');
    Route::post('/library-manage/ill-requests/{id}/transition', [IllRequestController::class, 'transition'])
        ->name('library.ill-requests.transition')->where('id', '[0-9]+');
    Route::post('/library-manage/ill-requests/{id}/send-edi', [IllRequestController::class, 'sendEdi'])
        ->name('library.ill-requests.send-edi')->where('id', '[0-9]+');
    Route::delete('/library-manage/ill-requests/{id}', [IllRequestController::class, 'destroy'])
        ->name('library.ill-requests.destroy')->where('id', '[0-9]+');
});
