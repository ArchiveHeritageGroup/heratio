<?php

use AhgAiServices\Controllers\AiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AI Services Routes
|--------------------------------------------------------------------------
| All routes require authentication via 'auth' middleware.
| Admin routes are under /admin/ai prefix.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('admin/ai')->group(function () {

    // Dashboard
    Route::get('/', [AiController::class, 'index'])->name('admin.ai.index');

    // Configuration (GET = form, POST = save)
    Route::match(['get', 'post'], '/config', [AiController::class, 'config'])->name('admin.ai.config');

    // Test connection (AJAX)
    Route::post('/test-connection', [AiController::class, 'testConnection'])->name('admin.ai.test-connection');

    // AJAX API endpoints (JSON)
    Route::post('/summarize', [AiController::class, 'summarize'])->name('admin.ai.summarize');
    Route::post('/translate', [AiController::class, 'translate'])->name('admin.ai.translate');
    Route::post('/entities', [AiController::class, 'extractEntities'])->name('admin.ai.entities');
    Route::post('/suggest', [AiController::class, 'suggestDescription'])->name('admin.ai.suggest');
    Route::post('/spellcheck', [AiController::class, 'spellcheck'])->name('admin.ai.spellcheck');
});
