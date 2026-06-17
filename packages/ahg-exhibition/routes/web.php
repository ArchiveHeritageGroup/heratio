<?php

use AhgExhibition\Controllers\ExhibitionController;
use AhgExhibition\Controllers\ExhibitionEventController;
use AhgExhibition\Controllers\ExhibitionSpaceController;
use AhgExhibition\Controllers\GenerativeController;
use AhgExhibition\Controllers\ReconstructionController;
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

// Public demo: these four pages are viewable without login (builder/plan/analytics/forecast). All
// edit/save/delete POST actions stay inside the auth+acl group below, and the edit controls in the
// views are @auth-gated, so a guest can explore but cannot change anything.
Route::get('/exhibition-space/{slug}/builder', [ExhibitionSpaceController::class, 'builder'])->name('exhibition-space.builder');
Route::get('/exhibition-space/{slug}/plan', [ExhibitionSpaceController::class, 'plan'])->name('exhibition-space.plan');
// heratio#1217 - building-scale twin, first slice: read-only 2D top-down wayfinding
// floor plan + "take me to X" object directory (deep-links into the walkthrough).
// Public, multi-segment so it stays clear of the single-segment /{slug} catch-all.
Route::get('/exhibition-space/{slug}/wayfinding', [ExhibitionSpaceController::class, 'wayfinding'])->name('exhibition-space.wayfinding');
// Printable / PDF-ready exhibition catalogue (the classic museum deliverable).
// Public, read-only, multi-segment so it stays clear of the single-segment /{slug} catch-all.
Route::get('/exhibition-space/{slug}/catalogue', [ExhibitionSpaceController::class, 'catalogue'])->name('exhibition-space.catalogue');
Route::get('/exhibition-space/{slug}/forecast', [ExhibitionSpaceController::class, 'forecast'])->name('exhibition-space.forecast');
Route::get('/exhibition-space/{slug}/analytics', [ExhibitionSpaceController::class, 'analytics'])->name('exhibition-space.analytics');

Route::middleware('auth')->group(function () {
    // heratio#1186 - generative exhibitions: theme -> AI-curated draft (rooms + objects + labels)
    Route::get('/exhibition-space/generate', [GenerativeController::class, 'index'])->name('exhibition-space.generate');
    Route::post('/exhibition-space/generate/suggest', [GenerativeController::class, 'suggestAjax'])->name('exhibition-space.generate.suggest')->middleware('acl:create');
    Route::post('/exhibition-space/generate/build', [GenerativeController::class, 'buildAjax'])->name('exhibition-space.generate.build')->middleware('acl:create');
    // heratio#1186 - the one-shot theme curator has been RETIRED in favour of the canonical
    // two-step flow (GenerativeController + GenerativeExhibitionService: draft -> review -> build,
    // multi-room, era-aware). The two legacy URIs are kept (same name/URI/verb/middleware) but
    // REPOINTED to the canonical designer so any existing UI link (e.g. the orphaned
    // theme-exhibition.blade.php form) keeps resolving and now lands on the better flow.
    Route::get('/exhibition-space/generate/theme', [GenerativeController::class, 'index'])->name('exhibition-space.generate.theme');
    Route::post('/exhibition-space/generate/theme', [GenerativeController::class, 'index'])->name('exhibition-space.generate.theme.store')->middleware('acl:create');

    Route::get('/exhibition-space/add', [ExhibitionSpaceController::class, 'create'])->name('exhibition-space.create');
    Route::post('/exhibition-space/add', [ExhibitionSpaceController::class, 'store'])->name('exhibition-space.store')->middleware('acl:create');
    Route::get('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'edit'])->name('exhibition-space.edit');
    Route::post('/exhibition-space/{slug}/edit', [ExhibitionSpaceController::class, 'update'])->name('exhibition-space.update')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/place', [ExhibitionSpaceController::class, 'placePlacement'])->name('exhibition-space.place')->middleware('acl:update');
    // heratio#1277 federated twin: borrow a peer institution's object into this space (read-only)
    Route::post('/exhibition-space/{slug}/peer-scene', [ExhibitionSpaceController::class, 'peerScene'])->name('exhibition-space.peer-scene')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/place-remote', [ExhibitionSpaceController::class, 'placeRemote'])->name('exhibition-space.place-remote')->middleware('acl:update');
    // heratio#1195 - publish this space into the RiC graph as a rico:Activity
    Route::post('/exhibition-space/{slug}/sync-ric', [ExhibitionSpaceController::class, 'syncRic'])->name('exhibition-space.sync-ric')->middleware('acl:update');
    Route::post('/exhibition-space/placement/{placementId}/remove', [ExhibitionSpaceController::class, 'removePlacement'])->name('exhibition-space.placement.remove')->middleware('acl:update')->whereNumber('placementId');

    // heratio#1138 — digital twin builder + #1143 plan editor: GET views are public (see above);
    // all save/edit/delete actions below stay auth+acl gated.
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
    Route::post('/exhibition-space/{slug}/tour-audio', [ExhibitionSpaceController::class, 'uploadTourAudio'])->name('exhibition-space.tour-audio')->middleware('acl:update');
    // heratio#1146 - live data link
    Route::post('/exhibition-space/{slug}/readings', [ExhibitionSpaceController::class, 'recordReadingsAjax'])->name('exhibition-space.readings')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/readings/simulate', [ExhibitionSpaceController::class, 'simulateReadingsAjax'])->name('exhibition-space.readings.simulate')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/sensor/regenerate', [ExhibitionSpaceController::class, 'regenerateSensorTokenAjax'])->name('exhibition-space.sensor.regenerate')->middleware('acl:update');   // #1188
    // heratio#1149 - precompute AI recommendations (admin)
    Route::post('/exhibition-space/{slug}/recommend/generate', [ExhibitionSpaceController::class, 'generateRecommendationsAjax'])->name('exhibition-space.recommend.generate')->middleware('acl:update');
    // heratio#1147 forecast + #1148 analytics: GET views are public (see above).
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
    Route::post('/exhibition-space/{slug}/builder/view-spot', [ExhibitionSpaceController::class, 'updatePlacementViewAjax'])->name('exhibition-space.builder.view-spot')->middleware('acl:update');
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
    Route::post('/exhibition-space/{slug}/builder/scan-shell', [ExhibitionSpaceController::class, 'uploadScanShell'])->name('exhibition-space.builder.scan-shell')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/scan-shell-clear', [ExhibitionSpaceController::class, 'clearScanShell'])->name('exhibition-space.builder.scan-shell-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/scan-meta', [ExhibitionSpaceController::class, 'setScanMetaAjax'])->name('exhibition-space.builder.scan-meta')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-color', [ExhibitionSpaceController::class, 'saveWallColor'])->name('exhibition-space.builder.wall-color')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/wall-color-clear', [ExhibitionSpaceController::class, 'clearWallColor'])->name('exhibition-space.builder.wall-color-clear')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/room-dims', [ExhibitionSpaceController::class, 'roomDimsAjax'])->name('exhibition-space.builder.room-dims')->middleware('acl:update');
    Route::post('/exhibition-space/{slug}/builder/walkthrough-path', [ExhibitionSpaceController::class, 'saveWalkthroughPath'])->name('exhibition-space.builder.walkthrough-path')->middleware('acl:update');

    // heratio#1192 - live virtual openings: admin schedule + manage (multi-segment URLs
    // so they never collide with the single-segment /exhibition-space/{slug} catch-all).
    Route::get('/exhibition-space/{slug}/openings', [ExhibitionEventController::class, 'index'])->name('exhibition-space.openings');
    Route::post('/exhibition-space/{slug}/openings', [ExhibitionEventController::class, 'store'])->name('exhibition-space.openings.store')->middleware('acl:create');
    Route::post('/exhibition-space/{slug}/openings/{eventId}/status', [ExhibitionEventController::class, 'updateStatus'])->name('exhibition-space.openings.status')->middleware('acl:update')->whereNumber('eventId');
    Route::post('/exhibition-space/{slug}/openings/{eventId}/delete', [ExhibitionEventController::class, 'destroy'])->name('exhibition-space.openings.delete')->middleware('acl:delete')->whereNumber('eventId');
    // heratio#1192 slice 2b - settle a pending paid ticket (admin "mark as paid")
    Route::post('/exhibition-space/{slug}/openings/{eventId}/rsvp/{rsvpId}/mark-paid', [ExhibitionEventController::class, 'markPaid'])->name('exhibition-space.openings.mark-paid')->middleware('acl:update')->whereNumber('eventId')->whereNumber('rsvpId');

    // heratio#1206 - "walk through what no longer exists": curator links a catalogue
    // record (a lost / destroyed place) to a walkable reconstruction space. Multi-segment
    // URLs so they stay clear of the single-segment /exhibition-space/{slug} catch-all.
    Route::get('/exhibition-space/reconstructions/manage', [ReconstructionController::class, 'manage'])->name('exhibition-space.reconstructions.manage');
    Route::post('/exhibition-space/reconstructions/manage', [ReconstructionController::class, 'store'])->name('exhibition-space.reconstructions.store')->middleware('acl:create');
    Route::post('/exhibition-space/reconstructions/{id}/delete', [ReconstructionController::class, 'destroy'])->name('exhibition-space.reconstructions.delete')->middleware('acl:delete')->whereNumber('id');

    // heratio#1219 - rebuild-stage CRUD + montage style for a reconstruction.
    // Multi-segment, ACL-gated like the manage actions above. Numeric ids only.
    Route::post('/admin/reconstructions/{id}/stages', [ReconstructionController::class, 'addStage'])->name('exhibition-space.reconstructions.stages.add')->middleware('acl:create')->whereNumber('id');
    Route::match(['put', 'patch'], '/admin/reconstructions/{id}/stages/{stageId}', [ReconstructionController::class, 'updateStage'])->name('exhibition-space.reconstructions.stages.update')->middleware('acl:update')->whereNumber('id')->whereNumber('stageId');
    Route::delete('/admin/reconstructions/{id}/stages/{stageId}', [ReconstructionController::class, 'deleteStage'])->name('exhibition-space.reconstructions.stages.delete')->middleware('acl:delete')->whereNumber('id')->whereNumber('stageId');
    Route::post('/admin/reconstructions/{id}/stages/reorder', [ReconstructionController::class, 'reorderStages'])->name('exhibition-space.reconstructions.stages.reorder')->middleware('acl:update')->whereNumber('id');
    Route::post('/admin/reconstructions/{id}/style', [ReconstructionController::class, 'setStyle'])->name('exhibition-space.reconstructions.style')->middleware('acl:update')->whereNumber('id');

    // heratio#1206 - optional AI "evidence layer" annotator. annotate = AI suggestion
    // (AJAX, read-only, acl:read); metadata = persist the curator-confirmed JSON.
    Route::post('/admin/reconstructions/{id}/stages/{stageId}/annotate', [ReconstructionController::class, 'annotateStage'])->name('exhibition-space.reconstructions.stages.annotate')->middleware('acl:read')->whereNumber('id')->whereNumber('stageId');
    Route::post('/admin/reconstructions/{id}/stages/{stageId}/metadata', [ReconstructionController::class, 'saveStageMetadata'])->name('exhibition-space.reconstructions.stages.metadata')->middleware('acl:update')->whereNumber('id')->whereNumber('stageId');
});

// heratio#1192 - live virtual openings: PUBLIC tokenised event page + RSVP (no login;
// the walkthrough it links into is itself public). Multi-segment, so clear of the slug catch-all.
Route::get('/exhibition-space/opening/{token}', [ExhibitionEventController::class, 'publicShow'])->name('exhibition-space.opening-public')->where('token', '[a-z0-9]+');
Route::post('/exhibition-space/opening/{token}/rsvp', [ExhibitionEventController::class, 'rsvp'])->name('exhibition-space.opening-rsvp')->where('token', '[a-z0-9]+');
// heratio#1192 slice 2a - ticket-gated join: validates the ticket + join window, pins the
// attendee in session, redirects into the walkthrough as a live co-present visitor.
Route::get('/exhibition-space/opening/{token}/join', [ExhibitionEventController::class, 'join'])->name('exhibition-space.opening-join')->where('token', '[a-z0-9]+');

// heratio#1138 — digital twin: 2.5D pannable walkthrough (Phase 2, visitor-facing/public)
Route::get('/exhibition-space/{slug}/walkthrough', [ExhibitionSpaceController::class, 'walkthrough'])->name('exhibition-space.walkthrough');
// heratio#1191 - on-site AR companion (first slice): mobile-first phone page opened in the
// gallery via QR; twin-sourced object cards + room AI docent. Geo/marker AR anchoring is a later slice.
Route::get('/exhibition-space/{slug}/companion', [ExhibitionSpaceController::class, 'companion'])->name('exhibition-space.companion');
// heratio#1194 - accessible text + narration tour (keyboard / screen-reader alternative)
Route::get('/exhibition-space/{slug}/accessible-tour', [ExhibitionSpaceController::class, 'accessibleTour'])->name('exhibition-space.accessible-tour');
// heratio#1153 — WebGPU renderer spike (proof page; live walkthrough untouched)
Route::get('/exhibition-space/{slug}/walkthrough-webgpu', [ExhibitionSpaceController::class, 'walkthroughWebgpu'])->name('exhibition-space.walkthrough-webgpu');
// heratio#1153/#1193 — BETA ESM/r169 walkthrough with in-room Gaussian splats (live one untouched)
Route::get('/exhibition-space/{slug}/walkthrough-next', [ExhibitionSpaceController::class, 'walkthroughNext'])->name('exhibition-space.walkthrough-next');
// heratio#1149 — in-twin recommendations (public, read-only) for the walkthrough
Route::get('/exhibition-space/{slug}/recommend', [ExhibitionSpaceController::class, 'recommendAjax'])->name('exhibition-space.recommend');
// AI-describe an object with no metadata (walkthrough T=talk docent, public)
Route::get('/exhibition-space/object/{ioId}/describe', [ExhibitionSpaceController::class, 'describeObjectAjax'])->name('exhibition-space.describe')->whereNumber('ioId');
// heratio#1185 — AI docent: grounded Q&A about an object (public, read-only)
Route::get('/exhibition-space/object/{ioId}/ask', [ExhibitionSpaceController::class, 'askObjectAjax'])->name('exhibition-space.ask')->whereNumber('ioId');
// heratio#1185 — AI docent: grounded Q&A about the whole ROOM / exhibition + suggested questions (public, read-only)
Route::get('/exhibition-space/{slug}/ask-room', [ExhibitionSpaceController::class, 'askRoomAjax'])->name('exhibition-space.ask-room');
Route::get('/exhibition-space/{slug}/room-questions', [ExhibitionSpaceController::class, 'roomQuestionsAjax'])->name('exhibition-space.room-questions');
// heratio#1185 — conversational (multi-turn) room docent; POST so the transcript fits the body
Route::post('/exhibition-space/{slug}/converse', [ExhibitionSpaceController::class, 'converseRoomAjax'])->name('exhibition-space.converse');
Route::post('/exhibition-space/tts', [ExhibitionSpaceController::class, 'ttsAjax'])->name('exhibition-space.tts');   // #1168 neural TTS via the gateway (public; walkthrough narration)
// heratio#1188 — IoT sensor/gateway ingest, authenticated by a per-space token (no session/CSRF)
Route::post('/exhibition-space/sensor/ingest', [ExhibitionSpaceController::class, 'sensorIngestAjax'])->name('exhibition-space.sensor.ingest');
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

// heratio#exhibitions-index - PUBLIC "Explore our exhibitions" landing: one page
// that lists every public exhibition space so a visitor can find them all (until
// now each space was only reachable by its own URL). A fixed literal single-segment
// path, registered ahead of the /{slug} IO catch-all below (same precedence trick
// as /reconstructions and /verify), so it binds before the catch-all ever sees it.
Route::get('/exhibitions', [ExhibitionSpaceController::class, 'index'])->name('exhibition-space.index');

// heratio#1192 deepened - PUBLIC "What's on": upcoming + live openings across every
// exhibition space, in start-time order, each linking to its existing tokenised
// opening page for details + RSVP. A fixed literal single-segment path, registered
// ahead of the /{slug} IO catch-all below (same precedence trick as /exhibitions
// and /reconstructions), so it binds before the catch-all ever sees it.
Route::get('/whats-on', [ExhibitionEventController::class, 'whatsOn'])->name('exhibition.whats-on');

// heratio#1206 - "walk through what no longer exists": PUBLIC gallery of every
// catalogue record (a lost / destroyed place) linked to a walkable reconstruction
// twin. A fixed literal path, registered ahead of the /{slug} catch-all below.
Route::get('/reconstructions', [ReconstructionController::class, 'gallery'])->name('exhibition-space.reconstructions');

// heratio#1219 - "reconstruction assembly montage": a lost structure rebuilding
// itself on screen before the visitor walks into its 3D twin. All public, all
// two-segment so the single-segment /{slug} IO catch-all never sees them.
// Precedence matters: the LITERAL /reconstructions/demo MUST be registered before
// the numeric /reconstructions/{id} player, and {id} is constrained to digits so a
// stray slug falls through to the catch-all rather than hitting the player.
Route::get('/reconstructions/demo', [ReconstructionController::class, 'demo'])->name('reconstruction.demo');
Route::get('/reconstructions/demo-asset/{file}', [ReconstructionController::class, 'demoAsset'])->name('reconstruction.demo.asset')->where('file', '[a-z0-9-]+');
Route::get('/reconstructions/stage/{id}/image', [ReconstructionController::class, 'stageImage'])->name('reconstruction.stage.image')->whereNumber('id');
Route::get('/reconstructions/{id}', [ReconstructionController::class, 'show'])->name('reconstruction.show')->whereNumber('id');

Route::middleware('admin')->group(function () {
    Route::get('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'confirmDelete'])->name('exhibition-space.confirmDelete');
    Route::delete('/exhibition-space/{slug}/delete', [ExhibitionSpaceController::class, 'destroy'])->name('exhibition-space.destroy')->middleware('acl:delete');
});

Route::get('/exhibition-space/{slug}', [ExhibitionSpaceController::class, 'show'])
    ->name('exhibition-space.show')
    ->where('slug', '(?!browse|add|placement)[a-z0-9][a-z0-9-]*');
