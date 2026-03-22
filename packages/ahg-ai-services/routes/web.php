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
    Route::get('/admin/ai/batch', [AiController::class, 'batch'])->name('admin.ai.batch');
    Route::get('/admin/ai/batch/{id}', [AiController::class, 'batchView'])->name('admin.ai.batch.view')->whereNumber('id');
    Route::match(['get','post'], '/admin/ai/pdf-overlay', [AiController::class, 'pdfOverlay'])->name('admin.ai.pdf-overlay');
    Route::get('/admin/ai/review', [AiController::class, 'review'])->name('admin.ai.review');
    Route::get('/admin/ai/suggest-review', [AiController::class, 'suggestReview'])->name('admin.ai.suggest-review');
    Route::get('/admin/ai/condition/assess', [AiController::class, 'conditionAssess'])->name('admin.ai.condition.assess');
    Route::get('/admin/ai/condition/browse', [AiController::class, 'conditionBrowse'])->name('admin.ai.condition.browse');
    Route::match(['get','post'], '/admin/ai/condition/bulk', [AiController::class, 'conditionBulk'])->name('admin.ai.condition.bulk');
    Route::get('/admin/ai/condition/clients', [AiController::class, 'conditionClients'])->name('admin.ai.condition.clients');
    Route::get('/admin/ai/condition/dashboard', [AiController::class, 'conditionDashboard'])->name('admin.ai.condition.dashboard');
    Route::get('/admin/ai/condition/history', [AiController::class, 'conditionHistory'])->name('admin.ai.condition.history');
    Route::match(['get','post'], '/admin/ai/condition/manual', [AiController::class, 'conditionManualAssess'])->name('admin.ai.condition.manual');
    Route::match(['get','post'], '/admin/ai/condition/training', [AiController::class, 'conditionTraining'])->name('admin.ai.condition.training');
    Route::get('/admin/ai/condition/{id}', [AiController::class, 'conditionView'])->name('admin.ai.condition.view')->whereNumber('id');
