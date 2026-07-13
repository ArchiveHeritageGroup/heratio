<?php

use AhgPrivacy\Controllers\Article30Controller;
use AhgPrivacy\Controllers\ComplianceAutopilotController;
use AhgPrivacy\Controllers\ComplianceControlController;
use AhgPrivacy\Controllers\DescriptionPrivacyController;
use AhgPrivacy\Controllers\DpiaController;
use AhgPrivacy\Controllers\EmbeddedFindingsController;
use AhgPrivacy\Controllers\PrivacyController;
use Illuminate\Support\Facades\Route;

// Legacy AtoM URL alias
Route::get('/privacyAdmin', fn () => redirect('/admin/privacy/index'));
Route::get('/privacyAdmin/{action}', fn (string $action) => redirect('/admin/privacy/'.\Illuminate\Support\Str::kebab($action)));

// ====================================================================
// DPO / ADMIN MANAGEMENT SURFACE (heratio#1369)
// Gated to staff only via `admin` (RequireAdmin -> AclService::canAdmin,
// ADMINISTRATOR/EDITOR). Previously this entire surface was gated only by
// ['dp.enabled','auth'], letting ANY authenticated user read/edit/delete
// every data subject's DSAR/breach/complaint/consent/ROPA/DPIA/Article-30/
// PII records (broad IDOR). `dp.enabled` feature flag is retained.
// ====================================================================
Route::prefix('admin/privacy')->middleware(['dp.enabled', 'auth', 'admin'])->group(function () {
    // ------------------------------------------------------------------
    // Compliance Control Catalog - regime -> obligation -> control mapping
    // (vendor- and jurisdiction-agnostic). Admin index + queryable JSON artefact.
    // ------------------------------------------------------------------
    Route::get('/control-catalog', [ComplianceControlController::class, 'index'])->name('ahgprivacy.control-catalog');
    Route::get('/control-catalog.json', [ComplianceControlController::class, 'json'])->name('ahgprivacy.control-catalog.json');

    // ------------------------------------------------------------------
    // Issue #1108: field-level structured redaction on archival descriptions
    // ------------------------------------------------------------------
    Route::get('/description/{id}/redaction', [DescriptionPrivacyController::class, 'panel'])->name('ahgprivacy.description.redaction')->whereNumber('id');
    Route::post('/description/{id}/redaction', [DescriptionPrivacyController::class, 'saveProfile'])->whereNumber('id');
    Route::post('/description/{id}/redaction/field', [DescriptionPrivacyController::class, 'addField'])->whereNumber('id');
    Route::post('/description/{id}/redaction/field/{fieldId}/remove', [DescriptionPrivacyController::class, 'removeField'])->whereNumber('id')->whereNumber('fieldId');
    // ------------------------------------------------------------------
    // Issue #669 Phase 1: Article 30 register + DPIA workflow
    // ------------------------------------------------------------------
    // heratio#1199 - compliance autopilot: scan catalogue for PII -> auto-draft a ROPA entry
    Route::get('/autopilot',         [ComplianceAutopilotController::class, 'index'])->name('ahgprivacy.autopilot');
    Route::post('/autopilot/scan',   [ComplianceAutopilotController::class, 'scanAjax'])->name('ahgprivacy.autopilot.scan');
    Route::post('/autopilot/create', [ComplianceAutopilotController::class, 'createRopa'])->name('ahgprivacy.autopilot.create');
    // heratio#1199 retention slice: auto-draft a retention schedule + DPO accept
    Route::post('/autopilot/retention',            [ComplianceAutopilotController::class, 'draftRetention'])->name('ahgprivacy.autopilot.retention');
    Route::post('/autopilot/retention/{id}/accept', [ComplianceAutopilotController::class, 'acceptRetention'])->name('ahgprivacy.autopilot.retention.accept')->whereNumber('id');
    // heratio#1199 DPIA slice: screen the scan (DpiaRiskService) + auto-draft a DPIA for DPO review
    Route::post('/autopilot/dpia',            [ComplianceAutopilotController::class, 'draftDpia'])->name('ahgprivacy.autopilot.dpia');
    Route::post('/autopilot/dpia/{id}/accept', [ComplianceAutopilotController::class, 'acceptDpia'])->name('ahgprivacy.autopilot.dpia.accept')->whereNumber('id');

    Route::get('/article-30',            [Article30Controller::class, 'index'])->name('ahgprivacy.article-30.index');
    Route::get('/article-30/export',     [Article30Controller::class, 'export'])->name('ahgprivacy.article-30.export');
    Route::get('/article-30/new',        [Article30Controller::class, 'create'])->name('ahgprivacy.article-30.create');
    Route::post('/article-30',           [Article30Controller::class, 'store'])->name('ahgprivacy.article-30.store');
    Route::get('/article-30/{id}/edit',  [Article30Controller::class, 'edit'])->name('ahgprivacy.article-30.edit')->whereNumber('id');
    Route::put('/article-30/{id}',       [Article30Controller::class, 'update'])->name('ahgprivacy.article-30.update')->whereNumber('id');
    Route::delete('/article-30/{id}',    [Article30Controller::class, 'destroy'])->name('ahgprivacy.article-30.destroy')->whereNumber('id');

    // ------------------------------------------------------------------
    // Issue #751 Phase 2: PII findings over embedded image metadata
    // (EXIF / IPTC / XMP) - admin review + resolution UI.
    // ------------------------------------------------------------------
    Route::get('/embedded-findings',                  [EmbeddedFindingsController::class, 'index'])->name('ahgprivacy.embedded-findings.index');
    Route::post('/embedded-findings/{id}/resolve',    [EmbeddedFindingsController::class, 'resolve'])->name('ahgprivacy.embedded-findings.resolve')->whereNumber('id');

    Route::get('/dpia',                  [DpiaController::class, 'index'])->name('ahgprivacy.dpia.index');
    Route::get('/dpia/new',              [DpiaController::class, 'create'])->name('ahgprivacy.dpia.create');
    Route::post('/dpia',                 [DpiaController::class, 'store'])->name('ahgprivacy.dpia.store');
    Route::get('/dpia/{id}/edit',        [DpiaController::class, 'edit'])->name('ahgprivacy.dpia.edit')->whereNumber('id');
    Route::put('/dpia/{id}',             [DpiaController::class, 'update'])->name('ahgprivacy.dpia.update')->whereNumber('id');
    Route::post('/dpia/{id}/review',     [DpiaController::class, 'moveToReview'])->name('ahgprivacy.dpia.review')->whereNumber('id');
    Route::post('/dpia/{id}/signoff',    [DpiaController::class, 'signOff'])->name('ahgprivacy.dpia.signoff')->whereNumber('id');
    Route::post('/dpia/{id}/archive',    [DpiaController::class, 'archive'])->name('ahgprivacy.dpia.archive')->whereNumber('id');

    // #1108 deliverable 5 - DSAR redaction scope (pre-populate IO privacy profiles)
    Route::get('/dsar/{id}/scope',              [PrivacyController::class, 'dsarScope'])->name('ahgprivacy.dsar-scope')->whereNumber('id');
    Route::post('/dsar/{id}/scope',             [PrivacyController::class, 'dsarScopeAdd'])->name('ahgprivacy.dsar-scope-add')->whereNumber('id');
    Route::post('/dsar/{id}/scope/{ioId}/remove', [PrivacyController::class, 'dsarScopeRemove'])->name('ahgprivacy.dsar-scope-remove')->whereNumber('id')->whereNumber('ioId');

    Route::get('/dashboard', [PrivacyController::class, 'dashboard'])->name('ahgprivacy.dashboard');
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
    Route::post('/dsar-add', [PrivacyController::class, 'dsarAddStore'])->name('ahgprivacy.dsar-add.store');
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
    Route::post('/pii-scan-run', [PrivacyController::class, 'piiScanRun'])->name('ahgprivacy.pii-scan-run');
    Route::post('/pii-entity-action', [PrivacyController::class, 'piiEntityAction'])->name('ahgprivacy.pii-entity-action');
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

// ====================================================================
// DATA-SUBJECT SELF-SERVICE SURFACE (heratio#1369)
// Stays on ['dp.enabled','auth'] (any logged-in user). Lets a data subject
// submit and check their OWN request. Ownership is enforced in-controller:
// dsarStatus scopes by created_by / requestor_email (see PrivacyController).
// These MUST NOT be admin-gated or self-service breaks.
// ====================================================================
Route::prefix('admin/privacy')->middleware(['dp.enabled', 'auth'])->group(function () {
    Route::get('/dsar-request', [PrivacyController::class, 'dsarRequest'])->name('ahgprivacy.dsar-request');
    Route::post('/dsar-request', [PrivacyController::class, 'dsarRequestStore'])->name('ahgprivacy.dsar-request.store');
    Route::get('/dsar-status', [PrivacyController::class, 'dsarStatus'])->name('ahgprivacy.dsar-status');
    Route::get('/dsar-confirmation', [PrivacyController::class, 'dsarConfirmation'])->name('ahgprivacy.dsar-confirmation');
    Route::get('/complaint', [PrivacyController::class, 'complaint'])->name('ahgprivacy.complaint');
    Route::get('/complaint-confirmation', [PrivacyController::class, 'complaintConfirmation'])->name('ahgprivacy.complaint-confirmation');
});
