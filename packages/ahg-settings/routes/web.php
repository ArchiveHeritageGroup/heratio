<?php

use AhgSettings\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Dynamic theme CSS — public, no auth needed
Route::get('/css/ahg-theme-dynamic.css', [SettingsController::class, 'dynamicCss'])->name('settings.dynamic-css');

// Legacy AtoM URL aliases
Route::get('/ahgSettings', fn () => redirect('/admin/settings'));
Route::get('/ahgSettings/{action}', fn (string $action) => redirect('/admin/settings/' . \Illuminate\Support\Str::kebab($action)));

Route::middleware('admin')->group(function () {
    // Dedicated settings pages (alphabetical, BEFORE catch-all)
    Route::match(['get', 'post'], '/admin/settings/clipboard', [SettingsController::class, 'clipboard'])->name('settings.clipboard');
    Route::match(['get', 'post'], '/admin/settings/csv-validator', [SettingsController::class, 'csvValidator'])->name('settings.csv-validator');
    Route::match(['get', 'post'], '/admin/settings/default-template', [SettingsController::class, 'defaultTemplate'])->name('settings.default-template');
    Route::match(['get', 'post'], '/admin/settings/diacritics', [SettingsController::class, 'diacritics'])->name('settings.diacritics');
    Route::match(['get', 'post'], '/admin/settings/digital-objects', [SettingsController::class, 'digitalObjects'])->name('settings.digital-objects');
    Route::match(['get', 'post'], '/admin/settings/dip-upload', [SettingsController::class, 'dipUpload'])->name('settings.dip-upload');
    Route::get('/admin/settings/email', fn () => redirect('/admin/ahgSettings/email')); // legacy redirect
    Route::match(['get', 'post'], '/settings/findingAid', [SettingsController::class, 'findingAid'])->name('settings.finding-aid');
    Route::match(['get', 'post'], '/admin/settings/finding-aid', [SettingsController::class, 'findingAid']); // legacy alias
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
    Route::get('/admin/settings/ai-condition', fn () => redirect('/admin/ahgSettings/aiCondition')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ldap', [SettingsController::class, 'ldap'])->name('settings.ldap');
    Route::get('/admin/settings/levels', fn () => redirect('/admin/ahgSettings/levels')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/paths', [SettingsController::class, 'paths'])->name('settings.paths');
    Route::match(['get', 'post'], '/admin/settings/preservation', [SettingsController::class, 'preservation'])->name('settings.preservation');
    Route::match(['get', 'post'], '/admin/settings/webhooks', [SettingsController::class, 'webhooks'])->name('settings.webhooks');
    Route::match(['get', 'post'], '/admin/settings/tts', [SettingsController::class, 'tts'])->name('settings.tts');
    Route::get('/admin/settings/icip-settings', fn () => redirect('/admin/ahgSettings/icipSettings')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/sector-numbering', [SettingsController::class, 'sectorNumbering'])->name('settings.sector-numbering');
    Route::match(['get', 'post'], '/admin/settings/numbering-schemes', [SettingsController::class, 'numberingSchemes'])->name('settings.numbering-schemes');
    Route::match(['get', 'post'], '/admin/settings/numbering-scheme-edit/{id?}', [SettingsController::class, 'numberingSchemeEdit'])->name('settings.numbering-scheme-edit');
    Route::match(['get', 'post'], '/admin/settings/dam-tools', [SettingsController::class, 'damTools'])->name('settings.dam-tools');
    Route::get('/admin/settings/ai-services', fn () => redirect('/admin/ahgSettings/aiServices')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg-import', [SettingsController::class, 'ahgImportSettings'])->name('settings.ahg-import');
    Route::get('/admin/settings/ahg-integration', fn () => redirect('/admin/ahgSettings/ahgIntegration')); // legacy redirect
    Route::get('/admin/settings/library', [SettingsController::class, 'library'])->name('settings.library');
    Route::get('/admin/settings/carousel', [SettingsController::class, 'carousel'])->name('settings.carousel');
    Route::get('/admin/settings/authority', fn () => redirect('/admin/ahgSettings/authority')); // legacy redirect
    Route::match(['get', 'post'], '/settings/pageElements', [SettingsController::class, 'pageElements'])->name('settings.page-elements');
    Route::match(['get', 'post'], '/admin/settings/page-elements', [SettingsController::class, 'pageElements']); // legacy alias
    // Dropdown manager
    Route::get('/admin/settings/dropdown', [SettingsController::class, 'dropdownIndex'])->name('settings.dropdown.index');
    Route::match(['get', 'post'], '/admin/settings/dropdown/edit/{id?}', [SettingsController::class, 'dropdownEdit'])->name('settings.dropdown.store');
    Route::match(['get', 'post'], '/admin/errorLog', [SettingsController::class, 'errorLog'])->name('settings.error-log');
    Route::get('/admin/settings/cron-jobs', fn () => redirect('/admin/ahgSettings/cronJobs')); // legacy redirect
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

    // ── Canonical /admin/ahgSettings/ routes (standardised URLs) ──
    Route::match(['get', 'post'], '/admin/ahgSettings/aiCondition', [SettingsController::class, 'aiCondition'])->name('settings.ai-condition');
    Route::match(['get', 'post'], '/admin/ahgSettings/accession', [SettingsController::class, 'accessionSettings'])->name('settings.ahg.accession');
    Route::match(['get', 'post'], '/admin/ahgSettings/ahgIntegration', [SettingsController::class, 'ahgIntegration'])->name('settings.ahg-integration');
    Route::match(['get', 'post'], '/admin/ahgSettings/aiServices', [SettingsController::class, 'aiServices'])->name('settings.ai-services');
    Route::match(['get', 'post'], '/admin/ahgSettings/audit', [SettingsController::class, 'auditSettings'])->name('settings.ahg.audit');
    Route::match(['get', 'post'], '/admin/ahgSettings/authority', [SettingsController::class, 'authority'])->name('settings.authority');
    Route::match(['get', 'post'], '/admin/ahgSettings/jobs', [SettingsController::class, 'jobsSettings'])->name('settings.ahg.jobs');
    Route::match(['get', 'post'], '/admin/ahgSettings/photos', [SettingsController::class, 'photosSettings'])->name('settings.ahg.photos');
    Route::get('/admin/ahgSettings/cronJobs', [SettingsController::class, 'cronJobs'])->name('settings.cron-jobs');
    Route::match(['get', 'post'], '/admin/ahgSettings/ingest', [SettingsController::class, 'ingestSettings'])->name('settings.ahg.ingest');
    Route::match(['get', 'post'], '/admin/ahgSettings/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::match(['get', 'post'], '/admin/ahgSettings/encryption', [SettingsController::class, 'encryptionSettings'])->name('settings.ahg.encryption');
    Route::match(['get', 'post'], '/admin/ahgSettings/ftp', [SettingsController::class, 'ftpSettings'])->name('settings.ahg.ftp');
    Route::match(['get', 'post'], '/admin/ahgSettings/fuseki', [SettingsController::class, 'fusekiSettings'])->name('settings.ahg.fuseki');
    Route::match(['get', 'post'], '/admin/ahgSettings/icipSettings', [SettingsController::class, 'icipSettings'])->name('settings.icip-settings');
    Route::match(['get', 'post'], '/admin/ahgSettings/iiif', [SettingsController::class, 'iiifGroupSettings'])->name('settings.ahg.iiif');
    Route::match(['get', 'post'], '/admin/ahgSettings/levels', [SettingsController::class, 'levels'])->name('settings.levels');
    Route::match(['get', 'post'], '/admin/ahgSettings/library', [SettingsController::class, 'librarySettings'])->name('settings.ahg.library');

    // Legacy redirects for old /admin/settings/ahg/ paths
    Route::get('/admin/settings/ahg/ai_condition', fn () => redirect('/admin/ahgSettings/aiCondition'));
    Route::get('/admin/settings/ahg/accession', fn () => redirect('/admin/ahgSettings/accession'));
    Route::get('/admin/settings/ahg/audit', fn () => redirect('/admin/ahgSettings/audit')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/data_protection', [SettingsController::class, 'dataProtectionSettings'])->name('settings.ahg.data_protection');
    Route::get('/admin/settings/ahg/encryption', fn () => redirect('/admin/ahgSettings/encryption')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/faces', [SettingsController::class, 'facesSettings'])->name('settings.ahg.faces');
    Route::get('/admin/settings/ahg/ftp', fn () => redirect('/admin/ahgSettings/ftp')); // legacy redirect
    Route::get('/admin/settings/ahg/fuseki', fn () => redirect('/admin/ahgSettings/fuseki')); // legacy redirect
    Route::get('/admin/settings/ahg/jobs', fn () => redirect('/admin/ahgSettings/jobs')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/spectrum', [SettingsController::class, 'spectrumSettings'])->name('settings.ahg.spectrum');
    Route::get('/admin/settings/ahg/photos', fn () => redirect('/admin/ahgSettings/photos')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/media', [SettingsController::class, 'mediaSettings'])->name('settings.ahg.media');
    Route::match(['get', 'post'], '/admin/settings/ahg/metadata', [SettingsController::class, 'metadataSettings'])->name('settings.ahg.metadata');
    Route::get('/admin/settings/ahg/ingest', fn () => redirect('/admin/ahgSettings/ingest')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/integrity', [SettingsController::class, 'integritySettings'])->name('settings.ahg.integrity');
    Route::match(['get', 'post'], '/admin/settings/ahg/voice_ai', [SettingsController::class, 'voiceAiSettings'])->name('settings.ahg.voice_ai');
    Route::get('/admin/settings/ahg/iiif', fn () => redirect('/admin/ahgSettings/iiif')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/security', [SettingsController::class, 'securitySettings'])->name('settings.ahg.security');
    Route::get('/admin/settings/ahg/library', fn () => redirect('/admin/ahgSettings/library')); // legacy redirect
    Route::match(['get', 'post'], '/admin/settings/ahg/multi_tenant', [SettingsController::class, 'multiTenantSettings'])->name('settings.ahg.multi_tenant');
    Route::match(['get', 'post'], '/admin/settings/ahg/portable_export', [SettingsController::class, 'portableExportSettings'])->name('settings.ahg.portable_export');
    Route::match(['get', 'post'], '/admin/settings/ahg/compliance', [SettingsController::class, 'complianceSettings'])->name('settings.ahg.compliance');

    // AHG group route — catch-all for generic key-value settings
    Route::match(['get', 'post'], '/admin/settings/ahg/{group}', [SettingsController::class, 'ahgSection'])->name('settings.ahg');
    Route::match(['get', 'post'], '/admin/settings/{section}', [SettingsController::class, 'section'])->name('settings.section');
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('settings.index');
});

