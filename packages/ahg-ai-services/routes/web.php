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

    // HTR routes
    Route::get('/admin/ai/htr', [AiController::class, 'htrDashboard'])->name('admin.ai.htr.dashboard');
    Route::get('/admin/ai/htr/extract', [AiController::class, 'htrExtract'])->name('admin.ai.htr.extract');
    Route::post('/admin/ai/htr/extract', [AiController::class, 'htrDoExtract'])->name('admin.ai.htr.doExtract');
    Route::get('/admin/ai/htr/results/{jobId}', [AiController::class, 'htrResults'])->name('admin.ai.htr.results');
    Route::get('/admin/ai/htr/extract-image/{jobId}', [AiController::class, 'htrExtractImage'])->name('admin.ai.htr.extractImage');
    Route::get('/admin/ai/htr/download/{jobId}/{fmt}', [AiController::class, 'htrDownload'])->name('admin.ai.htr.download');
    Route::get('/admin/ai/htr/batch', [AiController::class, 'htrBatch'])->name('admin.ai.htr.batch');
    Route::post('/admin/ai/htr/batch', [AiController::class, 'htrDoBatch'])->name('admin.ai.htr.doBatch');
    Route::get('/admin/ai/htr/sources', [AiController::class, 'htrSources'])->name('admin.ai.htr.sources');
    Route::post('/admin/ai/htr/save-fs-config', [AiController::class, 'htrSaveFsConfig'])->name('admin.ai.htr.saveFsConfig');
    Route::get('/admin/ai/htr/annotate', [AiController::class, 'htrAnnotate'])->name('admin.ai.htr.annotate');
    Route::post('/admin/ai/htr/annotate', [AiController::class, 'htrSaveAnnotation'])->name('admin.ai.htr.saveAnnotation');
    Route::get('/admin/ai/htr/folder-list', [AiController::class, 'htrFolderList'])->name('admin.ai.htr.folderList');
    Route::get('/admin/ai/htr/serve-image', [AiController::class, 'htrServeImage'])->name('admin.ai.htr.serveImage');
    Route::post('/admin/ai/htr/skip-image', [AiController::class, 'htrSkipImage'])->name('admin.ai.htr.skipImage');
    Route::post('/admin/ai/htr/split-rows', [AiController::class, 'htrSplitRows'])->name('admin.ai.htr.splitRows');
    Route::post('/admin/ai/htr/crop-ocr', [AiController::class, 'htrCropOcr'])->name('admin.ai.htr.cropOcr');
    Route::post('/admin/ai/htr/spellcheck', [AiController::class, 'htrSpellcheck'])->name('admin.ai.htr.spellcheck');
    Route::post('/admin/ai/htr/add-word', [AiController::class, 'htrAddWord'])->name('admin.ai.htr.addWord');
    Route::get('/admin/ai/htr/training', [AiController::class, 'htrTraining'])->name('admin.ai.htr.training');
    Route::post('/admin/ai/htr/training/start', [AiController::class, 'htrStartTraining'])->name('admin.ai.htr.startTraining');
