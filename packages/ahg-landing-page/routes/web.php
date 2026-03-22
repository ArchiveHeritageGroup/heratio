<?php

use AhgLandingPage\Controllers\LandingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/landing/{slug?}', [LandingPageController::class, 'index'])->name('landing-page.show');

Route::middleware('auth')->prefix('landing-page')->group(function () {
    Route::get('/my-dashboard', [LandingPageController::class, 'myDashboard'])->name('landing-page.myDashboard');
    Route::get('/my-dashboard/list', [LandingPageController::class, 'myDashboardList'])->name('landing-page.myDashboard.list');
    Route::match(['get', 'post'], '/my-dashboard/create', [LandingPageController::class, 'myDashboardCreate'])->name('landing-page.myDashboard.create');
});

Route::middleware(['auth', 'admin'])->prefix('landing-page/admin')->group(function () {
    Route::get('/', [LandingPageController::class, 'list'])->name('landing-page.list');
    Route::match(['get', 'post'], '/create', [LandingPageController::class, 'create'])->name('landing-page.create');
    Route::get('/{id}/edit', [LandingPageController::class, 'edit'])->name('landing-page.edit');

    // AJAX endpoints
    Route::post('/{id}/settings', [LandingPageController::class, 'updateSettings'])->name('landing-page.updateSettings');
    Route::post('/{id}/delete', [LandingPageController::class, 'deletePage'])->name('landing-page.delete');
    Route::post('/block/add', [LandingPageController::class, 'addBlock'])->name('landing-page.block.add');
    Route::post('/block/{blockId}/update', [LandingPageController::class, 'updateBlock'])->name('landing-page.block.update');
    Route::post('/block/{blockId}/delete', [LandingPageController::class, 'deleteBlock'])->name('landing-page.block.delete');
    Route::post('/blocks/reorder', [LandingPageController::class, 'reorderBlocks'])->name('landing-page.blocks.reorder');
    Route::post('/block/{blockId}/duplicate', [LandingPageController::class, 'duplicateBlock'])->name('landing-page.block.duplicate');
    Route::post('/block/{blockId}/toggle-visibility', [LandingPageController::class, 'toggleVisibility'])->name('landing-page.block.toggleVisibility');
});
