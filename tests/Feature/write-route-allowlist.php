<?php

/*
 * heratio#1777 - non-GET routes that are guarded only by sign-in, on purpose.
 *
 * WriteRouteGuardTest fails when a POST/PUT/PATCH/DELETE route has the 'auth'
 * middleware but no acl:, admin or API-auth middleware and is not listed here.
 * A new staff write should get route middleware instead of an entry here.
 *
 * Reasons: own = writes only the caller's own rows (owner check in the
 * controller); project = research project data behind the membership gate;
 * ctrl = staff action with its permission check in the controller;
 * read = the POST does not write; design = open to any signed-in user by
 * design; review = shared data still to be decided (see heratio#1777).
 *
 * Closures are keyed by URI.
 */

return [
    \Ahg3dModel\Controllers\CameraBookmarkController::class => [
        'destroy' => 'own',
        'store' => 'own',
        'update' => 'own',
    ],
    \Ahg3dModel\Controllers\Model3dController::class => [
        'discard3d' => 'own',
    ],
    \AhgAccessRequest\Controllers\AccessRequestController::class => [
        'cancel' => 'own',
        'store' => 'own',
        'storeObjectRequest' => 'own',
    ],
    \AhgAcl\Controllers\AclController::class => [
        'setupTwoFactorStore' => 'own',
        'submitAccessRequest' => 'own',
        'verifyTwoFactor' => 'own',
    ],
    \AhgAiChatbot\Controllers\ChatbotController::class => [
        'escalate' => 'design',
        'message' => 'design',
        'reset' => 'design',
    ],
    \AhgApi\Controllers\KeyPageController::class => [
        'revoke' => 'own',
        'revokeFor' => 'ctrl',
        'store' => 'own',
        'storeFor' => 'ctrl',
    ],
    \AhgArticles\Controllers\Admin\BlogAdminController::class => [
        'attachmentsReorder' => 'ctrl',
        'commentDestroy' => 'ctrl',
        'commentStatus' => 'ctrl',
        'destroy' => 'ctrl',
        'destroyAttachment' => 'ctrl',
        'linksAdd' => 'ctrl',
        'linksRemove' => 'ctrl',
        'linksReorder' => 'ctrl',
        'store' => 'ctrl',
        'storeAttachment' => 'ctrl',
        'toggleProtect' => 'ctrl',
        'update' => 'ctrl',
        'updateAttachment' => 'ctrl',
        'uploadImage' => 'ctrl',
    ],
    \AhgArtworkRequest\Controllers\ArtworkRequestController::class => [
        'approvers' => 'ctrl',
        'requestForm' => 'design',
    ],
    \AhgBiblioBf\Controllers\BibframeController::class => [
        'exportRun' => 'read',
        'validateRun' => 'read',
    ],
    \AhgBiblioFrbr\Controllers\FrbrController::class => [
        'exportRun' => 'read',
        'validateRun' => 'read',
    ],
    \AhgCart\Controllers\CartController::class => [
        'acceptQuote' => 'own',
        'subscribe' => 'own',
        'addListing' => 'own',
        'marketplaceCheckout' => 'own',
        'marketplaceDemoCheckout' => 'own',
        'removeListing' => 'own',
    ],
    \AhgDonorManage\Controllers\DonorController::class => [
        'autocomplete' => 'read',
    ],
    \AhgFavorites\Controllers\FavoritesController::class => [
        'ajaxToggle' => 'own',
        'ajaxToggleCustom' => 'own',
        'bulk' => 'own',
        'clear' => 'own',
        'folderCreate' => 'own',
        'folderDelete' => 'own',
        'folderEdit' => 'own',
        'importFavorites' => 'own',
        'moveToFolder' => 'own',
        'remove' => 'own',
        'revokeSharing' => 'own',
        'sendToBibliography' => 'own',
        'sendToCollection' => 'own',
        'sendToProject' => 'own',
        'shareFolder' => 'own',
        'updateNotes' => 'own',
    ],
    \AhgForms\Controllers\FormsController::class => [
        'apiAutosave' => 'own',
    ],
    \AhgHeritageManage\Controllers\HeritageController::class => [
        'contributeToItem' => 'design',
    ],
    \AhgIiifCollection\Controllers\WorkspaceController::class => [
        'destroy' => 'own',
        'load' => 'own',
        'store' => 'own',
        'update' => 'own',
    ],
    \AhgInformationObjectManage\Controllers\InformationObjectController::class => [
        'reports' => 'read',
    ],
    \AhgInformationObjectManage\Controllers\TreeViewPageController::class => [
        'sync' => 'read',
    ],
    \AhgLabel\Controllers\ContainerController::class => [
        'print' => 'read',
    ],
    \AhgLabel\Controllers\LabelController::class => [
        'batchPrint' => 'read',
        'generate' => 'read',
    ],
    \AhgLandingPage\Controllers\LandingPageController::class => [
        'myDashboardCreate' => 'own',
    ],
    \AhgLibrary\Controllers\CirculationDeskController::class => [
        'chooseBranch' => 'design',
    ],
    \AhgLibrary\Controllers\LibraryController::class => [
        'opacIllStore' => 'own',
    ],
    \AhgLoan\Controllers\TourController::class => [
        'checkJson' => 'read',
    ],
    \AhgMarketplace\Controllers\MarketplaceController::class => [
        'cancelReservation' => 'own',
        'checkoutBuy' => 'own',
        'checkoutWin' => 'own',
        'reserveListing' => 'own',
        'toggleFavouriteApi' => 'own',
    ],
    \AhgMultiTenant\Controllers\TenantController::class => [
        'switchTo' => 'own',
    ],
    \AhgPrivacy\Controllers\PrivacyController::class => [
        'dsarRequestStore' => 'design',
    ],
    \AhgRdm\Controllers\DatasetController::class => [
        'deposit' => 'own',
        'linkDmp' => 'own',
        'resolveFinding' => 'own',
        'scan' => 'own',
        'setDisposition' => 'own',
        'store' => 'own',
        'unlinkDmp' => 'own',
    ],
    \AhgRequestPublish\Controllers\RequestPublishController::class => [
        'submit' => 'read',
    ],
    \AhgResearch\Controllers\AiDisclosureController::class => [
        'logDestroy' => 'project',
        'logStore' => 'project',
    ],
    \AhgResearch\Controllers\AnalysisBridgeController::class => [
        'addCode' => 'project',
        'deleteCode' => 'project',
        'destroy' => 'project',
        'linkClaim' => 'project',
        'store' => 'project',
        'unlinkClaim' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ArgumentBuilderController::class => [
        'addStep' => 'project',
        'attachClaim' => 'project',
        'removeStep' => 'project',
        'reorder' => 'project',
        'update' => 'project',
        'updateStep' => 'project',
    ],
    \AhgResearch\Controllers\ClaimLedgerController::class => [
        'attachEvidence' => 'project',
        'destroy' => 'project',
        'detachEvidence' => 'project',
        'setStatus' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ContradictionEngineController::class => [
        'aiScan' => 'project',
        'dismiss' => 'project',
        'reopen' => 'project',
        'resolve' => 'project',
        'scan' => 'project',
    ],
    \AhgResearch\Controllers\DecisionLogController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\DmpController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\FieldAlertController::class => [
        'addWatch' => 'project',
        'markAllRead' => 'project',
        'markRead' => 'project',
        'removeWatch' => 'project',
    ],
    \AhgResearch\Controllers\GrantEngineController::class => [
        'aiDraft' => 'project',
        'destroyCall' => 'project',
        'store' => 'project',
        'storeCall' => 'project',
        'update' => 'project',
        'updateCall' => 'project',
    ],
    \AhgResearch\Controllers\ImpactTrackingController::class => [
        'refresh' => 'project',
    ],
    \AhgResearch\Controllers\InboxController::class => [
        'archive' => 'own',
        'capture' => 'own',
        'moveToProject' => 'own',
        'restore' => 'own',
        'triage' => 'own',
    ],
    \AhgResearch\Controllers\MethodStudioController::class => [
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\PublicationStudioController::class => [
        'addRequirement' => 'project',
        'addResponse' => 'project',
        'aiFit' => 'project',
        'deleteRequirement' => 'project',
        'storeSubmission' => 'project',
        'transition' => 'project',
        'updateRequirement' => 'project',
        'updateSubmission' => 'project',
    ],
    \AhgResearch\Controllers\QuestionBuilderController::class => [
        'diagnose' => 'project',
        'save' => 'project',
    ],
    \AhgResearch\Controllers\ReplicationPackController::class => [
        'build' => 'project',
    ],
    \AhgResearch\Controllers\ResearchAiDecisionController::class => [
        'decision' => 'own',
    ],
    \AhgResearch\Controllers\ResearchAnalyticsController::class => [
        'crossFondsQuery' => 'read',
    ],
    \AhgResearch\Controllers\ResearchAnnotationsController::class => [
        'destroyAnnotation' => 'own',
        'storeAnnotation' => 'own',
        'updateAnnotation' => 'own',
    ],
    \AhgResearch\Controllers\ResearchApiKeysController::class => [
        'apiKeys' => 'own',
    ],
    \AhgResearch\Controllers\ResearchAssessmentsController::class => [
        'sourceAssessment' => 'own',
    ],
    \AhgResearch\Controllers\ResearchBibliographiesController::class => [
        'bibliographies' => 'own',
        'viewBibliography' => 'own',
    ],
    \AhgResearch\Controllers\ResearchBookingsController::class => [
        'book' => 'own',
        'cancelBooking' => 'own',
        'checkIn' => 'own',
        'checkInBooking' => 'own',
        'checkOut' => 'own',
        'checkOutBooking' => 'own',
        'confirmBooking' => 'own',
        'noShowBooking' => 'own',
        'viewBooking' => 'own',
    ],
    \AhgResearch\Controllers\ResearchCollaborationController::class => [
        'collabComment' => 'project',
        'collabCommentResolve' => 'project',
        'collabJoin' => 'project',
        'inviteCollaborator' => 'project',
        'projectCollaborators' => 'project',
        'shareProject' => 'project',
    ],
    \AhgResearch\Controllers\ResearchCollectionsController::class => [
        'addItemToCollection' => 'own',
        'addToCollection' => 'own',
        'createCollectionAjax' => 'own',
        'destroyCollection' => 'own',
        'removeItemFromCollection' => 'own',
        'storeCollection' => 'own',
        'updateCollection' => 'own',
        'viewCollection' => 'own',
    ],
    \AhgResearch\Controllers\ResearchController::class => [
        'dashboard' => 'read',
        'index' => 'read',
        'profile' => 'own',
    ],
    \AhgResearch\Controllers\ResearchCopilotController::class => [
        'askAjax' => 'own',
        'saveAjax' => 'own',
    ],
    \AhgResearch\Controllers\ResearchDocumentTemplatesController::class => [
        'documentTemplates' => 'own',
    ],
    \AhgResearch\Controllers\ResearchEntityResolutionController::class => [
        'entityResolution' => 'design',
        'resolveEntityResolution' => 'own',
    ],
    \AhgResearch\Controllers\ResearchEthicsController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchEvidenceController::class => [
        'evidenceViewer' => 'own',
    ],
    \AhgResearch\Controllers\ResearchFundingController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchJournalController::class => [
        'destroy' => 'own',
        'destroyArticle' => 'own',
        'destroyIssue' => 'own',
        'setStatus' => 'own',
        'store' => 'own',
        'storeArticle' => 'own',
        'storeIssue' => 'own',
        'update' => 'own',
        'updateArticle' => 'own',
        'updateIssue' => 'own',
    ],
    \AhgResearch\Controllers\ResearchJournalEntryController::class => [
        'journal' => 'own',
        'journalEntry' => 'own',
    ],
    \AhgResearch\Controllers\ResearchLectureController::class => [
        'destroy' => 'own',
        'destroyResource' => 'own',
        'destroySection' => 'own',
        'setStatus' => 'own',
        'store' => 'own',
        'storeResource' => 'own',
        'storeSection' => 'own',
        'update' => 'own',
        'updateSection' => 'own',
    ],
    \AhgResearch\Controllers\ResearchMemoryController::class => [
        'accept' => 'project',
        'destroy' => 'project',
        'status' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchMilestoneController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchMobileController::class => [
        'buildOfflinePackage' => 'own',
        'offlineSync' => 'own',
        'syncUpload' => 'own',
    ],
    \AhgResearch\Controllers\ResearchNotebooksController::class => [
        'notebookDelete' => 'own',
        'notebookPromote' => 'own',
        'notebookShow' => 'own',
        'notebooks' => 'own',
    ],
    \AhgResearch\Controllers\ResearchNotificationsController::class => [
        'notifications' => 'own',
    ],
    \AhgResearch\Controllers\ResearchOdrlPoliciesController::class => [
        'odrlPolicies' => 'own',
    ],
    \AhgResearch\Controllers\ResearchOfflineController::class => [
        'take' => 'own',
    ],
    \AhgResearch\Controllers\ResearchOrcidController::class => [
        'orcidClearCredentials' => 'own',
        'orcidPullProfile' => 'own',
        'orcidSaveCredentials' => 'own',
        'orcidSync' => 'own',
        'orcidUnlink' => 'own',
    ],
    \AhgResearch\Controllers\ResearchOutputController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchProjectOutputsController::class => [
        'assertionBatchReview' => 'project',
        'assertions' => 'project',
        'complianceDashboard' => 'project',
        'ethicsMilestones' => 'project',
        'extractionJobs' => 'project',
        'hypotheses' => 'project',
        'knowledgeGraph' => 'project',
        'mapBuilder' => 'project',
        'mintDoi' => 'project',
        'networkGraph' => 'project',
        'reproducibilityPack' => 'project',
        'roCrate' => 'project',
        'snapshots' => 'project',
        'timelineBuilder' => 'project',
    ],
    \AhgResearch\Controllers\ResearchProjectsController::class => [
        'storeProject' => 'own',
        'viewProject' => 'own',
    ],
    \AhgResearch\Controllers\ResearchRegistrationController::class => [
        'renewal' => 'own',
    ],
    \AhgResearch\Controllers\ResearchReportsController::class => [
        'reportTemplates' => 'ctrl',
        'reports' => 'own',
        'viewReport' => 'own',
    ],
    \AhgResearch\Controllers\ResearchReproductionsController::class => [
        'reproductions' => 'own',
        'viewReproduction' => 'own',
    ],
    \AhgResearch\Controllers\ResearchSavedSearchesController::class => [
        'destroySavedSearch' => 'own',
        'searchDiff' => 'own',
        'searchSnapshot' => 'own',
        'storeSavedSearch' => 'own',
    ],
    \AhgResearch\Controllers\ResearchStudioController::class => [
        'studioDelete' => 'project',
        'studioGenerate' => 'project',
    ],
    \AhgResearch\Controllers\ResearchTargetJournalController::class => [
        'destroy' => 'ctrl',
        'seedDhet' => 'ctrl',
        'store' => 'ctrl',
        'update' => 'ctrl',
    ],
    \AhgResearch\Controllers\ResearchTeamController::class => [
        'destroy' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgResearch\Controllers\ResearchTrainingController::class => [
        'completeModule' => 'own',
        'destroy' => 'own',
        'destroyEnrolment' => 'own',
        'destroyModule' => 'own',
        'enrol' => 'own',
        'saveAssessment' => 'own',
        'setStatus' => 'own',
        'store' => 'own',
        'storeModule' => 'own',
        'submitAssessment' => 'own',
        'update' => 'own',
        'updateModule' => 'own',
    ],
    \AhgResearch\Controllers\ResearchValidationQueueController::class => [
        'bulkValidate' => 'project',
        'validateResult' => 'project',
    ],
    \AhgResearch\Controllers\ResearchWorkspaceController::class => [
        'saveExperienceLevel' => 'own',
        'viewWorkspace' => 'read',
        'workspace' => 'own',
        'workspaces' => 'read',
    ],
    \AhgResearch\Controllers\ResearchWorkspaceFileController::class => [
        'destroy' => 'own',
        'fetch' => 'own',
        'store' => 'own',
    ],
    \AhgResearch\Controllers\ReviewStudioController::class => [
        'destroyComment' => 'project',
        'destroyRun' => 'project',
        'resolveComment' => 'project',
        'runReviewer' => 'project',
        'storeComment' => 'project',
    ],
    \AhgResearch\Controllers\SourceTriageController::class => [
        'aiPreview' => 'project',
        'setCategory' => 'project',
        'setNotes' => 'project',
        'setReadStatus' => 'project',
    ],
    \AhgResearch\Controllers\WritingStudioController::class => [
        'addSection' => 'project',
        'aiDraft' => 'project',
        'citeClaim' => 'project',
        'deleteSection' => 'project',
        'destroy' => 'project',
        'pullSource' => 'project',
        'saveSection' => 'project',
        'saveVersion' => 'project',
        'store' => 'project',
        'update' => 'project',
    ],
    \AhgSearch\Controllers\CiteController::class => [
        'chatbot' => 'own',
    ],
    \AhgSecurityClearance\Controllers\OtpController::class => [
        'assertBegin' => 'own',
        'assertComplete' => 'own',
        'delete' => 'own',
        'enrol' => 'own',
        'resendEnrolment' => 'own',
        'verifyEnrolment' => 'own',
    ],
    \AhgSecurityClearance\Controllers\SecurityClearanceController::class => [
        'confirmTwoFactor' => 'own',
        'disableTwoFactor' => 'own',
        'regenerateRecoveryCodes' => 'own',
        'verifyTwoFactor' => 'own',
    ],
    \AhgSecurityClearance\Controllers\WebAuthnController::class => [
        'assertBegin' => 'own',
        'assertComplete' => 'own',
        'delete' => 'own',
        'registerBegin' => 'own',
        'registerComplete' => 'own',
    ],
    \AhgShareLink\Controllers\ShareLinkAdminController::class => [
        'revoke' => 'ctrl',
    ],
    \AhgShareLink\Controllers\ShareLinkIssueController::class => [
        'store' => 'ctrl',
    ],
    \AhgSpectrum\Controllers\SpectrumController::class => [
        'barcodeScan' => 'read',
        'generalWorkflow' => 'read',
    ],
    \AhgTranslation\Controllers\TranslationController::class => [
        'stringsSave' => 'ctrl',
    ],
    \AhgUserManage\Controllers\UserController::class => [
        'passwordReset' => 'own',
        'savePluginPreferences' => 'ctrl',
    ],
    \AhgVersionControl\Controllers\VersionController::class => [
        'restore' => 'ctrl',
    ],
    'Closure' => [
        'api-autocomplete' => 'read',
        'api-delete-file' => 'read',
        'api-upload' => 'read',
        'donor/add' => 'read',
        'object/{slug}/icip/consent' => 'ctrl',
        'object/{slug}/icip/labels' => 'ctrl',
        'object/{slug}/icip/notices' => 'ctrl',
        'object/{slug}/icip/restrictions' => 'ctrl',
        'search-enhancement/admin-templates' => 'read',
        'search-enhancement/history' => 'read',
        'search-enhancement/save-search' => 'read',
        'search-enhancement/saved-searches' => 'read',
        'semantic-search-admin/test-expand' => 'read',
        'session/keepalive' => 'design',
    ],
];
