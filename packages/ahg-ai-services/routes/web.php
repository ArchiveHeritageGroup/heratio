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

    // ─── NER Entity Management ─────────────────────────────────────
    Route::get('/ner/extract/{id}', [AiController::class, 'nerExtract'])->name('admin.ai.ner.extract')->whereNumber('id');
    Route::get('/ner/entities/{id}', [AiController::class, 'nerGetEntities'])->name('admin.ai.ner.entities')->whereNumber('id');
    Route::post('/ner/entity/update', [AiController::class, 'nerUpdateEntity'])->name('admin.ai.ner.entity.update');
    Route::post('/ner/create/actor', [AiController::class, 'nerCreateActor'])->name('admin.ai.ner.create.actor');
    Route::post('/ner/create/place', [AiController::class, 'nerCreatePlace'])->name('admin.ai.ner.create.place');
    Route::post('/ner/create/subject', [AiController::class, 'nerCreateSubject'])->name('admin.ai.ner.create.subject');
    Route::get('/ner/health', [AiController::class, 'nerHealth'])->name('admin.ai.ner.health');
    Route::post('/ner/bulk-save', [AiController::class, 'nerBulkSave'])->name('admin.ai.ner.bulk-save');
    Route::get('/ner/pdf-overlay/{id}', [AiController::class, 'nerPdfOverlay'])->name('admin.ai.ner.pdf-overlay')->whereNumber('id');
    Route::get('/ner/approved-entities/{id}', [AiController::class, 'nerGetApprovedEntities'])->name('admin.ai.ner.approved-entities')->whereNumber('id');

    // ─── HTR (single object) ───────────────────────────────────────
    Route::get('/htr/{id}', [AiController::class, 'htrForObject'])->name('admin.ai.htr.object')->whereNumber('id');

    // ─── Summarize (single object) ─────────────────────────────────
    Route::get('/summarize/{id}', [AiController::class, 'summarizeObject'])->name('admin.ai.summarize.object')->whereNumber('id');

    // ─── LLM Description Suggestion ────────────────────────────────
    Route::get('/suggest/{id}', [AiController::class, 'suggest'])->name('admin.ai.suggest')->whereNumber('id');
    Route::get('/suggest/{id}/preview', [AiController::class, 'suggestPreview'])->name('admin.ai.suggest.preview')->whereNumber('id');
    Route::get('/suggest/{id}/view', [AiController::class, 'suggestView'])->name('admin.ai.suggest.view')->whereNumber('id');
    Route::post('/suggest/{id}/decision', [AiController::class, 'suggestDecision'])->name('admin.ai.suggest.decision')->whereNumber('id');
    Route::get('/suggest/object/{id}', [AiController::class, 'suggestObject'])->name('admin.ai.suggest.object')->whereNumber('id');

    // ─── LLM Configurations & Health ───────────────────────────────
    Route::get('/llm/configs', [AiController::class, 'llmConfigs'])->name('admin.ai.llm.configs');
    Route::get('/llm/health', [AiController::class, 'llmHealth'])->name('admin.ai.llm.health');

    // ─── Prompt Templates ──────────────────────────────────────────
    Route::get('/templates', [AiController::class, 'templates'])->name('admin.ai.templates');

    // ─── Batch Queue Management ────────────────────────────────────
    Route::match(['get', 'post'], '/batch/create', [AiController::class, 'batchCreate'])->name('admin.ai.batch.create');
    Route::get('/batch/{id}/progress', [AiController::class, 'batchProgress'])->name('admin.ai.batch.progress')->whereNumber('id');
    Route::post('/batch/{id}/action', [AiController::class, 'batchAction'])->name('admin.ai.batch.action')->whereNumber('id');
    Route::post('/batch/{id}/process', [AiController::class, 'batchProcess'])->name('admin.ai.batch.process')->whereNumber('id');

    // ─── Individual Job View ───────────────────────────────────────
    Route::get('/job/{id}', [AiController::class, 'jobView'])->name('admin.ai.job.view')->whereNumber('id');

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
    Route::get('/htr/training/status', [AiController::class, 'htrTrainingStatus'])->name('admin.ai.htr.trainingStatus');

    // --- Donut (Document Understanding) ---
    Route::get('/donut', [AiController::class, 'donutDashboard'])->name('admin.ai.donut.dashboard');
    Route::get('/donut/extract', [AiController::class, 'donutExtract'])->name('admin.ai.donut.extract');
    Route::post('/donut/extract', [AiController::class, 'donutDoExtract'])->name('admin.ai.donut.doExtract');
    Route::get('/donut/batch', [AiController::class, 'donutBatch'])->name('admin.ai.donut.batch');
    Route::post('/donut/batch', [AiController::class, 'donutDoBatch'])->name('admin.ai.donut.doBatch');
    Route::get('/donut/download/{jobId}', [AiController::class, 'donutDownload'])->name('admin.ai.donut.download');
    Route::get('/donut/training/status', [AiController::class, 'donutTrainingStatus'])->name('admin.ai.donut.trainingStatus');
    Route::post('/donut/training/start', [AiController::class, 'donutStartTraining'])->name('admin.ai.donut.startTraining');
});

/*
|--------------------------------------------------------------------------
| Legacy NER routes (backward compatibility with AtoM URLs)
|--------------------------------------------------------------------------
| These redirect /ai/... and /ner/... paths to the admin/ai/... equivalents.
*/
Route::middleware(['auth'])->group(function () {
    // /ai/ner/* → /admin/ai/ner/*
    Route::get('/ai/ner/extract/{id}', fn ($id) => redirect()->route('admin.ai.ner.extract', $id))->whereNumber('id');
    Route::get('/ai/ner/entities/{id}', fn ($id) => redirect()->route('admin.ai.ner.entities', $id))->whereNumber('id');
    Route::get('/ai/ner/health', fn () => redirect()->route('admin.ai.ner.health'));
    Route::get('/ai/ner/pdf-overlay/{id}', fn ($id) => redirect()->route('admin.ai.ner.pdf-overlay', $id))->whereNumber('id');
    Route::get('/ai/ner/approved-entities/{id}', fn ($id) => redirect()->route('admin.ai.ner.approved-entities', $id))->whereNumber('id');

    // /ai/htr/:id → /admin/ai/htr/:id
    Route::get('/ai/htr/{id}', fn ($id) => redirect()->route('admin.ai.htr.object', $id))->whereNumber('id');

    // /ai/summarize/:id → /admin/ai/summarize/:id
    Route::get('/ai/summarize/{id}', fn ($id) => redirect()->route('admin.ai.summarize.object', $id))->whereNumber('id');

    // /ai/suggest/* → /admin/ai/suggest/*
    Route::get('/ai/suggest/{id}', fn ($id) => redirect()->route('admin.ai.suggest', $id))->whereNumber('id');
    Route::get('/ai/suggest/{id}/preview', fn ($id) => redirect()->route('admin.ai.suggest.preview', $id))->whereNumber('id');
    Route::get('/ai/suggest/{id}/view', fn ($id) => redirect()->route('admin.ai.suggest.view', $id))->whereNumber('id');
    Route::get('/ai/suggest/object/{id}', fn ($id) => redirect()->route('admin.ai.suggest.object', $id))->whereNumber('id');
    Route::get('/ai/suggest/review', fn () => redirect()->route('admin.ai.suggest-review'));

    // /ai/llm/* → /admin/ai/llm/*
    Route::get('/ai/llm/configs', fn () => redirect()->route('admin.ai.llm.configs'));
    Route::get('/ai/llm/health', fn () => redirect()->route('admin.ai.llm.health'));

    // /ai/templates → /admin/ai/templates
    Route::get('/ai/templates', fn () => redirect()->route('admin.ai.templates'));

    // /ai/batch/* → /admin/ai/batch/*
    Route::get('/ai/batch', fn () => redirect()->route('admin.ai.batch'));
    Route::get('/ai/batch/{id}', fn ($id) => redirect()->route('admin.ai.batch.view', $id))->whereNumber('id');
    Route::get('/ai/job/{id}', fn ($id) => redirect()->route('admin.ai.job.view', $id))->whereNumber('id');

    // /ner/* legacy aliases → /admin/ai/ner/*
    Route::get('/ner/extract/{id}', fn ($id) => redirect()->route('admin.ai.ner.extract', $id))->whereNumber('id');
    Route::get('/ner/review', fn () => redirect()->route('admin.ai.review'));
    Route::get('/ner/entities/{id}', fn ($id) => redirect()->route('admin.ai.ner.entities', $id))->whereNumber('id');
    Route::get('/ner/summarize/{id}', fn ($id) => redirect()->route('admin.ai.summarize.object', $id))->whereNumber('id');
    Route::get('/ner/htr/{id}', fn ($id) => redirect()->route('admin.ai.htr.object', $id))->whereNumber('id');

    // POST aliases for AtoM-compatible URLs
    Route::post('/ai/ner/entity/update', [AiController::class, 'nerUpdateEntity']);
    Route::post('/ai/ner/create/actor', [AiController::class, 'nerCreateActor']);
    Route::post('/ai/ner/create/place', [AiController::class, 'nerCreatePlace']);
    Route::post('/ai/ner/create/subject', [AiController::class, 'nerCreateSubject']);
    Route::post('/ai/ner/bulk-save', [AiController::class, 'nerBulkSave']);
    Route::post('/ner/bulk-save', [AiController::class, 'nerBulkSave']);
    Route::post('/ai/suggest/{id}/decision', [AiController::class, 'suggestDecision'])->whereNumber('id');
    Route::match(['get', 'post'], '/ai/batch/create', [AiController::class, 'batchCreate']);
    Route::get('/ai/batch/{id}/progress', [AiController::class, 'batchProgress'])->whereNumber('id');
    Route::post('/ai/batch/{id}/action', [AiController::class, 'batchAction'])->whereNumber('id');
    Route::post('/ai/batch/{id}/process', [AiController::class, 'batchProcess'])->whereNumber('id');
});
