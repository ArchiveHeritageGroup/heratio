<?php

use AhgSharePoint\Controllers\SharePointController;
use AhgSharePoint\Controllers\SharePointFederatedSearchController;
use AhgSharePoint\Controllers\SharePointWebhookController;
use Illuminate\Support\Facades\Route;

// Phase 1 — admin foundation
Route::prefix('sharepoint')->group(function () {
    Route::get('/',                            [SharePointController::class, 'index'])->name('sharepoint.index');
    Route::get('/tenants',                     [SharePointController::class, 'tenants'])->name('sharepoint.tenants');
    Route::match(['get', 'post'], '/tenants/{id}',         [SharePointController::class, 'tenantEdit'])->whereNumber('id')->name('sharepoint.tenant.edit');
    Route::post('/tenants/{id}/test',          [SharePointController::class, 'tenantTest'])->whereNumber('id')->name('sharepoint.tenant.test');
    Route::get('/drives',                      [SharePointController::class, 'drives'])->name('sharepoint.drives');
    Route::get('/drives/browse',               [SharePointController::class, 'driveBrowse'])->name('sharepoint.drives.browse');
    Route::match(['get', 'post'], '/drives/{id}/mapping',  [SharePointController::class, 'mapping'])->whereNumber('id')->name('sharepoint.drives.mapping');

    // Phase 2.A — subscription + event admin UI
    Route::get('/subscriptions',                          [SharePointController::class, 'subscriptions'])->name('sharepoint.subscriptions');
    Route::get('/events',                                 [SharePointController::class, 'events'])->name('sharepoint.events');
    Route::match(['get', 'post'], '/events/{id}',         [SharePointController::class, 'eventDetail'])->whereNumber('id')->name('sharepoint.events.detail');

    // Phase 2.B — User mapping admin
    Route::get('/user-mappings',                          [\AhgSharePoint\Controllers\SharePointUserMappingController::class, 'index'])->name('sharepoint.user-mappings');
    Route::match(['get', 'post'], '/user-mappings/{id}',  [\AhgSharePoint\Controllers\SharePointUserMappingController::class, 'edit'])->whereNumber('id')->name('sharepoint.user-mapping.edit');

    // Phase 3
    Route::get('/federated-search',            [SharePointFederatedSearchController::class, 'search'])->name('sharepoint.federated-search');

    // Phase 2 — v2 ingest plan: auto-ingest rules + per-drive mapping templates
    Route::get('/rules',                       [SharePointController::class, 'rules'])->name('sharepoint.rules');
    Route::get('/rules/edit',                  [SharePointController::class, 'ruleEdit'])->name('sharepoint.rule.edit');
    Route::post('/rules/save',                 [SharePointController::class, 'ruleSave'])->name('sharepoint.rule.save');
    Route::post('/rules/{id}/delete',          [SharePointController::class, 'ruleDelete'])->whereNumber('id')->name('sharepoint.rule.delete');
    Route::post('/rules/{id}/run',             [SharePointController::class, 'ruleRun'])->whereNumber('id')->name('sharepoint.rule.run');
    Route::get('/mappings',                    [SharePointController::class, 'mappings'])->name('sharepoint.mappings');
    Route::post('/mappings/save',              [SharePointController::class, 'mappingsSave'])->name('sharepoint.mappings.save');
    Route::post('/mappings/template/delete',   [SharePointController::class, 'mappingTemplateDelete'])->name('sharepoint.mappings.template.delete');
    Route::get('/columns',                     [SharePointController::class, 'columns'])->name('sharepoint.columns');
});

// Phase 2 — Graph webhook receiver. PUBLIC, NO CSRF.
// Excluded from VerifyCsrfToken middleware — see App\Http\Middleware\VerifyCsrfToken
// $except array (must be added during Phase 2 implementation).
Route::match(['get', 'post'], '/sharepoint/webhook', [SharePointWebhookController::class, 'receive'])
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('sharepoint.webhook');
