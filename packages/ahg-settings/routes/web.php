<?php

use AhgSettings\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Dynamic theme CSS — public, no auth needed
Route::get('/css/ahg-theme-dynamic.css', [SettingsController::class, 'dynamicCss'])->name('settings.dynamic-css');

Route::middleware('admin')->group(function () {
    // Dedicated settings pages
    Route::match(['get', 'post'], '/admin/settings/global', [SettingsController::class, 'global'])->name('settings.global');
    Route::match(['get', 'post'], '/admin/settings/site-information', [SettingsController::class, 'siteInformation'])->name('settings.site-information');
    Route::match(['get', 'post'], '/admin/settings/security', [SettingsController::class, 'security'])->name('settings.security');
    Route::match(['get', 'post'], '/admin/settings/identifier', [SettingsController::class, 'identifier'])->name('settings.identifier');
    Route::match(['get', 'post'], '/admin/settings/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::match(['get', 'post'], '/admin/settings/treeview', [SettingsController::class, 'treeview'])->name('settings.treeview');
    Route::match(['get', 'post'], '/admin/settings/digital-objects', [SettingsController::class, 'digitalObjects'])->name('settings.digital-objects');
    Route::match(['get', 'post'], '/admin/settings/interface-labels', [SettingsController::class, 'interfaceLabels'])->name('settings.interface-labels');
    Route::match(['get', 'post'], '/admin/settings/oai', [SettingsController::class, 'oai'])->name('settings.oai');
    Route::get('/admin/settings/system-info', [SettingsController::class, 'systemInfo'])->name('settings.system-info');
    Route::get('/admin/settings/services', [SettingsController::class, 'services'])->name('settings.services');
    Route::match(['get', 'post'], '/admin/settings/themes', [SettingsController::class, 'themes'])->name('settings.themes');
    // AHG group route must come before the catch-all {section} route
    Route::match(['get', 'post'], '/admin/settings/ahg/{group}', [SettingsController::class, 'ahgSection'])->name('settings.ahg');
    Route::match(['get', 'post'], '/admin/settings/{section}', [SettingsController::class, 'section'])->name('settings.section');
    Route::get('/admin/settings', [SettingsController::class, 'index'])->name('settings.index');
});
