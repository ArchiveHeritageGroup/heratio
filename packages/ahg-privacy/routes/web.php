<?php

use Illuminate\Support\Facades\Route;
use AhgPrivacy\Controllers\PrivacyController;

// Legacy AtoM URL alias
Route::get('/privacyAdmin', fn () => redirect('/admin/privacy/index'));
Route::get('/privacyAdmin/{action}', fn (string $action) => redirect('/admin/privacy/' . \Illuminate\Support\Str::kebab($action)));

Route::prefix('admin/privacy')->middleware(['web', 'auth'])->group(function () {
    Route::get('/complaint-confirmation', [PrivacyController::class, 'complaintConfirmation'])->name('ahgprivacy.complaint-confirmation');
    Route::get('/complaint', [PrivacyController::class, 'complaint'])->name('ahgprivacy.complaint');
    Route::get('/dashboard', [PrivacyController::class, 'dashboard'])->name('ahgprivacy.dashboard');
    Route::get('/dsar-confirmation', [PrivacyController::class, 'dsarConfirmation'])->name('ahgprivacy.dsar-confirmation');
    Route::get('/dsar-request', [PrivacyController::class, 'dsarRequest'])->name('ahgprivacy.dsar-request');
    Route::get('/dsar-status', [PrivacyController::class, 'dsarStatus'])->name('ahgprivacy.dsar-status');
    Route::get('/index', [PrivacyController::class, 'index'])->name('ahgprivacy.index');
    Route::get('/breach-add', [PrivacyController::class, 'breachAdd'])->name('ahgprivacy.breach-add');
    Route::get('/breach-edit', [PrivacyController::class, 'breachEdit'])->name('ahgprivacy.breach-edit');
    Route::get('/breach-list', [PrivacyController::class, 'breachList'])->name('ahgprivacy.breach-list');
    Route::get('/breach-view', [PrivacyController::class, 'breachView'])->name('ahgprivacy.breach-view');
    Route::get('/complaint-add', [PrivacyController::class, 'complaintAdd'])->name('ahgprivacy.complaint-add');
    Route::get('/complaint-edit', [PrivacyController::class, 'complaintEdit'])->name('ahgprivacy.complaint-edit');
    Route::get('/complaint-list', [PrivacyController::class, 'complaintList'])->name('ahgprivacy.complaint-list');
    Route::get('/complaint-view', [PrivacyController::class, 'complaintView'])->name('ahgprivacy.complaint-view');
    Route::match(['get', 'post'], '/config', [PrivacyController::class, 'config'])->name('ahgprivacy.config');
    Route::get('/consent-add', [PrivacyController::class, 'consentAdd'])->name('ahgprivacy.consent-add');
    Route::get('/consent-edit', [PrivacyController::class, 'consentEdit'])->name('ahgprivacy.consent-edit');
    Route::get('/consent-list', [PrivacyController::class, 'consentList'])->name('ahgprivacy.consent-list');
    Route::get('/consent-view', [PrivacyController::class, 'consentView'])->name('ahgprivacy.consent-view');
    Route::get('/dsar-add', [PrivacyController::class, 'dsarAdd'])->name('ahgprivacy.dsar-add');
    Route::get('/dsar-edit', [PrivacyController::class, 'dsarEdit'])->name('ahgprivacy.dsar-edit');
    Route::get('/dsar-list', [PrivacyController::class, 'dsarList'])->name('ahgprivacy.dsar-list');
    Route::get('/dsar-view', [PrivacyController::class, 'dsarView'])->name('ahgprivacy.dsar-view');
    Route::get('/jurisdiction-add', [PrivacyController::class, 'jurisdictionAdd'])->name('ahgprivacy.jurisdiction-add');
    Route::get('/jurisdiction-edit', [PrivacyController::class, 'jurisdictionEdit'])->name('ahgprivacy.jurisdiction-edit');
    Route::get('/jurisdiction-info', [PrivacyController::class, 'jurisdictionInfo'])->name('ahgprivacy.jurisdiction-info');
    Route::get('/jurisdiction-list', [PrivacyController::class, 'jurisdictionList'])->name('ahgprivacy.jurisdiction-list');
    Route::get('/jurisdictions', [PrivacyController::class, 'jurisdictions'])->name('ahgprivacy.jurisdictions');
    Route::get('/notifications', [PrivacyController::class, 'notifications'])->name('ahgprivacy.notifications');
    Route::get('/officer-add', [PrivacyController::class, 'officerAdd'])->name('ahgprivacy.officer-add');
    Route::get('/officer-edit', [PrivacyController::class, 'officerEdit'])->name('ahgprivacy.officer-edit');
    Route::get('/officer-list', [PrivacyController::class, 'officerList'])->name('ahgprivacy.officer-list');
    Route::get('/paia-add', [PrivacyController::class, 'paiaAdd'])->name('ahgprivacy.paia-add');
    Route::get('/paia-list', [PrivacyController::class, 'paiaList'])->name('ahgprivacy.paia-list');
    Route::get('/pii-review', [PrivacyController::class, 'piiReview'])->name('ahgprivacy.pii-review');
    Route::get('/pii-scan-object', [PrivacyController::class, 'piiScanObject'])->name('ahgprivacy.pii-scan-object');
    Route::get('/pii-scan', [PrivacyController::class, 'piiScan'])->name('ahgprivacy.pii-scan');
    Route::get('/report', [PrivacyController::class, 'report'])->name('ahgprivacy.report');
    Route::get('/ropa-add', [PrivacyController::class, 'ropaAdd'])->name('ahgprivacy.ropa-add');
    Route::get('/ropa-edit', [PrivacyController::class, 'ropaEdit'])->name('ahgprivacy.ropa-edit');
    Route::get('/ropa-list', [PrivacyController::class, 'ropaList'])->name('ahgprivacy.ropa-list');
    Route::get('/ropa-view', [PrivacyController::class, 'ropaView'])->name('ahgprivacy.ropa-view');
    Route::get('/visual-redaction-editor', [PrivacyController::class, 'visualRedactionEditor'])->name('ahgprivacy.visual-redaction-editor');

    // Phase X.2 — POST handlers cloned from PSIS privacyAdmin actions
    Route::post('/dsar-update', [PrivacyController::class, 'dsarUpdate'])->name('ahgprivacy.dsar-update');
    Route::post('/breach-update', [PrivacyController::class, 'breachUpdate'])->name('ahgprivacy.breach-update');
    Route::post('/consent-withdraw', [PrivacyController::class, 'consentWithdraw'])->name('ahgprivacy.consent-withdraw');
    Route::post('/ropa-submit', [PrivacyController::class, 'ropaSubmit'])->name('ahgprivacy.ropa-submit');
    Route::post('/ropa-approve', [PrivacyController::class, 'ropaApprove'])->name('ahgprivacy.ropa-approve');
    Route::post('/ropa-reject', [PrivacyController::class, 'ropaReject'])->name('ahgprivacy.ropa-reject');
});
