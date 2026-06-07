<?php

use AhgExhibition\Controllers\ExhibitionController;
use AhgExhibition\Controllers\ExhibitionSpaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('exhibition')->group(function () {
    Route::get('/', [ExhibitionController::class, 'index'])->name('exhibition.index');
    Route::get('/dashboard', [ExhibitionController::class, 'dashboard'])->name('exhibition.dashboard');
});

// Dashboard URL alias under /museum/exhibitions (matches reports dashboard link)
Route::middleware('auth')->group(function () {
    Route::get('/museum/exhibitions', [ExhibitionController::class, 'index'])->name('museum.exhibitions');
});

Route::middleware('auth')->prefix('exhibition')->group(function () {
    Route::match(['get', 'post'], '/add', [ExhibitionController::class, 'add'])->name('exhibition.add'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/{id}/edit', [ExhibitionController::class, 'edit'])->name('exhibition.edit'); // ACL must be checked in controller (Route::match)
    Route::get('/{id}/objects', [ExhibitionController::class, 'objects'])->name('exhibition.objects');
    Route::get('/{id}/object-list', [ExhibitionController::class, 'objectList'])->name('exhibition.objectList');
    Route::get('/{id}/object-list/csv', [ExhibitionController::class, 'objectListCsv'])->name('exhibition.objectListCsv');
    Route::get('/{id}/storylines', [ExhibitionController::class, 'storylines'])->name('exhibition.storylines');
    Route::get('/{exhibitionId}/storyline/{storylineId}', [ExhibitionController::class, 'storyline'])->name('exhibition.storyline');
    Route::get('/{id}/sections', [ExhibitionController::class, 'sections'])->name('exhibition.sections');
    Route::get('/{id}/events', [ExhibitionController::class, 'events'])->name('exhibition.events');
    Route::get('/{id}/checklists', [ExhibitionController::class, 'checklists'])->name('exhibition.checklists');
    Route::get('/{id}', [ExhibitionController::class, 'show'])->name('exhibition.show');
});

// heratio#146 — exhibition space (front-of-house space allocation, sibling of strongroom)
Route::get('/exhibition-space/browse', [ExhibitionSpaceController::class, 'browse'])->name('exhibition-space.browse');

Route::middleware('auth')->group(function () {
    Route::get('/exhibition-space/add', [ExhibitionSpaceController::class, 'create'])->name('exhibition-space.create');
    Route::post('/exhibition-space/add', [ExhibitionSpaceController::class, 'store'])->name('exhibition-space.store')->middleware('acl:create');
    Route::get('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'edit'])->name('exhibition-space.edit');
    Route::post('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'update'])->name('exhibition-space.update')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/place', [ExhibitionSpaceController::class, 'placePlacement'])->name('exhibition-space.place')->middleware('acl:update');
    Route::post('/exhibition-space/placement/{placementId}/remove', [ExhibitionSpaceController::class, 'removePlacement'])->name('exhibition-space.placement.remove')->middleware('acl:update')->whereNumber('placementId');

    // heratio#1138 — digital twin: virtual collection builder (Phase 1)
    Route::get('/exhibition-space/{slug}/builder', [ExhibitionSpaceController::class, 'builder'])->name('exhibition-space.builder');
    // heratio#1143 — building plan editor
    Route::get('/exhibition-space/{slug}/plan', [ExhibitionSpaceController::class, 'plan'])->name('exhibition-space.plan');
    Route::post('/exhibition-space/{slug}/plan/save', [ExhibitionSpaceController::class, 'savePlanAjax'])->name('exhibition-space.plan.save')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/doors', [ExhibitionSpaceController::class, 'saveDoorsAjax'])->name('exhibition-space.plan.doors')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/shape', [ExhibitionSpaceController::class, 'saveShapeAjax'])->name('exhibition-space.plan.shape')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/windows', [ExhibitionSpaceController::class, 'saveWindowsAjax'])->name('exhibition-space.plan.windows')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/add-room', [ExhibitionSpaceController::class, 'addRoomAjax'])->name('exhibition-space.plan.add-room')->middleware('acl:create');
    Route::post('/exhibition-space/{slug}/plan/group', [ExhibitionSpaceController::class, 'savePlanGroupAjax'])->name('exhibition-space.plan.group')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/stairs', [ExhibitionSpaceController::class, 'savePlanStairsAjax'])->name('exhibition-space.plan.stairs')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/room-floor', [ExhibitionSpaceController::class, 'savePlanRoomFloorAjax'])->name('exhibition-space.plan.room-floor')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/room-lock', [ExhibitionSpaceController::class, 'savePlanRoomLockAjax'])->name('exhibition-space.plan.room-lock')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/delete-room', [ExhibitionSpaceController::class, 'deleteRoomAjax'])->name('exhibition-space.plan.delete-room')->middleware('acl:update');
    // authored audio guided tour (curator saves route + narration)
    Route::post('/exhibition-space/{slug}/guided-tour', [ExhibitionSpaceController::class, 'saveGuidedTourAjax'])->name('exhibition-space.guided-tour')->middleware('acl:update');
    // heratio#1146 - live data link
    Route::post('/exhibition-space/{slug}/readings', [ExhibitionSpaceController::class, 'recordReadingsAjax'])->name('exhibition-space.readings')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/readings/simulate', [ExhibitionSpaceController::class, 'simulateReadingsAjax'])->name('exhibition-space.readings.simulate')->middleware('acl:update');
    // heratio#1149 - precompute AI recommendations (admin)
    Route::post('/exhibition-space/{slug}/recommend/generate', [ExhibitionSpaceController::class, 'generateRecommendationsAjax'])->name('exhibition-space.recommend.generate')->middleware('acl:update');
    // heratio#1147 - conservation forecast
    Route::get('/exhibition-space/{slug}/forecast', [ExhibitionSpaceController::class, 'forecast'])->name('exhibition-space.forecast');
    // heratio#1148 - analytics dashboard
    Route::get('/exhibition-space/{slug}/analytics', [ExhibitionSpaceController::class, 'analytics'])->name('exhibition-space.analytics');
    Route::post('/exhibition-space/{slug}/plan/image-rect', [ExhibitionSpaceController::class, 'planImageRectAjax'])->name('exhibition-space.plan.image-rect')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/corridor-add', [ExhibitionSpaceController::class, 'corridorAddAjax'])->name('exhibition-space.plan.corridor-add')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/corridor-move', [ExhibitionSpaceController::class, 'corridorMoveAjax'])->name('exhibition-space.plan.corridor-move')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/corridor-remove', [ExhibitionSpaceController::class, 'corridorRemoveAjax'])->name('exhibition-space.plan.corridor-remove')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/image', [ExhibitionSpaceController::class, 'uploadBuildingPlan'])->name('exhibition-space.plan.image')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/plan/image-clear', [ExhibitionSpaceController::class, 'clearBuildingPlan'])->name('exhibition-space.plan.image-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/layout', [ExhibitionSpaceController::class, 'saveLayout'])->name('exhibition-space.builder.layout')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/place', [ExhibitionSpaceController::class, 'placeAjax'])->name('exhibition-space.builder.place')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/remove', [ExhibitionSpaceController::class, 'removeAjax'])->name('exhibition-space.builder.remove')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/size', [ExhibitionSpaceController::class, 'updateSizeAjax'])->name('exhibition-space.builder.size')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/tilt', [ExhibitionSpaceController::class, 'updateTiltAjax'])->name('exhibition-space.builder.tilt')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/spotlight', [ExhibitionSpaceController::class, 'updateSpotlightAjax'])->name('exhibition-space.builder.spotlight')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/display-case', [ExhibitionSpaceController::class, 'updateDisplayCaseAjax'])->name('exhibition-space.builder.display-case')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/on-floor', [ExhibitionSpaceController::class, 'updateOnFloorAjax'])->name('exhibition-space.builder.on-floor')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/zorder', [ExhibitionSpaceController::class, 'updateZOrderAjax'])->name('exhibition-space.builder.zorder')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-add', [ExhibitionSpaceController::class, 'furnitureAddAjax'])->name('exhibition-space.builder.furniture-add')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-move', [ExhibitionSpaceController::class, 'furnitureMoveAjax'])->name('exhibition-space.builder.furniture-move')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-remove', [ExhibitionSpaceController::class, 'furnitureRemoveAjax'])->name('exhibition-space.builder.furniture-remove')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-poles', [ExhibitionSpaceController::class, 'furniturePolesAjax'])->name('exhibition-space.builder.furniture-poles')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-asset-upload', [ExhibitionSpaceController::class, 'uploadFurnitureAsset'])->name('exhibition-space.builder.furniture-asset-upload')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/furniture-asset-delete', [ExhibitionSpaceController::class, 'deleteFurnitureAssetAjax'])->name('exhibition-space.builder.furniture-asset-delete')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/walls', [ExhibitionSpaceController::class, 'saveWallsAjax'])->name('exhibition-space.builder.walls')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall', [ExhibitionSpaceController::class, 'updateWallAjax'])->name('exhibition-space.builder.wall')->middleware('acl:update');
    Route::get('/exhibition-space/{slug}/builder/placements', [ExhibitionSpaceController::class, 'placementsJson'])->name('exhibition-space.builder.placements');
    Route::post('/exhibition-space/{slug}/builder/wall-place', [ExhibitionSpaceController::class, 'placeWallAjax'])->name('exhibition-space.builder.wall-place')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-pos', [ExhibitionSpaceController::class, 'updateWallPosAjax'])->name('exhibition-space.builder.wall-pos')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/floorplan', [ExhibitionSpaceController::class, 'uploadFloorplan'])->name('exhibition-space.builder.floorplan')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/ceiling', [ExhibitionSpaceController::class, 'uploadCeiling'])->name('exhibition-space.builder.ceiling')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/ceiling-clear', [ExhibitionSpaceController::class, 'clearCeiling'])->name('exhibition-space.builder.ceiling-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-image', [ExhibitionSpaceController::class, 'uploadWallImage'])->name('exhibition-space.builder.wall-image')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-image-clear', [ExhibitionSpaceController::class, 'clearWallImage'])->name('exhibition-space.builder.wall-image-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/floor-image', [ExhibitionSpaceController::class, 'uploadFloorImage'])->name('exhibition-space.builder.floor-image')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/floor-image-clear', [ExhibitionSpaceController::class, 'clearFloorImage'])->name('exhibition-space.builder.floor-image-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/floor-grout', [ExhibitionSpaceController::class, 'setFloorGroutAjax'])->name('exhibition-space.builder.floor-grout')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-color', [ExhibitionSpaceController::class, 'saveWallColor'])->name('exhibition-space.builder.wall-color')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-color-clear', [ExhibitionSpaceController::class, 'clearWallColor'])->name('exhibition-space.builder.wall-color-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/room-dims', [ExhibitionSpaceController::class, 'roomDimsAjax'])->name('exhibition-space.builder.room-dims')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/walkthrough-path', [ExhibitionSpaceController::class, 'saveWalkthroughPath'])->name('exhibition-space.builder.walkthrough-path')->middleware('acl:update');
});

// heratio#1138 — digital twin: 2.5D pannable walkthrough (Phase 2, visitor-facing/public)
Route::get('/exhibition-space/{slug}/walkthrough', [ExhibitionSpaceController::class, 'walkthrough'])->name('exhibition-space.walkthrough');
// heratio#1149 — in-twin recommendations (public, read-only) for the walkthrough
Route::get('/exhibition-space/{slug}/recommend', [ExhibitionSpaceController::class, 'recommendAjax'])->name('exhibition-space.recommend');
// AI-describe an object with no metadata (walkthrough T=talk docent, public)
Route::get('/exhibition-space/object/{ioId}/describe', [ExhibitionSpaceController::class, 'describeObjectAjax'])->name('exhibition-space.describe')->whereNumber('ioId');
// heratio#1150 — multi-user presence (public; docent role gated server-side on auth)
Route::post('/exhibition-space/{slug}/presence/beat', [ExhibitionSpaceController::class, 'presenceBeatAjax'])->name('exhibition-space.presence.beat');
Route::post('/exhibition-space/{slug}/presence/leave', [ExhibitionSpaceController::class, 'presenceLeaveAjax'])->name('exhibition-space.presence.leave');
// heratio#1165 — wall graffiti / annotations (public, walkthrough)
Route::post('/exhibition-space/{slug}/annotation', [ExhibitionSpaceController::class, 'annotationAddAjax'])->name('exhibition-space.annotation');
Route::post('/exhibition-space/{slug}/annotation/{id}/delete', [ExhibitionSpaceController::class, 'annotationDeleteAjax'])->name('exhibition-space.annotation.delete')->whereNumber('id');
// heratio#1173 — visitor analytics event (public, walkthrough)
Route::post('/exhibition-space/{slug}/visit-event', [ExhibitionSpaceController::class, 'visitEventAjax'])->name('exhibition-space.visit-event');
// heratio#1151 — open-standard interoperability exports (public, read-only, CORS *)
Route::get('/exhibition-space/{slug}/manifest.json', [ExhibitionSpaceController::class, 'iiifManifest'])->name('exhibition-space.iiif');
Route::get('/exhibition-space/{slug}/scene.json', [ExhibitionSpaceController::class, 'sceneExport'])->name('exhibition-space.scene');
Route::get('/exhibition-space/{slug}/exhibition.jsonld', [ExhibitionSpaceController::class, 'exhibitionJsonLd'])->name('exhibition-space.jsonld');

Route::middleware('admin')->group(function () {
    Route::get('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'confirmDelete'])->name('exhibition-space.confirmDelete');
    Route::delete('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'destroy'])->name('exhibition-space.destroy')->middleware('acl:delete');
});

Route::get('/exhibition-space/{slug}', [ExhibitionSpaceController::class, 'show'])
    ->name('exhibition-space.show')
    ->where('slug', '(?!browse|add|placement)[a-z0-9][a-z0-9-]*');
