<?php

use Illuminate\Support\Facades\Route;

// =====================================================================
// Primary routes (admin/icip prefix, query-parameter style)
// =====================================================================
Route::prefix('admin/icip')->middleware(['web', 'auth'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [\AhgIcip\Controllers\IcipController::class, 'dashboard'])->name('ahgicip.dashboard');

    // Communities
    Route::get('/communities', [\AhgIcip\Controllers\IcipController::class, 'communities'])->name('ahgicip.communities');
    Route::match(['get', 'post'], '/community-edit', [\AhgIcip\Controllers\IcipController::class, 'communityEdit'])->name('ahgicip.community-edit'); // ACL must be checked in controller (Route::match)
    Route::get('/community-view', [\AhgIcip\Controllers\IcipController::class, 'communityView'])->name('ahgicip.community-view');
    Route::post('/community-delete', [\AhgIcip\Controllers\IcipController::class, 'communityDelete'])->name('ahgicip.community-delete')->middleware('acl:delete');

    // Consent
    Route::get('/consent-list', [\AhgIcip\Controllers\IcipController::class, 'consentList'])->name('ahgicip.consent-list');
    Route::match(['get', 'post'], '/consent-edit', [\AhgIcip\Controllers\IcipController::class, 'consentEdit'])->name('ahgicip.consent-edit'); // ACL must be checked in controller (Route::match)
    Route::get('/consent-view', [\AhgIcip\Controllers\IcipController::class, 'consentView'])->name('ahgicip.consent-view');

    // Consultations
    Route::get('/consultations', [\AhgIcip\Controllers\IcipController::class, 'consultations'])->name('ahgicip.consultations');
    Route::match(['get', 'post'], '/consultation-edit', [\AhgIcip\Controllers\IcipController::class, 'consultationEdit'])->name('ahgicip.consultation-edit'); // ACL must be checked in controller (Route::match)
    Route::get('/consultation-view', [\AhgIcip\Controllers\IcipController::class, 'consultationView'])->name('ahgicip.consultation-view');

    // TK Labels
    Route::get('/tk-labels', [\AhgIcip\Controllers\IcipController::class, 'tkLabels'])->name('ahgicip.tk-labels');

    // Cultural Notices
    Route::get('/notices', [\AhgIcip\Controllers\IcipController::class, 'notices'])->name('ahgicip.notices');
    Route::match(['get', 'post'], '/notice-types', [\AhgIcip\Controllers\IcipController::class, 'noticeTypes'])->name('ahgicip.notice-types'); // ACL must be checked in controller (Route::match)

    // Access Restrictions
    Route::get('/restrictions', [\AhgIcip\Controllers\IcipController::class, 'restrictions'])->name('ahgicip.restrictions');

    // Reports
    Route::get('/reports', [\AhgIcip\Controllers\IcipController::class, 'reports'])->name('ahgicip.reports');
    Route::get('/report-pending', [\AhgIcip\Controllers\IcipController::class, 'reportPending'])->name('ahgicip.report-pending');
    Route::get('/report-expiry', [\AhgIcip\Controllers\IcipController::class, 'reportExpiry'])->name('ahgicip.report-expiry');
    Route::get('/report-community', [\AhgIcip\Controllers\IcipController::class, 'reportCommunity'])->name('ahgicip.report-community');

    // Object-specific ICIP
    Route::get('/object-icip', [\AhgIcip\Controllers\IcipController::class, 'objectIcip'])->name('ahgicip.object-icip');
    Route::match(['get', 'post'], '/object-consent', [\AhgIcip\Controllers\IcipController::class, 'objectConsent'])->name('ahgicip.object-consent'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/object-notices', [\AhgIcip\Controllers\IcipController::class, 'objectNotices'])->name('ahgicip.object-notices'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/object-labels', [\AhgIcip\Controllers\IcipController::class, 'objectLabels'])->name('ahgicip.object-labels'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/object-restrictions', [\AhgIcip\Controllers\IcipController::class, 'objectRestrictions'])->name('ahgicip.object-restrictions'); // ACL must be checked in controller (Route::match)
    Route::get('/object-consultations', [\AhgIcip\Controllers\IcipController::class, 'objectConsultations'])->name('ahgicip.object-consultations');

    // Acknowledgement
    Route::post('/acknowledge', [\AhgIcip\Controllers\IcipController::class, 'acknowledge'])->name('ahgicip.acknowledge')->middleware('acl:create');

    // API endpoints
    Route::get('/api/summary', [\AhgIcip\Controllers\IcipController::class, 'apiSummary'])->name('ahgicip.api.summary');
    Route::get('/api/check-access', [\AhgIcip\Controllers\IcipController::class, 'apiCheckAccess'])->name('ahgicip.api.check-access');
});

// =====================================================================
// AtoM-compatible alias routes (icip/ prefix, path-parameter style)
// These map AtoM-style URLs to the existing controller methods by
// merging path parameters into the request as query parameters.
// =====================================================================
Route::prefix('icip')->middleware(['web', 'auth'])->group(function () {

    // 1. icip → dashboard
    Route::get('/', fn () => redirect()->route('ahgicip.dashboard'));

    // 2. icip/communities → communities browse
    Route::get('/communities', fn () => redirect()->route('ahgicip.communities', request()->query()));

    // 3-4. icip/community/add → communityEdit (add)
    Route::match(['get', 'post'], '/community/add', [\AhgIcip\Controllers\IcipController::class, 'communityEdit']);

    // 5-6. icip/community/{id}/edit → communityEdit (edit)
    Route::match(['get', 'post'], '/community/{id}/edit', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->communityEdit(request());
    });

    // 7. icip/community/{id} → communityView
    Route::get('/community/{id}', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->communityView(request());
    });

    // 8. icip/community/{id}/delete → communityDelete
    Route::post('/community/{id}/delete', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->communityDelete(request());
    })->middleware('acl:delete');

    // 9. icip/consent → consentList
    Route::get('/consent', fn () => redirect()->route('ahgicip.consent-list', request()->query()));

    // 10-11. icip/consent/add → consentEdit (add)
    Route::match(['get', 'post'], '/consent/add', [\AhgIcip\Controllers\IcipController::class, 'consentEdit']);

    // 12-13. icip/consent/{id}/edit → consentEdit (edit)
    Route::match(['get', 'post'], '/consent/{id}/edit', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->consentEdit(request());
    });

    // 14. icip/consent/{id} → consentView
    Route::get('/consent/{id}', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->consentView(request());
    });

    // 15. icip/consultations → consultations browse
    Route::get('/consultations', fn () => redirect()->route('ahgicip.consultations', request()->query()));

    // 16-17. icip/consultation/add → consultationEdit (add)
    Route::match(['get', 'post'], '/consultation/add', [\AhgIcip\Controllers\IcipController::class, 'consultationEdit']);

    // 18-19. icip/consultation/{id}/edit → consultationEdit (edit)
    Route::match(['get', 'post'], '/consultation/{id}/edit', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->consultationEdit(request());
    });

    // 20. icip/consultation/{id} → consultationView
    Route::get('/consultation/{id}', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->consultationView(request());
    });

    // 21. icip/tk-labels → tkLabels
    Route::get('/tk-labels', fn () => redirect()->route('ahgicip.tk-labels', request()->query()));

    // 22. icip/notices → notices
    Route::get('/notices', fn () => redirect()->route('ahgicip.notices', request()->query()));

    // 23. icip/notice-types → noticeTypes (GET + POST)
    Route::match(['get', 'post'], '/notice-types', [\AhgIcip\Controllers\IcipController::class, 'noticeTypes']);

    // 24. icip/restrictions → restrictions
    Route::get('/restrictions', fn () => redirect()->route('ahgicip.restrictions', request()->query()));

    // 25. icip/reports → reports
    Route::get('/reports', fn () => redirect()->route('ahgicip.reports'));

    // 26. icip/reports/pending-consultation → reportPending
    Route::get('/reports/pending-consultation', fn () => redirect()->route('ahgicip.report-pending', request()->query()));

    // 27. icip/reports/consent-expiry → reportExpiry
    Route::get('/reports/consent-expiry', fn () => redirect()->route('ahgicip.report-expiry', request()->query()));

    // 28. icip/reports/community/{id} → reportCommunity
    Route::get('/reports/community/{id}', function ($id) {
        request()->merge(['id' => $id]);
        return app(\AhgIcip\Controllers\IcipController::class)->reportCommunity(request());
    });

    // 29. icip/acknowledge/{notice_id} → acknowledge
    Route::post('/acknowledge/{notice_id}', function ($noticeId) {
        request()->merge(['notice_id' => $noticeId]);
        return app(\AhgIcip\Controllers\IcipController::class)->acknowledge(request());
    })->middleware('acl:create');

    // 30. icip/api/summary/{object_id} → apiSummary (JSON)
    Route::get('/api/summary/{object_id}', function ($objectId) {
        request()->merge(['object_id' => $objectId]);
        return app(\AhgIcip\Controllers\IcipController::class)->apiSummary(request());
    });

    // 31. icip/api/check-access/{object_id} → apiCheckAccess (JSON)
    Route::get('/api/check-access/{object_id}', function ($objectId) {
        request()->merge(['object_id' => $objectId]);
        return app(\AhgIcip\Controllers\IcipController::class)->apiCheckAccess(request());
    });
});

// =====================================================================
// Object-specific ICIP routes (AtoM-compatible: object/{slug}/icip/*)
// These use the slug as a path parameter instead of a query parameter.
// =====================================================================
Route::prefix('object/{slug}/icip')->middleware(['web', 'auth'])->group(function () {

    // object/{slug}/icip → objectIcip
    Route::get('/', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectIcip(request());
    });

    // object/{slug}/icip/consent → objectConsent (GET + POST)
    Route::match(['get', 'post'], '/consent', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectConsent(request());
    });

    // object/{slug}/icip/notices → objectNotices (GET + POST)
    Route::match(['get', 'post'], '/notices', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectNotices(request());
    });

    // object/{slug}/icip/labels → objectLabels (GET + POST)
    Route::match(['get', 'post'], '/labels', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectLabels(request());
    });

    // object/{slug}/icip/restrictions → objectRestrictions (GET + POST)
    Route::match(['get', 'post'], '/restrictions', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectRestrictions(request());
    });

    // object/{slug}/icip/consultations → objectConsultations
    Route::get('/consultations', function ($slug) {
        request()->merge(['slug' => $slug]);
        return app(\AhgIcip\Controllers\IcipController::class)->objectConsultations(request());
    });
});
