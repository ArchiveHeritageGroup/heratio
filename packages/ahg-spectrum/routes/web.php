<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/spectrum')->middleware(['web'])->group(function () {
    Route::get('/condition-admin', [\AhgPrivacy\Controllers\PrivacyController::class, 'conditionAdmin'])->name('ahgspectrum.condition-admin');
    Route::get('/condition-photos', [\AhgPrivacy\Controllers\PrivacyController::class, 'conditionPhotos'])->name('ahgspectrum.condition-photos');
    Route::get('/condition-risk', [\AhgPrivacy\Controllers\PrivacyController::class, 'conditionRisk'])->name('ahgspectrum.condition-risk');
    Route::get('/dashboard', [\AhgPrivacy\Controllers\PrivacyController::class, 'dashboard'])->name('ahgspectrum.dashboard');
    Route::get('/data-quality', [\AhgPrivacy\Controllers\PrivacyController::class, 'dataQuality'])->name('ahgspectrum.data-quality');
    Route::get('/export', [\AhgPrivacy\Controllers\PrivacyController::class, 'export'])->name('ahgspectrum.export');
    Route::get('/general', [\AhgPrivacy\Controllers\PrivacyController::class, 'general'])->name('ahgspectrum.general');
    Route::get('/general-workflow', [\AhgPrivacy\Controllers\PrivacyController::class, 'generalWorkflow'])->name('ahgspectrum.general-workflow');
    Route::get('/grap-dashboard', [\AhgPrivacy\Controllers\PrivacyController::class, 'grapDashboard'])->name('ahgspectrum.grap-dashboard');
    Route::get('/index', [\AhgPrivacy\Controllers\PrivacyController::class, 'index'])->name('ahgspectrum.index');
    Route::get('/label', [\AhgPrivacy\Controllers\PrivacyController::class, 'label'])->name('ahgspectrum.label');
    Route::get('/my-tasks', [\AhgPrivacy\Controllers\PrivacyController::class, 'myTasks'])->name('ahgspectrum.my-tasks');
    Route::get('/privacy-admin', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyAdmin'])->name('ahgspectrum.privacy-admin');
    Route::get('/privacy-breaches', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyBreaches'])->name('ahgspectrum.privacy-breaches');
    Route::get('/privacy-compliance', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyCompliance'])->name('ahgspectrum.privacy-compliance');
    Route::get('/privacy-dsar', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyDsar'])->name('ahgspectrum.privacy-dsar');
    Route::get('/privacy-ropa', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyRopa'])->name('ahgspectrum.privacy-ropa');
    Route::get('/privacy-templates', [\AhgPrivacy\Controllers\PrivacyController::class, 'privacyTemplates'])->name('ahgspectrum.privacy-templates');
    Route::get('/security-compliance', [\AhgPrivacy\Controllers\PrivacyController::class, 'securityCompliance'])->name('ahgspectrum.security-compliance');
    Route::get('/spectrum-export', [\AhgPrivacy\Controllers\PrivacyController::class, 'spectrumExport'])->name('ahgspectrum.spectrum-export');
    Route::get('/workflow', [\AhgPrivacy\Controllers\PrivacyController::class, 'workflow'])->name('ahgspectrum.workflow');
    Route::get('/acquisitions', [\AhgPrivacy\Controllers\PrivacyController::class, 'acquisitions'])->name('ahgspectrum.acquisitions');
    Route::get('/conditions', [\AhgPrivacy\Controllers\PrivacyController::class, 'conditions'])->name('ahgspectrum.conditions');
    Route::get('/conservation', [\AhgPrivacy\Controllers\PrivacyController::class, 'conservation'])->name('ahgspectrum.conservation');
    Route::get('/loans', [\AhgPrivacy\Controllers\PrivacyController::class, 'loans'])->name('ahgspectrum.loans');
    Route::get('/movements', [\AhgPrivacy\Controllers\PrivacyController::class, 'movements'])->name('ahgspectrum.movements');
    Route::get('/object-entry', [\AhgPrivacy\Controllers\PrivacyController::class, 'objectEntry'])->name('ahgspectrum.object-entry');
    Route::get('/valuations', [\AhgPrivacy\Controllers\PrivacyController::class, 'valuations'])->name('ahgspectrum.valuations');
});
