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

// Media routes — read-only transcription/snippet views are public
Route::get('/media/transcription/{id}/vtt', [MediaController::class, 'transcriptionVtt'])->name('media.transcription.vtt')->where('id', '[0-9]+');
Route::get('/media/transcription/{id}/srt', [MediaController::class, 'transcriptionSrt'])->name('media.transcription.srt')->where('id', '[0-9]+');
Route::get('/media/transcription/{id}', [MediaController::class, 'transcriptionJson'])->name('media.transcription.json')->where('id', '[0-9]+');
Route::get('/media/snippets/{id}', [MediaController::class, 'snippetsList'])->name('media.snippets.list')->where('id', '[0-9]+');
// GET /media/snippets — list snippets by digital_object_id query param (legacy AtoM URL)
Route::get('/media/snippets', [MediaController::class, 'snippetsListByQuery'])->name('media.snippets.listByQuery');
Route::get('/media/export-snippet', [MediaController::class, 'exportSnippet'])->name('media.export-snippet');

// Media routes — mutating actions require auth + ACL
Route::middleware('auth')->group(function () {
    Route::delete('/media/transcription/{id}', [MediaController::class, 'transcriptionDelete'])->name('media.transcription.delete')->middleware('acl:delete')->where('id', '[0-9]+');
    Route::post('/media/extract/{id}', [MediaController::class, 'extract'])->name('media.extract')->middleware('acl:update')->where('id', '[0-9]+');
    Route::post('/media/transcribe/{id}', [MediaController::class, 'transcribe'])->name('media.transcribe')->middleware('acl:update')->where('id', '[0-9]+');
    Route::post('/media/snippets', [MediaController::class, 'snippetStore'])->name('media.snippets.store')->middleware('acl:create');
    Route::delete('/media/snippets/{id}', [MediaController::class, 'snippetDelete'])->name('media.snippets.delete')->middleware('acl:delete')->where('id', '[0-9]+');
});

Route::get('/informationobject/browse', [InformationObjectController::class, 'browse'])->name('informationobject.browse');
Route::get('/informationobject/autocomplete', [InformationObjectController::class, 'autocomplete'])->name('informationobject.autocomplete');
Route::get('/informationobject/{slug}/print', [InformationObjectController::class, 'print'])->name('informationobject.print')->middleware('odrl:reproduce');

// IO CRUD routes require auth + ACL
Route::middleware('auth')->group(function () {
    Route::post('/admin/fix-missing-slug', [InformationObjectController::class, 'fixMissingSlug'])->name('admin.fix-missing-slug');
    Route::get('/informationobject/add', [InformationObjectController::class, 'create'])->name('informationobject.create');
    Route::post('/informationobject/store', [InformationObjectController::class, 'store'])->name('informationobject.store')->middleware('acl:create');
    Route::match(['get', 'post'], '/informationobject/{slug}/reports', [InformationObjectController::class, 'reports'])->name('informationobject.reports');
    Route::get('/informationobject/{slug}/reports/{type}', [InformationObjectController::class, 'generateReport'])->name('informationobject.generateReport');
    Route::get('/informationobject/{slug}/rename', [InformationObjectController::class, 'rename'])->name('informationobject.rename');
    Route::put('/informationobject/{slug}/rename', [InformationObjectController::class, 'renameUpdate'])->name('informationobject.renameUpdate')->middleware('acl:update');
    Route::get('/informationobject/{slug}/move', [InformationObjectController::class, 'move'])->name('informationobject.move');
    Route::post('/informationobject/{slug}/move', [InformationObjectController::class, 'moveStore'])->name('informationobject.move.store')->middleware('acl:update');
    Route::get('/informationobject/{slug}/inventory', [InformationObjectController::class, 'inventory'])->name('informationobject.inventory');
    Route::get('/informationobject/{slug}/edit', [InformationObjectController::class, 'edit'])->name('informationobject.edit');
    Route::put('/informationobject/{slug}', [InformationObjectController::class, 'update'])->name('informationobject.update')->middleware('acl:update');
});

Route::middleware('admin')->group(function () {
    Route::get('/informationobject/{slug}/delete', [InformationObjectController::class, 'confirmDelete'])->name('informationobject.confirmDelete');
    Route::delete('/informationobject/{slug}', [InformationObjectController::class, 'destroy'])->name('informationobject.destroy')->middleware('acl:delete');
});

// Treeview API (public for viewing)
Route::get('/informationobject/treeview', [TreeviewController::class, 'treeview'])->name('io.treeview');
Route::get('/informationobject/treeview-data', [TreeviewController::class, 'treeviewData'])->name('io.treeviewData');
Route::post('/informationobject/treeview-sort', [TreeviewController::class, 'treeviewSort'])->middleware(['auth', 'acl:update'])->name('io.treeviewSort');

// Export
Route::get('/informationobject/{slug}/export/dc', [ExportController::class, 'dc'])->name('informationobject.export.dc');
Route::get('/informationobject/{slug}/export/ead', [ExportController::class, 'ead'])->name('informationobject.export.ead');
Route::get('/informationobject/{slug}/export/ead3', [ExportController::class, 'ead3'])->name('informationobject.export.ead3');
Route::get('/informationobject/{slug}/export/ead4', [ExportController::class, 'ead4'])->name('informationobject.export.ead4');
Route::get('/informationobject/{slug}/export/mods', [ExportController::class, 'mods'])->name('informationobject.export.mods');
Route::get('/informationobject/{slug}/export/csv', [ExportController::class, 'csv'])->name('informationobject.export.csv');
Route::get('/informationobject/{slug}/export/rico', [ExportController::class, 'ricJsonLd'])->name('informationobject.export.rico');

// Auth-required features
Route::middleware('auth')->group(function () {
    // Publication status update (GET = show form, POST = process)
    Route::get('/informationobject/{slug}/update-status', [InformationObjectController::class, 'showUpdateStatus'])->name('io.showUpdateStatus');
    Route::post('/informationobject/{slug}/update-status', [InformationObjectController::class, 'updateStatus'])->name('io.updateStatus')->middleware('acl:publish');

    // Calculate dates
    Route::post('/informationobject/{slug}/calculate-dates', [InformationObjectController::class, 'calculateDates'])->name('informationobject.calculateDates')->middleware('acl:update');

    // Display standard update (Administration area form)
    Route::post('/informationobject/{slug}/display-standard', [InformationObjectController::class, 'updateDisplayStandard'])->name('io.updateDisplayStandard')->middleware('acl:update');

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
    Route::post('/informationobject/import/process', [ImportController::class, 'process'])->name('informationobject.import.process')->middleware('acl:create');

    // Validate CSV (menu path: object/validateCsv)
    Route::get('/object/validateCsv', [ImportController::class, 'validateCsv'])->name('object.validateCsv');
    Route::post('/object/validateCsv', [ImportController::class, 'validateCsvProcess'])->name('object.validateCsv.process')->middleware('acl:create');

    // SKOS Import (menu path: sfSkosPlugin/import)
    Route::get('/sfSkosPlugin/import', [ImportController::class, 'skosImport'])->name('sfSkosPlugin.import');
    Route::post('/sfSkosPlugin/import', [ImportController::class, 'skosImportProcess'])->name('sfSkosPlugin.import.process')->middleware('acl:create');

    // Finding aid
    Route::get('/informationobject/{slug}/findingaid/generate', [FindingAidController::class, 'generate'])->name('informationobject.findingaid.generate');
    Route::get('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'uploadForm'])->name('informationobject.findingaid.upload.form');
    Route::post('/informationobject/{slug}/findingaid/upload', [FindingAidController::class, 'upload'])->name('informationobject.findingaid.upload')->middleware('acl:create');
    Route::get('/informationobject/{slug}/findingaid/download', [FindingAidController::class, 'download'])->name('informationobject.findingaid.download');
    Route::post('/informationobject/{slug}/findingaid/delete', [FindingAidController::class, 'delete'])->name('informationobject.findingaid.delete')->middleware('acl:delete');

    // Collections Management — Provenance (write operations require auth)
    Route::post('/provenance/{slug}/store', [ProvenanceController::class, 'store'])->name('io.provenance.store')->middleware('acl:create');
    Route::put('/provenance/{id}/update', [ProvenanceController::class, 'update'])->name('io.provenance.update')->middleware('acl:update')->where('id', '[0-9]+');
    Route::delete('/provenance/{id}/delete', [ProvenanceController::class, 'destroy'])->name('io.provenance.delete')->middleware('acl:delete')->where('id', '[0-9]+');

    // Collections Management — Condition
    Route::get('/condition/{slug}', [ConditionController::class, 'index'])->name('io.condition');
    Route::get('/condition/{slug}/create', [ConditionController::class, 'create'])->name('io.condition.create');
    Route::post('/condition/{slug}/store', [ConditionController::class, 'store'])->name('io.condition.store')->middleware('acl:create');
    Route::get('/condition/report/{id}', [ConditionController::class, 'show'])->name('io.condition.show')->where('id', '[0-9]+');
    Route::get('/condition/report/{id}/photo', fn (int $id) => redirect()->route('io.condition.show', $id))->where('id', '[0-9]+');
    Route::post('/condition/report/{id}/photo', [ConditionController::class, 'uploadPhoto'])->name('io.condition.photo.upload')->where('id', '[0-9]+');
    Route::delete('/condition/photo/{id}', [ConditionController::class, 'deletePhoto'])->name('io.condition.photo.delete')->where('id', '[0-9]+');
    Route::match(['get', 'post'], '/condition/api/annotation/{id}', [ConditionController::class, 'annotation'])->name('io.condition.annotation')->where('id', '[0-9]+');
    Route::get('/condition/check/{id}/photos', [ConditionController::class, 'spectrumShow'])->name('io.condition.spectrum.show')->where('id', '[0-9]+');
    Route::post('/condition/ai-assess', [ConditionController::class, 'aiAssess'])->name('io.condition.ai-assess');
    Route::post('/ai/describe/{id}', [InformationObjectController::class, 'aiDescribe'])->name('io.ai.describe')->where('id', '[0-9]+');
    Route::get('/spectrum/{slug}', [SpectrumController::class, 'index'])->name('io.spectrum');
    Route::get('/heritage/{slug}', [SpectrumController::class, 'heritage'])->name('io.heritage');

    // Digital Preservation (OAIS)
    Route::get('/preservation/{slug}', [PreservationController::class, 'index'])->name('io.preservation');
    Route::post('/preservation/{slug}', [PreservationController::class, 'createPackage'])->name('io.preservation.create');
    Route::post('/preservation/{slug}/{id}/update', [PreservationController::class, 'updatePackage'])->name('io.preservation.update')->where('id', '[0-9]+');
    Route::post('/preservation/{slug}/{id}/export', [PreservationController::class, 'exportPackage'])->name('io.preservation.export')->where('id', '[0-9]+');

    // AI Tools
    Route::get('/ai/ner/extract/{id}', [AiController::class, 'extract'])->name('io.ai.extract')->where('id', '[0-9]+');
    Route::get('/ai/ner/review', [AiController::class, 'review'])->name('io.ai.review');
    Route::get('/ai/summarize/{id}', [AiController::class, 'summarize'])->name('io.ai.summarize')->where('id', '[0-9]+');
    Route::get('/ai/translate/{id}', [AiController::class, 'translate'])->name('io.ai.translate')->where('id', '[0-9]+');

    // Privacy & PII
    Route::get('/privacy/scan/{id}', [PrivacyController::class, 'scan'])->name('io.privacy.scan')->where('id', '[0-9]+');
    Route::get('/privacy/redaction/{slug}', [PrivacyController::class, 'redaction'])->name('io.privacy.redaction');
    Route::post('/privacy/redaction/{slug}/save', [PrivacyController::class, 'saveRedactions'])->name('io.privacy.redaction.save');
    Route::get('/privacy/dashboard', [PrivacyController::class, 'dashboard'])->name('io.privacy.dashboard');
    Route::get('/privacy/dsar-request', fn () => redirect('/admin/privacy/dsar-request'));
    Route::get('/privacy/dsar-status', fn () => redirect('/admin/privacy/dsar-status'));
    Route::get('/privacy/complaint', fn () => redirect('/admin/privacy/dsar-add'));

    // Unified Rights Management (combined PREMIS + Extended + Embargo)
    Route::get('/rights/manage/{slug}', [ExtendedRightsController::class, 'manage'])->name('io.rights.manage');
    Route::post('/rights/manage/{slug}', [ExtendedRightsController::class, 'manageStore'])->name('io.rights.manage.store')->middleware('acl:create');

    // Extended Rights (legacy routes kept for backwards compatibility)
    Route::get('/rights/extended/{slug}', [ExtendedRightsController::class, 'add'])->name('io.rights.extended');
    Route::post('/rights/extended/{slug}/store', [ExtendedRightsController::class, 'store'])->name('io.rights.extended.store')->middleware('acl:create');
    Route::get('/rights/embargo/{slug}', [ExtendedRightsController::class, 'embargo'])->name('io.rights.embargo');
    Route::post('/rights/embargo/{slug}/store', [ExtendedRightsController::class, 'storeEmbargo'])->name('io.rights.embargo.store')->middleware('acl:create');
    Route::post('/rights/embargo/{id}/lift', [ExtendedRightsController::class, 'liftEmbargo'])->name('io.rights.embargo.lift')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/rights/export/{slug}', [ExtendedRightsController::class, 'exportJsonLd'])->name('io.rights.export');

    // Dedicated "Add / Link digital object" page (clone of AtoM object/addDigitalObject)
    Route::get('/{slug}/object/addDigitalObject', [DigitalObjectController::class, 'addDigitalObject'])
        ->name('io.digitalobject.add')
        ->middleware('acl:create')
        ->where('slug', '[a-z0-9][a-z0-9-]*');

    // Digital Object upload/delete
    Route::post('/informationobject/{slug}/upload', [DigitalObjectController::class, 'upload'])->name('io.digitalobject.upload')->middleware('acl:create');
    Route::match(['get', 'post'], '/informationobject/{slug}/multiFileUpload', [DigitalObjectController::class, 'multiFileUpload'])->name('io.multiFileUpload');
    Route::delete('/digitalobject/{id}', [DigitalObjectController::class, 'delete'])->name('io.digitalobject.delete')->middleware('acl:delete')->where('id', '[0-9]+');
    Route::put('/digitalobject/{id}', [DigitalObjectController::class, 'update'])->name('io.digitalobject.update')->middleware('acl:update')->where('id', '[0-9]+');
    Route::get('/digitalobject/{id}', [DigitalObjectController::class, 'show'])->name('io.digitalobject.show')->where('id', '[0-9]+');

    // Research Tools
    Route::get('/research/citation/{slug}', [ResearchController::class, 'citation'])->name('io.research.citation');
    Route::match(['get', 'post'], '/research/assessment/{slug}', [ResearchController::class, 'sourceAssessment'])->name('io.research.assessment');
    Route::match(['get', 'post'], '/research/annotations/{slug}', [ResearchController::class, 'annotations'])->name('io.research.annotations');
    Route::get('/research/trust/{slug}', [ResearchController::class, 'trustScore'])->name('io.research.trust');
    Route::get('/research/tools', [ResearchController::class, 'dashboard'])->name('io.research.dashboard');

    // Legacy AtoM autocomplete/identifier aliases
    Route::get('/informationobject/actorAutocomplete', [InformationObjectController::class, 'autocomplete'])->name('io.actorAutocomplete');
    Route::get('/informationobject/repositoryAutocomplete', [InformationObjectController::class, 'autocomplete'])->name('io.repositoryAutocomplete');
    Route::get('/informationobject/termAutocomplete', [InformationObjectController::class, 'autocomplete'])->name('io.termAutocomplete');
    Route::get('/informationobject/generateIdentifierJson', [InformationObjectController::class, 'generateIdentifier'])->name('io.generateIdentifier');

    // Legacy digital object URL aliases
    Route::post('/digitalobject/upload', [DigitalObjectController::class, 'upload'])->middleware('acl:create');
    Route::get('/digitalobject/{id}/edit', [DigitalObjectController::class, 'show'])->whereNumber('id');
    Route::delete('/digitalobject/{id}/delete', [DigitalObjectController::class, 'delete'])->whereNumber('id')->middleware('acl:delete');
});

// Provenance read routes (public — matches AtoM CCO route pattern)
Route::get('/{slug}/cco/provenance', [ProvenanceController::class, 'index'])->name('io.provenance');
Route::get('/provenance/{slug}/timeline', [ProvenanceController::class, 'timeline'])->name('io.provenance.timeline');
Route::get('/provenance/{slug}/export-csv', [ProvenanceController::class, 'exportCsv'])->name('io.provenance.exportCsv');

Route::get('/{slug}', [InformationObjectController::class, 'show'])->name('informationobject.show')->middleware('odrl:use')->where('slug', '^(?!search$|login$|logout$|register$|admin$|api$|storage$|up$|about$|privacy$|terms$|pages$|contact$|provenance$|condition$|spectrum$|heritage$|preservation$|ai$|rights$|research$|researcher$|oai$|accession$|aclGroup$|actor$|ahgSettings$|cart$|clipboard$|css$|digitalobject$|display$|donor$|favorites$|feedback$|ftpUpload$|function$|glam$|help$|informationobject$|ingest$|integrity$|jobs$|loan$|media$|object$|physicalobject$|portableExport$|portable-export$|reports$|repository$|registry$|requesttopublish$|rightsholder$|settings$|sfPluginAdminPlugin$|sfSkosPlugin$|staticpage$|taxonomy$|term$|user$|workflow$|security$|manifest-collections$|manifest-collection$|iiif-manifest$|dam$|museum$|gallery$|library$|ric$|vendor$|ipsas$|nmmz$|naz$|cdpa$|icip$|tenant$|forms$|exhibition$|statistics$|metadata-export$|semantic-search$|data-migration$|dacs-manage$|dc-manage$|mods-manage$|rad-manage$|ric-capture$|scan$|version$|health$)[a-z0-9][a-z0-9-]*$');

