<?php

use AhgSemanticSearch\Controllers\DisplacedHeritageController;
use AhgSemanticSearch\Controllers\EndangeredHeritageController;
use AhgSemanticSearch\Controllers\RepatriationClaimController;
use AhgSemanticSearch\Controllers\RepatriationDialogueController;
use AhgSemanticSearch\Controllers\RepatriationKnowledgeController;
use AhgSemanticSearch\Controllers\ResearchLeadAdminController;
use AhgSemanticSearch\Controllers\ScholarshipController;
use AhgSemanticSearch\Controllers\SemanticSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('semantic-search')->group(function () {
    Route::get('/saved-searches', [SemanticSearchController::class, 'savedSearches'])->name('semantic-search.savedSearches');
    Route::get('/history', [SemanticSearchController::class, 'history'])->name('semantic-search.history');
});

Route::middleware(['auth', 'admin'])->prefix('semantic-search/admin')->group(function () {
    Route::get('/', [SemanticSearchController::class, 'index'])->name('semantic-search.index');
    Route::match(['get', 'post'], '/config', [SemanticSearchController::class, 'config'])->name('semantic-search.config');
    Route::get('/terms', [SemanticSearchController::class, 'terms'])->name('semantic-search.terms');
    Route::match(['get', 'post'], '/term/add', [SemanticSearchController::class, 'termAdd'])->name('semantic-search.term.add');
    Route::get('/term/{id}', [SemanticSearchController::class, 'termView'])->name('semantic-search.term.view');
    Route::get('/search-logs', [SemanticSearchController::class, 'searchLogs'])->name('semantic-search.searchLogs');
    Route::get('/sync-logs', [SemanticSearchController::class, 'syncLogs'])->name('semantic-search.syncLogs');
    Route::get('/templates', [SemanticSearchController::class, 'adminTemplates'])->name('semantic-search.admin.templates');
    Route::match(['get', 'post'], '/template/edit/{id?}', [SemanticSearchController::class, 'adminTemplateEdit'])->name('semantic-search.admin.template.edit');

    // heratio#1210 - generative scholarship: discovered-connections report for one
    // record. Accepts a numeric id or a slug. Admin-gated like the rest of this group.
    Route::get('/scholarship/{objectId}', [ScholarshipController::class, 'show'])
        ->name('semantic-search.scholarship.show')
        ->where('objectId', '[A-Za-z0-9][A-Za-z0-9_-]*');

    // heratio#1207 - repatriation engine, first slice (detection): the
    // "potentially displaced heritage" review register. Origin-vs-holding
    // mismatch flags for curatorial review only - not a repatriation claim.
    Route::get('/displaced-heritage', [DisplacedHeritageController::class, 'index'])
        ->name('semantic-search.displaced-heritage.index');
});

// heratio#1207 - repatriation engine, next slice: structured repatriation-claim
// workflow on top of the displaced-heritage register. Admin-gated CRUD over the
// new displaced_heritage_claim table (the public virtual-return view is bound
// separately in the provider's register()). All paths are 2-segment+ so they
// never collide with the single-segment /{slug} archival-record catch-all.
Route::middleware(['auth', 'admin'])->prefix('repatriation')->group(function () {
    Route::get('/claims', [RepatriationClaimController::class, 'index'])
        ->name('repatriation.claims.index');
    Route::get('/claims/create', [RepatriationClaimController::class, 'create'])
        ->name('repatriation.claims.create');
    Route::post('/claims', [RepatriationClaimController::class, 'store'])
        ->name('repatriation.claims.store');
    Route::get('/claims/{id}/edit', [RepatriationClaimController::class, 'edit'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.edit');
    Route::post('/claims/{id}', [RepatriationClaimController::class, 'update'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.update');
    Route::post('/claims/{id}/status', [RepatriationClaimController::class, 'status'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.status');

    // heratio#1207 - the STAFF dialogue workspace for one claim: the two-way
    // threaded dialogue (incl. internal notes), the status audit trail (status
    // change WITH a note, recorded who/when/from->to), the provenance-trace
    // links, and the shared-record ACCESS grants (mint / revoke a capability
    // token for a claimant). Writes go ONLY to the new dialogue / audit / access
    // tables (and to the claim's status via the claim service, which records the
    // transition). Admin-gated like the rest of this group.
    Route::get('/claims/{id}/dialogue', [RepatriationDialogueController::class, 'show'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.dialogue');
    Route::post('/claims/{id}/dialogue/message', [RepatriationDialogueController::class, 'message'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.dialogue.message');
    Route::post('/claims/{id}/dialogue/status', [RepatriationDialogueController::class, 'status'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.dialogue.status');
    Route::post('/claims/{id}/dialogue/grant', [RepatriationDialogueController::class, 'grant'])
        ->where('id', '[0-9]+')
        ->name('repatriation.claims.dialogue.grant');
    Route::post('/claims/{id}/dialogue/revoke/{grant}', [RepatriationDialogueController::class, 'revoke'])
        ->where('id', '[0-9]+')
        ->where('grant', '[0-9]+')
        ->name('repatriation.claims.dialogue.revoke');

    // heratio#1207 - admin moderation of community KNOWLEDGE contributions about
    // displaced items / repatriation claims (oral history, provenance,
    // corrections, source community). Writes go ONLY to the new
    // repatriation_knowledge_contribution table. Mirrors the language-revival
    // glossary / transcription moderation flow. The PUBLIC submit form is bound
    // in the provider's register() (catch-all precedence; numeric {claim} so it
    // can never shadow a slug).
    Route::get('/knowledge', [RepatriationKnowledgeController::class, 'moderate'])
        ->name('repatriation-knowledge.moderate');
    Route::post('/knowledge/{id}', [RepatriationKnowledgeController::class, 'moderateSet'])
        ->where('id', '[0-9]+')
        ->name('repatriation-knowledge.set');
});

// heratio#1205 - North Star "race against loss": endangered-heritage register +
// capture-priority list. Admin-gated worklist + flag + capture-status workflow
// over the new endangered_heritage_item table. All paths are 2-segment+ so they
// never collide with the single-segment /{slug} archival-record catch-all. The
// PUBLIC read-only register lives at the single-segment /at-risk and is bound
// separately in the provider's register() (callAfterResolving('router')) so it
// wins the match ahead of the catch-all - see
// memory/reference_slug_catchall_route_precedence.md.
Route::middleware(['auth', 'admin'])->prefix('endangered')->group(function () {
    Route::get('/priority', [EndangeredHeritageController::class, 'worklist'])
        ->name('endangered.priority');
    Route::get('/flag', [EndangeredHeritageController::class, 'flagForm'])
        ->name('endangered.flag.form');
    Route::post('/flag', [EndangeredHeritageController::class, 'flag'])
        ->name('endangered.flag');
    Route::post('/{id}/capture-status', [EndangeredHeritageController::class, 'captureStatus'])
        ->where('id', '[0-9]+')
        ->name('endangered.capture-status');
});

// heratio#1210 - North Star "generative scholarship": curation of the public
// "Research Leads" feed. Admin-gated worklist + generate + publish/dismiss/repend
// over the new research_lead table. All paths are 2-segment+ (prefixed
// /admin/research-leads) so they never collide with the single-segment /{slug}
// archival-record catch-all. The PUBLIC read-only feed lives at the single-
// segment /research-leads and /research-leads/{id} and is bound separately in the
// provider's register() (callAfterResolving('router')) so it wins the match ahead
// of the catch-all - see memory/reference_slug_catchall_route_precedence.md.
Route::middleware(['auth', 'admin'])->prefix('admin/research-leads')->group(function () {
    Route::get('/', [ResearchLeadAdminController::class, 'index'])
        ->name('research-leads.admin');
    Route::post('/generate', [ResearchLeadAdminController::class, 'generate'])
        ->name('research-leads.generate');
    Route::post('/{id}/publish', [ResearchLeadAdminController::class, 'publish'])
        ->where('id', '[0-9]+')
        ->name('research-leads.publish');
    Route::post('/{id}/dismiss', [ResearchLeadAdminController::class, 'dismiss'])
        ->where('id', '[0-9]+')
        ->name('research-leads.dismiss');
    Route::post('/{id}/repend', [ResearchLeadAdminController::class, 'repend'])
        ->where('id', '[0-9]+')
        ->name('research-leads.repend');
});

// AJAX endpoints (legacy camelCase aliases)
Route::middleware(['auth', 'admin'])->group(function () {
    Route::post('/semanticSearchAdmin/runSync', [SemanticSearchController::class, 'runSync'])->name('semantic-search.runSync');
});
// testExpand is public (called from browse semantic search modal)
Route::match(['get', 'post'], '/semanticSearchAdmin/testExpand', [SemanticSearchController::class, 'testExpand'])->name('semantic-search.testExpand');

// Legacy admin URL aliases
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/admin/semantic-search', fn () => redirect('/semantic-search/admin', 301));
    Route::get('/admin/semantic-search/config', fn () => redirect('/semantic-search/admin/config', 301));
    Route::get('/admin/semantic-search/terms', fn () => redirect('/semantic-search/admin/terms', 301));
    Route::get('/admin/semantic-search/term/{id}', fn ($id) => redirect("/semantic-search/admin/term/{$id}", 301));
    Route::get('/admin/semantic-search/term/add', fn () => redirect('/semantic-search/admin/term/add', 301));
    Route::get('/admin/semantic-search/sync-logs', fn () => redirect('/semantic-search/admin/sync-logs', 301));
    Route::get('/admin/semantic-search/search-logs', fn () => redirect('/semantic-search/admin/search-logs', 301));
});

// heratio#1208 - admin moderation of the community language-revival glossary.
// Writes go ONLY to language_revival_glossary. The public read-only corpus
// surfaces are bound in the provider's register() (catch-all precedence).
Route::middleware(['auth', 'admin'])->prefix('language-corpus-admin')->group(function () {
    Route::get('/glossary', [\AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'moderate'])
        ->name('language-corpus.glossary.moderate');
    Route::post('/glossary/{id}', [\AhgSemanticSearch\Controllers\LanguageCorpusController::class, 'moderateSet'])
        ->where('id', '[0-9]+')
        ->name('language-corpus.glossary.set');

    // heratio#1208 - admin moderation of community TRANSCRIPTION / correction /
    // translation contributions. Writes go ONLY to
    // language_transcription_contribution. Mirrors the glossary moderation flow
    // above. The public submit form is bound in the provider's register()
    // (catch-all precedence; numeric {item} so it can never shadow a slug).
    Route::get('/transcriptions', [\AhgSemanticSearch\Controllers\LanguageTranscriptionController::class, 'moderate'])
        ->name('language-transcribe.moderate');
    Route::post('/transcriptions/{id}', [\AhgSemanticSearch\Controllers\LanguageTranscriptionController::class, 'moderateSet'])
        ->where('id', '[0-9]+')
        ->name('language-transcribe.set');
});
