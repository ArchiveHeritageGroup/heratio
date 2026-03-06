<?php

use AhgSettings\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// AHG group route must come before the catch-all {section} route
Route::get('/admin/settings/ahg/{group}', [SettingsController::class, 'ahgSection'])->name('settings.ahg');
Route::get('/admin/settings/{section}', [SettingsController::class, 'section'])->name('settings.section');
Route::get('/admin/settings', [SettingsController::class, 'index'])->name('settings.index');
