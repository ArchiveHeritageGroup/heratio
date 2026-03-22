<?php

use AhgResearch\Controllers\ResearchController;
use AhgResearch\Controllers\AuditController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Research Portal Routes
|--------------------------------------------------------------------------
| Migrated from AtoM: ahgResearchPlugin/config/routing.yml
| All routes follow the AtoM URL structure: /research/*
*/

Route::prefix('research')->name('research.')->group(function () {

    // Dashboard & Index
    Route::match(['get', 'post'], '/', [ResearchController::class, 'index'])->name('index');
    Route::match(['get', 'post'], '/dashboard', [ResearchController::class, 'dashboard'])->name('dashboard');
    Route::get('/admin', [ResearchController::class, 'dashboard'])->name('admin'); // AtoM menu alias

    // Registration (public and authenticated)
    Route::get('/register', [ResearchController::class, 'register'])->name('register');
    Route::post('/register', [ResearchController::class, 'register'])->name('register.store');
    Route::get('/publicRegister', [ResearchController::class, 'publicRegister'])->name('publicRegister');
    Route::post('/public-register', [ResearchController::class, 'publicRegister'])->name('publicRegister.store');
    Route::get('/registrationComplete', [ResearchController::class, 'registrationComplete'])->name('registrationComplete');

    // Profile
    Route::get('/profile', [ResearchController::class, 'profile'])->name('profile');
    Route::post('/profile', [ResearchController::class, 'profile'])->name('profile.update');
    Route::match(['get', 'post'], '/apiKeys', [ResearchController::class, 'apiKeys'])->name('apiKeys');
    Route::match(['get', 'post'], '/renewal', [ResearchController::class, 'renewal'])->name('renewal');

    // Workspace (personal)
    Route::match(['get', 'post'], '/workspace', [ResearchController::class, 'workspace'])->name('workspace');

    // Team Workspaces
    Route::match(['get', 'post'], '/workspaces', [ResearchController::class, 'workspaces'])->name('workspaces');

    // Validation Queue
    Route::get('/validationQueue', [ResearchController::class, 'validationQueue'])->name('validationQueue');
    Route::post('/validate/{resultId}', [ResearchController::class, 'validateResult'])->name('validateResult')->where('resultId', '[0-9]+');
    Route::post('/bulk-validate', [ResearchController::class, 'bulkValidate'])->name('bulkValidate');

    // Entity Resolution
    Route::match(['get', 'post'], '/entityResolution', [ResearchController::class, 'entityResolution'])->name('entityResolution');
    Route::post('/entity-resolution/{id}/resolve', [ResearchController::class, 'resolveEntityResolution'])->name('resolveEntityResolution')->where('id', '[0-9]+');
    Route::get('/entity-resolution/{id}/conflicts', [ResearchController::class, 'entityResolutionConflicts'])->name('entityResolutionConflicts')->where('id', '[0-9]+');

    // ODRL Policies
    Route::match(['get', 'post'], '/odrlPolicies', [ResearchController::class, 'odrlPolicies'])->name('odrlPolicies');

    // Document Templates
    Route::match(['get', 'post'], '/documentTemplates', [ResearchController::class, 'documentTemplates'])->name('documentTemplates');

    // Saved Searches
    Route::get('/savedSearches', [ResearchController::class, 'savedSearches'])->name('savedSearches');
    Route::post('/saved-searches', [ResearchController::class, 'storeSavedSearch'])->name('savedSearches.store');
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

    // Annotations
    Route::get('/annotations', [ResearchController::class, 'annotations'])->name('annotations');
    Route::post('/annotations', [ResearchController::class, 'storeAnnotation'])->name('annotations.store');
    Route::put('/annotations/{id}', [ResearchController::class, 'updateAnnotation'])->name('annotations.update')->where('id', '[0-9]+');
    Route::delete('/annotations/{id}', [ResearchController::class, 'destroyAnnotation'])->name('annotations.destroy')->where('id', '[0-9]+');

    // Citations
    Route::get('/cite/{slug}', [ResearchController::class, 'cite'])->name('cite');

    // Projects
    Route::get('/projects', [ResearchController::class, 'projects'])->name('projects');
    Route::get('/projects/create', [ResearchController::class, 'createProject'])->name('projects.create');
    Route::post('/projects', [ResearchController::class, 'storeProject'])->name('projects.store');
    Route::match(['get', 'post'], '/viewProject/{id}', [ResearchController::class, 'viewProject'])->name('viewProject')->where('id', '[0-9]+');

    // Journal
    Route::match(['get', 'post'], '/journal', [ResearchController::class, 'journal'])->name('journal');
    Route::get('/journal/create', [ResearchController::class, 'createJournalEntry'])->name('journal.create');
    Route::get('/journal/{id}', [ResearchController::class, 'showJournalEntry'])->name('journal.show')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/journal/entry/{id}', [ResearchController::class, 'journalEntry'])->name('journalEntry')->where('id', '[0-9]+');

    // Bibliographies
    Route::match(['get', 'post'], '/bibliographies', [ResearchController::class, 'bibliographies'])->name('bibliographies');
    Route::match(['get', 'post'], '/viewBibliography/{id}', [ResearchController::class, 'viewBibliography'])->name('viewBibliography')->where('id', '[0-9]+');

    // Reports
    Route::match(['get', 'post'], '/reports', [ResearchController::class, 'reports'])->name('reports');
    Route::match(['get', 'post'], '/viewReport/{id}', [ResearchController::class, 'viewReport'])->name('viewReport')->where('id', '[0-9]+');

    // Reproductions
    Route::match(['get', 'post'], '/reproductions', [ResearchController::class, 'reproductions'])->name('reproductions');

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

    // Admin: Researchers
    Route::match(['get', 'post'], '/researchers', [ResearchController::class, 'researchers'])->name('researchers');
    Route::match(['get', 'post'], '/viewResearcher/{id}', [ResearchController::class, 'viewResearcher'])->name('viewResearcher')->where('id', '[0-9]+');
    Route::post('/approveResearcher/{id}', [ResearchController::class, 'approveResearcher'])->name('approveResearcher')->where('id', '[0-9]+');
    Route::post('/rejectResearcher/{id}', [ResearchController::class, 'rejectResearcher'])->name('rejectResearcher')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/approve', [ResearchController::class, 'approveResearcher'])->name('researchers.approve')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/reject', [ResearchController::class, 'rejectResearcher'])->name('researchers.reject')->where('id', '[0-9]+');
    Route::post('/researchers/{id}/suspend', [ResearchController::class, 'suspendResearcher'])->name('researchers.suspend')->where('id', '[0-9]+');

    // Admin: Bookings
    Route::match(['get', 'post'], '/bookings', [ResearchController::class, 'bookings'])->name('bookings');

    // Admin: Rooms
    Route::get('/rooms', [ResearchController::class, 'rooms'])->name('rooms');
    Route::match(['get', 'post'], '/editRoom', [ResearchController::class, 'editRoom'])->name('editRoom');

    // Admin: Seats, Equipment, Retrieval, Walk-In
    Route::match(['get', 'post'], '/seats', [ResearchController::class, 'seats'])->name('seats');
    Route::match(['get', 'post'], '/equipment', [ResearchController::class, 'equipment'])->name('equipment');
    Route::match(['get', 'post'], '/retrievalQueue', [ResearchController::class, 'retrievalQueue'])->name('retrievalQueue');
    Route::match(['get', 'post'], '/walkIn', [ResearchController::class, 'walkIn'])->name('walkIn');

    // Admin: Types, Statistics, Institutions, Activities
    Route::get('/adminTypes', [ResearchController::class, 'adminTypes'])->name('adminTypes');
    Route::match(['get', 'post'], '/adminStatistics', [ResearchController::class, 'adminStatistics'])->name('adminStatistics');
    Route::match(['get', 'post'], '/institutions', [ResearchController::class, 'institutions'])->name('institutions');
    Route::match(['get', 'post'], '/activities', [ResearchController::class, 'activities'])->name('activities');

    // AJAX endpoints
    Route::get('/searchItems', [ResearchController::class, 'searchItems'])->name('searchItems');
    Route::post('/addToCollection', [ResearchController::class, 'addToCollection'])->name('addToCollection');
    Route::post('/createCollectionAjax', [ResearchController::class, 'createCollectionAjax'])->name('createCollectionAjax');
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

// Settings alias
Route::get('/admin/ahg-settings', fn() => redirect()->route('settings.index'))->name('settings.ahgSettings');
