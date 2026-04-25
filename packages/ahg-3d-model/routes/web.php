<?php

use Ahg3dModel\Controllers\Model3dController;
use Illuminate\Support\Facades\Route;

// User-facing — auth-only Generate 3D button on IO show pages.
// Two-step flow: generate → preview modal → save / discard.
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/3d-models/generate/{ioId}', [Model3dController::class, 'userGenerate3d'])
        ->where('ioId', '[0-9]+')
        ->name('admin.3d-models.user-generate');
    Route::post('/3d-models/preview/{ioId}/save', [Model3dController::class, 'confirmAttach3d'])
        ->where('ioId', '[0-9]+')
        ->name('admin.3d-models.preview-save');
    Route::post('/3d-models/preview/{ioId}/discard', [Model3dController::class, 'discard3d'])
        ->where('ioId', '[0-9]+')
        ->name('admin.3d-models.preview-discard');
    Route::get('/3d-models/preview-file', [Model3dController::class, 'previewFile'])
        ->name('admin.3d-models.preview-file');
});

Route::middleware('admin')->group(function () {
    // Browse (derivative management)
    Route::get('/admin/3d-models', [Model3dController::class, 'browse'])
        ->name('admin.3d-models.browse');

    Route::get('/admin/3d-models/{id}/thumbnail', [Model3dController::class, 'generateThumbnail'])
        ->where('id', '[0-9]+')
        ->name('admin.3d-models.thumbnail');

    Route::get('/admin/3d-models/{id}/multiangle', [Model3dController::class, 'generateMultiAngle'])
        ->where('id', '[0-9]+')
        ->name('admin.3d-models.multiangle');

    Route::post('/admin/3d-models/batch-thumbnails', [Model3dController::class, 'batchThumbnails'])
        ->name('admin.3d-models.batch-thumbnails');

    // Index (object_3d_model list)
    Route::get('/admin/3d-models/index', [Model3dController::class, 'index'])
        ->name('admin.3d-models.index');

    // View
    Route::get('/admin/3d-models/{id}/view', [Model3dController::class, 'view'])
        ->whereNumber('id')
        ->name('admin.3d-models.view');

    // Edit
    Route::match(['get', 'post'], '/admin/3d-models/{id}/edit', [Model3dController::class, 'edit'])
        ->whereNumber('id')
        ->name('admin.3d-models.edit');

    // Embed
    Route::get('/admin/3d-models/{id}/embed', [Model3dController::class, 'embed'])
        ->whereNumber('id')
        ->name('admin.3d-models.embed');

    // Upload
    Route::match(['get', 'post'], '/admin/3d-models/upload/{objectId}', [Model3dController::class, 'upload'])
        ->whereNumber('objectId')
        ->name('admin.3d-models.upload');

    // Delete
    Route::post('/admin/3d-models/{id}/delete', [Model3dController::class, 'delete'])
        ->whereNumber('id')
        ->name('admin.3d-models.delete');

    // Settings
    Route::match(['get', 'post'], '/admin/3d-models/settings', [Model3dController::class, 'settings'])
        ->name('admin.3d-models.settings');

    // TripoSR
    Route::match(['get', 'post'], '/admin/3d-models/triposr', [Model3dController::class, 'triposr'])
        ->name('admin.3d-models.triposr');

    // Hotspot AJAX
    Route::post('/admin/3d-models/{modelId}/hotspot', [Model3dController::class, 'addHotspot'])
        ->whereNumber('modelId')
        ->name('admin.3d-models.add-hotspot');

    Route::post('/admin/3d-models/hotspot/{hotspotId}/delete', [Model3dController::class, 'deleteHotspot'])
        ->whereNumber('hotspotId')
        ->name('admin.3d-models.delete-hotspot');

    // API
    Route::get('/admin/3d-models/api/models/{objectId}', [Model3dController::class, 'apiModels'])
        ->whereNumber('objectId')
        ->name('admin.3d-models.api.models');

    Route::get('/admin/3d-models/api/hotspots/{modelId}', [Model3dController::class, 'apiHotspots'])
        ->whereNumber('modelId')
        ->name('admin.3d-models.api.hotspots');
});

// IIIF 3D manifest (public)
Route::get('/iiif/3d/{id}/manifest.json', [Model3dController::class, 'iiifManifest'])
    ->whereNumber('id')
    ->name('iiif.3d.manifest');

// Public API aliases (used by JS viewers on public pages)
Route::get('/api/3d/models/{objectId}', [Model3dController::class, 'apiModels'])
    ->whereNumber('objectId')
    ->name('api.3d.models');

Route::get('/api/3d/hotspots/{modelId}', [Model3dController::class, 'apiHotspots'])
    ->whereNumber('modelId')
    ->name('api.3d.hotspots');

// Legacy AtoM URL aliases (JS widgets reference these paths)
Route::middleware('auth')->group(function () {
    Route::post('/index.php/ar3DModel/addHotspot/{modelId}', [Model3dController::class, 'addHotspot'])
        ->whereNumber('modelId')
        ->name('legacy.3d.addHotspot');

    Route::post('/index.php/ar3DModel/deleteHotspot/{hotspotId}', [Model3dController::class, 'deleteHotspot'])
        ->whereNumber('hotspotId')
        ->name('legacy.3d.deleteHotspot');

    // Also handle without index.php prefix
    Route::post('/ar3DModel/addHotspot/{modelId}', [Model3dController::class, 'addHotspot'])
        ->whereNumber('modelId')
        ->name('ar3d.addHotspot');

    Route::post('/ar3DModel/deleteHotspot/{hotspotId}', [Model3dController::class, 'deleteHotspot'])
        ->whereNumber('hotspotId')
        ->name('ar3d.deleteHotspot');

    // Legacy base-path aliases (no ID) — return JSON error for parity coverage
    Route::match(['get', 'post'], '/index.php/ar3DModel/addHotspot', function () {
        return response()->json(['success' => false, 'error' => 'Model ID required. Use /ar3DModel/addHotspot/{modelId}'], 400);
    })->name('legacy.3d.addHotspot.base');

    Route::match(['get', 'post'], '/index.php/ar3DModel/deleteHotspot', function () {
        return response()->json(['success' => false, 'error' => 'Hotspot ID required. Use /ar3DModel/deleteHotspot/{hotspotId}'], 400);
    })->name('legacy.3d.deleteHotspot.base');
});
