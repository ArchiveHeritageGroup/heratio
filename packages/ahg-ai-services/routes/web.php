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

    // Batch & review
    Route::get('/batch', [AiController::class, 'batch'])->name('admin.ai.batch');
    Route::get('/batch/{id}', [AiController::class, 'batchView'])->name('admin.ai.batch.view')->whereNumber('id');
    Route::match(['get','post'], '/pdf-overlay', [AiController::class, 'pdfOverlay'])->name('admin.ai.pdf-overlay');
    Route::get('/review', [AiController::class, 'review'])->name('admin.ai.review');
    Route::get('/suggest-review', [AiController::class, 'suggestReview'])->name('admin.ai.suggest-review');

    // Condition assessment
    Route::get('/condition/assess', [AiController::class, 'conditionAssess'])->name('admin.ai.condition.assess');
    Route::get('/condition/browse', [AiController::class, 'conditionBrowse'])->name('admin.ai.condition.browse');
    Route::match(['get','post'], '/condition/bulk', [AiController::class, 'conditionBulk'])->name('admin.ai.condition.bulk');
    Route::get('/condition/clients', [AiController::class, 'conditionClients'])->name('admin.ai.condition.clients');
    Route::get('/condition/dashboard', [AiController::class, 'conditionDashboard'])->name('admin.ai.condition.dashboard');
    Route::get('/condition/history', [AiController::class, 'conditionHistory'])->name('admin.ai.condition.history');
    Route::match(['get','post'], '/condition/manual', [AiController::class, 'conditionManualAssess'])->name('admin.ai.condition.manual');
    Route::match(['get','post'], '/condition/training', [AiController::class, 'conditionTraining'])->name('admin.ai.condition.training');
    Route::get('/condition/{id}', [AiController::class, 'conditionView'])->name('admin.ai.condition.view')->whereNumber('id');

    // HTR routes
    Route::get('/htr', [AiController::class, 'htrDashboard'])->name('admin.ai.htr.dashboard');
    Route::get('/htr/extract', [AiController::class, 'htrExtract'])->name('admin.ai.htr.extract');
    Route::post('/htr/extract', [AiController::class, 'htrDoExtract'])->name('admin.ai.htr.doExtract');
    Route::get('/htr/results/{jobId}', [AiController::class, 'htrResults'])->name('admin.ai.htr.results');
    Route::get('/htr/extract-image/{jobId}', [AiController::class, 'htrExtractImage'])->name('admin.ai.htr.extractImage');
    Route::get('/htr/download/{jobId}/{fmt}', [AiController::class, 'htrDownload'])->name('admin.ai.htr.download');
    Route::get('/htr/batch', [AiController::class, 'htrBatch'])->name('admin.ai.htr.batch');
    Route::post('/htr/batch', [AiController::class, 'htrDoBatch'])->name('admin.ai.htr.doBatch');
    Route::get('/htr/sources', [AiController::class, 'htrSources'])->name('admin.ai.htr.sources');
    Route::post('/htr/save-fs-config', [AiController::class, 'htrSaveFsConfig'])->name('admin.ai.htr.saveFsConfig');
    Route::get('/htr/annotate', [AiController::class, 'htrAnnotate'])->name('admin.ai.htr.annotate');
    Route::post('/htr/annotate', [AiController::class, 'htrSaveAnnotation'])->name('admin.ai.htr.saveAnnotation');
    Route::get('/htr/folder-list', [AiController::class, 'htrFolderList'])->name('admin.ai.htr.folderList');
    Route::get('/htr/serve-image', [AiController::class, 'htrServeImage'])->name('admin.ai.htr.serveImage');
    Route::post('/htr/skip-image', [AiController::class, 'htrSkipImage'])->name('admin.ai.htr.skipImage');
    Route::post('/htr/split-rows', [AiController::class, 'htrSplitRows'])->name('admin.ai.htr.splitRows');
    Route::post('/htr/crop-ocr', [AiController::class, 'htrCropOcr'])->name('admin.ai.htr.cropOcr');
    Route::get('/htr/bulk-annotate', [AiController::class, 'htrBulkAnnotate'])->name('admin.ai.htr.bulkAnnotate');
    Route::post('/htr/bulk-annotate/load', [AiController::class, 'htrBulkAnnotateLoad'])->name('admin.ai.htr.bulkAnnotateLoad');
    Route::post('/htr/bulk-annotate/save', [AiController::class, 'htrBulkAnnotateSave'])->name('admin.ai.htr.bulkAnnotateSave');

    // FS Overlay Annotate — cloned from bulk-annotate for overlay positioning tests
    Route::get('/htr/fs-overlay', [AiController::class, 'htrFsOverlay'])->name('admin.ai.htr.fsOverlay');
    Route::post('/htr/fs-overlay/load', [AiController::class, 'htrBulkAnnotateLoad'])->name('admin.ai.htr.fsOverlayLoad');
    Route::post('/htr/fs-overlay/save', [AiController::class, 'htrBulkAnnotateSave'])->name('admin.ai.htr.fsOverlaySave');
    Route::post('/htr/fs-overlay/ocr-labels', [AiController::class, 'htrFsOverlayOcr'])->name('admin.ai.htr.fsOverlayOcr');
    Route::get('/htr/fs-overlay/serve-cropped', [AiController::class, 'htrServeCroppedImage'])->name('admin.ai.htr.serveCroppedImage');
    Route::post('/htr/fs-overlay/recognise', [AiController::class, 'htrFsOverlayRecognise'])->name('admin.ai.htr.fsOverlayRecognise');
    Route::post('/htr/fs-overlay/manual-crop', [AiController::class, 'htrFsOverlayManualCrop'])->name('admin.ai.htr.fsOverlayManualCrop');
    Route::post('/htr/fs-overlay/save-positions', [AiController::class, 'htrFsOverlaySavePositions'])->name('admin.ai.htr.fsOverlaySavePositions');
    Route::get('/htr/fs-overlay/load-positions', [AiController::class, 'htrFsOverlayLoadPositions'])->name('admin.ai.htr.fsOverlayLoadPositions');
    Route::post('/htr/spellcheck', [AiController::class, 'htrSpellcheck'])->name('admin.ai.htr.spellcheck');
    Route::post('/htr/add-word', [AiController::class, 'htrAddWord'])->name('admin.ai.htr.addWord');
    Route::post('/htr/add-town', [AiController::class, 'htrAddTown'])->name('admin.ai.htr.addTown');
    Route::get('/htr/training', [AiController::class, 'htrTraining'])->name('admin.ai.htr.training');
    Route::post('/htr/training/start', [AiController::class, 'htrStartTraining'])->name('admin.ai.htr.startTraining');
});
