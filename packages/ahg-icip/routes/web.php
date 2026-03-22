<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/icip')->middleware(['web'])->group(function () {
    Route::get('/communities', [\AhgMarketplace\Controllers\MarketplaceController::class, 'communities'])->name('ahgicip.communities');
    Route::get('/community-edit', [\AhgMarketplace\Controllers\MarketplaceController::class, 'communityEdit'])->name('ahgicip.community-edit');
    Route::get('/community-view', [\AhgMarketplace\Controllers\MarketplaceController::class, 'communityView'])->name('ahgicip.community-view');
    Route::get('/consent-edit', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consentEdit'])->name('ahgicip.consent-edit');
    Route::get('/consent-list', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consentList'])->name('ahgicip.consent-list');
    Route::get('/consent-view', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consentView'])->name('ahgicip.consent-view');
    Route::get('/consultation-edit', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consultationEdit'])->name('ahgicip.consultation-edit');
    Route::get('/consultation-view', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consultationView'])->name('ahgicip.consultation-view');
    Route::get('/consultations', [\AhgMarketplace\Controllers\MarketplaceController::class, 'consultations'])->name('ahgicip.consultations');
    Route::get('/dashboard', [\AhgMarketplace\Controllers\MarketplaceController::class, 'dashboard'])->name('ahgicip.dashboard');
    Route::get('/notice-types', [\AhgMarketplace\Controllers\MarketplaceController::class, 'noticeTypes'])->name('ahgicip.notice-types');
    Route::get('/notices', [\AhgMarketplace\Controllers\MarketplaceController::class, 'notices'])->name('ahgicip.notices');
    Route::get('/object-consent', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectConsent'])->name('ahgicip.object-consent');
    Route::get('/object-consultations', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectConsultations'])->name('ahgicip.object-consultations');
    Route::get('/object-icip', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectIcip'])->name('ahgicip.object-icip');
    Route::get('/object-labels', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectLabels'])->name('ahgicip.object-labels');
    Route::get('/object-notices', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectNotices'])->name('ahgicip.object-notices');
    Route::get('/object-restrictions', [\AhgMarketplace\Controllers\MarketplaceController::class, 'objectRestrictions'])->name('ahgicip.object-restrictions');
    Route::get('/report-community', [\AhgMarketplace\Controllers\MarketplaceController::class, 'reportCommunity'])->name('ahgicip.report-community');
    Route::get('/report-expiry', [\AhgMarketplace\Controllers\MarketplaceController::class, 'reportExpiry'])->name('ahgicip.report-expiry');
    Route::get('/report-pending', [\AhgMarketplace\Controllers\MarketplaceController::class, 'reportPending'])->name('ahgicip.report-pending');
    Route::get('/reports', [\AhgMarketplace\Controllers\MarketplaceController::class, 'reports'])->name('ahgicip.reports');
    Route::get('/restrictions', [\AhgMarketplace\Controllers\MarketplaceController::class, 'restrictions'])->name('ahgicip.restrictions');
    Route::get('/tk-labels', [\AhgMarketplace\Controllers\MarketplaceController::class, 'tkLabels'])->name('ahgicip.tk-labels');
});
