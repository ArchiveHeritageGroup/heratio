<?php

use AhgInformationObjectManage\Controllers\InformationObjectController;
use AhgInformationObjectManage\Controllers\ExportController;
use AhgInformationObjectManage\Controllers\ImportController;
use AhgInformationObjectManage\Controllers\FindingAidController;
use AhgInformationObjectManage\Controllers\ProvenanceController;
use AhgInformationObjectManage\Controllers\ConditionController;
use AhgInformationObjectManage\Controllers\SpectrumController;
use AhgInformationObjectManage\Controllers\PreservationController;
use AhgInformationObjectManage\Controllers\AiController;
use AhgInformationObjectManage\Controllers\PrivacyController;
use AhgInformationObjectManage\Controllers\ExtendedRightsController;
use AhgInformationObjectManage\Controllers\DigitalObjectController;
use AhgInformationObjectManage\Controllers\ResearchController;
use AhgInformationObjectManage\Controllers\TreeviewController;
use AhgInformationObjectManage\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

// Media routes (transcription, metadata extraction, snippets)
Route::get('/media/transcription/{id}/vtt', [MediaController::class, 'transcriptionVtt'])->name('media.transcription.vtt')->where('id', '[0-9]+');
Route::get('/media/transcription/{id}/srt', [MediaController::class, 'transcriptionSrt'])->name('media.transcription.srt')->where('id', '[0-9]+');
Route::post('/media/extract/{id}', [MediaController::class, 'extract'])->name('media.extract')->where('id', '[0-9]+');
Route::post('/media/transcribe/{id}', [MediaController::class, 'transcribe'])->name('media.transcribe')->where('id', '[0-9]+');
Route::post('/media/snippets', [MediaController::class, 'snippetStore'])->name('media.snippets.store');

Route::get('/informationobject/browse', [InformationObjectController::class, 'browse'])->name('informationobject.browse');
Route::get('/informationobject/{slug}/print', [InformationObjectController::class, 'print'])->name('informationobject.print');
Route::get('/informationobject/add', [InformationObjectController::class, 'create'])->name('informationobject.create');
Route::post('/informationobject/store', [InformationObjectController::class, 'store'])->name('informationobject.store');
Route::get('/informationobject/{slug}/edit', [InformationObjectController::class, 'edit'])->name('informationobject.edit');
Route::put('/informationobject/{slug}', [InformationObjectController::class, 'update'])->name('informationobject.update');
Route::get('/informationobject/{slug}/delete', [InformationObjectController::class, 'confirmDelete'])->name('informationobject.confirmDelete');
Route::delete('/informationobject/{slug}', [InformationObjectController::class, 'destroy'])->name('informationobject.destroy');

// Treeview API (public for viewing)
Route::get('/informationobject/treeview', [TreeviewController::class, 'treeview'])->name('io.treeview');
Route::get('/informationobject/treeview-data', [TreeviewController::class, 'treeviewData'])->name('io.treeviewData');
Route::post('/informationobject/treeview-sort', [TreeviewController::class, 'treeviewSort'])->middleware('auth')->name('io.treeviewSort');

// Export
Route::get('/informationobject/{slug}/export/dc', [ExportController::class, 'dc'])->name('informationobject.export.dc');
Route::get('/informationobject/{slug}/export/ead', [ExportController::class, 'ead'])->name('informationobject.export.ead');

// Auth-required features
Route::middleware('auth')->group(function () {
    // Import
    Route::get('/informationobject/import/xml/{slug?}', [ImportController::class, 'xml'])->name('informationobject.import.xml');
    Route::get('/informationobject/import/csv/{slug?}', [ImportController::class, 'csv'])->name('informationobject.import.csv');

    // AtoM menu path alias: object/importSelect?type=xml|csv
    Route::get('/object/importSelect', function (\Illuminate\Http\Request $request) {
        $type = $request->get('type', 'xml');
        return redirect($type === 'csv'
            ? route('informationobject.import.csv')
            : route('informationobject.import.xml'));
    });
    Route::post('/informationobject/import/process', [ImportController::class, 'process'])->name('informationobject.import.process');

    // Validate CSV (menu path: object/validateCsv)
    Route::get('/object/validateCsv', [ImportController::class, 'validateCsv'])->name('object.validateCsv');
    Route::post('/object/validateCsv', [ImportController::class, 'validateCsvProcess'])->name('object.validateCsv.process');

    // SKOS Import (menu path: sfSkosPlugin/import)
    Route::get('/sfSkosPlugin/import', [ImportController::class, 'skosImport'])->name('sfSkosPlugin.import');
    Route::post('/sfSkosPlugin/import', [ImportController::class, 'skosImportProcess'])->name('sfSkosPlugin.import.process');

    // Finding aid
    Route::get('/informationobject/{slug}/findingaid/generate', [FindingAidController::class, 'generate'])->name('informationobject.findingaid.generate');
    Route::get('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'uploadForm'])->name('informationobject.findingaid.upload.form');
    Route::post('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'upload'])->name('informationobject.findingaid.upload');
    Route::get('/informationobject/{slug}/findingaid/download', [FindingAidController::class, 'download'])->name('informationobject.findingaid.download');

    // Collections Management — Provenance
    Route::get('/provenance/{slug}', [ProvenanceController::class, 'index'])->name('io.provenance');
    Route::get('/provenance/{slug}/timeline', [ProvenanceController::class, 'timeline'])->name('io.provenance.timeline');
    Route::post('/provenance/{slug}/store', [ProvenanceController::class, 'store'])->name('io.provenance.store');
    Route::put('/provenance/{id}/update', [ProvenanceController::class, 'update'])->name('io.provenance.update')->where('id', '[0-9]+');
    Route::delete('/provenance/{id}/delete', [ProvenanceController::class, 'destroy'])->name('io.provenance.delete')->where('id', '[0-9]+');

    // Collections Management — Condition
    Route::get('/condition/{slug}', [ConditionController::class, 'index'])->name('io.condition');
    Route::get('/condition/{slug}/create', [ConditionController::class, 'create'])->name('io.condition.create');
    Route::post('/condition/{slug}/store', [ConditionController::class, 'store'])->name('io.condition.store');
    Route::get('/condition/report/{id}', [ConditionController::class, 'show'])->name('io.condition.show')->where('id', '[0-9]+');
    Route::get('/spectrum/{slug}', [SpectrumController::class, 'index'])->name('io.spectrum');
    Route::get('/heritage/{slug}', [SpectrumController::class, 'heritage'])->name('io.heritage');

    // Digital Preservation (OAIS)
    Route::get('/preservation/{slug}', [PreservationController::class, 'index'])->name('io.preservation');

    // AI Tools
    Route::get('/ai/ner/extract/{id}', [AiController::class, 'extract'])->name('io.ai.extract')->where('id', '[0-9]+');
    Route::get('/ai/ner/review', [AiController::class, 'review'])->name('io.ai.review');
    Route::get('/ai/summarize/{id}', [AiController::class, 'summarize'])->name('io.ai.summarize')->where('id', '[0-9]+');
    Route::get('/ai/translate/{id}', [AiController::class, 'translate'])->name('io.ai.translate')->where('id', '[0-9]+');

    // Privacy & PII
    Route::get('/privacy/scan/{id}', [PrivacyController::class, 'scan'])->name('io.privacy.scan')->where('id', '[0-9]+');
    Route::get('/privacy/redaction/{slug}', [PrivacyController::class, 'redaction'])->name('io.privacy.redaction');
    Route::get('/privacy/dashboard', [PrivacyController::class, 'dashboard'])->name('io.privacy.dashboard');

    // Extended Rights
    Route::get('/rights/extended/{slug}', [ExtendedRightsController::class, 'add'])->name('io.rights.extended');
    Route::get('/rights/embargo/{slug}', [ExtendedRightsController::class, 'embargo'])->name('io.rights.embargo');
    Route::post('/rights/embargo/{slug}/store', [ExtendedRightsController::class, 'storeEmbargo'])->name('io.rights.embargo.store');
    Route::post('/rights/embargo/{id}/lift', [ExtendedRightsController::class, 'liftEmbargo'])->name('io.rights.embargo.lift')->where('id', '[0-9]+');
    Route::get('/rights/export/{slug}', [ExtendedRightsController::class, 'exportJsonLd'])->name('io.rights.export');

    // Digital Object upload/delete
    Route::post('/informationobject/{slug}/upload', [DigitalObjectController::class, 'upload'])->name('io.digitalobject.upload');
    Route::delete('/digitalobject/{id}', [DigitalObjectController::class, 'delete'])->name('io.digitalobject.delete')->where('id', '[0-9]+');
    Route::get('/digitalobject/{id}', [DigitalObjectController::class, 'show'])->name('io.digitalobject.show')->where('id', '[0-9]+');

    // Research Tools
    Route::get('/research/citation/{slug}', [ResearchController::class, 'citation'])->name('io.research.citation');
    Route::get('/research/assessment/{slug}', [ResearchController::class, 'sourceAssessment'])->name('io.research.assessment');
    Route::get('/research/annotations/{slug}', [ResearchController::class, 'annotations'])->name('io.research.annotations');
    Route::get('/research/trust/{slug}', [ResearchController::class, 'trustScore'])->name('io.research.trust');
    Route::get('/research/tools', [ResearchController::class, 'dashboard'])->name('io.research.dashboard');
});

Route::get('/{slug}', [InformationObjectController::class, 'show'])->name('informationobject.show')->where('slug', '^(?!search|login|logout|register|admin|api|storage|up|about|privacy|terms|pages|contact|provenance|condition|spectrum|heritage|preservation|ai|rights|research|researcher|oai|accession|aclGroup|actor|ahgSettings|cart|clipboard|css|digitalobject|display|donor|favorites|feedback|ftpUpload|function|glam|help|informationobject|integrity|jobs|loan|media|object|physicalobject|portableExport|reports|repository|requesttopublish|rightsholder|settings|sfPluginAdminPlugin|sfSkosPlugin|staticpage|taxonomy|term|user|workflow)[a-z0-9-]+$');
