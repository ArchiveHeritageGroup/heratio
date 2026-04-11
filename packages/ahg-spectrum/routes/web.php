<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/spectrum')->middleware(['web', 'auth'])->group(function () {
    Route::get('/condition-admin', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionAdmin'])->name('ahgspectrum.condition-admin');
    Route::get('/condition-photos', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionPhotos'])->name('ahgspectrum.condition-photos');
    Route::get('/condition-risk', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditionRisk'])->name('ahgspectrum.condition-risk');
    Route::get('/dashboard', [\AhgSpectrum\Controllers\SpectrumController::class, 'dashboard'])->name('ahgspectrum.dashboard');
    Route::get('/data-quality', [\AhgSpectrum\Controllers\SpectrumController::class, 'dataQuality'])->name('ahgspectrum.data-quality');
    Route::get('/export', [\AhgSpectrum\Controllers\SpectrumController::class, 'export'])->name('ahgspectrum.export');
    Route::get('/general', [\AhgSpectrum\Controllers\SpectrumController::class, 'general'])->name('ahgspectrum.general');
    Route::match(['get', 'post'], '/general-workflow', [\AhgSpectrum\Controllers\SpectrumController::class, 'generalWorkflow'])->name('ahgspectrum.general-workflow');
    Route::get('/grap-dashboard', [\AhgSpectrum\Controllers\SpectrumController::class, 'grapDashboard'])->name('ahgspectrum.grap-dashboard');
    Route::get('/index', [\AhgSpectrum\Controllers\SpectrumController::class, 'index'])->name('ahgspectrum.index');
    Route::get('/label', [\AhgSpectrum\Controllers\SpectrumController::class, 'label'])->name('ahgspectrum.label');
    Route::get('/my-tasks', [\AhgSpectrum\Controllers\SpectrumController::class, 'myTasks'])->name('ahgspectrum.my-tasks');
    Route::get('/privacy-admin', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyAdmin'])->name('ahgspectrum.privacy-admin');
    Route::match(['get', 'post'], '/privacy-breaches', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyBreaches'])->name('ahgspectrum.privacy-breaches');
    Route::get('/privacy-compliance', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyCompliance'])->name('ahgspectrum.privacy-compliance');
    Route::match(['get', 'post'], '/privacy-dsar', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyDsar'])->name('ahgspectrum.privacy-dsar');
    Route::match(['get', 'post'], '/privacy-ropa', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyRopa'])->name('ahgspectrum.privacy-ropa');
    Route::match(['get', 'post'], '/privacy-templates', [\AhgSpectrum\Controllers\SpectrumController::class, 'privacyTemplates'])->name('ahgspectrum.privacy-templates');
    Route::get('/security-compliance', [\AhgSpectrum\Controllers\SpectrumController::class, 'securityCompliance'])->name('ahgspectrum.security-compliance');
    Route::get('/spectrum-export', [\AhgSpectrum\Controllers\SpectrumController::class, 'spectrumExport'])->name('ahgspectrum.spectrum-export');
    Route::get('/workflow', [\AhgSpectrum\Controllers\SpectrumController::class, 'workflow'])->name('ahgspectrum.workflow');
    Route::post('/workflow-transition', [\AhgSpectrum\Controllers\SpectrumController::class, 'workflowTransition'])->name('ahgspectrum.workflow-transition');
    Route::post('/workflow-sop', [\AhgSpectrum\Controllers\SpectrumController::class, 'workflowSop'])->name('ahgspectrum.workflow-sop');

    // Notifications
    Route::get('/notifications', [\AhgSpectrum\Controllers\SpectrumController::class, 'notifications'])->name('ahgspectrum.notifications');
    Route::post('/notification/mark-read', [\AhgSpectrum\Controllers\SpectrumController::class, 'notificationMarkRead'])->name('ahgspectrum.notification.mark-read');
    Route::post('/notification/mark-all-read', [\AhgSpectrum\Controllers\SpectrumController::class, 'notificationMarkAllRead'])->name('ahgspectrum.notification.mark-all-read');
    Route::get('/acquisitions', [\AhgSpectrum\Controllers\SpectrumController::class, 'acquisitions'])->name('ahgspectrum.acquisitions');
    Route::get('/conditions', [\AhgSpectrum\Controllers\SpectrumController::class, 'conditions'])->name('ahgspectrum.conditions');
    Route::get('/conservation', [\AhgSpectrum\Controllers\SpectrumController::class, 'conservation'])->name('ahgspectrum.conservation');
    Route::get('/loans', [\AhgSpectrum\Controllers\SpectrumController::class, 'loans'])->name('ahgspectrum.loans');
    Route::get('/movements', [\AhgSpectrum\Controllers\SpectrumController::class, 'movements'])->name('ahgspectrum.movements');
    Route::get('/object-entry', [\AhgSpectrum\Controllers\SpectrumController::class, 'objectEntry'])->name('ahgspectrum.object-entry');
    Route::get('/valuations', [\AhgSpectrum\Controllers\SpectrumController::class, 'valuations'])->name('ahgspectrum.valuations');

    // Spectrum Reports (cloned from AtoM spectrumReports module)
    Route::get('/reports', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportIndex'])->name('ahgspectrum.reports');
    Route::get('/reports/object-entry', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportObjectEntry'])->name('ahgspectrum.report-object-entry');
    Route::get('/reports/acquisitions', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportAcquisitions'])->name('ahgspectrum.report-acquisitions');
    Route::get('/reports/loans', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportLoans'])->name('ahgspectrum.report-loans');
    Route::get('/reports/movements', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportMovements'])->name('ahgspectrum.report-movements');
    Route::get('/reports/conditions', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportConditions'])->name('ahgspectrum.report-conditions');
    Route::get('/reports/conservation', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportConservation'])->name('ahgspectrum.report-conservation');
    Route::get('/reports/valuations', [\AhgSpectrum\Controllers\SpectrumController::class, 'reportValuations'])->name('ahgspectrum.report-valuations');

    // Condition photo annotations
    Route::post('/save-annotations', [\AhgSpectrum\Controllers\SpectrumController::class, 'saveAnnotations'])->name('spectrum.saveAnnotations');
    Route::get('/get-annotations', [\AhgSpectrum\Controllers\SpectrumController::class, 'getAnnotations'])->name('spectrum.getAnnotations');
    Route::get('/export-annotated-photo', [\AhgSpectrum\Controllers\SpectrumController::class, 'exportAnnotatedPhoto'])->name('spectrum.exportAnnotatedPhoto');
});
