<?php

use AhgRegistry\Controllers\RegistryController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ */
/*  Public routes                                                      */
/* ------------------------------------------------------------------ */
Route::prefix('registry')->name('registry.')->group(function () {

    Route::get('/',                          [RegistryController::class, 'index'])->name('index');
    Route::get('/search',                    [RegistryController::class, 'search'])->name('search');
    Route::get('/map',                       [RegistryController::class, 'map'])->name('map');

    // Institutions
    Route::get('/institutions',              [RegistryController::class, 'institutionBrowse'])->name('institutionBrowse');
    Route::get('/institution/{id}',          [RegistryController::class, 'institutionView'])->name('institutionView')->where('id', '[0-9]+');
    Route::get('/institution/{id}/software', [RegistryController::class, 'institutionSoftware'])->name('institutionSoftware')->where('id', '[0-9]+');
    Route::get('/institution/{id}/vendors',  [RegistryController::class, 'institutionVendors'])->name('institutionVendors')->where('id', '[0-9]+');

    // Vendors
    Route::get('/vendors',                   [RegistryController::class, 'vendorBrowse'])->name('vendorBrowse');
    Route::get('/vendor/{id}',               [RegistryController::class, 'vendorView'])->name('vendorView')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/clients',       [RegistryController::class, 'vendorClients'])->name('vendorClients')->where('id', '[0-9]+');

    // Software
    Route::get('/software',                  [RegistryController::class, 'softwareBrowse'])->name('softwareBrowse');
    Route::get('/software/{id}',             [RegistryController::class, 'softwareView'])->name('softwareView')->where('id', '[0-9]+');
    Route::get('/software/{id}/releases',    [RegistryController::class, 'softwareReleases'])->name('softwareReleases')->where('id', '[0-9]+');
    Route::get('/software/{id}/components',  [RegistryController::class, 'softwareComponents'])->name('softwareComponents')->where('id', '[0-9]+');

    // Standards
    Route::get('/standards',                 [RegistryController::class, 'standardBrowse'])->name('standardBrowse');
    Route::get('/standard/{id}',             [RegistryController::class, 'standardView'])->name('standardView')->where('id', '[0-9]+');
    Route::get('/standards/schema',          [RegistryController::class, 'standardsSchema'])->name('standardsSchema');

    // Groups
    Route::get('/groups',                    [RegistryController::class, 'groupBrowse'])->name('groupBrowse');
    Route::get('/group/{id}',                [RegistryController::class, 'groupView'])->name('groupView')->where('id', '[0-9]+');
    Route::get('/group/{id}/members',        [RegistryController::class, 'groupMembers'])->name('groupMembers')->where('id', '[0-9]+');

    // Blog
    Route::get('/blog',                      [RegistryController::class, 'blogList'])->name('blogList');
    Route::get('/blog/{slug}',               [RegistryController::class, 'blogView'])->name('blogView');

    // Community / Discussions
    Route::get('/community',                 [RegistryController::class, 'community'])->name('community');
    Route::get('/discussions',               [RegistryController::class, 'discussionList'])->name('discussionList');
    Route::get('/discussion/{id}',           [RegistryController::class, 'discussionView'])->name('discussionView')->where('id', '[0-9]+');

    // ERD
    Route::get('/erd',                       [RegistryController::class, 'erdBrowse'])->name('erdBrowse');
    Route::get('/erd/{id}',                  [RegistryController::class, 'erdView'])->name('erdView')->where('id', '[0-9]+');

    // Setup guides
    Route::get('/setup-guides',              [RegistryController::class, 'setupGuideBrowse'])->name('setupGuideBrowse');
    Route::get('/setup-guide/{id}',          [RegistryController::class, 'setupGuideView'])->name('setupGuideView')->where('id', '[0-9]+');

    // Newsletters
    Route::get('/newsletters',               [RegistryController::class, 'newsletterBrowse'])->name('newsletterBrowse');
    Route::get('/newsletter/{id}',           [RegistryController::class, 'newsletterView'])->name('newsletterView')->where('id', '[0-9]+');
    Route::get('/newsletter/subscribe',      [RegistryController::class, 'newsletterSubscribe'])->name('newsletterSubscribe');
    Route::get('/newsletter/unsubscribe',    [RegistryController::class, 'newsletterUnsubscribe'])->name('newsletterUnsubscribe');

    // Reviews
    Route::get('/review',                    [RegistryController::class, 'reviewForm'])->name('reviewForm');

    // Instance
    Route::get('/instance/{id}',             [RegistryController::class, 'instanceView'])->name('instanceView')->where('id', '[0-9]+');

    // Contact
    Route::get('/contact',                   [RegistryController::class, 'contactForm'])->name('contactForm');

    // Auth
    Route::get('/login',                     [RegistryController::class, 'login'])->name('login');
    Route::get('/register',                  [RegistryController::class, 'register'])->name('register');
});

/* ------------------------------------------------------------------ */
/*  Authenticated routes                                               */
/* ------------------------------------------------------------------ */
Route::prefix('registry')->name('registry.')->middleware('auth')->group(function () {

    // Institution management
    Route::get('/institution/register',          [RegistryController::class, 'institutionRegister'])->name('institutionRegister');
    Route::post('/institution/register',         [RegistryController::class, 'institutionRegisterStore'])->name('institutionRegisterStore');
    Route::get('/institution/{id}/edit',         [RegistryController::class, 'institutionEdit'])->name('institutionEdit')->where('id', '[0-9]+');

    // Vendor management
    Route::get('/vendor/register',               [RegistryController::class, 'vendorRegister'])->name('vendorRegister');
    Route::get('/vendor/{id}/edit',              [RegistryController::class, 'vendorEdit'])->name('vendorEdit')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/client-form',       [RegistryController::class, 'vendorClientForm'])->name('vendorClientForm')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/software-manage',   [RegistryController::class, 'vendorSoftwareManage'])->name('vendorSoftwareManage')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/software-form',     [RegistryController::class, 'vendorSoftwareForm'])->name('vendorSoftwareForm')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/software-upload',   [RegistryController::class, 'vendorSoftwareUpload'])->name('vendorSoftwareUpload')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/release-manage',    [RegistryController::class, 'vendorReleaseManage'])->name('vendorReleaseManage')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/release-form',      [RegistryController::class, 'vendorReleaseForm'])->name('vendorReleaseForm')->where('id', '[0-9]+');
    Route::get('/vendor/{id}/call-log-form',     [RegistryController::class, 'vendorCallLogForm'])->name('vendorCallLogForm')->where('id', '[0-9]+');

    // Software component management
    Route::get('/software/{id}/component/add',            [RegistryController::class, 'softwareComponentAdd'])->name('softwareComponentAdd')->where('id', '[0-9]+');
    Route::get('/software/{id}/component/{cid}/edit',     [RegistryController::class, 'softwareComponentEdit'])->name('softwareComponentEdit')->where('id', '[0-9]+');

    // Group management
    Route::get('/group/create',                  [RegistryController::class, 'groupCreate'])->name('groupCreate');
    Route::get('/group/{id}/edit',               [RegistryController::class, 'groupEdit'])->name('groupEdit')->where('id', '[0-9]+');
    Route::get('/group/{id}/members/manage',     [RegistryController::class, 'groupMembersManage'])->name('groupMembersManage')->where('id', '[0-9]+');

    // Blog
    Route::get('/blog/new',                      [RegistryController::class, 'blogNew'])->name('blogNew');
    Route::get('/blog/{id}/edit',                [RegistryController::class, 'blogEdit'])->name('blogEdit')->where('id', '[0-9]+');
    Route::get('/my-blog',                       [RegistryController::class, 'myBlog'])->name('myBlog');

    // Discussion
    Route::get('/discussion/new',                [RegistryController::class, 'discussionNew'])->name('discussionNew');
    Route::get('/discussion/{id}/reply',         [RegistryController::class, 'discussionReply'])->name('discussionReply')->where('id', '[0-9]+');

    // Favorites & groups
    Route::get('/my-favorites',                  [RegistryController::class, 'myFavorites'])->name('myFavorites');
    Route::get('/my-groups',                     [RegistryController::class, 'myGroups'])->name('myGroups');

    // My institution
    Route::get('/my-institution',                [RegistryController::class, 'myInstitutionDashboard'])->name('myInstitutionDashboard');
    Route::get('/my-institution/contacts',       [RegistryController::class, 'myInstitutionContacts'])->name('myInstitutionContacts');
    Route::get('/my-institution/contact/add',    [RegistryController::class, 'myInstitutionContactAdd'])->name('myInstitutionContactAdd');
    Route::get('/my-institution/contact/{id}/edit', [RegistryController::class, 'myInstitutionContactEdit'])->name('myInstitutionContactEdit')->where('id', '[0-9]+');
    Route::get('/my-institution/instances',      [RegistryController::class, 'myInstitutionInstances'])->name('myInstitutionInstances');
    Route::get('/my-institution/instance/add',   [RegistryController::class, 'myInstitutionInstanceAdd'])->name('myInstitutionInstanceAdd');
    Route::get('/my-institution/instance/{id}/edit', [RegistryController::class, 'myInstitutionInstanceEdit'])->name('myInstitutionInstanceEdit')->where('id', '[0-9]+');
    Route::get('/my-institution/software',       [RegistryController::class, 'myInstitutionSoftware'])->name('myInstitutionSoftware');
    Route::get('/my-institution/vendors',        [RegistryController::class, 'myInstitutionVendors'])->name('myInstitutionVendors');
    Route::get('/my-institution/review',         [RegistryController::class, 'myInstitutionReview'])->name('myInstitutionReview');

    // My vendor
    Route::get('/my-vendor',                     [RegistryController::class, 'myVendorDashboard'])->name('myVendorDashboard');
    Route::get('/my-vendor/contacts',            [RegistryController::class, 'myVendorContacts'])->name('myVendorContacts');
    Route::get('/my-vendor/contact/add',         [RegistryController::class, 'myVendorContactAdd'])->name('myVendorContactAdd');
    Route::get('/my-vendor/contact/{id}/edit',   [RegistryController::class, 'myVendorContactEdit'])->name('myVendorContactEdit')->where('id', '[0-9]+');
    Route::get('/my-vendor/clients',             [RegistryController::class, 'myVendorClients'])->name('myVendorClients');
    Route::get('/my-vendor/client/add',          [RegistryController::class, 'myVendorClientAdd'])->name('myVendorClientAdd');
    Route::get('/my-vendor/software',            [RegistryController::class, 'myVendorSoftware'])->name('myVendorSoftware');
    Route::get('/my-vendor/software/add',        [RegistryController::class, 'myVendorSoftwareAdd'])->name('myVendorSoftwareAdd');
    Route::get('/my-vendor/software/{id}/edit',  [RegistryController::class, 'myVendorSoftwareEdit'])->name('myVendorSoftwareEdit')->where('id', '[0-9]+');
    Route::get('/my-vendor/software/{id}/releases', [RegistryController::class, 'myVendorSoftwareReleases'])->name('myVendorSoftwareReleases')->where('id', '[0-9]+');
    Route::get('/my-vendor/software/{id}/release/add', [RegistryController::class, 'myVendorSoftwareReleaseAdd'])->name('myVendorSoftwareReleaseAdd')->where('id', '[0-9]+');
    Route::get('/my-vendor/software/{id}/upload', [RegistryController::class, 'myVendorSoftwareUpload'])->name('myVendorSoftwareUpload')->where('id', '[0-9]+');
    Route::get('/my-vendor/call-log',            [RegistryController::class, 'myVendorCallLog'])->name('myVendorCallLog');
    Route::get('/my-vendor/call-log/{id}',       [RegistryController::class, 'myVendorCallLogView'])->name('myVendorCallLogView')->where('id', '[0-9]+');

    // Contacts & instances generic
    Route::get('/contacts/{parentId}/manage',    [RegistryController::class, 'contactsManage'])->name('contactsManage');
    Route::get('/instances/{parentId}/manage',   [RegistryController::class, 'instancesManage'])->name('instancesManage');
    Route::get('/instance/{id}/relink',          [RegistryController::class, 'instanceRelink'])->name('instanceRelink')->where('id', '[0-9]+');
    Route::get('/instance/form/{id?}',           [RegistryController::class, 'instanceForm'])->name('instanceForm');
});

/* ------------------------------------------------------------------ */
/*  Admin routes                                                       */
/* ------------------------------------------------------------------ */
Route::prefix('registry/admin')->name('registry.admin.')->middleware('admin')->group(function () {
    Route::get('/',                    [RegistryController::class, 'adminDashboard'])->name('dashboard');
    Route::get('/users',               [RegistryController::class, 'adminUsers'])->name('users');
    Route::get('/user/{id}/edit',      [RegistryController::class, 'adminUserEdit'])->name('userEdit')->where('id', '[0-9]+');
    Route::get('/user/{id}/manage',    [RegistryController::class, 'adminUserManage'])->name('userManage')->where('id', '[0-9]+');
    Route::get('/groups',              [RegistryController::class, 'adminGroups'])->name('groups');
    Route::get('/group/{id}/edit',     [RegistryController::class, 'adminGroupEdit'])->name('groupEdit')->where('id', '[0-9]+');
    Route::get('/group/{id}/members',  [RegistryController::class, 'adminGroupMembers'])->name('groupMembers')->where('id', '[0-9]+');
    Route::get('/institutions',        [RegistryController::class, 'adminInstitutions'])->name('institutions');
    Route::get('/institution/{id}/users', [RegistryController::class, 'adminInstitutionUsers'])->name('institutionUsers')->where('id', '[0-9]+');
    Route::get('/vendors',             [RegistryController::class, 'adminVendors'])->name('vendors');
    Route::get('/software',            [RegistryController::class, 'adminSoftware'])->name('software');
    Route::get('/standards',           [RegistryController::class, 'adminStandards'])->name('standards');
    Route::get('/standard/{id}/edit',  [RegistryController::class, 'adminStandardEdit'])->name('standardEdit')->where('id', '[0-9]+');
    Route::get('/dropdowns',           [RegistryController::class, 'adminDropdowns'])->name('dropdowns');
    Route::get('/dropdown/{id}/edit',  [RegistryController::class, 'adminDropdownEdit'])->name('dropdownEdit')->where('id', '[0-9]+');
    Route::get('/blog',                [RegistryController::class, 'adminBlog'])->name('blog');
    Route::get('/discussions',         [RegistryController::class, 'adminDiscussions'])->name('discussions');
    Route::get('/reviews',             [RegistryController::class, 'adminReviews'])->name('reviews');
    Route::get('/newsletters',         [RegistryController::class, 'adminNewsletters'])->name('newsletters');
    Route::get('/newsletter/form/{id?}', [RegistryController::class, 'adminNewsletterForm'])->name('newsletterForm');
    Route::get('/subscribers',         [RegistryController::class, 'adminSubscribers'])->name('subscribers');
    Route::get('/email',               [RegistryController::class, 'adminEmail'])->name('email');
    Route::get('/import',              [RegistryController::class, 'adminImport'])->name('import');
    Route::get('/sync',                [RegistryController::class, 'adminSync'])->name('sync');
    Route::get('/settings',            [RegistryController::class, 'adminSettings'])->name('settings');
    Route::get('/footer',              [RegistryController::class, 'adminFooter'])->name('footer');
    Route::get('/setup-guides',        [RegistryController::class, 'adminSetupGuides'])->name('setupGuides');
    Route::get('/erd',                 [RegistryController::class, 'adminErd'])->name('erd');
    Route::get('/erd/{id}/edit',       [RegistryController::class, 'adminErdEdit'])->name('erdEdit')->where('id', '[0-9]+');
    Route::get('/extension/{id}/edit', [RegistryController::class, 'adminExtensionEdit'])->name('extensionEdit')->where('id', '[0-9]+');

});
