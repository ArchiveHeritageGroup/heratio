<?php

use Illuminate\Support\Facades\Route;

// Per-record translate routes — gated by AclService 'translate' action so an
// editor can translate records they otherwise can't update. Mirrors AtoM's
// QubitAcl::check($resource, 'translate') ACL bit.
Route::prefix('admin/translation')->middleware(['web', 'auth', 'acl:translate'])->group(function () {
    Route::get('/translate/{slug}', [\AhgTranslation\Controllers\TranslationController::class, 'translate'])->name('ahgtranslation.translate');
    Route::post('/translate/{slug}', [\AhgTranslation\Controllers\TranslationController::class, 'store'])->name('ahgtranslation.store');

    Route::post('/apply', [\AhgTranslation\Controllers\TranslationController::class, 'apply'])->name('ahgtranslation.apply');
    Route::post('/save', [\AhgTranslation\Controllers\TranslationController::class, 'save'])->name('ahgtranslation.save');

    // Drafts review (still translate-capability gated; reviewers need it to approve/reject)
    Route::get('/drafts', [\AhgTranslation\Controllers\TranslationController::class, 'drafts'])->name('ahgtranslation.drafts');
    Route::post('/drafts/{id}/approve', [\AhgTranslation\Controllers\TranslationController::class, 'draftApprove'])->name('ahgtranslation.draft-approve');
    Route::post('/drafts/{id}/reject', [\AhgTranslation\Controllers\TranslationController::class, 'draftReject'])->name('ahgtranslation.draft-reject');
    Route::post('/drafts/batch', [\AhgTranslation\Controllers\TranslationController::class, 'draftBatch'])->name('ahgtranslation.draft-batch');
});

// Admin-only translation infrastructure routes (settings, languages, MT health probe).
// These configure the translation service itself, not per-record translation, so they
// stay admin-gated rather than acl:translate-gated.
Route::prefix('admin/translation')->middleware(['web', 'auth', 'admin'])->group(function () {
    Route::get('/settings', [\AhgTranslation\Controllers\TranslationController::class, 'settings'])->name('ahgtranslation.settings');
    Route::post('/settings', [\AhgTranslation\Controllers\TranslationController::class, 'settings']);
    Route::get('/health', [\AhgTranslation\Controllers\TranslationController::class, 'health'])->name('ahgtranslation.health');
    Route::get('/languages', [\AhgTranslation\Controllers\TranslationController::class, 'languages'])->name('ahgtranslation.languages');
    Route::post('/languages', [\AhgTranslation\Controllers\TranslationController::class, 'addLanguage'])->name('ahgtranslation.addLanguage');
});
