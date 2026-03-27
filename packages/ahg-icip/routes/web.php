<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/icip')->middleware(['web', 'auth'])->group(function () {
    Route::get('/communities', [\AhgIcip\Controllers\IcipController::class, 'communities'])->name('ahgicip.communities');
    Route::get('/community-edit', [\AhgIcip\Controllers\IcipController::class, 'communityEdit'])->name('ahgicip.community-edit');
    Route::get('/community-view', [\AhgIcip\Controllers\IcipController::class, 'communityView'])->name('ahgicip.community-view');
    Route::get('/consent-edit', [\AhgIcip\Controllers\IcipController::class, 'consentEdit'])->name('ahgicip.consent-edit');
    Route::get('/consent-list', [\AhgIcip\Controllers\IcipController::class, 'consentList'])->name('ahgicip.consent-list');
    Route::get('/consent-view', [\AhgIcip\Controllers\IcipController::class, 'consentView'])->name('ahgicip.consent-view');
    Route::get('/consultation-edit', [\AhgIcip\Controllers\IcipController::class, 'consultationEdit'])->name('ahgicip.consultation-edit');
    Route::get('/consultation-view', [\AhgIcip\Controllers\IcipController::class, 'consultationView'])->name('ahgicip.consultation-view');
    Route::get('/consultations', [\AhgIcip\Controllers\IcipController::class, 'consultations'])->name('ahgicip.consultations');
    Route::get('/dashboard', [\AhgIcip\Controllers\IcipController::class, 'dashboard'])->name('ahgicip.dashboard');
    Route::get('/notice-types', [\AhgIcip\Controllers\IcipController::class, 'noticeTypes'])->name('ahgicip.notice-types');
    Route::get('/notices', [\AhgIcip\Controllers\IcipController::class, 'notices'])->name('ahgicip.notices');
    Route::get('/object-consent', [\AhgIcip\Controllers\IcipController::class, 'objectConsent'])->name('ahgicip.object-consent');
    Route::get('/object-consultations', [\AhgIcip\Controllers\IcipController::class, 'objectConsultations'])->name('ahgicip.object-consultations');
    Route::get('/object-icip', [\AhgIcip\Controllers\IcipController::class, 'objectIcip'])->name('ahgicip.object-icip');
    Route::get('/object-labels', [\AhgIcip\Controllers\IcipController::class, 'objectLabels'])->name('ahgicip.object-labels');
    Route::get('/object-notices', [\AhgIcip\Controllers\IcipController::class, 'objectNotices'])->name('ahgicip.object-notices');
    Route::get('/object-restrictions', [\AhgIcip\Controllers\IcipController::class, 'objectRestrictions'])->name('ahgicip.object-restrictions');
    Route::get('/report-community', [\AhgIcip\Controllers\IcipController::class, 'reportCommunity'])->name('ahgicip.report-community');
    Route::get('/report-expiry', [\AhgIcip\Controllers\IcipController::class, 'reportExpiry'])->name('ahgicip.report-expiry');
    Route::get('/report-pending', [\AhgIcip\Controllers\IcipController::class, 'reportPending'])->name('ahgicip.report-pending');
    Route::get('/reports', [\AhgIcip\Controllers\IcipController::class, 'reports'])->name('ahgicip.reports');
    Route::get('/restrictions', [\AhgIcip\Controllers\IcipController::class, 'restrictions'])->name('ahgicip.restrictions');
    Route::get('/tk-labels', [\AhgIcip\Controllers\IcipController::class, 'tkLabels'])->name('ahgicip.tk-labels');
});
