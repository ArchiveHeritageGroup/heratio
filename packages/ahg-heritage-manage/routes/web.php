<?php

use AhgHeritageManage\Controllers\HeritageController;
use AhgHeritageManage\Controllers\HeritageAccountingController;
use AhgHeritageManage\Controllers\GrapComplianceController;
use AhgHeritageManage\Controllers\HeritageAdminController;
use AhgHeritageManage\Controllers\HeritageReportController;
use Illuminate\Support\Facades\Route;

// Public heritage landing page
Route::get('/heritage', [HeritageController::class, 'landing'])->name('heritage.landing');

// Heritage sub-pages (match AtoM URL structure)
Route::get('/heritage/search', [HeritageController::class, 'search'])->name('heritage.search');
Route::get('/heritage/timeline', [HeritageController::class, 'timeline'])->name('heritage.timeline');
Route::get('/heritage/creators', [HeritageController::class, 'creators'])->name('heritage.creators');
Route::get('/heritage/explore', [HeritageController::class, 'explore'])->name('heritage.explore');
Route::get('/heritage/graph', [HeritageController::class, 'graph'])->name('heritage.graph');
Route::get('/heritage/trending', [HeritageController::class, 'trending'])->name('heritage.trending');
Route::get('/heritage/login', [HeritageController::class, 'login'])->name('heritage.login');
Route::get('/heritage/collections', [HeritageController::class, 'collections'])->name('heritage.collections');
Route::get('/heritage/entity/{id}', [HeritageController::class, 'entity'])->name('heritage.entity');
Route::get('/heritage/error', [HeritageController::class, 'landingError'])->name('heritage.landing-error');
Route::get('/heritage/search-error', [HeritageController::class, 'searchError'])->name('heritage.search-error');

// Contributor routes (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/heritage/contribute', [HeritageController::class, 'contribute'])->name('heritage.contribute');
    Route::get('/heritage/my-contributions', [HeritageController::class, 'myContributions'])->name('heritage.my-contributions');
    Route::get('/heritage/my-access-requests', [HeritageController::class, 'myAccessRequests'])->name('heritage.my-access-requests');
    Route::get('/heritage/request-access/{id?}', [HeritageController::class, 'requestAccess'])->name('heritage.request-access');
    Route::get('/heritage/contributor/profile', [HeritageController::class, 'contributorProfile'])->name('heritage.contributor-profile');
});

// Contributor auth (public)
Route::get('/heritage/contributor/login', [HeritageController::class, 'contributorLogin'])->name('heritage.contributor-login');
Route::get('/heritage/contributor/register', [HeritageController::class, 'contributorRegister'])->name('heritage.contributor-register');
Route::get('/heritage/contributor/verify', [HeritageController::class, 'contributorVerify'])->name('heritage.contributor-verify');

// Admin heritage routes
Route::middleware('admin')->group(function () {
    Route::get('/heritage/admin', [HeritageController::class, 'adminDashboard'])->name('heritage.admin');
    Route::get('/heritage/analytics', [HeritageController::class, 'analyticsDashboard'])->name('heritage.analytics');
    Route::get('/heritage/custodian', [HeritageController::class, 'custodianDashboard'])->name('heritage.custodian');

    // Admin sub-pages
    Route::get('/heritage/admin/access-requests', [HeritageController::class, 'adminAccessRequests'])->name('heritage.admin-access-requests');
    Route::get('/heritage/admin/branding', [HeritageController::class, 'adminBranding'])->name('heritage.admin-branding');
    Route::get('/heritage/admin/config', [HeritageController::class, 'adminConfig'])->name('heritage.admin-config');
    Route::get('/heritage/admin/embargoes', [HeritageController::class, 'adminEmbargoes'])->name('heritage.admin-embargoes');
    Route::get('/heritage/admin/featured-collections', [HeritageController::class, 'adminFeaturedCollections'])->name('heritage.admin-featured-collections');
    Route::get('/heritage/admin/features', [HeritageController::class, 'adminFeatures'])->name('heritage.admin-features');
    Route::get('/heritage/admin/hero-slides', [HeritageController::class, 'adminHeroSlides'])->name('heritage.admin-hero-slides');
    Route::get('/heritage/admin/popia', [HeritageController::class, 'adminPopia'])->name('heritage.admin-popia');
    Route::get('/heritage/admin/users', [HeritageController::class, 'adminUsers'])->name('heritage.admin-users');

    // Analytics sub-pages
    Route::get('/heritage/analytics/alerts', [HeritageController::class, 'analyticsAlerts'])->name('heritage.analytics-alerts');
    Route::get('/heritage/analytics/content', [HeritageController::class, 'analyticsContent'])->name('heritage.analytics-content');
    Route::get('/heritage/analytics/search', [HeritageController::class, 'analyticsSearch'])->name('heritage.analytics-search');

    // Custodian sub-pages
    Route::get('/heritage/custodian/batch', [HeritageController::class, 'custodianBatch'])->name('heritage.custodian-batch');
    Route::get('/heritage/custodian/history', [HeritageController::class, 'custodianHistory'])->name('heritage.custodian-history');
    Route::get('/heritage/custodian/item/{id}', [HeritageController::class, 'custodianItem'])->name('heritage.custodian-item');

    // Review & leaderboard
    Route::get('/heritage/review-queue', [HeritageController::class, 'reviewQueue'])->name('heritage.review-queue');
    Route::get('/heritage/review/{id}', [HeritageController::class, 'reviewContribution'])->name('heritage.review-contribution');
    Route::get('/heritage/leaderboard', [HeritageController::class, 'leaderboard'])->name('heritage.leaderboard');

    // Heritage Accounting
    Route::prefix('heritage/accounting')->group(function () {
        Route::get('/', [HeritageAccountingController::class, 'dashboard'])->name('heritage.accounting.dashboard');
        Route::get('/browse', [HeritageAccountingController::class, 'browse'])->name('heritage.accounting.browse');
        Route::get('/add', [HeritageAccountingController::class, 'add'])->name('heritage.accounting.add');
        Route::post('/store', [HeritageAccountingController::class, 'store'])->name('heritage.accounting.store');
        Route::get('/{id}/edit', [HeritageAccountingController::class, 'edit'])->name('heritage.accounting.edit');
        Route::put('/{id}', [HeritageAccountingController::class, 'update'])->name('heritage.accounting.update');
        Route::get('/{id}', [HeritageAccountingController::class, 'view'])->name('heritage.accounting.view');
        Route::get('/by-object/{id}', [HeritageAccountingController::class, 'viewByObject'])->name('heritage.accounting.view-by-object');
        Route::get('/add-valuation/{id?}', [HeritageAccountingController::class, 'addValuation'])->name('heritage.accounting.add-valuation');
        Route::get('/add-impairment/{id?}', [HeritageAccountingController::class, 'addImpairment'])->name('heritage.accounting.add-impairment');
        Route::get('/add-journal/{id?}', [HeritageAccountingController::class, 'addJournal'])->name('heritage.accounting.add-journal');
        Route::get('/add-movement/{id?}', [HeritageAccountingController::class, 'addMovement'])->name('heritage.accounting.add-movement');
        Route::get('/settings', [HeritageAccountingController::class, 'settings'])->name('heritage.accounting.settings');
    });

    // GRAP Compliance
    Route::prefix('heritage/grap')->group(function () {
        Route::get('/', [GrapComplianceController::class, 'dashboard'])->name('heritage.grap.dashboard');
        Route::get('/batch-check', [GrapComplianceController::class, 'batchCheck'])->name('heritage.grap.batch-check');
        Route::get('/check/{id?}', [GrapComplianceController::class, 'check'])->name('heritage.grap.check');
        Route::get('/national-treasury-report', [GrapComplianceController::class, 'nationalTreasuryReport'])->name('heritage.grap.national-treasury-report');
    });

    // Heritage Admin (accounting standards, rules, regions)
    Route::prefix('heritage/hadmin')->group(function () {
        Route::get('/', [HeritageAdminController::class, 'index'])->name('heritage.hadmin.index');
        Route::get('/regions', [HeritageAdminController::class, 'regions'])->name('heritage.hadmin.regions');
        Route::get('/region/{id}', [HeritageAdminController::class, 'regionInfo'])->name('heritage.hadmin.region-info');
        Route::get('/rules', [HeritageAdminController::class, 'ruleList'])->name('heritage.hadmin.rule-list');
        Route::get('/rule/add', [HeritageAdminController::class, 'ruleAdd'])->name('heritage.hadmin.rule-add');
        Route::get('/rule/{id}/edit', [HeritageAdminController::class, 'ruleEdit'])->name('heritage.hadmin.rule-edit');
        Route::get('/standards', [HeritageAdminController::class, 'standardList'])->name('heritage.hadmin.standard-list');
        Route::get('/standard/add', [HeritageAdminController::class, 'standardAdd'])->name('heritage.hadmin.standard-add');
        Route::get('/standard/{id}/edit', [HeritageAdminController::class, 'standardEdit'])->name('heritage.hadmin.standard-edit');
    });

    // Heritage Reports
    Route::prefix('heritage/reports')->group(function () {
        Route::get('/', [HeritageReportController::class, 'index'])->name('heritage.hreport.index');
        Route::get('/asset-register', [HeritageReportController::class, 'assetRegister'])->name('heritage.hreport.asset-register');
        Route::get('/movement', [HeritageReportController::class, 'movement'])->name('heritage.hreport.movement');
        Route::get('/valuation', [HeritageReportController::class, 'valuation'])->name('heritage.hreport.valuation');
    });
});
