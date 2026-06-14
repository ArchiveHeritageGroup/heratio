<?php

use AhgResearch\Controllers\ResearchController;
use AhgResearch\Controllers\ResearchReproductionsController;
use AhgResearch\Controllers\ResearchAnnotationsController;
use AhgResearch\Controllers\ResearchCitationsController;
use AhgResearch\Controllers\ResearchNotebooksController;
use AhgResearch\Controllers\ResearchReportsController;
use AhgResearch\Controllers\ResearchBibliographiesController;
use AhgResearch\Controllers\ResearchCopilotController;
use AhgResearch\Controllers\ResearchAiDecisionController;
use AhgResearch\Controllers\AuditController;
use AhgResearch\Controllers\ResearchJournalController;
use AhgResearch\Controllers\ResearchLectureController;
use AhgResearch\Controllers\ResearchTargetJournalController;
use AhgResearch\Controllers\ResearchTrainingController;
use AhgResearch\Controllers\ResearchWorkspaceController;
use AhgResearch\Controllers\ResearchValidationQueueController;
use AhgResearch\Controllers\ResearchEntityResolutionController;
use AhgResearch\Controllers\ResearchOdrlPoliciesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Research Portal Routes
|--------------------------------------------------------------------------
| Migrated from AtoM: ahgResearchPlugin/config/routing.yml
| All routes follow the AtoM URL structure: /research/*
*/

// Public research routes
Route::prefix('research')->name('research.')->group(function () {
    Route::get('/publicRegister', [ResearchController::class, 'publicRegister'])->name('publicRegister');
    Route::post('/public-register', [ResearchController::class, 'publicRegister'])->name('publicRegister.store');
    Route::get('/registrationComplete', [ResearchController::class, 'registrationComplete'])->name('registrationComplete');
    Route::get('/cite/{slug}', [ResearchCitationsController::class, 'cite'])->name('cite');
    Route::get('/cite/{slug}/export/{format}', [ResearchCitationsController::class, 'citeExport'])->name('citeExport')->where('format', 'ris|bibtex|endnote|apa|mla|chicago');

    // ORCID public-record lookup for register-form auto-populate (rate-limited
    // in the controller). Public so both the staff register + public-register
    // forms can prefill from an entered ORCID iD without an account.
    Route::post('/orcid/fetch-public', [ResearchController::class, 'orcidFetchPublic'])->name('orcidFetchPublic');
});

Route::prefix('research')->name('research.')->middleware('auth')->group(function () {

    // heratio#1198 - researcher copilot: question -> cited synthesis from the catalogue,
    // savable (with citations) into a research workspace
    Route::get('/copilot', [ResearchCopilotController::class, 'index'])->name('copilot');
    Route::post('/copilot/ask', [ResearchCopilotController::class, 'askAjax'])->name('copilot.ask');
    Route::post('/copilot/save', [ResearchCopilotController::class, 'saveAjax'])->name('copilot.save');
    Route::get('/copilot/answers', [ResearchCopilotController::class, 'answersAjax'])->name('copilot.answers');

    // heratio#1252 - Accept/Reject an AI suggestion. Thin JSON endpoint posted to
    // by the <x-research::ai-decision> component. Full name: research.ai.decision.
    Route::post('/ai/decision', [ResearchAiDecisionController::class, 'decision'])->name('ai.decision');

    // AJAX autocomplete endpoints (JSON)
    Route::get('/researcher-autocomplete', [ResearchOdrlPoliciesController::class, 'researcherAutocomplete'])->name('researcherAutocomplete');
    Route::get('/target-autocomplete', [ResearchOdrlPoliciesController::class, 'targetAutocomplete'])->name('targetAutocomplete');

    // #1105 Journal builder — institutional publication + manuscript workspace
    Route::prefix('journals')->name('journal-builder.')->group(function () {
        Route::get('/', [ResearchJournalController::class, 'index'])->name('index');
        Route::get('/create', [ResearchJournalController::class, 'create'])->name('create');
        Route::post('/', [ResearchJournalController::class, 'store'])->name('store');
        Route::get('/{id}', [ResearchJournalController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/edit', [ResearchJournalController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [ResearchJournalController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [ResearchJournalController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/{id}/status', [ResearchJournalController::class, 'setStatus'])->whereNumber('id')->name('status');
        // Issues
        Route::post('/{journalId}/issues', [ResearchJournalController::class, 'storeIssue'])->whereNumber('journalId')->name('issue-store');
        Route::put('/issue/{id}', [ResearchJournalController::class, 'updateIssue'])->whereNumber('id')->name('issue-update');
        Route::delete('/issue/{id}', [ResearchJournalController::class, 'destroyIssue'])->whereNumber('id')->name('issue-destroy');
        // Articles / manuscript builder
        Route::get('/{journalId}/article/create', [ResearchJournalController::class, 'createArticle'])->whereNumber('journalId')->name('article-create');
        Route::post('/{journalId}/article', [ResearchJournalController::class, 'storeArticle'])->whereNumber('journalId')->name('article-store');
        Route::get('/article/{id}/edit', [ResearchJournalController::class, 'editArticle'])->whereNumber('id')->name('article-edit');
        Route::put('/article/{id}', [ResearchJournalController::class, 'updateArticle'])->whereNumber('id')->name('article-update');
        Route::delete('/article/{id}', [ResearchJournalController::class, 'destroyArticle'])->whereNumber('id')->name('article-destroy');
    });

    // #1105 Lecture builder — curriculum content / talk records / standalone authoring
    Route::prefix('lectures')->name('lecture-builder.')->group(function () {
        Route::get('/', [ResearchLectureController::class, 'index'])->name('index');
        Route::get('/create', [ResearchLectureController::class, 'create'])->name('create');
        Route::post('/', [ResearchLectureController::class, 'store'])->name('store');
        Route::get('/{id}', [ResearchLectureController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/edit', [ResearchLectureController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [ResearchLectureController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [ResearchLectureController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/{id}/status', [ResearchLectureController::class, 'setStatus'])->whereNumber('id')->name('status');
        // Sections
        Route::post('/{lectureId}/sections', [ResearchLectureController::class, 'storeSection'])->whereNumber('lectureId')->name('section-store');
        Route::get('/section/{id}/edit', [ResearchLectureController::class, 'editSection'])->whereNumber('id')->name('section-edit');
        Route::put('/section/{id}', [ResearchLectureController::class, 'updateSection'])->whereNumber('id')->name('section-update');
        Route::delete('/section/{id}', [ResearchLectureController::class, 'destroySection'])->whereNumber('id')->name('section-destroy');
        // Resources
        Route::post('/{lectureId}/resources', [ResearchLectureController::class, 'storeResource'])->whereNumber('lectureId')->name('resource-store');
        Route::delete('/resource/{id}', [ResearchLectureController::class, 'destroyResource'])->whereNumber('id')->name('resource-destroy');
    });

    // #1107 Target-journal directory — where to publish (scope + rules), DHET-seeded
    Route::prefix('target-journals')->name('target-journal.')->group(function () {
        Route::get('/', [ResearchTargetJournalController::class, 'index'])->name('index');
        Route::post('/seed-dhet', [ResearchTargetJournalController::class, 'seedDhet'])->name('seed-dhet');
        Route::get('/create', [ResearchTargetJournalController::class, 'create'])->name('create');
        Route::post('/', [ResearchTargetJournalController::class, 'store'])->name('store');
        Route::get('/{id}', [ResearchTargetJournalController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/edit', [ResearchTargetJournalController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [ResearchTargetJournalController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [ResearchTargetJournalController::class, 'destroy'])->whereNumber('id')->name('destroy');
    });

    // #1099 Generic training curriculum + LMS module
    Route::prefix('training')->name('training.')->group(function () {
        Route::get('/', [ResearchTrainingController::class, 'index'])->name('index');
        Route::get('/create', [ResearchTrainingController::class, 'create'])->name('create');
        Route::post('/', [ResearchTrainingController::class, 'store'])->name('store');
        Route::get('/{id}', [ResearchTrainingController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/edit', [ResearchTrainingController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::put('/{id}', [ResearchTrainingController::class, 'update'])->whereNumber('id')->name('update');
        Route::delete('/{id}', [ResearchTrainingController::class, 'destroy'])->whereNumber('id')->name('destroy');
        Route::post('/{id}/status', [ResearchTrainingController::class, 'setStatus'])->whereNumber('id')->name('status');
        // Modules
        Route::post('/{courseId}/modules', [ResearchTrainingController::class, 'storeModule'])->whereNumber('courseId')->name('module-store');
        Route::get('/module/{id}/edit', [ResearchTrainingController::class, 'editModule'])->whereNumber('id')->name('module-edit');
        Route::put('/module/{id}', [ResearchTrainingController::class, 'updateModule'])->whereNumber('id')->name('module-update');
        Route::delete('/module/{id}', [ResearchTrainingController::class, 'destroyModule'])->whereNumber('id')->name('module-destroy');
        // Assessment
        Route::get('/{courseId}/assessment', [ResearchTrainingController::class, 'editAssessment'])->whereNumber('courseId')->name('assessment-edit');
        Route::post('/{courseId}/assessment', [ResearchTrainingController::class, 'saveAssessment'])->whereNumber('courseId')->name('assessment-save');
        // Enrolment
        Route::post('/{courseId}/enrol', [ResearchTrainingController::class, 'enrol'])->whereNumber('courseId')->name('enrol');
        Route::delete('/enrolment/{id}', [ResearchTrainingController::class, 'destroyEnrolment'])->whereNumber('id')->name('enrolment-destroy');
        // Learner flow
        Route::get('/learn/{enrolmentId}', [ResearchTrainingController::class, 'learn'])->whereNumber('enrolmentId')->name('learn');
        Route::post('/learn/{enrolmentId}/module/{moduleId}', [ResearchTrainingController::class, 'completeModule'])->whereNumber('enrolmentId')->whereNumber('moduleId')->name('module-complete');
        Route::get('/learn/{enrolmentId}/assessment', [ResearchTrainingController::class, 'takeAssessment'])->whereNumber('enrolmentId')->name('assessment-take');
        Route::post('/learn/{enrolmentId}/assessment', [ResearchTrainingController::class, 'submitAssessment'])->whereNumber('enrolmentId')->name('assessment-submit');
        Route::get('/learn/{enrolmentId}/certificate', [ResearchTrainingController::class, 'certificate'])->whereNumber('enrolmentId')->name('certificate');
    });

    // Dashboard & Index
    Route::match(['get', 'post'], '/', [ResearchController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/dashboard', [ResearchController::class, 'dashboard'])->name('dashboard');
    Route::get('/admin', [ResearchController::class, 'dashboard'])->name('admin');

    // Registration
    Route::get('/register', [ResearchController::class, 'register'])->name('register');
    Route::post('/register', [ResearchController::class, 'register'])->name('register.store');

    // Profile
    Route::get('/profile', [ResearchController::class, 'profile'])->name('profile');
    Route::post('/profile', [ResearchController::class, 'profile'])->name('profile.update');
    Route::match(['get', 'post'], '/apiKeys', [ResearchController::class, 'apiKeys'])->name('apiKeys');
    Route::match(['get', 'post'], '/renewal', [ResearchController::class, 'renewal'])->name('renewal');

    // Workspace (personal)
    Route::match(['get', 'post'], '/workspace', [ResearchController::class, 'workspace'])->name('workspace');
    // Persist the self-declared research mode (beginning/intermediate/advanced)
    // chosen from the sidebar selector. JSON endpoint, hit via fetch().
    Route::post('/experience-level', [ResearchWorkspaceController::class, 'saveExperienceLevel'])->name('saveExperienceLevel');
    Route::get('/viewWorkspace', function (\Illuminate\Http\Request $r) {
        $id = $r->input('id') ?: $r->getQueryString();
        return redirect('/research/workspaces/' . (int) $id, 301);
    });

    // Team Workspaces
    Route::get('/exportFindingAid', [ResearchController::class, 'exportFindingAid'])->name('exportFindingAid');
    Route::get('/exportNotes', [ResearchController::class, 'exportNotes'])->name('exportNotes');
    Route::get('/generateFindingAid', [ResearchController::class, 'generateFindingAid'])->name('generateFindingAid');
    Route::match(['get', 'post'], '/workspaces', [ResearchController::class, 'workspaces'])->name('workspaces');
    Route::match(['get', 'post'], '/workspaces/{id}', [ResearchController::class, 'viewWorkspace'])->name('viewWorkspace')->where('id', '[0-9]+');

    // Validation Queue (extracted to ResearchValidationQueueController - stage 7, issue #1269)
    Route::get('/validationQueue', [ResearchValidationQueueController::class, 'validationQueue'])->name('validationQueue');
    Route::post('/validate/{resultId}', [ResearchValidationQueueController::class, 'validateResult'])->name('validateResult')->where('resultId', '[0-9]+');
    Route::post('/bulk-validate', [ResearchValidationQueueController::class, 'bulkValidate'])->name('bulkValidate');

    // Entity Resolution (extracted to ResearchEntityResolutionController - stage 7, issue #1269)
    Route::match(['get', 'post'], '/entityResolution', [ResearchEntityResolutionController::class, 'entityResolution'])->name('entityResolution');
    Route::match(['get', 'post'], '/entity-resolution', [ResearchEntityResolutionController::class, 'entityResolution'])->name('entity-resolution');
    Route::post('/entity-resolution/{id}/resolve', [ResearchEntityResolutionController::class, 'resolveEntityResolution'])->name('resolveEntityResolution')->where('id', '[0-9]+');
    Route::get('/entity-resolution/{id}/conflicts', [ResearchEntityResolutionController::class, 'entityResolutionConflicts'])->name('entityResolutionConflicts')->where('id', '[0-9]+');

    // ODRL Policies (extracted to ResearchOdrlPoliciesController - stage 7, issue #1269)
    Route::match(['get', 'post'], '/odrlPolicies', [ResearchOdrlPoliciesController::class, 'odrlPolicies'])->name('odrlPolicies');
    Route::match(['get', 'post'], '/odrl-policies', [ResearchOdrlPoliciesController::class, 'odrlPolicies'])->name('odrl-policies');

    // Document Templates
    Route::match(['get', 'post'], '/documentTemplates', [ResearchController::class, 'documentTemplates'])->name('documentTemplates');
    Route::match(['get', 'post'], '/document-templates', [ResearchController::class, 'documentTemplates'])->name('document-templates');

    // Saved Searches
    Route::get('/savedSearches', [ResearchController::class, 'savedSearches'])->name('savedSearches');
    Route::post('/saved-searches', [ResearchController::class, 'storeSavedSearch'])->name('savedSearches.store');
    Route::post('/search-diff/{id}', [ResearchController::class, 'searchDiff'])->name('searchDiff')->where('id', '[0-9]+');
    Route::post('/search-snapshot/{id}', [ResearchController::class, 'searchSnapshot'])->name('searchSnapshot')->where('id', '[0-9]+');
    Route::get('/saved-searches/{id}/run', [ResearchController::class, 'runSavedSearch'])->name('savedSearches.run')->where('id', '[0-9]+');
    Route::delete('/saved-searches/{id}', [ResearchController::class, 'destroySavedSearch'])->name('savedSearches.destroy')->where('id', '[0-9]+');

    // Collections (Evidence Sets)
    Route::get('/collections', [ResearchController::class, 'collections'])->name('collections');
    Route::get('/collections/create', [ResearchController::class, 'createCollection'])->name('collections.create');
    Route::post('/collections', [ResearchController::class, 'storeCollection'])->name('collections.store');
    Route::put('/collections/{id}', [ResearchController::class, 'updateCollection'])->name('collections.update')->where('id', '[0-9]+');
    Route::delete('/collections/{id}', [ResearchController::class, 'destroyCollection'])->name('collections.destroy')->where('id', '[0-9]+');
    Route::post('/collections/{id}/add-item', [ResearchController::class, 'addItemToCollection'])->name('collections.addItem')->where('id', '[0-9]+');
    Route::delete('/collections/{collectionId}/remove-item/{itemId}', [ResearchController::class, 'removeItemFromCollection'])->name('collections.removeItem')->where(['collectionId' => '[0-9]+', 'itemId' => '[0-9]+']);
    Route::match(['get', 'post'], '/viewCollection', [ResearchController::class, 'viewCollection'])->name('viewCollection')->where('id', '[0-9]+');

    // Annotations (extracted to ResearchAnnotationsController - stage 2, issue #1253)
    Route::get('/annotations', [ResearchAnnotationsController::class, 'annotations'])->name('annotations');
    Route::post('/annotations', [ResearchAnnotationsController::class, 'storeAnnotation'])->name('annotations.store');
    Route::put('/annotations/{id}', [ResearchAnnotationsController::class, 'updateAnnotation'])->name('annotations.update')->where('id', '[0-9]+');
    Route::delete('/annotations/{id}', [ResearchAnnotationsController::class, 'destroyAnnotation'])->name('annotations.destroy')->where('id', '[0-9]+');

    // Projects
    Route::get('/projects', [ResearchController::class, 'projects'])->name('projects');
    Route::get('/projects/create', [ResearchController::class, 'createProject'])->name('projects.create');
    Route::post('/projects', [ResearchController::class, 'storeProject'])->name('projects.store');
    Route::match(['get', 'post'], '/viewProject/{id}', [ResearchController::class, 'viewProject'])->name('viewProject')->where('id', '[0-9]+');

    // Project Analysis Tools
    Route::match(['get', 'post'], '/knowledge-graph/{id}', [ResearchController::class, 'knowledgeGraph'])->name('knowledgeGraph')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/assertions/{id}', [ResearchController::class, 'assertions'])->name('assertions')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/hypotheses/{id}', [ResearchController::class, 'hypotheses'])->name('hypotheses')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/extraction-jobs/{id}', [ResearchController::class, 'extractionJobs'])->name('extractionJobs')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/snapshots/{id}', [ResearchController::class, 'snapshots'])->name('snapshots')->where('id', '[0-9]+');
    Route::get('/viewSnapshot/{id}', [ResearchController::class, 'viewSnapshot'])->name('viewSnapshot')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/assertion-batch-review/{id}', [ResearchController::class, 'assertionBatchReview'])->name('assertionBatchReview')->where('id', '[0-9]+');

    // Project Visualization
    Route::match(['get', 'post'], '/timeline/{id}', [ResearchController::class, 'timelineBuilder'])->name('timelineBuilder')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/map/{id}', [ResearchController::class, 'mapBuilder'])->name('mapBuilder')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/network-graph/{id}', [ResearchController::class, 'networkGraph'])->name('networkGraph')->where('id', '[0-9]+');

    // Project Research Output
    Route::match(['get', 'post'], '/ro-crate/{id}', [ResearchController::class, 'roCrate'])->name('roCrate')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/reproducibility/{id}', [ResearchController::class, 'reproducibilityPack'])->name('reproducibilityPack')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/doi/{id}', [ResearchController::class, 'mintDoi'])->name('mintDoi')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/ethics-milestones/{id}', [ResearchController::class, 'ethicsMilestones'])->name('ethicsMilestones')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/compliance/{id}', [ResearchController::class, 'complianceDashboard'])->name('complianceDashboard')->where('id', '[0-9]+');

    // Analytics dashboard
    Route::get('/analytics', [ResearchController::class, 'analytics'])->name('analytics');

    // Mobile / PWA + offline sync
    Route::get('/mobile',       [ResearchController::class, 'mobileHome'])->name('mobileHome');
    Route::post('/sync/offline', [ResearchController::class, 'offlineSync'])->name('offlineSync');

    // ORCID integration
    Route::get('/orcid',           [ResearchController::class, 'orcidLink'])->name('orcid');
    Route::get('/orcid/authorize', [ResearchController::class, 'orcidAuthorize'])->name('orcidAuthorize');
    Route::get('/orcid/callback',  [ResearchController::class, 'orcidCallback'])->name('orcidCallback');
    Route::post('/orcid/sync',     [ResearchController::class, 'orcidSync'])->name('orcidSync');
    Route::post('/orcid/pull-profile', [ResearchController::class, 'orcidPullProfile'])->name('orcidPullProfile');
    Route::post('/orcid/credentials', [ResearchController::class, 'orcidSaveCredentials'])->name('orcidSaveCredentials');
    Route::post('/orcid/credentials/clear', [ResearchController::class, 'orcidClearCredentials'])->name('orcidClearCredentials');
    Route::post('/orcid/unlink',   [ResearchController::class, 'orcidUnlink'])->name('orcidUnlink');

    // Real-time collaboration (polling fallback)
    Route::get('/projects/{projectId}/realtime/panel',  [ResearchController::class, 'collabPanel'])->name('collabPanel')->where('projectId', '[0-9]+');
    Route::post('/projects/{projectId}/realtime/join',  [ResearchController::class, 'collabJoin'])->name('collabJoin')->where('projectId', '[0-9]+');
    Route::get('/projects/{projectId}/realtime/poll',   [ResearchController::class, 'collabPoll'])->name('collabPoll')->where('projectId', '[0-9]+');
    Route::post('/projects/{projectId}/realtime/comment', [ResearchController::class, 'collabComment'])->name('collabComment')->where('projectId', '[0-9]+');
    Route::post('/projects/{projectId}/realtime/comment/{commentId}/resolve', [ResearchController::class, 'collabCommentResolve'])->name('collabCommentResolve')->where(['projectId' => '[0-9]+', 'commentId' => '[0-9]+']);

    // Cross-fonds reasoning queries
    Route::match(['get','post'], '/cross-fonds-query', [ResearchController::class, 'crossFondsQuery'])->name('crossFondsQuery');

    // Notebooks (private researcher scratchpad)
    // Extracted to ResearchNotebooksController - stage 4, issue #1253 / #1269
    Route::match(['get','post'], '/notebooks', [ResearchNotebooksController::class, 'notebooks'])->name('notebooks');
    Route::match(['get','post'], '/notebooks/{id}', [ResearchNotebooksController::class, 'notebookShow'])->name('notebookShow')->where('id', '[0-9]+');
    Route::delete('/notebooks/{id}', [ResearchNotebooksController::class, 'notebookDelete'])->name('notebookDelete')->where('id', '[0-9]+');
    Route::post('/notebooks/{id}/promote', [ResearchNotebooksController::class, 'notebookPromote'])->name('notebookPromote')->where('id', '[0-9]+');

    // Studio (NotebookLM-style artefact generator)
    Route::get('/studio/{projectId}', [ResearchController::class, 'studio'])->name('studio')->where('projectId', '[0-9]+');
    Route::post('/studio/{projectId}/generate', [ResearchController::class, 'studioGenerate'])->name('studioGenerate')->where('projectId', '[0-9]+');
    Route::get('/studio/{projectId}/artefact/{artefactId}', [ResearchController::class, 'studioShow'])->name('studioShow')->where(['projectId' => '[0-9]+', 'artefactId' => '[0-9]+']);
    Route::get('/studio/{projectId}/artefact/{artefactId}/download', [ResearchController::class, 'studioDownload'])->name('studioDownload')->where(['projectId' => '[0-9]+', 'artefactId' => '[0-9]+']);
    Route::delete('/studio/{projectId}/artefact/{artefactId}', [ResearchController::class, 'studioDelete'])->name('studioDelete')->where(['projectId' => '[0-9]+', 'artefactId' => '[0-9]+']);

    // Collaborator Management
    Route::match(['get', 'post'], '/invite-collaborator/{id}', [ResearchController::class, 'inviteCollaborator'])->name('inviteCollaborator')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/share-project/{id}', [ResearchController::class, 'shareProject'])->name('shareProject')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/project-collaborators/{id}', [ResearchController::class, 'projectCollaborators'])->name('projectCollaborators')->where('id', '[0-9]+');

    // Journal
    Route::match(['get', 'post'], '/journal', [ResearchController::class, 'journal'])->name('journal');
    Route::get('/journal/create', [ResearchController::class, 'createJournalEntry'])->name('journal.create');
    Route::get('/journal/{id}', [ResearchController::class, 'showJournalEntry'])->name('journal.show')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/journal/entry/{id}', [ResearchController::class, 'journalEntry'])->name('journalEntry')->where('id', '[0-9]+');

    // Bibliographies - extracted to ResearchBibliographiesController (stage 6,
    // issue #1253 / #1269). All four routes keep their names, URIs and the auth
    // middleware group unchanged.
    Route::match(['get', 'post'], '/bibliographies', [ResearchBibliographiesController::class, 'bibliographies'])->name('bibliographies');
    Route::match(['get', 'post'], '/viewBibliography/{id}', [ResearchBibliographiesController::class, 'viewBibliography'])->name('viewBibliography')->where('id', '[0-9]+');

    // Bibliography export for reference managers (BibTeX / RIS / CSL-JSON).
    // Same auth + ownership gating as viewBibliography (enforced in the controller).
    Route::get('/bibliography/{id}/export/{format}', [ResearchBibliographiesController::class, 'exportBibliography'])
        ->name('bibliography.export')
        ->where('id', '[0-9]+')
        ->where('format', 'bibtex|ris|csljson');
    Route::get('/cite/{itemId}/export/{format}', [ResearchBibliographiesController::class, 'exportBibliographyEntry'])
        ->name('bibliographyEntry.export')
        ->where('itemId', '[0-9]+')
        ->where('format', 'bibtex|ris|csljson');

    // Source Assessments
    Route::get('/assessments', [ResearchController::class, 'assessments'])->name('assessments');

    // Reports
    Route::match(['get', 'post'], '/viewReproduction/{id}', [ResearchReproductionsController::class, 'viewReproduction'])->name('viewReproduction')->where('id', '[0-9]+');
    // Extracted to ResearchReportsController - stage 5, issue #1253 / #1269
    Route::match(['get', 'post'], '/reports', [ResearchReportsController::class, 'reports'])->name('reports');
    Route::match(['get', 'post'], '/report-templates', [ResearchReportsController::class, 'reportTemplates'])->name('reportTemplates');
    Route::match(['get', 'post'], '/viewReport/{id}', [ResearchReportsController::class, 'viewReport'])->name('viewReport')->where('id', '[0-9]+');

    // Reproductions
    Route::match(['get', 'post'], '/reproductions', [ResearchReproductionsController::class, 'reproductions'])->name('reproductions');

    // Bookings
    Route::get('/book', [ResearchController::class, 'book'])->name('book');
    Route::post('/book', [ResearchController::class, 'book'])->name('book.store');
    Route::match(['get', 'post'], '/viewBooking/{id}', [ResearchController::class, 'viewBooking'])->name('viewBooking')->where('id', '[0-9]+');
    Route::post('/bookings/{id}/confirm', [ResearchController::class, 'confirmBooking'])->name('bookings.confirm')->where('id', '[0-9]+');
    Route::post('/bookings/{id}/check-in', [ResearchController::class, 'checkInBooking'])->name('bookings.checkIn')->where('id', '[0-9]+');
    Route::post('/bookings/{id}/check-out', [ResearchController::class, 'checkOutBooking'])->name('bookings.checkOut')->where('id', '[0-9]+');
    Route::post('/bookings/{id}/no-show', [ResearchController::class, 'noShowBooking'])->name('bookings.noShow')->where('id', '[0-9]+');
    Route::post('/bookings/{id}/cancel', [ResearchController::class, 'cancelBooking'])->name('bookings.cancel')->where('id', '[0-9]+');
    Route::post('/checkIn/{id}', [ResearchController::class, 'checkIn'])->name('checkIn')->where('id', '[0-9]+');
    Route::post('/checkOut/{id}', [ResearchController::class, 'checkOut'])->name('checkOut')->where('id', '[0-9]+');

    // Notifications
    Route::match(['get', 'post'], '/notifications', [ResearchController::class, 'notifications'])->name('notifications');

    // Evidence Viewer
    Route::match(['get', 'post'], '/evidence-viewer', [ResearchController::class, 'evidenceViewer'])->name('evidence-viewer');

    // AJAX endpoints
    Route::get('/searchItems', [ResearchController::class, 'searchItems'])->name('searchItems');
    Route::post('/addToCollection', [ResearchController::class, 'addToCollection'])->name('addToCollection');
    Route::post('/createCollectionAjax', [ResearchController::class, 'createCollectionAjax'])->name('createCollectionAjax');
});

// Admin research management routes
Route::prefix('research')->name('research.')->middleware('admin')->group(function () {
    // Dashboard URL aliases under /research/admin/* (matches reports dashboard links)
    Route::match(['get', 'post'], '/admin/researchers', [ResearchController::class, 'researchers'])->name('admin.researchers');
    Route::match(['get', 'post'], '/admin/bookings', [ResearchController::class, 'bookings'])->name('admin.bookings');
    Route::match(['get', 'post'], '/admin/statistics', [ResearchController::class, 'adminStatistics'])->name('admin.statistics');

    Route::match(['get', 'post'], '/researchers', [ResearchController::class, 'researchers'])->name('researchers');
    Route::match(['get', 'post'], '/viewResearcher/{id}', [ResearchController::class, 'viewResearcher'])->name('viewResearcher')->where('id', '[0-9]+');
    Route::post('/approveResearcher/{id}', [ResearchController::class, 'approveResearcher'])->name('approveResearcher')->where('id', '[0-9]+');
    Route::post('/rejectResearcher/{id}', [ResearchController::class, 'rejectResearcher'])->name('rejectResearcher')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/approve', [ResearchController::class, 'approveResearcher'])->name('researchers.approve')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/reject', [ResearchController::class, 'rejectResearcher'])->name('researchers.reject')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/suspend', [ResearchController::class, 'suspendResearcher'])->name('researchers.suspend')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/verify', [ResearchController::class, 'verifyResearcher'])->name('researchers.verify')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/reset-password', [ResearchController::class, 'resetPassword'])->name('resetPassword')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/bookings', [ResearchController::class, 'bookings'])->name('bookings');
    Route::get('/rooms', [ResearchController::class, 'rooms'])->name('rooms');
    Route::match(['get', 'post'], '/editRoom', [ResearchController::class, 'editRoom'])->name('editRoom');
    Route::match(['get', 'post'], '/seats', [ResearchController::class, 'seats'])->name('seats');
    Route::match(['get', 'post'], '/equipment', [ResearchController::class, 'equipment'])->name('equipment');
    Route::get('/equipment-history/{id}', [ResearchController::class, 'equipmentHistory'])->name('equipmentHistory')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/retrievalQueue', [ResearchController::class, 'retrievalQueue'])->name('retrievalQueue');
    Route::match(['get', 'post'], '/walkIn', [ResearchController::class, 'walkIn'])->name('walkIn');
    Route::match(['get', 'post'], '/adminTypes', [ResearchController::class, 'adminTypes'])->name('adminTypes');
    Route::match(['get', 'post'], '/adminStatistics', [ResearchController::class, 'adminStatistics'])->name('adminStatistics');
    Route::match(['get', 'post'], '/institutions', [ResearchController::class, 'institutions'])->name('institutions');
    Route::match(['get', 'post'], '/activities', [ResearchController::class, 'activities'])->name('activities');
});

/*
|--------------------------------------------------------------------------
| Audit Trail Routes
|--------------------------------------------------------------------------
| Migrated from AtoM: ahgResearchPlugin/modules/audit
*/

Route::prefix('audit')->name('audit.')->middleware('auth')->group(function () {
    Route::get('/', [AuditController::class, 'index'])->name('index');
    Route::get('/view/{id}', [AuditController::class, 'view'])->name('view')->where('id', '[0-9]+');
    Route::get('/record/{tableName}/{recordId}', [AuditController::class, 'record'])->name('record')->where('recordId', '[0-9]+');
    Route::get('/user/{userId}', [AuditController::class, 'user'])->name('user')->where('userId', '[0-9]+');

// Auto-registered stub routes
Route::match(['get','post'], '/settings/ahg-settings', function() { return view('research::ahg-settings'); })->name('settings.ahgSettings');
Route::match(['get','post'], '/saved-searches/run', function() { return view('research::run'); })->name('research.savedSearches.run');
Route::match(['get','post'], '/saved-searches/destroy', function() { return view('research::destroy'); })->name('research.savedSearches.destroy');
Route::match(['get','post'], '/researchers/approve', function() { return view('research::approve'); })->name('research.researchers.approve');
Route::match(['get','post'], '/researchers/suspend', function() { return view('research::suspend'); })->name('research.researchers.suspend');
Route::match(['get','post'], '/researchers/reject', function() { return view('research::reject'); })->name('research.researchers.reject');
Route::match(['get','post'], '/login', function() { return view('research::login'); })->name('login');
Route::match(['get','post'], '/collections/remove-item', function() { return view('research::remove-item'); })->name('research.collections.removeItem');
Route::match(['get','post'], '/collections/add-item', function() { return view('research::add-item'); })->name('research.collections.addItem');
Route::match(['get','post'], '/bookings/check-out', function() { return view('research::check-out'); })->name('research.bookings.checkOut');
});

// Settings alias (admin middleware returns 403 to anon, matching AtoM behavior)
Route::middleware('admin')->get('/admin/ahg-settings', fn() => redirect()->route('settings.index'))->name('settings.ahgSettings');
