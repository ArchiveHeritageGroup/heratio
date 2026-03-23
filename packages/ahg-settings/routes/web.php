<?php

use AhgSettings\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Dynamic theme CSS — public, no auth needed
Route::get('/css/ahg-theme-dynamic.css', [SettingsController::class, 'dynamicCss'])->name('settings.dynamic-css');

Route::middleware('admin')->group(function () {
    // Dedicated settings pages (alphabetical, BEFORE catch-all)
    Route::match(['get', 'post'], '/admin/settings/clipboard', [SettingsController::class, 'clipboard'])->name('settings.clipboard');
    Route::match(['get', 'post'], '/admin/settings/csv-validator', [SettingsController::class, 'csvValidator'])->name('settings.csv-validator');
    Route::match(['get', 'post'], '/admin/settings/default-template', [SettingsController::class, 'defaultTemplate'])->name('settings.default-template');
    Route::match(['get', 'post'], '/admin/settings/diacritics', [SettingsController::class, 'diacritics'])->name('settings.diacritics');
    Route::match(['get', 'post'], '/admin/settings/digital-objects', [SettingsController::class, 'digitalObjects'])->name('settings.digital-objects');
    Route::match(['get', 'post'], '/admin/settings/dip-upload', [SettingsController::class, 'dipUpload'])->name('settings.dip-upload');
    Route::match(['get', 'post'], '/admin/settings/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::match(['get', 'post'], '/admin/settings/finding-aid', [SettingsController::class, 'findingAid'])->name('settings.finding-aid');
    Route::match(['get', 'post'], '/admin/settings/global', [SettingsController::class, 'global'])->name('settings.global');
    Route::match(['get', 'post'], '/admin/settings/header-customizations', [SettingsController::class, 'headerCustomizations'])->name('settings.header-customizations');
    Route::match(['get', 'post'], '/admin/settings/identifier', [SettingsController::class, 'identifier'])->name('settings.identifier');
    Route::match(['get', 'post'], '/admin/settings/interface-labels', [SettingsController::class, 'interfaceLabels'])->name('settings.interface-labels');
    Route::match(['get', 'post'], '/admin/settings/inventory', [SettingsController::class, 'inventory'])->name('settings.inventory');
    Route::match(['get', 'post'], '/admin/settings/languages', [SettingsController::class, 'languages'])->name('settings.languages');
    Route::match(['get', 'post'], '/admin/settings/markdown', [SettingsController::class, 'markdown'])->name('settings.markdown');
    Route::match(['get', 'post'], '/admin/settings/oai', [SettingsController::class, 'oai'])->name('settings.oai');
    Route::match(['get', 'post'], '/admin/settings/permissions', [SettingsController::class, 'permissions'])->name('settings.permissions');
    Route::match(['get', 'post'], '/admin/settings/privacy-notification', [SettingsController::class, 'privacyNotification'])->name('settings.privacy-notification');
    Route::match(['get', 'post'], '/admin/settings/security', [SettingsController::class, 'security'])->name('settings.security');
    Route::match(['get', 'post'], '/admin/settings/site-information', [SettingsController::class, 'siteInformation'])->name('settings.site-information');
    Route::match(['get', 'post'], '/admin/settings/storage-service', [SettingsController::class, 'storageService'])->name('settings.storage-service');
    Route::get('/admin/settings/system-info', [SettingsController::class, 'systemInfo'])->name('settings.system-info');
    Route::get('/admin/settings/services', [SettingsController::class, 'services'])->name('settings.services');
    Route::match(['get', 'post'], '/admin/settings/themes', [SettingsController::class, 'themes'])->name('settings.themes');
    Route::match(['get', 'post'], '/admin/settings/treeview', [SettingsController::class, 'treeview'])->name('settings.treeview');
    Route::match(['get', 'post'], '/admin/settings/uploads', [SettingsController::class, 'uploads'])->name('settings.uploads');
    Route::match(['get', 'post'], '/admin/settings/visible-elements', [SettingsController::class, 'visibleElements'])->name('settings.visible-elements');
    Route::match(['get', 'post'], '/admin/settings/web-analytics', [SettingsController::class, 'webAnalytics'])->name('settings.web-analytics');
    Route::match(['get', 'post'], '/admin/settings/analytics', [SettingsController::class, 'webAnalytics'])->name('settings.analytics'); // AtoM alias
    Route::get('/admin/settings/ai-condition', [SettingsController::class, 'aiCondition'])->name('settings.ai-condition');
    Route::match(['get', 'post'], '/admin/settings/ldap', [SettingsController::class, 'ldap'])->name('settings.ldap');
    Route::match(['get', 'post'], '/admin/settings/levels', [SettingsController::class, 'levels'])->name('settings.levels');
    Route::match(['get', 'post'], '/admin/settings/paths', [SettingsController::class, 'paths'])->name('settings.paths');
    Route::match(['get', 'post'], '/admin/settings/preservation', [SettingsController::class, 'preservation'])->name('settings.preservation');
    Route::match(['get', 'post'], '/admin/settings/webhooks', [SettingsController::class, 'webhooks'])->name('settings.webhooks');
    Route::match(['get', 'post'], '/admin/settings/tts', [SettingsController::class, 'tts'])->name('settings.tts');
    Route::match(['get', 'post'], '/admin/settings/icip-settings', [SettingsController::class, 'icipSettings'])->name('settings.icip-settings');
    Route::match(['get', 'post'], '/admin/settings/sector-numbering', [SettingsController::class, 'sectorNumbering'])->name('settings.sector-numbering');
    Route::match(['get', 'post'], '/admin/settings/numbering-schemes', [SettingsController::class, 'numberingSchemes'])->name('settings.numbering-schemes');
    Route::match(['get', 'post'], '/admin/settings/numbering-scheme-edit/{id?}', [SettingsController::class, 'numberingSchemeEdit'])->name('settings.numbering-scheme-edit');
    Route::match(['get', 'post'], '/admin/settings/dam-tools', [SettingsController::class, 'damTools'])->name('settings.dam-tools');
    Route::match(['get', 'post'], '/admin/settings/ai-services', [SettingsController::class, 'aiServices'])->name('settings.ai-services');
    Route::match(['get', 'post'], '/admin/settings/ahg-import', [SettingsController::class, 'ahgImportSettings'])->name('settings.ahg-import');
    Route::match(['get', 'post'], '/admin/settings/ahg-integration', [SettingsController::class, 'ahgIntegration'])->name('settings.ahg-integration');
    Route::match(['get', 'post'], '/admin/settings/page-elements', [SettingsController::class, 'pageElements'])->name('settings.page-elements');
    // Dropdown manager
    Route::get('/admin/settings/dropdown', [SettingsController::class, 'dropdownIndex'])->name('settings.dropdown.index');
    Route::match(['get', 'post'], '/admin/settings/dropdown/edit/{id?}', [SettingsController::class, 'dropdownEdit'])->name('settings.dropdown.store');
    Route::match(['get', 'post'], '/admin/errorLog', [SettingsController::class, 'errorLog'])->name('settings.error-log');
    Route::get('/admin/settings/cron-jobs', [SettingsController::class, 'cronJobs'])->name('settings.cron-jobs');
    Route::post('/admin/settings/cron-jobs/toggle/{id}', [SettingsController::class, 'cronJobToggle'])->name('settings.cron-toggle');
    Route::post('/admin/settings/cron-jobs/update/{id}', [SettingsController::class, 'cronJobUpdate'])->name('settings.cron-update');
    Route::post('/admin/settings/cron-jobs/run/{id}', [SettingsController::class, 'cronJobRunNow'])->name('settings.cron-run');
    Route::post('/admin/settings/cron-jobs/seed', [SettingsController::class, 'cronJobSeed'])->name('settings.cron-seed');
    Route::get('/ahgSettings/cronJobs', [SettingsController::class, 'cronJobs']); // AtoM alias
    // Aliases: AtoM DB menu paths → Heratio settings pages
    Route::get('/sfPluginAdminPlugin/themes', [SettingsController::class, 'themes']);
    Route::get('/settings/siteInformation', [SettingsController::class, 'siteInformation']);
    Route::get('/settings/visibleElements', [SettingsController::class, 'visibleElements']);
    Route::match(['get', 'post'], '/sfPluginAdminPlugin/plugins', [SettingsController::class, 'plugins'])->name('settings.plugins');

    // AHG group route must come before the catch-all {section} route
    Route::match(['get', 'post'], '/admin/settings/ahg/{group}', [SettingsController::class, 'ahgSection'])->name('settings.ahg');
    Route::match(['get', 'post'], '/admin/settings/{section}', [SettingsController::class, 'section'])->name('settings.section');
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('settings.index');
});

