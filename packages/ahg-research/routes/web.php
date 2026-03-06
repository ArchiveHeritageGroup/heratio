<?php

use AhgResearch\Controllers\ResearchController;
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

    // Registration (public and authenticated)
    Route::match(['get', 'post'], '/register', [ResearchController::class, 'register'])->name('register');
    Route::match(['get', 'post'], '/publicRegister', [ResearchController::class, 'publicRegister'])->name('publicRegister');
    Route::get('/registrationComplete', [ResearchController::class, 'registrationComplete'])->name('registrationComplete');

    // Profile
    Route::match(['get', 'post'], '/profile', [ResearchController::class, 'profile'])->name('profile');
    Route::match(['get', 'post'], '/apiKeys', [ResearchController::class, 'apiKeys'])->name('apiKeys');
    Route::match(['get', 'post'], '/renewal', [ResearchController::class, 'renewal'])->name('renewal');

    // Workspace
    Route::match(['get', 'post'], '/workspace', [ResearchController::class, 'workspace'])->name('workspace');

    // Saved Searches
    Route::match(['get', 'post'], '/savedSearches', [ResearchController::class, 'savedSearches'])->name('savedSearches');

    // Collections (Evidence Sets)
    Route::match(['get', 'post'], '/collections', [ResearchController::class, 'collections'])->name('collections');
    Route::match(['get', 'post'], '/viewCollection', [ResearchController::class, 'viewCollection'])->name('viewCollection')->where('id', '[0-9]+');

    // Annotations
    Route::match(['get', 'post'], '/annotations', [ResearchController::class, 'annotations'])->name('annotations');

    // Citations
    Route::get('/cite/{slug}', [ResearchController::class, 'cite'])->name('cite');

    // Projects
    Route::match(['get', 'post'], '/projects', [ResearchController::class, 'projects'])->name('projects');
    Route::match(['get', 'post'], '/viewProject/{id}', [ResearchController::class, 'viewProject'])->name('viewProject')->where('id', '[0-9]+');

    // Journal
    Route::match(['get', 'post'], '/journal', [ResearchController::class, 'journal'])->name('journal');
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
    Route::match(['get', 'post'], '/book', [ResearchController::class, 'book'])->name('book');
    Route::match(['get', 'post'], '/viewBooking/{id}', [ResearchController::class, 'viewBooking'])->name('viewBooking')->where('id', '[0-9]+');
    Route::post('/checkIn/{id}', [ResearchController::class, 'checkIn'])->name('checkIn')->where('id', '[0-9]+');
    Route::post('/checkOut/{id}', [ResearchController::class, 'checkOut'])->name('checkOut')->where('id', '[0-9]+');

    // Notifications
    Route::match(['get', 'post'], '/notifications', [ResearchController::class, 'notifications'])->name('notifications');

    // Admin: Researchers
    Route::match(['get', 'post'], '/researchers', [ResearchController::class, 'researchers'])->name('researchers');
    Route::match(['get', 'post'], '/viewResearcher/{id}', [ResearchController::class, 'viewResearcher'])->name('viewResearcher')->where('id', '[0-9]+');
    Route::post('/approveResearcher/{id}', [ResearchController::class, 'approveResearcher'])->name('approveResearcher')->where('id', '[0-9]+');
    Route::post('/rejectResearcher/{id}', [ResearchController::class, 'rejectResearcher'])->name('rejectResearcher')->where('id', '[0-9]+');

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
