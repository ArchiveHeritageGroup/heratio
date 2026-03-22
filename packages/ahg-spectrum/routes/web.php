<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/spectrum')->middleware(['web'])->group(function () {
    Route::get('/condition-admin', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionAdmin'])->name('ahgspectrum.condition-admin');
    Route::get('/condition-photos', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionPhotos'])->name('ahgspectrum.condition-photos');
    Route::get('/condition-risk', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionRisk'])->name('ahgspectrum.condition-risk');
    Route::get('/dashboard', [\AhgSpectrum\Controllers\SpectrumController::class, 'dashboard'])->name('ahgspectrum.dashboard');
    Route::get('/data-quality', [\AhgSpectrum\Controllers\SpectrumController::class, 'dataQuality'])->name('ahgspectrum.data-quality');
    Route::get('/export', [\AhgSpectrum\Controllers\SpectrumController::class, 'export'])->name('ahgspectrum.export');
    Route::get('/general', [\AhgSpectrum\Controllers\SpectrumController::class, 'general'])->name('ahgspectrum.general');
    Route::get('/general-workflow', [\AhgSpectrum\Controllers\SpectrumController::class, 'generalWorkflow'])->name('ahgspectrum.general-workflow');
    Route::get('/grap-dashboard', [\AhgSpectrum\Controllers\SpectrumController::class, 'grapDashboard'])->name('ahgspectrum.grap-dashboard');
    Route::get('/index', [\AhgSpectrum\Controllers\SpectrumController::class, 'index'])->name('ahgspectrum.index');
    Route::get('/label', [\AhgSpectrum\Controllers\SpectrumController::class, 'label'])->name('ahgspectrum.label');
    Route::get('/my-tasks', [\AhgSpectrum\Controllers\SpectrumController::class, 'myTasks'])->name('ahgspectrum.my-tasks');
    Route::get('/privacy-admin', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyAdmin'])->name('ahgspectrum.privacy-admin');
    Route::get('/privacy-breaches', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyBreaches'])->name('ahgspectrum.privacy-breaches');
    Route::get('/privacy-compliance', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyCompliance'])->name('ahgspectrum.privacy-compliance');
    Route::get('/privacy-dsar', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyDsar'])->name('ahgspectrum.privacy-dsar');
    Route::get('/privacy-ropa', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyRopa'])->name('ahgspectrum.privacy-ropa');
    Route::get('/privacy-templates', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyTemplates'])->name('ahgspectrum.privacy-templates');
    Route::get('/security-compliance', [\AhgSpectrum\Controllers\SpectrumController::class, 'securityCompliance'])->name('ahgspectrum.security-compliance');
    Route::get('/spectrum-export', [\AhgSpectrum\Controllers\SpectrumController::class, 'spectrumExport'])->name('ahgspectrum.spectrum-export');
    Route::get('/workflow', [\AhgSpectrum\Controllers\SpectrumController::class, 'workflow'])->name('ahgspectrum.workflow');
    Route::get('/acquisitions', [\AhgSpectrum\Controllers\SpectrumController::class, 'acquisitions'])->name('ahgspectrum.acquisitions');
    Route::get('/conditions', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditions'])->name('ahgspectrum.conditions');
    Route::get('/conservation', [\AhgSpectrum\Controllers\SpectrumController::class, 'conservation'])->name('ahgspectrum.conservation');
    Route::get('/loans', [\AhgSpectrum\Controllers\SpectrumController::class, 'loans'])->name('ahgspectrum.loans');
    Route::get('/movements', [\AhgSpectrum\Controllers\SpectrumController::class, 'movements'])->name('ahgspectrum.movements');
    Route::get('/object-entry', [\AhgSpectrum\Controllers\SpectrumController::class, 'objectEntry'])->name('ahgspectrum.object-entry');
    Route::get('/valuations', [\AhgSpectrum\Controllers\SpectrumController::class, 'valuations'])->name('ahgspectrum.valuations');
});
