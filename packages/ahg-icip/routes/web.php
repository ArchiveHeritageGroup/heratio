<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/icip')->middleware(['web', 'auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [\AhgIcip\Controllers\IcipController::class, 'dashboard'])->name('ahgicip.dashboard');

    // Communities
    Route::get('/communities', [\AhgIcip\Controllers\IcipController::class, 'communities'])->name('ahgicip.communities');
    Route::match(['get', 'post'], '/community-edit', [\AhgIcip\Controllers\IcipController::class, 'communityEdit'])->name('ahgicip.community-edit');
    Route::get('/community-view', [\AhgIcip\Controllers\IcipController::class, 'communityView'])->name('ahgicip.community-view');
    Route::post('/community-delete', [\AhgIcip\Controllers\IcipController::class, 'communityDelete'])->name('ahgicip.community-delete');

    // Consent
    Route::get('/consent-list', [\AhgIcip\Controllers\IcipController::class, 'consentList'])->name('ahgicip.consent-list');
    Route::match(['get', 'post'], '/consent-edit', [\AhgIcip\Controllers\IcipController::class, 'consentEdit'])->name('ahgicip.consent-edit');
    Route::get('/consent-view', [\AhgIcip\Controllers\IcipController::class, 'consentView'])->name('ahgicip.consent-view');

    // Consultations
    Route::get('/consultations', [\AhgIcip\Controllers\IcipController::class, 'consultations'])->name('ahgicip.consultations');
    Route::match(['get', 'post'], '/consultation-edit', [\AhgIcip\Controllers\IcipController::class, 'consultationEdit'])->name('ahgicip.consultation-edit');
    Route::get('/consultation-view', [\AhgIcip\Controllers\IcipController::class, 'consultationView'])->name('ahgicip.consultation-view');

    // TK Labels
    Route::get('/tk-labels', [\AhgIcip\Controllers\IcipController::class, 'tkLabels'])->name('ahgicip.tk-labels');

    // Cultural Notices
    Route::get('/notices', [\AhgIcip\Controllers\IcipController::class, 'notices'])->name('ahgicip.notices');
    Route::match(['get', 'post'], '/notice-types', [\AhgIcip\Controllers\IcipController::class, 'noticeTypes'])->name('ahgicip.notice-types');

    // Access Restrictions
    Route::get('/restrictions', [\AhgIcip\Controllers\IcipController::class, 'restrictions'])->name('ahgicip.restrictions');

    // Reports
    Route::get('/reports', [\AhgIcip\Controllers\IcipController::class, 'reports'])->name('ahgicip.reports');
    Route::get('/report-pending', [\AhgIcip\Controllers\IcipController::class, 'reportPending'])->name('ahgicip.report-pending');
    Route::get('/report-expiry', [\AhgIcip\Controllers\IcipController::class, 'reportExpiry'])->name('ahgicip.report-expiry');
    Route::get('/report-community', [\AhgIcip\Controllers\IcipController::class, 'reportCommunity'])->name('ahgicip.report-community');

    // Object-specific ICIP
    Route::get('/object-icip', [\AhgIcip\Controllers\IcipController::class, 'objectIcip'])->name('ahgicip.object-icip');
    Route::match(['get', 'post'], '/object-consent', [\AhgIcip\Controllers\IcipController::class, 'objectConsent'])->name('ahgicip.object-consent');
    Route::match(['get', 'post'], '/object-notices', [\AhgIcip\Controllers\IcipController::class, 'objectNotices'])->name('ahgicip.object-notices');
    Route::match(['get', 'post'], '/object-labels', [\AhgIcip\Controllers\IcipController::class, 'objectLabels'])->name('ahgicip.object-labels');
    Route::match(['get', 'post'], '/object-restrictions', [\AhgIcip\Controllers\IcipController::class, 'objectRestrictions'])->name('ahgicip.object-restrictions');
    Route::get('/object-consultations', [\AhgIcip\Controllers\IcipController::class, 'objectConsultations'])->name('ahgicip.object-consultations');

    // Acknowledgement
    Route::post('/acknowledge', [\AhgIcip\Controllers\IcipController::class, 'acknowledge'])->name('ahgicip.acknowledge');

    // API endpoints
    Route::get('/api/summary', [\AhgIcip\Controllers\IcipController::class, 'apiSummary'])->name('ahgicip.api.summary');
    Route::get('/api/check-access', [\AhgIcip\Controllers\IcipController::class, 'apiCheckAccess'])->name('ahgicip.api.check-access');
});
