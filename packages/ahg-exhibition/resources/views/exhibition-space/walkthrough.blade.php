{{-- heratio#1138 — Digital Twin: first-person 3D walkthrough (Phase 3, Three.js). --}}
@extends('theme::layouts.1col')

@section('title', __('3D Walkthrough') . ' — ' . $space->name)
@section('body-class', 'exhibition-space walkthrough-3d')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-vr-cardboard me-2"></i>{{ __('3D Walkthrough') }}
      <small class="text-muted" id="wtSpaceName">{{ $space->name }}</small>
    </h1>
    <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to space') }}
    </a>
    @auth
      <a id="editBuilderBtn" href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}"
         data-tmpl="{{ route('exhibition-space.builder', ['slug' => '__SLUG__']) }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-cubes me-1"></i>{{ __('Edit in Builder') }}
      </a>
    @endauth
  </div>

  @if(!($hasContent ?? (count($stops) > 0)))
    <div class="alert alert-info">
      {{ __('Nothing has been placed in this space yet.') }}
      @auth <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}">{{ __('Open the Digital Twin Builder') }}</a>.@endauth
    </div>
  @else
    <div class="card">
      <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <strong>{{ __('Virtual gallery') }}</strong>
        <span class="small text-muted">{{ __('Click to enter. Move: W A S D. Look: mouse. Select: click an object. Exit: Esc.') }}</span>
      </div>
      <div class="card-body p-0">
        <div id="room" style="position:relative;width:100%;height:70vh;min-height:420px;background:#1a1d21;border-radius:0;overflow:hidden;">
          <div id="roomBlocker" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:5;cursor:pointer;">
            <div class="text-center text-white">
              <div style="font-size:2rem;"><i class="fas fa-vr-cardboard"></i></div>
              <div class="fw-bold mt-2">{{ __('Click to enter the gallery') }}</div>
              <div class="small text-white-50 mt-1">{{ __('W A S D to walk, mouse to look, click an object for details, Esc to exit') }}</div>
              <div class="small text-white-50">{{ __('Press H any time for the full controls') }}</div>
            </div>
          </div>
          <div id="roomCrosshair" style="position:absolute;top:50%;left:50%;width:16px;height:16px;margin:-8px 0 0 -8px;border-radius:50%;background:#000;border:2px solid rgba(255,255,255,.85);box-sizing:border-box;z-index:4;display:none;pointer-events:none;"></div>
          {{-- Figure tool: the pointer becomes a person silhouette; mouse wheel cycles man/woman; click drops a figure. --}}
          <img id="figurePointer" alt="" style="position:absolute;top:50%;left:50%;height:120px;margin-top:-110px;transform:translateX(-50%);z-index:4;display:none;pointer-events:none;opacity:.92;filter:drop-shadow(0 2px 3px rgba(0,0,0,.5));">
          <div id="figureHint" class="bg-dark text-white px-2 py-1 rounded small" style="position:absolute;bottom:90px;left:50%;transform:translateX(-50%);z-index:7;display:none;"><i class="fas fa-person-walking me-1"></i>{{ __('Wheel: change person - click: place (this view only)') }}</div>
          {{-- When the centre ray is over a clickable object, the pointer becomes a plain hand (person/figure suppressed) so you can interact with it. --}}
          <div id="objectPointer" style="position:absolute;top:50%;left:50%;margin:-12px 0 0 -11px;z-index:5;display:none;pointer-events:none;color:#fff;font-size:22px;text-shadow:0 1px 3px rgba(0,0,0,.8);"><i class="fas fa-hand-pointer"></i></div>
          <div id="roomLoading" style="position:absolute;bottom:8px;left:8px;z-index:4;color:#ccc;font-size:.8rem;">{{ __('Loading gallery...') }}</div>
          {{-- steal-alarm: flickering red overlay + silence control --}}
          <div id="wtAlarm" style="position:absolute;inset:0;background:#ff0000;opacity:0;z-index:9;pointer-events:none;display:none;"></div>
          <div id="wtAlarmBar" style="position:absolute;top:46%;left:50%;transform:translate(-50%,-50%);z-index:10;display:none;text-align:center;">
            <div class="bg-danger text-white px-4 py-2 rounded-pill shadow fw-bold"><i class="fas fa-triangle-exclamation me-2"></i><span id="wtAlarmText"></span></div>
            <button type="button" id="wtAlarmOff" class="btn btn-light btn-sm rounded-pill mt-2 shadow"><i class="fas fa-bell-slash me-1"></i>{{ __('Silence alarm') }}</button>
          </div>
          <div id="wtHeight" class="bg-dark text-white px-2 py-1 rounded small" style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);z-index:7;display:none;"></div>
          <div id="wtNarr" class="bg-primary text-white px-2 py-1 rounded small" style="position:absolute;bottom:34px;left:50%;transform:translateX(-50%);z-index:7;display:none;"><i class="fas fa-volume-high me-1"></i>{{ __('Reading description... (Esc to stop)') }}</div>
          <button id="roomHelpBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:8px;z-index:6;opacity:.85;" title="{{ __('Controls') }}"><i class="fas fa-question"></i></button>
          <button id="roomMapBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:44px;z-index:6;opacity:.85;" title="{{ __('Building map') }}"><i class="fas fa-map"></i></button>
          <button id="roomLiveBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:80px;z-index:6;opacity:.85;" title="{{ __('Live data') }}"><i class="fas fa-temperature-half"></i></button>
          <button id="wtPeopleBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:116px;z-index:6;opacity:.85;" title="{{ __('People here') }}"><i class="fas fa-users"></i> <span id="wtPeopleCount">1</span></button>
          <button id="wtTorchBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:156px;z-index:6;opacity:.85;" title="{{ __('Torch (F)') }}"><i class="fas fa-lightbulb"></i></button>
          <button id="wtGraffitiBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:192px;z-index:6;opacity:.85;" title="{{ __('Graffiti: click a wall to tag it') }}"><i class="fas fa-spray-can"></i></button>
          <button id="wtTourPlayBtn" type="button" class="btn btn-sm btn-success" style="position:absolute;top:8px;right:228px;z-index:6;opacity:.9;display:none;" title="{{ __('Play guided tour') }}"><i class="fas fa-play"></i></button>
          <button id="wtStealBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:264px;z-index:6;opacity:.85;" title="{{ __('Steal mode: click an object to trigger the alarm') }}"><i class="fas fa-mask"></i></button>
          <button id="wtFigureBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:300px;z-index:6;opacity:.85;" title="{{ __('Figures: wheel to pick a person, click to place') }}"><i class="fas fa-person-walking"></i></button>
          <button id="wtSunBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:336px;z-index:6;opacity:.85;" title="{{ __('Sun & shadows (off / morning / noon / afternoon)') }}"><i class="fas fa-sun"></i></button>
          <button id="wtFsBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:372px;z-index:6;opacity:.85;" title="{{ __('Fullscreen') }}"><i class="fas fa-expand"></i></button>
          <div id="wtTourBanner" class="bg-dark text-white px-3 py-2 rounded small" style="position:absolute;bottom:64px;left:50%;transform:translateX(-50%);z-index:7;display:none;max-width:86%;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.5);">
            <span id="wtTourText"></span>
            <button type="button" id="wtTourStopBtn" class="btn btn-sm btn-outline-light ms-2 py-0"><i class="fas fa-stop"></i></button>
          </div>
          {{-- mobile quick-launch: walking is hard on touch, so offer a big "play the tour" button --}}
          <div id="wtTourQuick" style="position:absolute;bottom:18px;left:50%;transform:translateX(-50%);z-index:8;display:none;text-align:center;width:90%;max-width:360px;">
            <select id="wtTourQuickSel" class="form-select form-select-sm mb-2 d-none"></select>
            <button type="button" id="wtTourQuickBtn" class="btn btn-success btn-lg rounded-pill shadow w-100"><i class="fas fa-play me-2"></i>{{ __('Start guided tour') }}</button>
          </div>
          <div id="wtPeople" class="bg-dark text-white p-2 rounded small" style="position:absolute;top:46px;right:8px;z-index:8;width:240px;display:none;box-shadow:0 4px 16px rgba(0,0,0,.5);">
            <div class="d-flex justify-content-between align-items-center mb-1"><span class="fw-bold"><i class="fas fa-users me-1"></i>{{ __('In this exhibition') }}</span><button type="button" id="wtPeopleClose" class="btn-close btn-close-white btn-sm" aria-label="{{ __('Close') }}"></button></div>
            <input id="wtNameInput" class="form-control form-control-sm mb-2" placeholder="{{ __('Your name') }}" maxlength="40">
            <div id="wtPeopleList"></div>
            <button id="wtFollowBtn" type="button" class="btn btn-sm btn-warning w-100 mt-2" style="display:none;"><i class="fas fa-shoe-prints me-1"></i>{{ __('Follow the docent') }}</button>
            @if($canDocent ?? false)
            <hr class="my-2">
            <button id="wtTourBtn" type="button" class="btn btn-sm btn-success w-100"><i class="fas fa-chalkboard-user me-1"></i>{{ __('Start guided tour') }}</button>
            <input id="wtDocentMsg" class="form-control form-control-sm mt-2" placeholder="{{ __('Say something to the tour') }}" maxlength="200" style="display:none;">
            <div class="text-muted mt-1" style="font-size:.72rem;">{{ __('While leading, click an object to spotlight it for everyone following.') }}</div>
            @endif
          </div>
          <div id="wtDocentBanner" class="bg-primary text-white px-3 py-2 rounded small" style="position:absolute;top:46px;left:50%;transform:translateX(-50%);z-index:7;display:none;max-width:80%;text-align:center;box-shadow:0 4px 16px rgba(0,0,0,.5);"></div>
          <div id="wtLive" class="bg-dark text-white p-2 rounded small" style="position:absolute;top:46px;left:8px;z-index:7;width:230px;display:none;box-shadow:0 4px 16px rgba(0,0,0,.5);">
            <div class="fw-bold mb-1"><i class="fas fa-temperature-half me-1"></i>{{ __('Live conditions') }}</div>
            <div id="wtLiveBody"></div>
          </div>
          <div id="wtMinimap" class="bg-dark text-white p-2 rounded" style="position:absolute;top:46px;right:8px;z-index:7;width:260px;display:none;box-shadow:0 4px 16px rgba(0,0,0,.5);">
            <div class="d-flex justify-content-between align-items-center mb-1"><span class="small fw-bold"><i class="fas fa-map me-1"></i>{{ __('Building — tap a room to enter') }}</span><button type="button" id="wtMiniClose" class="btn-close btn-close-white btn-sm" aria-label="{{ __('Close') }}"></button></div>
            <div id="wtMiniSvg"></div>
          </div>
          <div id="roomHelp" class="bg-dark text-white p-3 rounded small" style="position:absolute;top:46px;right:8px;z-index:6;max-width:260px;display:none;">
            <div class="fw-bold mb-2"><i class="fas fa-gamepad me-1"></i>{{ __('Controls') }}</div>
            <ul class="mb-0 ps-3">
              <li>{{ __('Click gallery to enter') }}</li>
              <li>{{ __('Forward / back: mouse wheel') }}</li>
              <li>{{ __('Stand taller / crouch: hold U + mouse wheel') }}</li>
              <li>{{ __('Virtual reality: tap the VR button (headset required); left stick moves, right stick turns') }}</li>
              <li>{{ __('Look around: move the mouse') }}</li>
              <li>{{ __('Open info: click an object (or a numbered button)') }}</li>
              <li>{{ __('Hear description read aloud: hold T (Talk) + click an object') }}</li>
              <li>{{ __('Force a fresh AI description: hold G + click an object') }}</li>
              <li>{{ __('Zoom in / out: Z') }}</li>
              <li>{{ __('Torch (light dark corners): F or the bulb button') }}</li>
              <li>{{ __('Graffiti: tap the spray-can, then click a wall to tag it') }}</li>
              <li>{{ __('Steal (sets off the alarm!): tap the mask, then click an object (or hold S + click)') }}</li>
              <li>{{ __('Open full record (new tab): V') }}</li>
              <li>{{ __('Close info: click or Esc') }}</li>
              <li>{{ __('Exit gallery: Esc') }}</li>
              <li class="mt-1 text-info">{{ __('Touch: drag to look, pinch to zoom, tap an object, tap a numbered button to travel') }}</li>
            </ul>
            <div class="mt-2" id="wtTourPick" style="display:none;">
              <label class="form-label mb-1"><i class="fas fa-route me-1"></i>{{ __('Guided tour') }}</label>
              <select id="wtTourSel" class="form-select form-select-sm"></select>
            </div>
            <div class="mt-2">
              <label class="form-label mb-1"><i class="fas fa-microphone-lines me-1"></i>{{ __('Narration voice') }}</label>
              <select id="wtVoiceSel" class="form-select form-select-sm"><option value="">{{ __('Default') }}</option></select>
            </div>
          </div>
          {{-- In-canvas detail inlay (replaces the side panel). --}}
          <div id="wtInlay" style="position:absolute;left:50%;bottom:14px;transform:translateX(-50%);z-index:6;max-width:520px;width:92%;display:none;background:rgba(20,22,26,.92);color:#fff;border-radius:.5rem;padding:14px 16px;box-shadow:0 4px 16px rgba(0,0,0,.45);">
            <button type="button" id="inlayClose" class="btn-close btn-close-white" style="position:absolute;top:8px;right:10px;" aria-label="{{ __('Close') }}"></button>
            <h6 id="inlayTitle" class="fw-bold mb-1 pe-4"></h6>
            <p id="inlayDesc" class="small mb-2" style="max-height:120px;overflow:auto;"></p>
            <a id="inlayRec" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="fas fa-external-link-alt me-1"></i>{{ __('View full record') }} <span class="badge bg-secondary ms-1">V</span></a>
            <div id="wtRelated" class="mt-2" style="display:none;">
              <div class="small text-white-50 mb-1"><i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('You might also like') }}</div>
              <div id="wtRelatedItems" class="d-flex flex-wrap gap-1"></div>
            </div>
          </div>
          {{-- #1142 full-record overlay: view the record inside the walkthrough; Back returns to the gallery. --}}
          <div id="recOverlay" style="position:absolute;inset:0;z-index:20;display:none;background:rgba(10,12,15,.96);">
            <div style="position:absolute;top:8px;right:10px;z-index:21;display:flex;gap:6px;">
              <a id="recOpenTab" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light" title="{{ __('Open in new tab') }}"><i class="fas fa-external-link-alt"></i></a>
              <button type="button" id="recClose" class="btn btn-sm btn-warning fw-semibold" title="{{ __('Back to the gallery') }}"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to gallery') }}</button>
            </div>
            <iframe id="recFrame" title="{{ __('Full record') }}" style="position:absolute;inset:0;width:100%;height:100%;border:0;background:#fff;"></iframe>
          </div>
        </div>
        {{-- Walk-to navigator: click an object to travel to it. --}}
        <div id="roomNav" class="d-flex gap-1 p-2 overflow-auto border-top bg-light" style="white-space:nowrap;"></div>
      </div>
    </div>
  @endif


  @if($hasContent ?? (count($stops) > 0))
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/DRACOLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/PointerLockControls.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/webxr/VRButton.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script nonce="{{ $cspNonce ?? '' }}">
  (function () {
    var BUILDING = @json($building ?? null);
    var ROOMS = (BUILDING && BUILDING.rooms && BUILDING.rooms.length) ? BUILDING.rooms : null;
    var room = document.getElementById('room');
    var loading = document.getElementById('roomLoading');
    if (typeof THREE === 'undefined' || !THREE.PointerLockControls) {
      room.innerHTML = '<div class="p-4 text-light">{{ __('3D engine failed to load.') }}</div>';
      return;
    }
    if (!ROOMS) {
      ROOMS = [{ id: 0, name: '', w: 18, d: 14, h: 4, x_offset: 0, z_offset: -7, is_current: true,
        floorplan: @json($space->floorplan_image_path), stops: @json($stops), walls: @json($walls ?? []) }];
    }
    var PLAN_MODE = !!(BUILDING && BUILDING.plan_mode);
    if (window.pdfjsLib) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    // Flatten object stops (tag each with its room); compute building extents.
    var STOPS = [];
    ROOMS.forEach(function (rm) { (rm.stops || []).forEach(function (s) { s._room = rm; STOPS.push(s); }); });
    // Corridor objects: free-standing, positioned as a fraction of the building bbox.
    var CORRIDOR = (BUILDING && BUILDING.corridor) ? BUILDING.corridor : [];
    // Stack index: objects sharing a wall + (near) the same point cascade as layers (#1140).
    // Wall-hung objects are keyed by their along-wall u + height v (the actual spot); free/auto
    // objects by their floor position. Same bucket => they layer instead of z-fighting.
    (function () {
      var seen = {};
      STOPS.forEach(function (s) {
        var room = s._room ? s._room.id : 0, key;
        if (s.wall_u != null && s.wall_or_zone) {
          key = room + '|' + s.wall_or_zone + '|u' + Math.round(s.wall_u * 12) + '|v' + Math.round((s.wall_v != null ? s.wall_v : 0.5) * 12);
        } else {
          key = room + '|' + (s.wall_or_zone || 'auto') + '|' + Math.round((s.pos_x || 0) * 12) + '|' + Math.round((s.pos_y || 0) * 12);
        }
        s._stack = seen[key] || 0;
        seen[key] = s._stack + 1;
      });
    })();
    var WALL_H = (BUILDING && BUILDING.max_h) ? BUILDING.max_h : 4;
    var RH = WALL_H;   // per-room wall height, set as each room renders
    var FLOOR_H = (BUILDING && BUILDING.floor_height) ? BUILDING.floor_height : 4.5;   // #1169 vertical gap between floors
    // Map an unrotated room point to its actual world position (rooms can be
    // rotated about their centre). Matches the per-room group rotation.y = -rot.
    function roomWorld(rm, x, z) {
      var rot = (rm.rot || 0) * Math.PI / 180;
      if (!rot) return { x: x, z: z };
      var cx = rm.x_offset, cz = rm.z_offset;   // top-left pivot (matches plan-editor rotation origin)
      var px = x - cx, pz = z - cz, c = Math.cos(rot), s = Math.sin(rot);
      return { x: cx + px * c - pz * s, z: cz + px * s + pz * c };
    }
    // World point -> room-local offset from the room's top-left (inverse of roomWorld).
    function roomWorldInverse(rm, wx, wz) {
      var rot = (rm.rot || 0) * Math.PI / 180, dx = wx - rm.x_offset, dz = wz - rm.z_offset, c = Math.cos(rot), s = Math.sin(rot);
      return { px: dx * c + dz * s, pz: -dx * s + dz * c };
    }
    // Which room's footprint contains a world point (rotation-aware); null if none.
    function findRoomAtWorld(wx, wz, exclude, preferFloor) {
      // Prefer a room on a specific floor (preferFloor, e.g. a door's own floor) else the
      // floor we're on. When preferFloor is given it's STRICT - no cross-floor fallback -
      // so a door never sends you to a stacked room on another floor (upper gallery / basement).
      var strict = (preferFloor !== undefined && preferFloor !== null);
      var want = strict ? preferFloor : ((typeof curFloorY === 'number') ? Math.round(curFloorY / FLOOR_H) : null), fallback = null;
      for (var i = 0; i < ROOMS.length; i++) {
        var r = ROOMS[i]; if (r === exclude) continue;
        var p = roomWorldInverse(r, wx, wz);
        if (p.px >= -0.05 && p.px <= r.w + 0.05 && p.pz >= -0.05 && p.pz <= r.d + 0.05) {
          if (want === null || (r.floor || 0) === want) return r;
          if (!fallback) fallback = r;
        }
      }
      return strict ? null : fallback;
    }
    // Billboard text label on a small dark plaque (room signage / doorway labels).
    function makeTextSprite(text, scaleH) {
      var cv = document.createElement('canvas'), ctx = cv.getContext('2d');
      ctx.font = 'bold 30px sans-serif';
      var tw = Math.ceil(ctx.measureText(text).width) + 28;
      cv.width = tw; cv.height = 48;
      ctx = cv.getContext('2d'); ctx.font = 'bold 30px sans-serif';
      ctx.fillStyle = 'rgba(20,22,26,0.86)'; ctx.fillRect(0, 0, tw, 48);
      ctx.fillStyle = '#fff'; ctx.textBaseline = 'middle'; ctx.fillText(text, 14, 25);
      var spr = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(cv), depthTest: true, transparent: true }));
      var h = scaleH || 0.5; spr.scale.set(h * tw / 48, h, 1);
      return spr;
    }
    // A clickable "floor plan / map" plaque to mount on a wall (opens the minimap).
    function makeWallIcon() {
      var cv = document.createElement('canvas'); cv.width = 128; cv.height = 128;
      var x = cv.getContext('2d');
      x.fillStyle = '#0d6efd'; x.fillRect(0, 0, 128, 128);
      x.strokeStyle = '#fff'; x.lineWidth = 6;
      x.strokeRect(20, 18, 88, 64); x.beginPath(); x.moveTo(64, 18); x.lineTo(64, 82); x.moveTo(20, 50); x.lineTo(108, 50); x.stroke();
      x.fillStyle = '#fff'; x.font = 'bold 20px sans-serif'; x.textAlign = 'center'; x.fillText('MAP', 64, 112);
      return new THREE.Mesh(new THREE.PlaneGeometry(0.5, 0.5), new THREE.MeshBasicMaterial({ map: new THREE.CanvasTexture(cv) }));
    }
    var BLD_minX = Infinity, BLD_maxX = -Infinity, BLD_minZ = Infinity, BLD_maxZ = -Infinity;
    ROOMS.forEach(function (rm) {
      if (rm.z_offset === null || rm.z_offset === undefined) rm.z_offset = -rm.d / 2;
      [[rm.x_offset, rm.z_offset], [rm.x_offset + rm.w, rm.z_offset], [rm.x_offset, rm.z_offset + rm.d], [rm.x_offset + rm.w, rm.z_offset + rm.d]]
        .forEach(function (c) { var w = roomWorld(rm, c[0], c[1]); BLD_minX = Math.min(BLD_minX, w.x); BLD_maxX = Math.max(BLD_maxX, w.x); BLD_minZ = Math.min(BLD_minZ, w.z); BLD_maxZ = Math.max(BLD_maxZ, w.z); });
    });
    var curRoom = ROOMS.filter(function (r) { return r.is_current; })[0] || ROOMS[0];
    function roomAt(x, z) {
      for (var i = 0; i < ROOMS.length; i++) {
        var r = ROOMS[i];
        if (x >= r.x_offset - 0.01 && x <= r.x_offset + r.w + 0.01 && z >= r.z_offset - 0.01 && z <= r.z_offset + r.d + 0.01) return r;
      }
      var best = curRoom, bd = Infinity;
      ROOMS.forEach(function (r) { var dx = x - (r.x_offset + r.w / 2), dz = z - (r.z_offset + r.d / 2), dd = dx * dx + dz * dz; if (dd < bd) { bd = dd; best = r; } });
      return best;
    }
    var W = room.clientWidth || 800, H = room.clientHeight || 480;

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0x20242a);

    var camera = new THREE.PerspectiveCamera(70, W / H, 0.05, 400);
    (function () {
      // Start in the MIDDLE of the entry room (not against a wall).
      var sp = roomWorld(curRoom, curRoom.x_offset + curRoom.w / 2, curRoom.z_offset + curRoom.d / 2);
      camera.position.set(sp.x, 1.6, sp.z);
    })();

    var renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));   // cap retina overdraw
    renderer.setSize(W, H);
    renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;   // #shadows: real-time sun shadows (toggled)
    renderer.xr.enabled = true;   // heratio#1152 - WebXR / VR headset support
    room.appendChild(renderer.domElement);

    // VR button: only shown when the device/browser actually supports immersive-vr.
    var xrFloorY = 0;   // holder y while in XR (headset supplies eye height on top)
    if (THREE.VRButton && navigator.xr) {
      var vrBtn = THREE.VRButton.createButton(renderer);
      vrBtn.style.cssText += ';position:absolute;bottom:12px;left:50%;transform:translateX(-50%);z-index:8;';
      room.appendChild(vrBtn);
      renderer.xr.addEventListener('sessionstart', function () { var o = controls.getObject(); xrFloorY = o.position.y; o.position.y = 0; });   // drop to floor; headset adds height
      renderer.xr.addEventListener('sessionend', function () { controls.getObject().position.y = xrFloorY || 1.6; });
    }

    // Warm, restrained gallery lighting - pure-white at high intensity was washing floors/furniture out to white.
    var hemiLight = new THREE.HemisphereLight(0xfff3e2, 0x6a6258, 0.6);   // ref kept for the steal-alarm flicker
    scene.add(hemiLight);
    // #1170 outdoor: sky-blue backdrop + a sun when the building has an open-air space.
    if (BUILDING && BUILDING.has_outdoor) {
      scene.background = new THREE.Color(0x9fc8e8);
      hemiLight.intensity = 0.78; hemiLight.groundColor.setHex(0x6b8f4e);
      var sun = new THREE.DirectionalLight(0xfff6e0, 0.7); sun.position.set(20, 40, 15); scene.add(sun);
    }
    var dir = new THREE.DirectionalLight(0xfff0d8, 0.45);
    dir.position.set(5, 10, 7);
    scene.add(dir);

    // ---- Dynamic sun + shadows (#shadows / #arch-sun): a steerable sun that casts real-time shadows
    // through windows, arches and doorways. Off by default; the Sun HUD button cycles the time of day.
    // The shadow camera follows the visitor (updated in animate) so shadows stay sharp wherever you walk.
    var sunDyn = new THREE.DirectionalLight(0xfff4dc, 0.0);
    sunDyn.castShadow = true;
    sunDyn.shadow.mapSize.width = sunDyn.shadow.mapSize.height = 2048;
    sunDyn.shadow.camera.near = 0.5; sunDyn.shadow.camera.far = 120;
    sunDyn.shadow.camera.left = -22; sunDyn.shadow.camera.right = 22;
    sunDyn.shadow.camera.top = 22; sunDyn.shadow.camera.bottom = -22;
    sunDyn.shadow.bias = -0.0004;
    var sunTarget = new THREE.Object3D(); scene.add(sunTarget); sunDyn.target = sunTarget;
    scene.add(sunDyn);
    // Time-of-day sun offsets (relative to the visitor); low east -> high noon -> low west.
    var SUN_TIMES = [
      null,
      { off: [-30, 22, -10], col: 0xffe2b0, int: 1.15 },   // morning
      { off: [6, 46, 8], col: 0xfff6e0, int: 1.35 },        // noon
      { off: [30, 22, 12], col: 0xffd9a0, int: 1.1 },       // afternoon
    ];
    var sunMode = 0, _sunShadowsApplied = false;
    function applyShadowFlags() {
      if (_sunShadowsApplied) return; _sunShadowsApplied = true;
      scene.traverse(function (n) {
        if (!n.isMesh || !n.material) return;
        var m = n.material, transparent = m.transparent && m.opacity < 0.9;   // glass/panes don't block sun
        n.castShadow = !transparent; n.receiveShadow = true;
      });
    }
    function setSun(mode) {
      sunMode = ((mode % SUN_TIMES.length) + SUN_TIMES.length) % SUN_TIMES.length;
      var t = SUN_TIMES[sunMode];
      var b = document.getElementById('wtSunBtn');
      if (!t) { sunDyn.intensity = 0; if (b) { b.classList.remove('btn-warning'); b.classList.add('btn-dark'); } return; }
      applyShadowFlags();
      sunDyn.color.setHex(t.col); sunDyn.intensity = t.int;
      if (b) { b.classList.add('btn-warning'); b.classList.remove('btn-dark'); }
    }

    // Per-room containers: each room's geometry + objects live in a group placed
    // at the room centre and rotated by its plan rotation. Children are added at
    // (unrotatedWorld - centre); for rot=0 this is identical to adding to scene.
    var roomGroups = {}, _curRoom = null;
    function roomGroup(rm) {
      var k = rm.id;
      if (roomGroups[k]) return roomGroups[k];
      var cx = rm.x_offset, cz = rm.z_offset;   // top-left pivot (matches plan-editor rotation origin)
      var g = new THREE.Group();
      g.position.set(cx, (rm.floor || 0) * FLOOR_H, cz);   // #1169 lift upper floors
      g.rotation.y = -((rm.rot || 0) * Math.PI / 180);
      scene.add(g);
      var cen = roomWorld(rm, rm.x_offset + rm.w / 2, rm.z_offset + rm.d / 2);   // world centre, for distance culling
      return (roomGroups[k] = { g: g, cx: cx, cz: cz, cwx: cen.x, cwz: cen.z });
    }
    function addToRoom(rm, mesh) {
      if (!rm) { scene.add(mesh); return; }
      var rg = roomGroup(rm);
      mesh.position.x -= rg.cx; mesh.position.z -= rg.cz;
      rg.g.add(mesh);
    }
    // Texture loader that downscales oversized images (big TIFF-derived JPEGs) to
    // cap GPU memory + draw cost. Same 4-arg signature as THREE.TextureLoader.load.
    var MAXTEX = 1024, _texLoader = new THREE.TextureLoader();
    function loadTex(url, onLoad, onProgress, onError) {
      _texLoader.load(url, function (tex) {
        var img = tex.image;
        if (img && img.width && (img.width > MAXTEX || img.height > MAXTEX)) {
          // Downscale big images. Build a fresh CanvasTexture from the scaled canvas -
          // swapping tex.image on the loader's texture does not reliably re-upload to the
          // GPU (large ceiling/object images rendered blank). CanvasTexture is the correct
          // source type for a canvas and uploads cleanly.
          var s = MAXTEX / Math.max(img.width, img.height);
          var c = document.createElement('canvas');
          c.width = Math.max(1, Math.round(img.width * s)); c.height = Math.max(1, Math.round(img.height * s));
          c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          var ct = new THREE.CanvasTexture(c);
          ct.minFilter = THREE.LinearFilter; ct.magFilter = THREE.LinearFilter; ct.generateMipmaps = false;
          if (tex.encoding !== undefined) ct.encoding = tex.encoding;
          if (tex.colorSpace !== undefined) ct.colorSpace = tex.colorSpace;
          ct.needsUpdate = true;
          tex.dispose();
          if (onLoad) onLoad(ct);
          return;
        }
        tex.minFilter = THREE.LinearFilter; tex.magFilter = THREE.LinearFilter; tex.needsUpdate = true;
        if (onLoad) onLoad(tex);
      }, onProgress, onError);
    }

    // Per-room floors, perimeter walls (with doorways between rooms) and dividers.
    var wallMat = new THREE.MeshStandardMaterial({ map: wallTexture(), color: 0xffffff, roughness: 0.95, side: THREE.DoubleSide });
    var doorMat = new THREE.MeshStandardMaterial({ color: 0x7c6a58, roughness: 0.9, side: THREE.DoubleSide });   // solid door panel (not see-through)
    var glassMat = new THREE.MeshStandardMaterial({ color: 0xbcd6e6, transparent: true, opacity: 0.32, roughness: 0.1, metalness: 0.1, side: THREE.DoubleSide });   // #1172 window pane
    var colliders = [];   // wall meshes the visitor cannot walk through (doorways/glass excluded)
    var stairRamps = [];  // walkable sloped corridors: {ax,az,bx,bz,ya,yb,ux,uz,len,half}
    // #1171 door leaves on a polygon-edge opening (single/double/glass/sliding/ornate). Built in a
    // group whose local X runs along the edge and local Z is the wall normal; leaves swing about Y
    // (hinged) or slide along X (sliding), animated by proximity. Leaves are NOT colliders, so the
    // doorway is always walk-through - the swing/slide is visual.
    var frontDoorMat = new THREE.MeshStandardMaterial({ color: 0x5a3a22, roughness: 0.65, side: THREE.DoubleSide });
    var ornateDoorMat = new THREE.MeshStandardMaterial({ color: 0x6b4a2b, roughness: 0.45, metalness: 0.25, side: THREE.DoubleSide });
    var frontDoors = [];
    function makeEdgeDoor(rm, s, e, ax, az, ux, uz, ang, doorH, dest, type) {
      var W = e - s, mid = (s + e) / 2, mx = ax + ux * mid, mz = az + uz * mid;
      var leafH = doorH - 0.05, t = 0.08;
      var mat = (type === 'glass') ? glassMat : (type === 'ornate') ? ornateDoorMat : frontDoorMat;
      var slide = (type === 'sliding'), single = (type === 'single');
      var grp = new THREE.Group(); grp.position.set(mx, 0, mz); grp.rotation.y = -ang;
      var handleMat = new THREE.MeshStandardMaterial({ color: 0xd4af37, metalness: 0.75, roughness: 0.3 });
      var goldMat = new THREE.MeshStandardMaterial({ color: 0xc8a84b, metalness: 0.6, roughness: 0.35, side: THREE.DoubleSide });
      var leaves = [];
      // A real, joinery-style leaf: slab + recessed panels (both faces) + a brass handle.
      function leaf(hingeX, sign, lw) {
        var pivot = new THREE.Group(); pivot.position.set(hingeX, doorH / 2, 0);
        var g = new THREE.Group(); g.position.x = sign * lw / 2;
        g.add(new THREE.Mesh(new THREE.BoxGeometry(lw, leafH, t), mat));
        if (type !== 'glass') {
          var pw = lw * 0.62, ph = leafH * 0.36, pmat = (type === 'ornate') ? ornateDoorMat : mat;
          [0.25, -0.25].forEach(function (fy) {
            [t * 0.5, -t * 0.5].forEach(function (fz) {
              var pp = new THREE.Mesh(new THREE.BoxGeometry(pw, ph, t * 0.5), pmat); pp.position.set(0, fy * leafH, fz); g.add(pp);
              if (type === 'ornate') { var rim = new THREE.Mesh(new THREE.BoxGeometry(pw * 1.14, ph * 1.14, t * 0.46), goldMat); rim.position.set(0, fy * leafH, fz * 0.92); g.add(rim); }
            });
          });
        }
        [t * 0.72, -t * 0.72].forEach(function (fz) { var hn = new THREE.Mesh(new THREE.SphereGeometry(0.055, 12, 8), handleMat); hn.position.set(sign * lw * 0.38, -leafH * 0.02, fz); g.add(hn); });
        pivot.add(g); grp.add(pivot); leaves.push({ pivot: pivot, sign: sign, hingeX: hingeX, lw: lw });
      }
      if (single) { leaf(-W / 2, 1, Math.max(0.2, W - 0.06)); }
      else { var lw = Math.max(0.2, W / 2 - 0.03); leaf(-W / 2, 1, lw); leaf(W / 2, -1, lw); }
      var hit = new THREE.Mesh(new THREE.BoxGeometry(W, doorH, 0.14), new THREE.MeshBasicMaterial({ visible: false }));
      hit.position.set(0, doorH / 2, 0); grp.add(hit);
      if (dest) { hit.userData.action = 'door'; hit.userData.doorDest = dest; hit.userData.doorHome = rm; pickables.push(hit); }
      addToRoom(rm, grp);
      var wc = roomWorld(rm, mx, mz);
      frontDoors.push({ leaves: leaves, x: wc.x, z: wc.z, open: 0, slide: slide });
    }
    var DOOR = 1.6;   // doorway width between connected rooms
    // Default plaster ceiling + decorative crown-moulding (cornice) for rooms with no ceiling image.
    var ceilMat = new THREE.MeshStandardMaterial({ color: 0xf4f1ea, emissive: 0x55514b, emissiveIntensity: 1, roughness: 1, side: THREE.DoubleSide });   // self-lit so the down-facing plaster ceiling is always clearly visible
    var corniceMat = new THREE.MeshStandardMaterial({ color: 0xefe7d6, roughness: 0.8, side: THREE.DoubleSide });
    var corniceGoldMat = new THREE.MeshStandardMaterial({ color: 0xb89a5e, roughness: 0.5, metalness: 0.4, side: THREE.DoubleSide });
    function addCornice(rm, ax, az, bx, bz, topY, cenx, cenz) {
      var dx = bx - ax, dz = bz - az, len = Math.hypot(dx, dz); if (len < 0.2) return;
      var ang = Math.atan2(dz, dx), mx = (ax + bx) / 2, mz = (az + bz) / 2;
      var nx = -dz / len, nz = dx / len;                       // inward normal (toward room centre)
      if ((cenx - mx) * nx + (cenz - mz) * nz < 0) { nx = -nx; nz = -nz; }
      var depth = 0.16, hgt = 0.34, g = new THREE.Group();
      var band = new THREE.Mesh(new THREE.BoxGeometry(len, hgt, depth), corniceMat); g.add(band);
      var gold = new THREE.Mesh(new THREE.BoxGeometry(len, 0.05, depth + 0.02), corniceGoldMat);
      gold.position.set(0, -hgt / 2 + 0.05, 0.01); g.add(gold);
      g.position.set(mx + nx * depth / 2, topY - hgt / 2, mz + nz * depth / 2);
      g.rotation.y = -ang; addToRoom(rm, g);
    }
    function wallSeg(len, x, z, ry, mat) {
      if (len <= 0.05) return;
      var m = new THREE.Mesh(new THREE.PlaneGeometry(len, RH), mat || wallMat);
      m.position.set(x, RH / 2, z); m.rotation.y = ry; addToRoom(_curRoom, m);
      if (mat !== doorMat && mat !== glassMat) colliders.push(m);
    }
    // Partial-height wall piece between heights y0..y1 (used for door lintels).
    function wallSegH(len, x, z, ry, mat, y0, y1) {
      if (len <= 0.05 || (y1 - y0) <= 0.05) return null;
      var m = new THREE.Mesh(new THREE.PlaneGeometry(len, y1 - y0), mat || wallMat);
      m.position.set(x, (y0 + y1) / 2, z); m.rotation.y = ry; addToRoom(_curRoom, m);
      if (mat !== doorMat && mat !== glassMat && y0 < 1.7) colliders.push(m);   // body-height wall pieces only
      return m;
    }
    // #1176 step 1: auto-doorways for a polygon EDGE, mirroring doorsOnWall's auto rule so a room
    // behaves identically whether rendered via planWall (rect) or edgeWall (polygon). Axis-aligned
    // edges only (every current room shape is an axis-aligned rectangle); skew edges get no auto.
    // Returns edge-local [s,e] spans (distance along A->B).
    function autoEdgeOpenings(rm, ax, az, bx, bz, L) {
      var out = [], vert = Math.abs(ax - bx) < 0.15, horiz = Math.abs(az - bz) < 0.15;
      if (vert === horiz) return out;   // need exactly one of axis-aligned (skip skew + degenerate)
      var fixed = vert ? ax : az, lo = vert ? Math.min(az, bz) : Math.min(ax, bx), hi = vert ? Math.max(az, bz) : Math.max(ax, bx);
      ROOMS.forEach(function (rj) {
        if (rj === rm || (rj.floor || 0) !== (rm.floor || 0)) return;
        var jPMin = vert ? rj.x_offset : rj.z_offset, jPMax = vert ? rj.x_offset + rj.w : rj.z_offset + rj.d;
        if (Math.abs(jPMax - fixed) > 0.3 && Math.abs(jPMin - fixed) > 0.3) return;   // rj not on this plane
        var jLo = vert ? rj.z_offset : rj.x_offset, jHi = vert ? rj.z_offset + rj.d : rj.x_offset + rj.w;
        var oa = Math.max(lo, jLo), ob = Math.min(hi, jHi);
        if (ob - oa <= 0.8) return;   // not enough shared run for a doorway
        var mid = (oa + ob) / 2, dw = Math.min(DOOR, (ob - oa) * 0.7), w0 = mid - dw / 2, w1 = mid + dw / 2;
        var f0 = vert ? (w0 - az) / (bz - az) : (w0 - ax) / (bx - ax);   // -> fraction along the edge (handles reversed edge)
        var f1 = vert ? (w1 - az) / (bz - az) : (w1 - ax) / (bx - ax);
        out.push([Math.min(f0, f1) * L, Math.max(f0, f1) * L]);
      });
      return out;
    }
    // A polygon-edge wall (A->B unrotated world) with doorways cut for any door
    // whose edge index == eIdx, PLUS auto-doorways where a neighbour abuts. cx/cz = centroid.
    function edgeWall(rm, eIdx, ax, az, bx, bz, mat, ccx, ccz) {
      var dx = bx - ax, dz = bz - az, L = Math.hypot(dx, dz); if (L < 0.05) return;
      var ang = Math.atan2(dz, dx), ux = dx / L, uz = dz / L;
      var doorH = Math.min(RH - 0.3, 2.6);
      var manual = (rm.doors || []).filter(function (d) { return d.edge === eIdx; }).map(function (d) {
        var c = (d.pos == null ? 0.5 : d.pos) * L, hw = (d.width || 1.6) / 2, s = Math.max(0, c - hw), e = Math.min(L, c + hw);
        if (s < 0.2) s = 0; if (L - e < 0.2) e = L; return { s: s, e: e, type: d.type || 'open' };   // #1171 leaf style
      });
      var auto = [];   // manual-only doors: a door appears ONLY where the curator placed one (no auto-doorways)
      var raw = manual.concat(auto).filter(function (d) { return d.e - d.s > 0.15; }).sort(function (p, q) { return p.s - q.s; });
      var doors = []; raw.forEach(function (d) { var last = doors[doors.length - 1]; if (last && d.s <= last.e + 0.01) { last.e = Math.max(last.e, d.e); if (d.type !== 'open') last.type = d.type; } else doors.push({ s: d.s, e: d.e, type: d.type }); });
      function seg(s, e, y0, y1, m2) { var len = e - s; if (len <= 0.1 || y1 - y0 <= 0.05) return null; var um = m2 || mat || wallMat, mid = (s + e) / 2, mx = ax + ux * mid, mz = az + uz * mid; var m = new THREE.Mesh(new THREE.PlaneGeometry(len, y1 - y0), um); m.position.set(mx, (y0 + y1) / 2, mz); m.rotation.y = -ang; addToRoom(rm, m); if (um !== doorMat && um !== glassMat && y0 < 1.7) colliders.push(m); return m; }
      // #1172 windows on this polygon edge: punch glass openings into full segments.
      var winsE = (rm.windows || []).filter(function (w) { return typeof w.edge === 'number' && w.edge === eIdx; });
      function fullEdge(s, e) {
        if (e - s <= 0.1) return;
        var wins = winsE.map(function (w) { var c = (w.pos == null ? 0.5 : w.pos) * L; return { ws: Math.max(s, c - w.width / 2), we: Math.min(e, c + w.width / 2), sill: w.sill || 0.9, header: Math.min(RH - 0.1, (w.sill || 0.9) + (w.height || 1.3)) }; })
          .filter(function (w) { return w.we - w.ws > 0.2; }).sort(function (p, q) { return p.ws - q.ws; });
        if (!wins.length) { seg(s, e, 0, RH); return; }
        var c2 = s;
        wins.forEach(function (w) {
          if (w.ws > c2) seg(c2, w.ws, 0, RH);
          seg(w.ws, w.we, 0, w.sill);                    // wall below the sill
          seg(w.ws, w.we, w.header, RH);                 // wall above the header
          seg(w.ws, w.we, w.sill, w.header, glassMat);   // glass pane (see-through)
          c2 = w.we;
        });
        if (c2 < e) seg(c2, e, 0, RH);
      }
      // #1176: where a staircase crosses this edge, render see-through glass (non-collider) so the
      // stairs are visible + walk-through instead of dead-ending in a wall (ported from planWall).
      var stairGapsE = [];
      (function () {
        var vert = Math.abs(ax - bx) < 0.15, horiz = Math.abs(az - bz) < 0.15;
        if (vert === horiz) return;   // skew / degenerate edge: no stair gap
        var fixed = vert ? ax : az, lo = vert ? Math.min(az, bz) : Math.min(ax, bx), hi = vert ? Math.max(az, bz) : Math.max(ax, bx);
        stairOpenings.forEach(function (op) {
          if ((rm.floor || 0) !== (op.fromF || 0) && (rm.floor || 0) !== (op.toF || 0)) return;
          if (vert ? (fixed < op.x0 - 0.4 || fixed > op.x1 + 0.4) : (fixed < op.z0 - 0.4 || fixed > op.z1 + 0.4)) return;
          var s = Math.max(lo, vert ? op.z0 : op.x0), e = Math.min(hi, vert ? op.z1 : op.x1);
          if (e - s <= 0.3) return;
          s -= 0.3; e += 0.3;
          var f0 = vert ? (s - az) / (bz - az) : (s - ax) / (bx - ax), f1 = vert ? (e - az) / (bz - az) : (e - ax) / (bx - ax);
          stairGapsE.push([Math.max(0, Math.min(f0, f1)) * L, Math.min(1, Math.max(f0, f1)) * L]);
        });
      })();
      var openings = doors.map(function (d) { return { s: d.s, e: d.e, type: d.type, stair: false }; })
        .concat(stairGapsE.map(function (g) { return { s: g[0], e: g[1], stair: true }; }))
        .sort(function (p, q) { return p.s - q.s; });
      var cur = 0;
      openings.forEach(function (dd) {
        if (dd.e <= cur) return;
        if (dd.s > cur) fullEdge(cur, dd.s);
        if (dd.stair) { seg(dd.s, dd.e, 0, RH, glassMat); cur = Math.max(cur, dd.e); return; }   // see-through, walk-through stair slice
        seg(dd.s, dd.e, doorH, RH);          // lintel above the opening
        var mid = (dd.s + dd.e) / 2, mx = ax + ux * mid, mz = az + uz * mid;
        var nx = -uz, nz = ux; if ((ccx - mx) * nx + (ccz - mz) * nz > 0) { nx = -nx; nz = -nz; }   // outward normal
        var ow = roomWorld(rm, mx + nx * 0.5, mz + nz * 0.5), dest = findRoomAtWorld(ow.x, ow.z, rm, rm.floor || 0);   // door leads to a room on the SAME floor
        if (dd.type && dd.type !== 'open') {
          makeEdgeDoor(rm, dd.s, dd.e, ax, az, ux, uz, ang, doorH, dest, dd.type);   // #1171 swinging / sliding leaf
        } else {
          var sm = seg(dd.s, dd.e, 0, doorH, glassMat);  // default interior doorway: see-through glass (like the stair wall), walk-through
          var ginx = ccx - mx, ginz = ccz - mz, ginl = Math.hypot(ginx, ginz) || 1;   // inward (toward room centre)
          var dnm = makeTextSprite('{{ __('Door') }}', 0.2); dnm.position.set(mx + ginx / ginl * 0.14, doorH * 0.5, mz + ginz / ginl * 0.14); addToRoom(rm, dnm);   // "Door" floated off the panel so it does not clip
          if (sm && dest) { sm.userData.action = 'door'; sm.userData.doorDest = dest; sm.userData.doorHome = rm; pickables.push(sm); }   // click the door to jump into that room
        }
        if (dest && dest.name) {
          var lab = makeTextSprite('→ ' + dest.name, 0.3); lab.position.set(mx - nx * 0.35, doorH + 0.35, mz - nz * 0.35); addToRoom(rm, lab);
        }
        cur = Math.max(cur, dd.e);
      });
      if (cur < L) fullEdge(cur, L);
    }
    var pickables = [];   // declared before the room loop: the map plaques push into it
    // Live conservation overlay (heratio#1146): per-room status tint, toggled.
    var STATUS_COLOR = { ok: 0x2e7d32, warn: 0xf9a825, alert: 0xc62828, none: 0x9e9e9e };
    var roomTints = [];
    // #1170 outdoor space: grass ground + scattered trees + a park bench, no walls/ceiling.
    var pathMat = new THREE.MeshStandardMaterial({ color: 0xc9bd9a, roughness: 1 });
    var benchMat = new THREE.MeshStandardMaterial({ color: 0x7a5230, roughness: 0.9 });
    // --- Life-like grass + trees: canvas-painted textures, no external assets (#1170) ---
    var _grassTex = null, _treeTex = null, _tuftTex = null;
    function grassTexture() {
      if (_grassTex) return _grassTex;
      var c = document.createElement('canvas'); c.width = c.height = 256; var g = c.getContext('2d');
      g.fillStyle = '#5c8540'; g.fillRect(0, 0, 256, 256);
      for (var i = 0; i < 1100; i++) { var sh = Math.random(); g.fillStyle = 'rgba(' + ((55 + sh * 45) | 0) + ',' + ((105 + sh * 60) | 0) + ',' + ((45 + sh * 35) | 0) + ',' + (0.2 + Math.random() * 0.35) + ')'; g.fillRect(Math.random() * 256, Math.random() * 256, 2 + Math.random() * 2, 2 + Math.random() * 2); }
      for (var b = 0; b < 600; b++) { var bx = Math.random() * 256, by = Math.random() * 256, h = 3 + Math.random() * 6; g.strokeStyle = 'rgba(' + ((40 + Math.random() * 40) | 0) + ',' + ((120 + Math.random() * 70) | 0) + ',' + ((40 + Math.random() * 30) | 0) + ',0.6)'; g.lineWidth = 1; g.beginPath(); g.moveTo(bx, by); g.lineTo(bx + (Math.random() * 2 - 1), by - h); g.stroke(); }
      var t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; _grassTex = t; return t;
    }
    // Simple stylised human silhouette (man / woman) on a transparent canvas - used for billboard
    // figures (furniture kinds person-man/person-woman) and the figure-pointer overlay.
    var _personTex = {};
    function personCanvas(kind) {
      var c = document.createElement('canvas'); c.width = 128; c.height = 256; var g = c.getContext('2d');
      var skin = '#caa07a', hair = '#3a2a1a', cloth = (kind === 'woman') ? '#7a3b6b' : '#36506e';
      g.fillStyle = cloth; g.fillRect(33, 78, 12, 70); g.fillRect(83, 78, 12, 70);   // arms
      if (kind === 'woman') { g.beginPath(); g.moveTo(46, 74); g.lineTo(82, 74); g.lineTo(99, 206); g.lineTo(29, 206); g.closePath(); g.fill(); }
      else { g.fillRect(45, 74, 38, 92); g.fillStyle = '#2c3e50'; g.fillRect(47, 162, 16, 84); g.fillRect(65, 162, 16, 84); }
      g.fillStyle = skin; g.beginPath(); g.arc(64, 46, 22, 0, Math.PI * 2); g.fill();   // head
      g.fillStyle = hair; g.beginPath(); g.arc(64, 42, 23, Math.PI, 2 * Math.PI); g.fill();   // hair cap
      if (kind === 'woman') { g.beginPath(); g.moveTo(41, 42); g.quadraticCurveTo(40, 78, 50, 80); g.lineTo(54, 60); g.closePath(); g.fill(); g.beginPath(); g.moveTo(87, 42); g.quadraticCurveTo(88, 78, 78, 80); g.lineTo(74, 60); g.closePath(); g.fill(); }
      return c;
    }
    function personTexture(kind) {
      if (_personTex[kind]) return _personTex[kind];
      var t = new THREE.CanvasTexture(personCanvas(kind)); t.minFilter = THREE.LinearFilter;
      return (_personTex[kind] = t);
    }
    function treeTexture() {
      if (_treeTex) return _treeTex;
      var c = document.createElement('canvas'); c.width = 256; c.height = 384; var g = c.getContext('2d');
      g.fillStyle = '#6b4a2b'; g.beginPath(); g.moveTo(119, 384); g.lineTo(137, 384); g.lineTo(132, 215); g.lineTo(124, 215); g.closePath(); g.fill();
      g.strokeStyle = '#6b4a2b'; g.lineWidth = 6; g.lineCap = 'round'; g.beginPath(); g.moveTo(128, 250); g.lineTo(98, 208); g.moveTo(128, 238); g.lineTo(162, 202); g.stroke();
      var greens = [['#8bc34a', '#3f7d2f'], ['#aed581', '#4a8c3a'], ['#689f38', '#33611f']];
      function blob(cx, cy, r, col) { var rg = g.createRadialGradient(cx - r * 0.3, cy - r * 0.35, r * 0.2, cx, cy, r); rg.addColorStop(0, col[0]); rg.addColorStop(1, col[1]); g.fillStyle = rg; g.beginPath(); g.arc(cx, cy, r, 0, Math.PI * 2); g.fill(); }
      [[128, 150, 72], [88, 172, 52], [168, 168, 52], [108, 108, 50], [150, 106, 50], [128, 86, 46], [128, 202, 58]].forEach(function (bl, i) { blob(bl[0], bl[1], bl[2], greens[i % greens.length]); });
      for (var i = 0; i < 500; i++) { var a = Math.random() * Math.PI * 2, rr = Math.random() * 78; var x = 128 + Math.cos(a) * rr, y = 150 + Math.sin(a) * rr * 1.15; if (y > 245 || y < 30) continue; g.fillStyle = 'rgba(' + ((70 + Math.random() * 90) | 0) + ',' + ((140 + Math.random() * 80) | 0) + ',' + ((45 + Math.random() * 45) | 0) + ',' + (0.35 + Math.random() * 0.4) + ')'; g.fillRect(x, y, 2, 2); }
      var t = new THREE.CanvasTexture(c); _treeTex = t; return t;
    }
    function tuftTexture() {
      if (_tuftTex) return _tuftTex;
      var c = document.createElement('canvas'); c.width = c.height = 64; var g = c.getContext('2d');
      for (var i = 0; i < 22; i++) { var bx = 10 + Math.random() * 44, h = 18 + Math.random() * 30; g.strokeStyle = 'rgba(' + ((50 + Math.random() * 40) | 0) + ',' + ((120 + Math.random() * 80) | 0) + ',' + ((40 + Math.random() * 35) | 0) + ',0.9)'; g.lineWidth = 1.5; g.beginPath(); g.moveTo(bx, 64); g.quadraticCurveTo(bx + (Math.random() * 10 - 5), 64 - h * 0.6, bx + (Math.random() * 14 - 7), 64 - h); g.stroke(); }
      var t = new THREE.CanvasTexture(c); _tuftTex = t; return t;
    }
    // Polished marble floor (cream field + soft clouds + grey veins). Tiled ~4 m. Glossy material.
    var _marbleTex = null, _wallTex = null;
    function marbleTexture() {
      if (_marbleTex) return _marbleTex;
      var N = 768, c = document.createElement('canvas'); c.width = c.height = N; var g = c.getContext('2d');
      // Warm off-white base with broad tonal drift (large soft patches, some darker, some lighter).
      g.fillStyle = '#d6c9a6'; g.fillRect(0, 0, N, N);   // warm cream/eggshell - a natural marble white, not stark (the floor read too bright)
      for (var b = 0; b < 9; b++) { var bx = Math.random() * N, by = Math.random() * N, br = N * 0.3 + Math.random() * N * 0.4; var bg = g.createRadialGradient(bx, by, 0, bx, by, br); var dk = Math.random() < 0.5; bg.addColorStop(0, dk ? 'rgba(140,130,106,0.14)' : 'rgba(238,231,210,0.08)'); bg.addColorStop(1, 'rgba(0,0,0,0)'); g.fillStyle = bg; g.fillRect(0, 0, N, N); }
      // Bright crystalline glints.
      for (var i = 0; i < 60; i++) { var x = Math.random() * N, y = Math.random() * N, r = 30 + Math.random() * 120; var rg = g.createRadialGradient(x, y, 0, x, y, r); rg.addColorStop(0, 'rgba(243,236,216,' + (0.02 + Math.random() * 0.05) + ')'); rg.addColorStop(1, 'rgba(243,236,216,0)'); g.fillStyle = rg; g.beginPath(); g.arc(x, y, r, 0, Math.PI * 2); g.fill(); }
      // A meandering vein that occasionally branches and tapers - the hallmark of real marble.
      function vein(px, py, len, w, alpha, col) {
        g.strokeStyle = col + alpha + ')'; g.lineWidth = w; g.lineJoin = 'round'; g.beginPath(); g.moveTo(px, py);
        var ang = Math.random() * Math.PI * 2;
        for (var s = 0; s < len; s++) { ang += (Math.random() - 0.5) * 0.9; px += Math.cos(ang) * 16; py += Math.sin(ang) * 16; g.lineTo(px, py); if (Math.random() < 0.08 && w > 0.6) vein(px, py, (len - s) * 0.6 | 0, w * 0.5, (alpha * 0.8).toFixed(2), col); }
        g.stroke();
      }
      for (var v = 0; v < 5; v++) vein(Math.random() * N, Math.random() * N, 26, 1.6 + Math.random() * 1.8, (0.18 + Math.random() * 0.16).toFixed(2), 'rgba(86,78,66,');   // bold dark primaries (contrast)
      for (var h = 0; h < 26; h++) vein(Math.random() * N, Math.random() * N, 14, 0.4 + Math.random() * 1.2, (0.08 + Math.random() * 0.12).toFixed(2), 'rgba(120,112,100,');   // fine grey hairlines
      // Grout seam around the tile edge: the texture maps to 2m (repeat 0.5 over the metre-based floor UV),
      // so this border lands on a clean 2m x 2m grid (half from each neighbouring tile meets at the seam).
      g.strokeStyle = 'rgba(120,110,92,0.55)'; g.lineWidth = 12; g.strokeRect(0, 0, N, N);
      var t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(0.5, 0.5); _marbleTex = t; return t;   // 1 tile = 2 metres
    }
    // Warm textured plaster wall (taupe with fine mottling) - the default look when no wall image.
    function wallTexture() {
      if (_wallTex) return _wallTex;
      var c = document.createElement('canvas'); c.width = c.height = 256; var g = c.getContext('2d');
      g.fillStyle = '#b3a187'; g.fillRect(0, 0, 256, 256);
      for (var i = 0; i < 4200; i++) { var sh = (Math.random() - 0.5) * 26; g.fillStyle = 'rgba(' + ((179 + sh) | 0) + ',' + ((161 + sh) | 0) + ',' + ((135 + sh) | 0) + ',0.5)'; g.fillRect(Math.random() * 256, Math.random() * 256, 2, 2); }
      var t = new THREE.CanvasTexture(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; _wallTex = t; return t;
    }
    // Crossed-plane billboard tree (volumetric from any angle), slight size/spin variance.
    function addTree(rm, x, z) {
      var s = 2.6 + Math.random() * 1.8, w = s, h = s * 1.55;
      var mat = new THREE.MeshStandardMaterial({ map: treeTexture(), transparent: false, alphaTest: 0.5, side: THREE.DoubleSide, roughness: 1 });
      var g = new THREE.Group();
      var p1 = new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat); p1.position.y = h / 2; g.add(p1);
      var p2 = new THREE.Mesh(new THREE.PlaneGeometry(w, h), mat); p2.position.y = h / 2; p2.rotation.y = Math.PI / 2; g.add(p2);
      g.position.set(x, 0, z); g.rotation.y = Math.random() * Math.PI; addToRoom(rm, g);
    }
    function addTuft(rm, x, z) {
      var sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tuftTexture(), transparent: true, alphaTest: 0.3, depthWrite: false }));
      var s = 0.4 + Math.random() * 0.5; sp.scale.set(s, s, s); sp.position.set(x, s / 2, z); addToRoom(rm, sp);
    }
    function addBench(rm, x, z, ry) {
      var g = new THREE.Group();
      g.add(new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.1, 0.5), benchMat));   // seat
      var back = new THREE.Mesh(new THREE.BoxGeometry(1.6, 0.5, 0.08), benchMat); back.position.set(0, 0.3, -0.21); g.add(back);
      [-0.7, 0.7].forEach(function (lx) { var lg = new THREE.Mesh(new THREE.BoxGeometry(0.1, 0.45, 0.45), benchMat); lg.position.set(lx, -0.27, 0); g.add(lg); });
      g.position.set(x, 0.5, z); g.rotation.y = ry || 0;
      addToRoom(rm, g);   // addToRoom re-bases x/z into the room group; child positions stay local to g
    }
    // Furniture & fittings: a placed prop (bench/pedestal/case/planter/table/chair/railing) at floor-fraction.
    function addFurniturePiece(rm, it) {
      var x = rm.x_offset + (it.pos_x == null ? 0.5 : it.pos_x) * rm.w;
      var z = rm.z_offset + (it.pos_y == null ? 0.5 : it.pos_y) * rm.d;
      var g = new THREE.Group(); g.position.set(x, 0, z); g.rotation.y = -(it.rotation_deg || 0) * Math.PI / 180;
      var sc = it.scale || 1;
      // Pillars take their HEIGHT from scale (3m base x scale, ~0.9-12m), keeping a fixed cross-section,
      // so the slider raises/lowers the column instead of fattening it. Uploaded assets handle their own
      // scaling (fit-to-box x scale). Other furniture scales uniformly via the group.
      var isPillar = (it.kind === 'pillar-round' || it.kind === 'pillar-square');
      var noGroupScale = isPillar || !!it.asset_path;
      g.scale.set(noGroupScale ? 1 : sc, noGroupScale ? 1 : sc, noGroupScale ? 1 : sc);
      // ---- Uploaded custom furniture (3D model or image from the library) ----
      if (it.asset_path) {
        var aext = (it.asset_ext || '').toLowerCase();
        if (aext === 'jpg' || aext === 'jpeg' || aext === 'png' || aext === 'webp') {
          loadTex(it.asset_path, function (tex) {
            var aspect = (tex.image && tex.image.width ? tex.image.width : 1) / (tex.image && tex.image.height ? tex.image.height : 1);
            var hgt = 1.6 * sc, wid = hgt * aspect;
            var pm = new THREE.MeshStandardMaterial({ map: tex, transparent: true, alphaTest: 0.4, side: THREE.DoubleSide, roughness: 1 });
            var pl = new THREE.Mesh(new THREE.PlaneGeometry(wid, hgt), pm); pl.position.y = hgt / 2; g.add(pl);
          });
        } else {
          loadModel(it.asset_path, aext, function (obj) {
            var pivot = new THREE.Group(); pivot.add(obj);
            var box = new THREE.Box3().setFromObject(pivot), size = box.getSize(new THREE.Vector3());
            var maxd = Math.max(size.x, size.y, size.z) || 1;
            pivot.scale.setScalar((1.5 / maxd) * sc); pivot.updateMatrixWorld(true);
            box = new THREE.Box3().setFromObject(pivot); var c = box.getCenter(new THREE.Vector3());
            pivot.position.x += -c.x; pivot.position.z += -c.z; pivot.position.y += -box.min.y;   // centre on floor
            g.add(pivot);
          });
        }
        addToRoom(rm, g);
        return;
      }
      var wood = new THREE.MeshStandardMaterial({ color: 0x6b4a2b, roughness: 0.8 });
      var stone = new THREE.MeshStandardMaterial({ color: 0xe8e2d4, roughness: 0.9 });
      var metal = new THREE.MeshStandardMaterial({ color: 0x9a9b9d, metalness: 0.7, roughness: 0.35 });
      var green = new THREE.MeshStandardMaterial({ color: 0x3f7d2f, roughness: 0.9 });
      function box(w, h, d, m, px, py, pz) { var b = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), m); b.position.set(px, py, pz); g.add(b); return b; }
      function cyl(rt, rb, h, m, px, py, pz) { var c = new THREE.Mesh(new THREE.CylinderGeometry(rt, rb, h, 16), m); c.position.set(px, py, pz); g.add(c); return c; }
      switch (it.kind) {
        case 'bench': box(1.6, 0.1, 0.5, wood, 0, 0.45, 0); box(1.6, 0.5, 0.08, wood, 0, 0.7, -0.21); [-0.7, 0.7].forEach(function (lx) { box(0.1, 0.45, 0.45, wood, lx, 0.22, 0); }); break;
        case 'pedestal': box(0.5, 1.1, 0.5, stone, 0, 0.55, 0); box(0.62, 0.06, 0.62, stone, 0, 1.11, 0); break;
        case 'case': box(0.8, 0.9, 0.8, wood, 0, 0.45, 0); var vc = new THREE.Mesh(new THREE.BoxGeometry(0.74, 0.9, 0.74), glassMat); vc.position.set(0, 1.35, 0); g.add(vc); box(0.84, 0.05, 0.84, metal, 0, 1.82, 0); break;
        case 'planter': cyl(0.28, 0.34, 0.5, stone, 0, 0.25, 0); var fol = new THREE.Mesh(new THREE.SphereGeometry(0.42, 12, 10), green); fol.position.set(0, 0.92, 0); fol.scale.set(1, 1.2, 1); g.add(fol); break;
        case 'table': box(1.2, 0.06, 0.7, wood, 0, 0.74, 0); [[-0.5, -0.28], [0.5, -0.28], [-0.5, 0.28], [0.5, 0.28]].forEach(function (p) { box(0.07, 0.74, 0.07, wood, p[0], 0.37, p[1]); }); break;
        case 'chair': box(0.45, 0.06, 0.45, wood, 0, 0.45, 0); box(0.45, 0.5, 0.06, wood, 0, 0.7, -0.2); [[-0.18, -0.18], [0.18, -0.18], [-0.18, 0.18], [0.18, 0.18]].forEach(function (p) { box(0.05, 0.45, 0.05, wood, p[0], 0.22, p[1]); }); break;
        case 'railing': {
          // Stanchion-and-rope barrier. Poles either follow explicit per-pole offsets (it.poles, set by
          // dragging them in the Builder) or are evenly spaced by count (it.segments). The rope between
          // consecutive poles drapes in a sagging curve, not a straight bar.
          var ropeMat = new THREE.MeshStandardMaterial({ color: 0x8a1f2b, roughness: 0.8 });
          var pts = [];
          if (it.poles && it.poles.length >= 2) { it.poles.forEach(function (p) { pts.push({ x: p.x, z: p.z }); }); }
          else { var n = Math.max(2, Math.min(20, it.segments || 2)), span = 1.4, x0 = -(n - 1) * span / 2; for (var pi = 0; pi < n; pi++) pts.push({ x: x0 + pi * span, z: 0 }); }
          pts.forEach(function (p) {
            cyl(0.04, 0.04, 0.95, metal, p.x, 0.475, p.z);
            var ball = new THREE.Mesh(new THREE.SphereGeometry(0.06, 10, 8), metal); ball.position.set(p.x, 0.98, p.z); g.add(ball);
          });
          for (var ri = 0; ri < pts.length - 1; ri++) {
            var a = pts[ri], b = pts[ri + 1];
            var curve = new THREE.QuadraticBezierCurve3(new THREE.Vector3(a.x, 0.86, a.z), new THREE.Vector3((a.x + b.x) / 2, 0.42, (a.z + b.z) / 2), new THREE.Vector3(b.x, 0.86, b.z));
            g.add(new THREE.Mesh(new THREE.TubeGeometry(curve, 16, 0.03, 6, false), ropeMat));
          }
          break;
        }
        // Architectural columns with a base + capital (classical look). Height = 3m x scale (adjustable).
        case 'pillar-round': { var ph = 3.0 * sc; cyl(0.3, 0.34, ph, stone, 0, ph / 2, 0); box(0.82, 0.12, 0.82, stone, 0, 0.06, 0); box(0.78, 0.16, 0.78, stone, 0, ph - 0.08, 0); break; }
        case 'pillar-square': { var ph2 = 3.0 * sc; box(0.5, ph2, 0.5, stone, 0, ph2 / 2, 0); box(0.78, 0.12, 0.78, stone, 0, 0.06, 0); box(0.74, 0.16, 0.74, stone, 0, ph2 - 0.08, 0); break; }
        // Billboard people (crossed planes so they read from any angle).
        case 'person-man': case 'person-woman': {
          var pkind = (it.kind === 'person-woman') ? 'woman' : 'man', pth = 1.75, ptw = pth * 0.42;
          var pmat = new THREE.MeshStandardMaterial({ map: personTexture(pkind), transparent: true, alphaTest: 0.5, side: THREE.DoubleSide, roughness: 1 });
          var pm1 = new THREE.Mesh(new THREE.PlaneGeometry(ptw, pth), pmat); pm1.position.y = pth / 2; g.add(pm1);
          var pm2 = pm1.clone(); pm2.rotation.y = Math.PI / 2; g.add(pm2);
          break;
        }
        // Free-standing archway: two piers + a semicircular arch (sunlight + shadows pass through the opening).
        case 'arch': {
          var aw = 1.9, pierW = 0.3, depth = 0.42, rA = aw / 2 - pierW / 2, legH = 1.9;
          [-(rA), rA].forEach(function (axp) { box(pierW, legH, depth, stone, axp, legH / 2, 0); });
          var ring = new THREE.Mesh(new THREE.TorusGeometry(rA, pierW / 2, 8, 24, Math.PI), stone);
          ring.position.set(0, legH, 0); g.add(ring);
          break;
        }
        default: box(0.5, 1.0, 0.5, stone, 0, 0.5, 0);
      }
      addToRoom(rm, g);
    }
    function renderOutdoor(rm) {
      var SH = (rm.shape && rm.shape.length >= 3) ? rm.shape : null;
      var gtex = grassTexture().clone(); gtex.needsUpdate = true; gtex.wrapS = gtex.wrapT = THREE.RepeatWrapping;
      var grassMat = new THREE.MeshStandardMaterial({ map: gtex, roughness: 1 });
      var ground;
      var gHoles = holesFor(rm);   // #1169 stairwell openings in the grass
      if (SH) {   // grass follows the room's polygon shape (so shaping the park works)
        var gs = new THREE.Shape();
        SH.forEach(function (p, j) { var px = p.x * rm.w, pz = p.z * rm.d; if (j === 0) gs.moveTo(px, pz); else gs.lineTo(px, pz); });
        gs.closePath(); addHoles(gs, gHoles);
        gtex.repeat.set(0.3, 0.3);   // ShapeGeometry UVs are in metres
        ground = new THREE.Mesh(new THREE.ShapeGeometry(gs), grassMat);
        ground.rotation.x = Math.PI / 2; ground.position.set(rm.x_offset, 0.01, rm.z_offset);
      } else if (gHoles.length) {
        gtex.repeat.set(0.3, 0.3);
        ground = new THREE.Mesh(shapeWithHoles([[0, 0], [rm.w, 0], [rm.w, rm.d], [0, rm.d]], gHoles), grassMat);
        ground.rotation.x = Math.PI / 2; ground.position.set(rm.x_offset, 0.01, rm.z_offset);
      } else {
        gtex.repeat.set(rm.w * 0.3, rm.d * 0.3);   // PlaneGeometry UVs are 0..1
        ground = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), grassMat);
        ground.rotation.x = -Math.PI / 2; ground.position.set(rm.x_offset + rm.w / 2, 0.01, rm.z_offset + rm.d / 2);
      }
      addToRoom(rm, ground);
      var path = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(2.2, rm.w * 0.3), rm.d), pathMat);
      path.rotation.x = -Math.PI / 2; path.position.set(rm.x_offset + rm.w / 2, 0.02, rm.z_offset + rm.d / 2); addToRoom(rm, path);
      var ox = rm.x_offset, oz = rm.z_offset, W = rm.w, D = rm.d;
      // trees clustered around the edges (clear of the central path)
      [[0.12, 0.16], [0.30, 0.12], [0.70, 0.13], [0.86, 0.18], [0.10, 0.62], [0.14, 0.86], [0.50, 0.92], [0.88, 0.6], [0.86, 0.85], [0.50, 0.07]]
        .forEach(function (s) { addTree(rm, ox + W * s[0], oz + D * s[1]); });
      addBench(rm, ox + W * 0.35, oz + D * 0.5, 0); addBench(rm, ox + W * 0.65, oz + D * 0.5, Math.PI);
      // scattered grass tufts for ground-level detail
      var n = Math.min(70, Math.max(24, Math.round(W * D * 0.12)));
      for (var i = 0; i < n; i++) { addTuft(rm, ox + W * (0.05 + Math.random() * 0.9), oz + D * (0.05 + Math.random() * 0.9)); }
    }
    // #1169 stairwell openings: a hole in the floor (or grass) above each inter-floor
    // staircase, so descending stairs are visible + walkable instead of hidden under a solid floor.
    var stairOpenings = [];
    (BUILDING && BUILDING.stairs ? BUILDING.stairs : []).forEach(function (st) {
      var from = st.from_floor || 0, to = (st.to_floor == null ? 1 : st.to_floor);
      if (from === to) return;
      var w = st.width || 1.4, legLen = st.length || 3, h = (st.hand === 'left') ? -1 : 1, legLen2 = st.length2 || legLen;
      var pts = (st.kind === 'elbow')
        ? [[-w / 2, 0], [w / 2, 0], [-w / 2, legLen], [w / 2, legLen], [h * (w / 2 + legLen2), legLen - w / 2], [h * (w / 2 + legLen2), legLen + w / 2]]
        : [[-w / 2, -legLen / 2], [w / 2, -legLen / 2], [-w / 2, legLen / 2], [w / 2, legLen / 2]];
      var a = (st.rot || 0) * Math.PI / 180, ca = Math.cos(a), sa = Math.sin(a), mnx = 1e9, mxx = -1e9, mnz = 1e9, mxz = -1e9;
      pts.forEach(function (p) { var wx = st.x + p[0] * ca + p[1] * sa, wz = st.z - p[0] * sa + p[1] * ca; if (wx < mnx) mnx = wx; if (wx > mxx) mxx = wx; if (wz < mnz) mnz = wz; if (wz > mxz) mxz = wz; });
      stairOpenings.push({ floor: Math.max(from, to), fromF: from, toF: to, x0: mnx, x1: mxx, z0: mnz, z1: mxz });
    });
    function holesFor(rm) {
      var out = [], rf = rm.floor || 0;
      stairOpenings.forEach(function (op) {
        if (op.floor !== rf) return;
        var x0 = Math.max(0.05, op.x0 - rm.x_offset), x1 = Math.min(rm.w - 0.05, op.x1 - rm.x_offset);
        var z0 = Math.max(0.05, op.z0 - rm.z_offset), z1 = Math.min(rm.d - 0.05, op.z1 - rm.z_offset);
        if (x1 - x0 > 0.3 && z1 - z0 > 0.3) out.push({ x0: x0, x1: x1, z0: z0, z1: z1 });
      });
      return out;
    }
    function ceilingHolesFor(rm) {   // a stair coming up FROM this room pierces its ceiling (the floor above)
      var out = [], af = (rm.floor || 0) + 1;
      stairOpenings.forEach(function (op) {
        if (op.floor !== af) return;
        var x0 = Math.max(0.05, op.x0 - rm.x_offset), x1 = Math.min(rm.w - 0.05, op.x1 - rm.x_offset);
        var z0 = Math.max(0.05, op.z0 - rm.z_offset), z1 = Math.min(rm.d - 0.05, op.z1 - rm.z_offset);
        if (x1 - x0 > 0.3 && z1 - z0 > 0.3) out.push({ x0: x0, x1: x1, z0: z0, z1: z1 });
      });
      return out;
    }
    function addHoles(shape, holes) { holes.forEach(function (hp) { var path = new THREE.Path(); path.moveTo(hp.x0, hp.z0); path.lineTo(hp.x1, hp.z0); path.lineTo(hp.x1, hp.z1); path.lineTo(hp.x0, hp.z1); path.closePath(); shape.holes.push(path); }); return shape; }
    function shapeWithHoles(outline, holes) { var s = new THREE.Shape(); outline.forEach(function (p, i) { if (i === 0) s.moveTo(p[0], p[1]); else s.lineTo(p[0], p[1]); }); s.closePath(); return new THREE.ShapeGeometry(addHoles(s, holes)); }
    ROOMS.forEach(function (rm, i) {
      var cx = rm.x_offset + rm.w / 2, cz = rm.z_offset + rm.d / 2;
      RH = rm.h || WALL_H;   // this room's wall height (per-room, not building-wide)
      _curRoom = rm;         // wallSeg/wallSegH add into this room's (possibly rotated) group
      (rm.furniture || []).forEach(function (it) { addFurniturePiece(rm, it); });   // furniture & fittings (indoor + outdoor)
      if (rm.is_outdoor) { renderOutdoor(rm); return; }   // #1170 open-air: no walls/ceiling/dividers
      // Decorated/painted wall material (#wall-pictures): each edge can carry its OWN image
      // (rm.wall_images[edge]); otherwise the room's all-walls default (rm.wall_image); else plain
      // plaster. Materials are cached per image path so a shared image is uploaded once.
      var _wmCache = {};
      function wallMaterial(edge) {
        var imgs = rm.wall_images || {};
        var path = (imgs[edge] != null) ? imgs[edge] : (rm.wall_image || null);
        if (path) {
          if (_wmCache[path]) return _wmCache[path];
          var m = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 1, side: THREE.DoubleSide });
          loadTex(path, function (tex) { m.map = tex; m.needsUpdate = true; });
          _wmCache[path] = m; return m;
        }
        // No image: fall back to a painted solid colour (per-edge, then all-walls), else plaster.
        var cols = rm.wall_colors || {};
        var col = (cols[edge] != null) ? cols[edge] : (rm.wall_color || null);
        if (col) { var ck = 'c:' + col; if (_wmCache[ck]) return _wmCache[ck]; var cm = new THREE.MeshStandardMaterial({ color: col, roughness: 0.9, side: THREE.DoubleSide }); _wmCache[ck] = cm; return cm; }
        return wallMat;
      }
      var rwMat = wallMaterial(-1);   // -1 = no edge override => all-walls default (used by auto-row branch)
      // #1176: every plan-mode room renders through the polygon/edge path. A room with no explicit
      // shape falls back to a unit rectangle so planWall is never needed (auto-row buildings stay null).
      var SHAPE = (rm.shape && rm.shape.length >= 3) ? rm.shape : (PLAN_MODE ? [{ x: 0, z: 0 }, { x: 1, z: 0 }, { x: 1, z: 1 }, { x: 0, z: 1 }] : null);
      if (SHAPE) {
        // ---- Custom polygon footprint: floor + ceiling + a wall per edge ----
        var shp = new THREE.Shape();
        SHAPE.forEach(function (p, j) { var px = p.x * rm.w, pz = p.z * rm.d; if (j === 0) shp.moveTo(px, pz); else shp.lineTo(px, pz); });
        shp.closePath();
        addHoles(shp, holesFor(rm));   // #1169 stairwell openings
        // Floor picture priority: an uploaded floor_image (stretched photo) > floor-plan tracing > polished marble.
        var floorPicP = rm.floor_image || rm.floorplan;
        var fmatP = floorPicP
          ? new THREE.MeshStandardMaterial({ color: 0x8a8f96, roughness: rm.floor_image ? 0.4 : 0.95, side: THREE.DoubleSide })
          : new THREE.MeshStandardMaterial({ map: marbleTexture(), color: 0xdccdab, roughness: 0.5, side: THREE.DoubleSide });
        var flP = new THREE.Mesh(new THREE.ShapeGeometry(shp), fmatP);
        flP.rotation.x = Math.PI / 2; flP.position.set(rm.x_offset, 0, rm.z_offset); addToRoom(rm, flP);
        // ShapeGeometry UVs are in metres (0..rm.w / 0..rm.d), so map one stretched copy over the whole floor.
        // ShapeGeometry UV is in metres: an uploaded floor image TILES at 2m (like the marble tiles); a floorplan tracing stretches once.
        if (floorPicP) { loadTex(floorPicP, function (tex) { tex.wrapS = tex.wrapT = THREE.RepeatWrapping; tex.repeat.set(rm.floor_image ? 0.5 : 1 / rm.w, rm.floor_image ? 0.5 : 1 / rm.d); fmatP.map = tex; fmatP.color.set(0xffffff); fmatP.needsUpdate = true; }); }
        if (rm.ceiling) {
          var cmatP = new THREE.MeshBasicMaterial({ side: THREE.DoubleSide });
          var clP = new THREE.Mesh(new THREE.ShapeGeometry(shp), cmatP);
          clP.rotation.x = Math.PI / 2; clP.position.set(rm.x_offset, rm.h || WALL_H, rm.z_offset); addToRoom(rm, clP);
          loadTex(rm.ceiling, function (tex) { cmatP.map = tex; cmatP.needsUpdate = true; });
        }
        var ccx = 0, ccz = 0;   // polygon centroid (unrotated world) for outward door labels
        SHAPE.forEach(function (p) { ccx += rm.x_offset + p.x * rm.w; ccz += rm.z_offset + p.z * rm.d; });
        ccx /= SHAPE.length; ccz /= SHAPE.length;
        // Dark marble border band along each room edge (the gallery-floor inlay in the reference).
        if (!floorPicP) {
          var bandMat = new THREE.MeshStandardMaterial({ color: 0x3a342c, roughness: 0.28, side: THREE.DoubleSide });
          var BW = 0.55;
          for (var be = 0; be < SHAPE.length; be++) {
            var qa = SHAPE[be], qb = SHAPE[(be + 1) % SHAPE.length];
            var qax = rm.x_offset + qa.x * rm.w, qaz = rm.z_offset + qa.z * rm.d, qbx = rm.x_offset + qb.x * rm.w, qbz = rm.z_offset + qb.z * rm.d;
            var elen = Math.hypot(qbx - qax, qbz - qaz); if (elen < 0.4) continue;
            var eang = Math.atan2(qbz - qaz, qbx - qax), emx = (qax + qbx) / 2, emz = (qaz + qbz) / 2;
            var inx = -Math.sin(eang), inz = Math.cos(eang);   // inward normal (toward centroid)
            if ((ccx - emx) * inx + (ccz - emz) * inz < 0) { inx = -inx; inz = -inz; }
            var band = new THREE.Mesh(new THREE.PlaneGeometry(elen, BW), bandMat);
            band.rotation.x = -Math.PI / 2; band.rotation.z = -eang;   // lay flat, align length to the edge
            band.position.set(emx + inx * BW / 2, 0.02, emz + inz * BW / 2);   // sit just above the marble
            addToRoom(rm, band);
          }
        }
        // Legacy rect-style doors ({wall:north|south|east|west}) on a now-shaped room
        // are otherwise dropped (edgeWall only cuts edge-indexed doors). Map each to
        // the polygon edge whose outward normal best matches that side, so it renders.
        (rm.doors || []).forEach(function (d) {
          if (typeof d.edge === 'number' || !d.wall) return;
          var want = { north: [0, -1], south: [0, 1], west: [-1, 0], east: [1, 0] }[d.wall]; if (!want) return;
          var bestE = -1, bestDot = -2;
          for (var ei = 0; ei < SHAPE.length; ei++) {
            var pa2 = SHAPE[ei], pb2 = SHAPE[(ei + 1) % SHAPE.length];
            var ex = (pb2.x - pa2.x) * rm.w, ez = (pb2.z - pa2.z) * rm.d, el = Math.hypot(ex, ez) || 1;
            var mxw = rm.x_offset + (pa2.x + pb2.x) / 2 * rm.w, mzw = rm.z_offset + (pa2.z + pb2.z) / 2 * rm.d;
            var nx = -ez / el, nz = ex / el;
            if ((mxw - ccx) * nx + (mzw - ccz) * nz < 0) { nx = -nx; nz = -nz; }
            var dot = nx * want[0] + nz * want[1];
            if (dot > bestDot) { bestDot = dot; bestE = ei; }
          }
          if (bestE >= 0) d.edge = bestE;
        });
        for (var e = 0; e < SHAPE.length; e++) {
          var pa = SHAPE[e], pb = SHAPE[(e + 1) % SHAPE.length];
          edgeWall(rm, e, rm.x_offset + pa.x * rm.w, rm.z_offset + pa.z * rm.d, rm.x_offset + pb.x * rm.w, rm.z_offset + pb.z * rm.d, wallMaterial(e), ccx, ccz);
        }
      } else {
        var fmat = new THREE.MeshStandardMaterial({ color: 0x8a8f96, roughness: rm.floor_image ? 0.4 : 0.95, side: THREE.DoubleSide });
        var rhStair = holesFor(rm), fl, metreUV = rhStair.length > 0;
        if (rhStair.length) { fl = new THREE.Mesh(shapeWithHoles([[0, 0], [rm.w, 0], [rm.w, rm.d], [0, rm.d]], rhStair), fmat); fl.rotation.x = Math.PI / 2; fl.position.set(rm.x_offset, 0, rm.z_offset); }
        else { fl = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), fmat); fl.rotation.x = -Math.PI / 2; fl.position.set(cx, 0, cz); }
        addToRoom(rm, fl);
        // Floor picture priority: uploaded floor_image > floor-plan tracing. PlaneGeometry UVs are 0..1 (1:1);
        // the stairwell shapeWithHoles path uses metre UVs, so scale one stretched copy over the whole floor.
        var floorPic = rm.floor_image || rm.floorplan;
        if (floorPic) { loadTex(floorPic, function (tex) {
          if (rm.floor_image) { tex.wrapS = tex.wrapT = THREE.RepeatWrapping; tex.repeat.set(metreUV ? 0.5 : rm.w / 2, metreUV ? 0.5 : rm.d / 2); }   // tile the uploaded floor image at 2m
          else if (metreUV) { tex.wrapS = tex.wrapT = THREE.ClampToEdgeWrapping; tex.repeat.set(1 / rm.w, 1 / rm.d); }   // stretch floorplan tracing
          fmat.map = tex; fmat.color.set(0xffffff); fmat.needsUpdate = true;
        }); }
        if (rm.ceiling) {                               // painted ceiling image
          var cmat = new THREE.MeshBasicMaterial({ side: THREE.DoubleSide });
          var cl = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), cmat);
          cl.rotation.x = Math.PI / 2; cl.position.set(cx, rm.h || WALL_H, cz);   // faces down
          addToRoom(rm, cl);
          loadTex(rm.ceiling, function (tex) { cmat.map = tex; cmat.needsUpdate = true; });
        }
        // #1176: plan-mode rooms always render through the polygon/edge path above (every room has a
        // shape now), so this branch is only the legacy auto-ROW layout: back+front full walls with
        // doorways auto-cut between consecutive rooms.
        var rx = rm.x_offset + rm.w;
        wallSeg(rm.w, cx, rm.z_offset, 0, rwMat);
        wallSeg(rm.w, cx, rm.z_offset + rm.d, Math.PI, rwMat);
        if (i === 0) wallSeg(rm.d, rm.x_offset, cz, Math.PI / 2, rwMat);
        if (i === ROOMS.length - 1) {
          wallSeg(rm.d, rx, cz, -Math.PI / 2, rwMat);
        } else {
          var half = (rm.d - DOOR) / 2;
          wallSeg(half, rx, cz - rm.d / 2 + half / 2, -Math.PI / 2, rwMat);
          wallSeg(half, rx, cz + rm.d / 2 - half / 2, -Math.PI / 2, rwMat);
        }
      }
      // No ceiling image set: cap the room with a plaster ceiling + a fancy cornice
      // (crown moulding) running around the top of the walls.
      if (!rm.ceiling) {
        var ceilY = rm.h || WALL_H, ceilHoles = ceilingHolesFor(rm);   // #1169 open the ceiling where a stair rises into the room above
        if (SHAPE) {
          var csh = new THREE.Shape(); SHAPE.forEach(function (p, j) { var px = p.x * rm.w, pz = p.z * rm.d; if (j === 0) csh.moveTo(px, pz); else csh.lineTo(px, pz); }); csh.closePath(); addHoles(csh, ceilHoles);
          var dcl = new THREE.Mesh(new THREE.ShapeGeometry(csh), ceilMat); dcl.rotation.x = Math.PI / 2; dcl.position.set(rm.x_offset, ceilY, rm.z_offset); addToRoom(rm, dcl);
          var gcx = 0, gcz = 0; SHAPE.forEach(function (p) { gcx += rm.x_offset + p.x * rm.w; gcz += rm.z_offset + p.z * rm.d; }); gcx /= SHAPE.length; gcz /= SHAPE.length;
          for (var ce = 0; ce < SHAPE.length; ce++) { var ca = SHAPE[ce], cb = SHAPE[(ce + 1) % SHAPE.length]; addCornice(rm, rm.x_offset + ca.x * rm.w, rm.z_offset + ca.z * rm.d, rm.x_offset + cb.x * rm.w, rm.z_offset + cb.z * rm.d, ceilY, gcx, gcz); }
        } else {
          var dclr = ceilHoles.length
            ? new THREE.Mesh(shapeWithHoles([[0, 0], [rm.w, 0], [rm.w, rm.d], [0, rm.d]], ceilHoles), ceilMat)
            : new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), ceilMat);
          dclr.rotation.x = Math.PI / 2; dclr.position.set(ceilHoles.length ? rm.x_offset : cx, ceilY, ceilHoles.length ? rm.z_offset : cz); addToRoom(rm, dclr);
          var x0 = rm.x_offset, z0 = rm.z_offset, x1 = rm.x_offset + rm.w, z1 = rm.z_offset + rm.d;
          addCornice(rm, x0, z0, x1, z0, ceilY, cx, cz); addCornice(rm, x1, z0, x1, z1, ceilY, cx, cz);
          addCornice(rm, x1, z1, x0, z1, ceilY, cx, cz); addCornice(rm, x0, z1, x0, z0, ceilY, cx, cz);
        }
      }
      (rm.walls || []).forEach(function (w) {          // interior dividers (normalized within room)
        var ax = rm.x_offset + w.x1 * rm.w, az = rm.z_offset + w.z1 * rm.d, bx = rm.x_offset + w.x2 * rm.w, bz = rm.z_offset + w.z2 * rm.d;
        var len = Math.hypot(bx - ax, bz - az); if (len < 0.1) return;
        var ang = Math.atan2(bz - az, bx - ax);
        var m = new THREE.Mesh(new THREE.PlaneGeometry(len, RH), wallMat);
        m.position.set((ax + bx) / 2, RH / 2, (az + bz) / 2); m.rotation.y = -ang; addToRoom(rm, m); colliders.push(m);
      });
      // Live conservation status tint (hidden until the Live button is pressed).
      (function () {
        var lv = rm.live || { status: 'none' };
        var tmat = new THREE.MeshBasicMaterial({ color: STATUS_COLOR[lv.status] || STATUS_COLOR.none, transparent: true, opacity: 0.3, side: THREE.DoubleSide, depthWrite: false });
        var tmesh;
        if (SHAPE) {
          var ts = new THREE.Shape(); SHAPE.forEach(function (p, j) { var px = p.x * rm.w, pz = p.z * rm.d; if (j === 0) ts.moveTo(px, pz); else ts.lineTo(px, pz); }); ts.closePath();
          tmesh = new THREE.Mesh(new THREE.ShapeGeometry(ts), tmat); tmesh.rotation.x = Math.PI / 2; tmesh.position.set(rm.x_offset, 0.04, rm.z_offset);
        } else {
          tmesh = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), tmat); tmesh.rotation.x = -Math.PI / 2; tmesh.position.set(cx, 0.04, cz);
        }
        tmesh.visible = false;
        addToRoom(rm, tmesh); roomTints.push(tmesh);
      })();
      // Clickable "MAP" plaque on the back wall's left corner (usually white space).
      var mapIcon = makeWallIcon();
      mapIcon.position.set(rm.x_offset + 0.5, Math.min(RH - 0.4, 1.6), rm.z_offset + 0.06);
      mapIcon.userData.action = 'minimap';
      addToRoom(rm, mapIcon); pickables.push(mapIcon);
    });

    // #1169 stairs: a straight flight or an L-shaped "elbow" linking two floors; click to change floor.
    (BUILDING && BUILDING.stairs ? BUILDING.stairs : []).forEach(function (st) {
      if ((st.from_floor || 0) === (st.to_floor == null ? 1 : st.to_floor)) return;   // must link two different floors
      var x = st.x, z = st.z, y0 = (st.from_floor || 0) * FLOOR_H, y1 = (st.to_floor || 1) * FLOOR_H;
      var rise = y1 - y0, width = st.width || 1.4, legLen = st.length || 3;   // legLen = run of each flight (metres)
      var stepMat = new THREE.MeshStandardMaterial({ color: 0xb6b1a8, roughness: 1 });
      // Build in a local group at (x,z), then spin by st.rot so it can face any way.
      var grp = new THREE.Group(); grp.position.set(x, 0, z); grp.rotation.y = (st.rot || 0) * Math.PI / 180; scene.add(grp);
      function flight(sx, sz, axis, ya, yb, m, depth, sign) {
        sign = sign || 1;
        for (var s = 0; s < m; s++) {
          var fy = ya + (s + 0.5) * (yb - ya) / m, step;
          if (axis === 'z') { step = new THREE.Mesh(new THREE.BoxGeometry(width, 0.18, depth), stepMat); step.position.set(sx, fy, sz + sign * s * depth); }
          else { step = new THREE.Mesh(new THREE.BoxGeometry(depth, 0.18, width), stepMat); step.position.set(sx + sign * s * depth, fy, sz); }
          grp.add(step);
        }
      }
      var footL, topL, hit, legSpecs = [];
      if (st.kind === 'elbow') {
        var h = (st.hand === 'left') ? -1 : 1;   // turn direction of the second flight
        var legLen2 = st.length2 || legLen;       // second leg can be a different length
        var half = Math.max(3, Math.round(Math.max(4, Math.abs(rise) / 0.2) / 2)), mid = y0 + rise / 2, depth = legLen / half, depth2 = legLen2 / half;
        flight(0, 0, 'z', y0, mid, half, depth, 1);                              // flight 1 along +z (length legLen)
        var land = new THREE.Mesh(new THREE.BoxGeometry(width + 0.2, 0.18, width + 0.2), stepMat); land.position.set(0, mid, legLen); grp.add(land);
        flight(h * (width / 2 + depth2), legLen, 'x', mid, y1, half, depth2, h);  // flight 2 turns left/right (length legLen2)
        var ex = h * (width / 2 + legLen2);
        legSpecs.push({ la: { x: 0, z: 0 }, lb: { x: 0, z: legLen }, ya: y0, yb: mid });
        legSpecs.push({ la: { x: 0, z: legLen }, lb: { x: ex, z: legLen }, ya: mid, yb: y1 });
        footL = { x: 0, y: y0, z: -0.8 }; topL = { x: ex + h * 0.8, y: y1, z: legLen };
        hit = new THREE.Mesh(new THREE.BoxGeometry(Math.abs(ex) + width + 1.2, Math.abs(rise) + 1.4, legLen + width + 1.2), new THREE.MeshBasicMaterial({ visible: false }));
        hit.position.set(ex / 2, (y0 + y1) / 2, legLen / 2); grp.add(hit);
        var eUp = makeTextSprite('{{ __('STAIRS ↑') }}', 0.42); eUp.position.set(0, y0 + 1.4, -0.8); grp.add(eUp);
        var eDn = makeTextSprite('{{ __('STAIRS ↓') }}', 0.42); eDn.position.set(ex, y1 + 1.4, legLen); grp.add(eDn);
      } else {
        var n = Math.max(4, Math.round(Math.abs(rise) / 0.2)), depth = legLen / n;
        flight(0, -legLen / 2, 'z', y0, y1, n, depth, 1);
        legSpecs.push({ la: { x: 0, z: -legLen / 2 }, lb: { x: 0, z: legLen / 2 }, ya: y0, yb: y1 });
        footL = { x: 0, y: y0, z: -legLen / 2 - 0.8 }; topL = { x: 0, y: y1, z: legLen / 2 + 0.8 };
        hit = new THREE.Mesh(new THREE.BoxGeometry(width, Math.abs(rise) + 1.2, legLen), new THREE.MeshBasicMaterial({ visible: false }));
        hit.position.set(0, (y0 + y1) / 2, 0); grp.add(hit);
        var sUp = makeTextSprite('{{ __('STAIRS ↑') }}', 0.42); sUp.position.set(0, y0 + 1.4, -legLen / 2 - 0.8); grp.add(sUp);
        var sDn = makeTextSprite('{{ __('STAIRS ↓') }}', 0.42); sDn.position.set(0, y1 + 1.4, legLen / 2 + 0.8); grp.add(sDn);
      }
      grp.updateMatrixWorld(true);
      var fw = grp.localToWorld(new THREE.Vector3(footL.x, footL.y, footL.z));
      var tw = grp.localToWorld(new THREE.Vector3(topL.x, topL.y, topL.z));
      legSpecs.forEach(function (ls) {   // world-space walkable ramp corridors
        var A = grp.localToWorld(new THREE.Vector3(ls.la.x, 0, ls.la.z));
        var B = grp.localToWorld(new THREE.Vector3(ls.lb.x, 0, ls.lb.z));
        var dx = B.x - A.x, dz = B.z - A.z, len = Math.hypot(dx, dz) || 1;
        stairRamps.push({ ax: A.x, az: A.z, ux: dx / len, uz: dz / len, len: len, ya: ls.ya, yb: ls.yb, half: width / 2 + 0.4 });
      });
      hit.userData.action = 'stair';
      hit.userData.bot = { x: fw.x, fy: y0, z: fw.z };
      hit.userData.top = { x: tw.x, fy: y1, z: tw.z };
      pickables.push(hit);
    });

    // #1174 spotlights: dim the surroundings + light a spotlit object as you approach it.
    var spotObjects = [], hemiBase = hemiLight.intensity;
    function buildSpots() {
      scene.updateMatrixWorld(true);
      pickables.forEach(function (o) {
        if (o.userData._spotDone) return;
        var s = o.userData && o.userData.stop; if (!s || !s.spotlight) return;   // 0 off, 1 on-approach, 2 always-on
        o.userData._spotDone = true;
        var wp = new THREE.Vector3(); o.getWorldPosition(wp);
        var mode = (+s.spotlight) || 1;
        var sp = new THREE.SpotLight(0xfff2d0, mode === 2 ? 1.6 : 0, 14, Math.PI / 7, 0.5, 1.2);
        sp.position.set(wp.x, wp.y + 3.2, wp.z + 0.01);
        var tgt = new THREE.Object3D(); tgt.position.copy(wp); scene.add(tgt); sp.target = tgt;
        scene.add(sp);
        // Hung pictures use an UNLIT material (MeshBasic) that a SpotLight cannot affect, so capture
        // its material and brighten it directly when spotlit - that is what actually makes a picture pop.
        var picMat = null;
        o.traverse(function (n) { if (n.isMesh && n.material && n.material.map && (n.material.type === 'MeshBasicMaterial' || n.material.isMeshBasicMaterial)) picMat = n.material; });
        spotObjects.push({ x: wp.x, y: wp.y, z: wp.z, light: sp, mode: mode, mat: picMat });
      });
    }
    buildSpots(); setTimeout(buildSpots, 1500); setTimeout(buildSpots, 4000);   // re-scan as async images finish loading

    // Controls. Desktop = first-person pointer-lock (WASD + mouse). Touch devices
    // can't pointer-lock, so they get OrbitControls (drag to look, pinch to zoom)
    // plus the walk-to buttons to travel.
    var isTouch = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    var controls = new THREE.PointerLockControls(camera, renderer.domElement);
    scene.add(controls.getObject());
    var blocker = document.getElementById('roomBlocker');
    var cross = document.getElementById('roomCrosshair');
    var orbit = null;

    if (isTouch && THREE.OrbitControls) {
      orbit = new THREE.OrbitControls(camera, renderer.domElement);
      orbit.enableDamping = true; orbit.dampingFactor = 0.12;
      orbit.target.set(0, 1.3, 0);
      orbit.minDistance = 1; orbit.maxDistance = Math.max(BLD_maxX - BLD_minX, BLD_maxZ - BLD_minZ);
      orbit.maxPolarAngle = Math.PI * 0.85;
      camera.position.set(curRoom.x_offset + curRoom.w / 2, 1.6, curRoom.z_offset + curRoom.d - 2);
      orbit.target.set(curRoom.x_offset + curRoom.w / 2, 1.3, curRoom.z_offset + curRoom.d / 2);
      blocker.style.display = 'none';
      cross.style.display = 'none';
    } else {
      blocker.addEventListener('click', function () { controls.lock(); });
      controls.addEventListener('lock', function () { blocker.style.display = 'none'; cross.style.display = 'block'; });
      controls.addEventListener('unlock', function () { blocker.style.display = 'flex'; cross.style.display = 'none'; });
    }

    var keys = {};
    document.addEventListener('keydown', function (e) {
      keys[e.code] = true;
      if (e.code === 'Escape') closeAllPopups();
      if (e.code === 'KeyH') {
        var hb = document.getElementById('roomHelp');
        hb.style.display = (hb.style.display === 'block') ? 'none' : 'block';
      }
      if (e.code === 'KeyV' && panelOpen) viewFullDetails();
      if (e.code === 'KeyZ') toggleZoom();      // #1163 - zoom in/out on what you're facing
      if (e.code === 'KeyF') toggleTorch();     // #1164 - spotlight / torch for dark corners
    });
    document.addEventListener('keyup', function (e) { keys[e.code] = false; });

    // Format-aware model loader (glb/gltf/obj/stl/ply).
    function modelExt(s) {
      if (s.model_format) return String(s.model_format).toLowerCase();
      var u = (s.model_url || '').split('?')[0];
      return u.substring(u.lastIndexOf('.') + 1).toLowerCase();
    }
    // Effective per-object tilt (degrees). Explicit Builder value wins; else auto:
    // OBJ/STL/PLY are usually Z-up so default to -90 X; glTF stays upright.
    function effTiltX(s) {
      if (s.tilt_x !== null && s.tilt_x !== undefined) return s.tilt_x;
      var e = modelExt(s);
      return (e === 'obj' || e === 'stl' || e === 'ply') ? -90 : 0;
    }
    function effTiltZ(s) {
      return (s.tilt_z !== null && s.tilt_z !== undefined) ? s.tilt_z : 0;
    }
    function greyMesh(geo) {
      geo.computeVertexNormals();
      return new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ color: 0xb0a89a, roughness: 0.8, metalness: 0.05 }));
    }
    var _gltfLoader = null;
    function gltfLoader() {
      if (_gltfLoader) return _gltfLoader;
      _gltfLoader = new THREE.GLTFLoader();
      if (THREE.DRACOLoader) {   // decode DRACO-compressed meshes (no-op for uncompressed)
        var dl = new THREE.DRACOLoader();
        dl.setDecoderPath('https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/libs/draco/');
        _gltfLoader.setDRACOLoader(dl);
      }
      return _gltfLoader;
    }
    // Studio environment so PBR metals (e.g. gold models) reflect light and show their real colour instead of
    // rendering near-black (a metallic surface with no environment reflects the dark background). Built once,
    // applied PER MODEL only, so the room's tuned (non-washed) lighting is unaffected.
    var _exEnv = null;
    function exhibitionEnv() {
      if (_exEnv) return _exEnv;
      var pg = new THREE.PMREMGenerator(renderer);
      var es = new THREE.Scene();
      es.add(new THREE.Mesh(new THREE.BoxGeometry(24, 14, 24), new THREE.MeshBasicMaterial({ color: 0x9aa0aa, side: THREE.BackSide })));
      var ceil = new THREE.Mesh(new THREE.PlaneGeometry(18, 18), new THREE.MeshBasicMaterial({ color: 0xffffff }));
      ceil.rotation.x = Math.PI / 2; ceil.position.y = 6.9; es.add(ceil);
      _exEnv = pg.fromScene(es, 0.04).texture; pg.dispose(); return _exEnv;
    }
    function applyEnv(obj) {
      var env = exhibitionEnv();
      obj.traverse(function (n) {
        if (!n.isMesh || !n.material) return;
        var mats = Array.isArray(n.material) ? n.material : [n.material];
        mats.forEach(function (m) { if ('envMap' in m) { m.envMap = env; if ('envMapIntensity' in m) m.envMapIntensity = 1.1; m.needsUpdate = true; } });
      });
    }
    function loadModel(url, ext, onLoad, onError) {
      var cb = function (o) { try { applyEnv(o); } catch (e) {} onLoad(o); };   // give every loaded model the studio env
      try {
        if (ext === 'glb' || ext === 'gltf') { gltfLoader().load(url, function (g) { cb(g.scene); }, undefined, onError); }
        else if (ext === 'obj') { new THREE.OBJLoader().load(url, function (o) { cb(o); }, undefined, onError); }
        else if (ext === 'stl') { new THREE.STLLoader().load(url, function (geo) { cb(greyMesh(geo)); }, undefined, onError); }
        else if (ext === 'ply') { new THREE.PLYLoader().load(url, function (geo) { cb(greyMesh(geo)); }, undefined, onError); }
        else if (onError) { onError(); }
      } catch (e) { if (onError) onError(); }
    }

    // Objects (pickables already declared before the room loop)
    var pedestalMat = new THREE.MeshStandardMaterial({ color: 0x3a3f47, roughness: 0.8 });
    var pending = STOPS.length + CORRIDOR.length;
    function doneOne() { pending--; if (pending <= 0) loading.style.display = 'none'; }

    function worldPos(s) {
      var rm = s._room || curRoom;
      return { x: rm.x_offset + s.pos_x * rm.w, z: rm.z_offset + s.pos_y * rm.d };
    }
    // Corridor object world position (fraction of the building bounding box).
    var CB = BUILDING ? { x0: BUILDING.min_x, z0: BUILDING.min_z, w: BUILDING.max_x - BUILDING.min_x, d: BUILDING.max_z - BUILDING.min_z } : { x0: 0, z0: 0, w: 0, d: 0 };
    function corridorPos(s) { return { x: CB.x0 + (s.pos_x || 0.5) * CB.w, z: CB.z0 + (s.pos_y || 0.5) * CB.d }; }
    // A free-standing framed picture on a slim post (corridor images/PDFs).
    function freeStandImage(x, z, s, tex, aspect) {
      var dsc = s.scale || 1, hgt = 1.4 * dsc, wdt = hgt * (aspect || 1);
      if (wdt > 2.4 * dsc) { wdt = 2.4 * dsc; hgt = wdt / (aspect || 1); }
      var base = 1.0, cy = base + hgt / 2, grp = new THREE.Group();
      var pole = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, base, 8), pedestalMat); pole.position.y = base / 2; grp.add(pole);
      var frame = new THREE.Mesh(new THREE.BoxGeometry(wdt + 0.1, hgt + 0.1, 0.06), new THREE.MeshStandardMaterial({ color: 0x222222 })); frame.position.y = cy; grp.add(frame);
      var pic = new THREE.Mesh(new THREE.PlaneGeometry(wdt, hgt), new THREE.MeshBasicMaterial({ map: tex, side: THREE.DoubleSide })); pic.position.set(0, cy, 0.04); grp.add(pic);
      grp.position.set(x, 0, z); grp.rotation.y = (s.rotation_deg || 0) * Math.PI / 180;
      grp.traverse(function (n) { n.userData.stop = s; }); grp.userData.stop = s;
      scene.add(grp); pickables.push(grp);
    }

    function addPedestal(x, z, h, rm) {
      var p = new THREE.Mesh(new THREE.BoxGeometry(0.7, h, 0.7), pedestalMat);
      p.position.set(x, h / 2, z); addToRoom(rm, p); return h;
    }
    // A glass display case (wooden plinth + glass vitrine + metal cap). Returns the plinth-top Y
    // where the item sits (inside the glass). Used when an item is flagged display_case.
    function addDisplayCase(x, z, rm) {
      var g = new THREE.Group(); g.position.set(x, 0, z);
      var woodM = new THREE.MeshStandardMaterial({ color: 0x5a4634, roughness: 0.7 });
      var metalM = new THREE.MeshStandardMaterial({ color: 0x9a9b9d, metalness: 0.7, roughness: 0.35 });
      var base = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.9, 0.9), woodM); base.position.y = 0.45; g.add(base);
      var glass = new THREE.Mesh(new THREE.BoxGeometry(0.82, 1.0, 0.82), glassMat); glass.position.y = 1.4; g.add(glass);
      var cap = new THREE.Mesh(new THREE.BoxGeometry(0.88, 0.05, 0.88), metalM); cap.position.y = 1.92; g.add(cap);
      addToRoom(rm, g); return 0.9;
    }
    // A small standing image on the display-case plinth (for picture items shown in a case).
    function addCaseImage(rm, x, z, ph, s, tex, aspect) {
      var h = 0.55, w = h * (aspect || 1); if (w > 0.62) { w = 0.62; h = w / (aspect || 1); }
      var grp = new THREE.Group();
      var pic = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ map: tex, side: THREE.DoubleSide }));
      pic.position.y = ph + h / 2 + 0.04; grp.add(pic);
      grp.position.set(x, 0, z); grp.rotation.y = (s.rotation_deg || 0) * Math.PI / 180;
      grp.traverse(function (n) { n.userData.stop = s; }); grp.userData.stop = s;
      addToRoom(rm, grp); pickables.push(grp);
    }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    // Hang a framed picture flat on the nearest wall (pictures on walls, not pedestals).
    // Choose where a picture hangs: a specific wall (perimeter key or interior id)
    // when assigned, otherwise the nearest wall (perimeter + interior dividers).
    // A wall segment candidate (used for interior dividers AND polygon edges):
    // projects the object onto the segment and faces the picture into the room.
    function segCand(key, ax, az, bx, bz, px, pz, hw, inset, forceSide) {
      var ex = bx - ax, ez = bz - az, L2 = ex * ex + ez * ez;
      if (L2 < 0.01) return null;
      var t = Math.max(0, Math.min(1, ((px - ax) * ex + (pz - az) * ez) / L2));
      var projx = ax + t * ex, projz = az + t * ez;
      return { key: key, dist: Math.hypot(px - projx, pz - projz), get: function () {
        var ang = Math.atan2(ez, ex), nx = -Math.sin(ang), nz = Math.cos(ang);
        var side = (forceSide != null) ? forceSide : (((px - projx) * nx + (pz - projz) * nz) >= 0 ? 1 : -1);
        var len = Math.sqrt(L2), tt = Math.max(hw / len, Math.min(1 - hw / len, t));
        var cx = ax + tt * ex, cz = az + tt * ez;
        return { x: cx + nx * inset * side, z: cz + nz * inset * side, ry: Math.atan2(nx * side, nz * side) };
      } };
    }
    function wallSpot(wallKey, px, pz, hw, inset, rm) {
      var x0 = rm.x_offset, x1 = rm.x_offset + rm.w, zN = rm.z_offset, zS = rm.z_offset + rm.d;
      var cands = [];
      var shaped = rm.shape && rm.shape.length >= 3;
      if (shaped) {   // the real walls are the polygon edges, not the bounding box
        rm.shape.forEach(function (pa, i) {
          var pb = rm.shape[(i + 1) % rm.shape.length];
          var c = segCand('edge:' + i, rm.x_offset + pa.x * rm.w, rm.z_offset + pa.z * rm.d, rm.x_offset + pb.x * rm.w, rm.z_offset + pb.z * rm.d, px, pz, hw, inset);
          if (c) cands.push(c);
        });
      } else {
        cands.push({ key: 'north', dist: pz - zN, get: function () { return { x: clamp(px, x0 + hw, x1 - hw), z: zN + inset, ry: 0 }; } });
        cands.push({ key: 'south', dist: zS - pz, get: function () { return { x: clamp(px, x0 + hw, x1 - hw), z: zS - inset, ry: Math.PI }; } });
        cands.push({ key: 'west', dist: px - x0, get: function () { return { x: x0 + inset, z: clamp(pz, zN + hw, zS - hw), ry: Math.PI / 2 }; } });
        cands.push({ key: 'east', dist: x1 - px, get: function () { return { x: x1 - inset, z: clamp(pz, zN + hw, zS - hw), ry: -Math.PI / 2 }; } });
      }
      // Interior dividers have two faces: a "|b" key forces the back face.
      var keyBack = (typeof wallKey === 'string' && wallKey.slice(-2) === '|b');
      var wallBase = keyBack ? wallKey.slice(0, -2) : wallKey;
      (rm.walls || []).forEach(function (w) {
        var force = null, ck = w.id;
        if (wallBase === w.id) { ck = wallKey; force = keyBack ? -1 : null; }   // back = forced face; front (bare) keeps legacy auto side
        var c = segCand(ck, rm.x_offset + w.x1 * rm.w, rm.z_offset + w.z1 * rm.d, rm.x_offset + w.x2 * rm.w, rm.z_offset + w.z2 * rm.d, px, pz, hw, inset, force);
        if (c) cands.push(c);
      });
      var chosen = null;
      if (wallKey) { for (var i = 0; i < cands.length; i++) { if (cands[i].key === wallKey) { chosen = cands[i]; break; } } }
      if (!chosen) { chosen = cands[0]; for (var j = 1; j < cands.length; j++) { if (cands[j].dist < chosen.dist) chosen = cands[j]; } }
      return chosen.get();
    }

    // Exact spot from a chosen wall + along-wall u (0-1) - used by the wall editor.
    function wallSpotUV(wallKey, u, hw, inset, rm) {
      var x0 = rm.x_offset, x1 = rm.x_offset + rm.w, zN = rm.z_offset, zS = rm.z_offset + rm.d;
      if (wallKey === 'north') return { x: clamp(x0 + u * rm.w, x0 + hw, x1 - hw), z: zN + inset, ry: 0 };
      if (wallKey === 'south') return { x: clamp(x0 + u * rm.w, x0 + hw, x1 - hw), z: zS - inset, ry: Math.PI };
      if (wallKey === 'west') return { x: x0 + inset, z: clamp(zN + u * rm.d, zN + hw, zS - hw), ry: Math.PI / 2 };
      if (wallKey === 'east') return { x: x1 - inset, z: clamp(zN + u * rm.d, zN + hw, zS - hw), ry: -Math.PI / 2 };
      // Polygon edge: along-wall u, facing the room centroid (into the room).
      if (typeof wallKey === 'string' && wallKey.indexOf('edge:') === 0 && rm.shape) {
        var i = parseInt(wallKey.slice(5), 10), pa = rm.shape[i], pb = rm.shape[(i + 1) % rm.shape.length];
        if (!pa || !pb) return null;
        var ax = rm.x_offset + pa.x * rm.w, az = rm.z_offset + pa.z * rm.d, bx = rm.x_offset + pb.x * rm.w, bz = rm.z_offset + pb.z * rm.d;
        var ex = bx - ax, ez = bz - az, len = Math.hypot(ex, ez) || 1, tt = clamp(u, hw / len, 1 - hw / len);
        var ang = Math.atan2(ez, ex), nx = -Math.sin(ang), nz = Math.cos(ang);
        var ccx = 0, ccz = 0; rm.shape.forEach(function (p) { ccx += rm.x_offset + p.x * rm.w; ccz += rm.z_offset + p.z * rm.d; }); ccx /= rm.shape.length; ccz /= rm.shape.length;
        var mx = ax + tt * ex, mz = az + tt * ez, side = ((ccx - mx) * nx + (ccz - mz) * nz) >= 0 ? 1 : -1;
        return { x: mx + nx * inset * side, z: mz + nz * inset * side, ry: Math.atan2(nx * side, nz * side) };
      }
      // Interior divider: a "|b" suffix selects the back face (the other side of the wall).
      var sideSign = 1, baseKey = wallKey;
      if (typeof wallKey === 'string' && wallKey.slice(-2) === '|b') { sideSign = -1; baseKey = wallKey.slice(0, -2); }
      var w = (rm.walls || []).filter(function (ww) { return ww.id === baseKey; })[0];
      if (w) {
        var ax = rm.x_offset + w.x1 * rm.w, az = rm.z_offset + w.z1 * rm.d, bx = rm.x_offset + w.x2 * rm.w, bz = rm.z_offset + w.z2 * rm.d;
        var ex = bx - ax, ez = bz - az, len = Math.hypot(ex, ez) || 1;
        var tt = clamp(u, hw / len, 1 - hw / len);
        var ang = Math.atan2(ez, ex), nx = -Math.sin(ang), nz = Math.cos(ang);
        return { x: ax + tt * ex + nx * inset * sideSign, z: az + tt * ez + nz * inset * sideSign, ry: Math.atan2(nx * sideSign, nz * sideSign) };
      }
      return null;
    }

    function hangOnWall(wp, s, tex, aspect) {
      var dsc = s.scale || 1;                       // display size from Builder Bigger/Smaller
      var hgt = 1.5 * dsc, wdt = hgt * (aspect || 1);
      var capW = 2.6 * dsc;
      if (wdt > capW) { wdt = capW; hgt = wdt / (aspect || 1); }
      var hw = wdt / 2;
      var inset = 0.08, cy, spot, rmh = s._room || curRoom;
      var wallH = rmh.h || WALL_H;                   // hang within this room's wall height
      var hasUV = (s.wall_u !== null && s.wall_u !== undefined && s.wall_or_zone);
      if (hasUV) {
        spot = wallSpotUV(s.wall_or_zone, s.wall_u, hw, inset, rmh);
        cy = clamp((s.wall_v != null ? s.wall_v : 0.5) * wallH, hgt / 2 + 0.1, wallH - hgt / 2 - 0.1);
      }
      if (!spot) {
        spot = wallSpot(s.wall_or_zone, wp.x, wp.z, hw, inset, rmh);
        cy = clamp(1.6, hgt / 2 + 0.1, wallH - hgt / 2 - 0.1);
      }
      var frame = new THREE.Mesh(new THREE.BoxGeometry(wdt + 0.12, hgt + 0.12, 0.06), new THREE.MeshStandardMaterial({ color: 0x222222 }));
      var pic = new THREE.Mesh(new THREE.PlaneGeometry(wdt, hgt), new THREE.MeshBasicMaterial({ map: tex }));
      pic.position.z = 0.045;
      var grp = new THREE.Group();
      grp.add(frame); grp.add(pic);
      // Cascade stacked layers: objects sharing a wall spot fan out (depth + along-wall + down)
      // so each is visible and the front one is clickable (#1140), for UV and auto placements alike.
      var st = s._stack || 0;
      var n = { x: Math.sin(spot.ry), z: Math.cos(spot.ry) };        // wall normal (into room)
      var tg = { x: Math.cos(spot.ry), z: -Math.sin(spot.ry) };      // along the wall
      grp.position.set(
        spot.x + n.x * st * 0.07 + tg.x * st * 0.14,
        cy - st * 0.12,
        spot.z + n.z * st * 0.07 + tg.z * st * 0.14
      );
      grp.rotation.y = spot.ry;
      grp.traverse(function (n) { n.userData.stop = s; });
      grp.userData.stop = s;
      addToRoom(s._room, grp); pickables.push(grp);
    }

    // Render a PDF's first page to a texture (PDF.js) for the gallery wall.
    function renderPdfTexture(url, onTex, onErr) {
      if (!window.pdfjsLib) { if (onErr) onErr(); return; }
      pdfjsLib.getDocument(url).promise.then(function (pdf) { return pdf.getPage(1); }).then(function (page) {
        var vp = page.getViewport({ scale: 1.5 });
        var canvas = document.createElement('canvas');
        canvas.width = vp.width; canvas.height = vp.height;
        return page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise.then(function () {
          onTex(new THREE.CanvasTexture(canvas), vp.width / vp.height);
        });
      }).catch(function () { if (onErr) onErr(); });
    }

    STOPS.forEach(function (s) {
      var wp = worldPos(s);
      if (s.kind === '3d' && s.model_url) {
        var inCase = !!s.display_case;
        // on_floor: stand the model straight on the floor (no pedestal). Case takes precedence if both set.
        var ph = inCase ? addDisplayCase(wp.x, wp.z, s._room) : (s.on_floor ? 0 : addPedestal(wp.x, wp.z, 0.6, s._room));
        loadModel(s.model_url, modelExt(s), function (obj) {
          // Per-object orientation (auto up-axis guess unless overridden in Builder).
          obj.rotation.x = effTiltX(s) * Math.PI / 180;
          obj.rotation.z = effTiltZ(s) * Math.PI / 180;
          // Wrap in a pivot so the builder's rotation_deg spins it (yaw) on its base.
          var pivot = new THREE.Group();
          pivot.add(obj);
          var box = new THREE.Box3().setFromObject(pivot);
          var size = box.getSize(new THREE.Vector3());
          var maxd = Math.max(size.x, size.y, size.z) || 1;
          pivot.scale.setScalar(((inCase ? 0.55 : 1.5) / maxd) * (s.scale || 1));   // smaller to fit inside the glass case; else display size from Builder
          pivot.rotation.y = (s.rotation_deg || 0) * Math.PI / 180;
          pivot.updateMatrixWorld(true);
          box = new THREE.Box3().setFromObject(pivot);
          var c = box.getCenter(new THREE.Vector3());
          pivot.position.x += wp.x - c.x;
          pivot.position.z += wp.z - c.z;
          pivot.position.y += ph - box.min.y;
          pivot.traverse(function (n) { if (n.isMesh) { n.userData.stop = s; } });
          pivot.userData.stop = s;
          addToRoom(s._room, pivot); pickables.push(pivot); doneOne();
        }, function () {
          addPlaceholder(wp, s, ph); doneOne();
        });
      } else if (s.image_url) {
        loadTex(s.image_url, function (tex) {
          var aspect = (tex.image && tex.image.width ? tex.image.width : 1) / (tex.image && tex.image.height ? tex.image.height : 1);
          if (s.display_case) { var phc = addDisplayCase(wp.x, wp.z, s._room); addCaseImage(s._room, wp.x, wp.z, phc, s, tex, aspect); }   // item shown inside a glass case
          else if (s._room && s._room.is_outdoor) { freeStandImage(wp.x, wp.z, s, tex, aspect); }   // #1170 statues free-stand on the ground outdoors
          else { hangOnWall(wp, s, tex, aspect); }
          doneOne();
        }, undefined, function () { addPlaceholder(wp, s, addPedestal(wp.x, wp.z, 0.4, s._room)); doneOne(); });
      } else if (s.kind === 'pdf' && s.doc_url) {
        renderPdfTexture(s.doc_url, function (tex, aspect) {
          hangOnWall(wp, s, tex, aspect); doneOne();
        }, function () { addPlaceholder(wp, s, addPedestal(wp.x, wp.z, 0.4, s._room)); doneOne(); });
      } else {
        var ph3 = addPedestal(wp.x, wp.z, 0.4, s._room);
        addPlaceholder(wp, s, ph3); doneOne();
      }
    });

    function addPlaceholder(wp, s, ph) {
      var m = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.8, 0.8), new THREE.MeshStandardMaterial({ color: 0x6c757d }));
      m.position.set(wp.x, ph + 0.4, wp.z); m.userData.stop = s;
      addToRoom(s._room, m); pickables.push(m);
    }

    // Corridor objects: free-standing in building space (between/around rooms).
    CORRIDOR.forEach(function (s) {
      var cp = corridorPos(s);
      if (s.kind === '3d' && s.model_url) {
        var ph = addPedestal(cp.x, cp.z, 0.6);   // no room -> added to scene
        loadModel(s.model_url, modelExt(s), function (obj) {
          obj.rotation.x = effTiltX(s) * Math.PI / 180; obj.rotation.z = effTiltZ(s) * Math.PI / 180;
          var pivot = new THREE.Group(); pivot.add(obj);
          var box = new THREE.Box3().setFromObject(pivot), size = box.getSize(new THREE.Vector3()), maxd = Math.max(size.x, size.y, size.z) || 1;
          pivot.scale.setScalar((1.5 / maxd) * (s.scale || 1)); pivot.rotation.y = (s.rotation_deg || 0) * Math.PI / 180; pivot.updateMatrixWorld(true);
          box = new THREE.Box3().setFromObject(pivot); var c = box.getCenter(new THREE.Vector3());
          pivot.position.x += cp.x - c.x; pivot.position.z += cp.z - c.z; pivot.position.y += ph - box.min.y;
          pivot.traverse(function (n) { if (n.isMesh) n.userData.stop = s; }); pivot.userData.stop = s;
          scene.add(pivot); pickables.push(pivot); doneOne();
        }, function () { addPlaceholder({ x: cp.x, z: cp.z }, s, ph); doneOne(); });
      } else if (s.image_url) {
        loadTex(s.image_url, function (tex) {
          var aspect = (tex.image && tex.image.width ? tex.image.width : 1) / (tex.image && tex.image.height ? tex.image.height : 1);
          freeStandImage(cp.x, cp.z, s, tex, aspect); doneOne();
        }, undefined, function () { addPlaceholder({ x: cp.x, z: cp.z }, s, addPedestal(cp.x, cp.z, 0.4)); doneOne(); });
      } else if (s.kind === 'pdf' && s.doc_url) {
        renderPdfTexture(s.doc_url, function (tex, aspect) { freeStandImage(cp.x, cp.z, s, tex, aspect); doneOne(); },
          function () { addPlaceholder({ x: cp.x, z: cp.z }, s, addPedestal(cp.x, cp.z, 0.4)); doneOne(); });
      } else {
        addPlaceholder({ x: cp.x, z: cp.z }, s, addPedestal(cp.x, cp.z, 0.4)); doneOne();
      }
    });

    // Detail inlay (in-canvas info block; click anywhere closes it; V opens record).
    var inlay = document.getElementById('wtInlay');
    var panelOpen = false;
    var currentStop = null;
    var RECOMMEND_URL = '{{ route('exhibition-space.recommend', ['slug' => $space->slug]) }}';
    function loadRelated(s) {
      var box = document.getElementById('wtRelated'), items = document.getElementById('wtRelatedItems');
      if (!box || !items) return;
      box.style.display = 'none'; items.innerHTML = '';
      if (!s.information_object_id) return;
      fetch(RECOMMEND_URL + '?io=' + s.information_object_id).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok || !d.items || !d.items.length || s !== currentStop) return;
        d.items.forEach(function (it) {
          var b = document.createElement('button');
          b.type = 'button'; b.className = 'btn btn-sm btn-outline-light';
          b.title = (it.reason || '') + (it.room_name ? ' (' + it.room_name + ')' : '');
          b.innerHTML = (it.ai ? '<i class="fas fa-wand-magic-sparkles me-1"></i>' : '') + (it.title || ('#' + it.io_id)).replace(/[<>&]/g, '');
          b.addEventListener('click', function (e) { e.stopPropagation(); var st = stopByPlacement(it.placement_id); if (st) flyTo(st); });
          items.appendChild(b);
        });
        box.style.display = 'block';
      }).catch(function () {});
    }
    function stopByPlacement(pid) { for (var i = 0; i < STOPS.length; i++) { if (STOPS[i].id === pid) return STOPS[i]; } return null; }
    // Audio description (docent): hold T (Talk) + click an object to hear its description read aloud.
    function showNarr(on) { var n = document.getElementById('wtNarr'); if (n) n.style.display = on ? 'block' : 'none'; }
    function stopNarrate() { try { if (window.speechSynthesis) window.speechSynthesis.cancel(); } catch (e) {} showNarr(false); }
    function setNarrLabel(html) { var n = document.getElementById('wtNarr'); if (n) { n.innerHTML = html; n.style.display = 'block'; } }
    var WT_VOICE = null;   // selected SpeechSynthesisVoice (user choice); null = browser default
    function speakText(text, onDone) {
      try {
        if (!('speechSynthesis' in window)) { if (onDone) onDone(); return; }
        window.speechSynthesis.cancel();
        var u = new SpeechSynthesisUtterance(text);
        u.rate = 0.95; u.lang = document.documentElement.lang || 'en';
        if (WT_VOICE) { u.voice = WT_VOICE; u.lang = WT_VOICE.lang || u.lang; }
        u.onend = function () { showNarr(false); if (onDone) onDone(); };
        u.onerror = function () { showNarr(false); if (onDone) onDone(); };
        setNarrLabel('<i class="fas fa-volume-high me-1"></i>{{ __('Reading description... (Esc to stop)') }}');
        window.speechSynthesis.speak(u);
      } catch (e) { if (onDone) onDone(); }
    }
    function narrate(s, force) {
      var desc = (s.description || '').trim();
      if (desc && !force) { speakText((s.title ? s.title + '. ' : '') + desc); return; }
      // No metadata (or force=G): ask the AI gateway to describe it, then read it out.
      setNarrLabel('<i class="fas fa-wand-magic-sparkles me-1"></i>{{ __('Generating AI description...') }}');
      fetch('/exhibition-space/object/' + s.information_object_id + '/describe' + (force ? '?fresh=1' : ''), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var ai = (d && d.description) ? d.description : null;
          var text = ai || '{{ __('No description available for this object.') }}';
          var dd = document.getElementById('inlayDesc');
          if (dd && currentStop === s) dd.textContent = (ai ? '🤖 ' + text : text);   // robot emoji marks an AI description
          speakText((s.title ? s.title + '. ' : '') + text);
        })
        .catch(function () { stopNarrate(); });
    }
    function openPanel(s) {
      document.getElementById('inlayTitle').textContent = s.title;
      document.getElementById('inlayDesc').textContent = s.description || '{{ __('No description available.') }}';
      var rec = document.getElementById('inlayRec');
      if (s.record_url) { rec.href = s.record_url; rec.style.display = ''; } else { rec.style.display = 'none'; }
      inlay.style.display = 'block';
      panelOpen = true;
      currentStop = s;
      // #1150: a docent leading a tour spotlights whatever object they open, so
      // everyone following is flown to it on the next presence beat.
      if (typeof CAN_DOCENT !== 'undefined' && CAN_DOCENT && myTourActive && s && s.information_object_id) { myFocus = s.information_object_id; }
      // #1173 log the object view for visitor analytics (anonymous token only).
      try { if (typeof VISIT_EVENT !== 'undefined' && s && s.information_object_id) { fetch(VISIT_EVENT, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WT_CSRF, 'Accept': 'application/json' }, body: JSON.stringify({ token: MY_TOKEN, type: 'object', object_id: s.information_object_id, room_id: (s._room ? s._room.id : null) }) }); } } catch (e) {}
      loadRelated(s);
    }
    function closeAllPopups() {
      inlay.style.display = 'none';
      if (typeof closeRecordOverlay === 'function') closeRecordOverlay();   // #1142 Esc also leaves the record view
      var rb = document.getElementById('wtRelated'); if (rb) rb.style.display = 'none';
      stopNarrate();
      panelOpen = false;
      currentStop = null;
    }
    // #1142 show the full record INSIDE the walkthrough; "Back to gallery" returns instantly.
    function openRecordOverlay(url) {
      if (!url) return;
      var ov = document.getElementById('recOverlay'), fr = document.getElementById('recFrame'), ot = document.getElementById('recOpenTab');
      if (!ov) { window.open(url, '_blank'); return; }
      fr.src = url; if (ot) ot.href = url; ov.style.display = 'block';
      try { if (controls && controls.unlock) controls.unlock(); } catch (e) {}   // free the cursor so you can read/close
    }
    function closeRecordOverlay() {
      var ov = document.getElementById('recOverlay'), fr = document.getElementById('recFrame');
      if (ov) ov.style.display = 'none';
      if (fr) fr.src = 'about:blank';   // stop the record loading/playing behind the gallery
    }
    function viewFullDetails() { if (currentStop && currentStop.record_url) openRecordOverlay(currentStop.record_url); }
    (function () {
      var rc = document.getElementById('recClose'); if (rc) rc.addEventListener('click', function (e) { e.stopPropagation(); closeRecordOverlay(); });
      var ir = document.getElementById('inlayRec'); if (ir) ir.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); if (ir.getAttribute('href') && ir.getAttribute('href') !== '#') openRecordOverlay(ir.getAttribute('href')); });
    })();
    document.getElementById('inlayClose').addEventListener('click', function (e) { e.stopPropagation(); closeAllPopups(); });

    // ---- Walk-to navigator: travel the camera to an object and open its panel ----
    var fly = null;
    function flyTo(s) {
      var wp = worldPos(s);
      var rm = s._room || curRoom;
      var ccx = rm.x_offset + rm.w / 2, ccz = rm.z_offset + rm.d / 2;   // object's room centre
      var look = new THREE.Vector3(wp.x, 1.3, wp.z);
      var toC = new THREE.Vector3(ccx - wp.x, 0, ccz - wp.z);           // toward room centre
      if (toC.lengthSq() < 0.01) toC.set(0, 0, 1);
      toC.normalize();
      var stand = new THREE.Vector3(wp.x + toC.x * 2.6, 1.6, wp.z + toC.z * 2.6);
      var m = 0.6;
      stand.x = Math.max(rm.x_offset + m, Math.min(rm.x_offset + rm.w - m, stand.x));
      stand.z = Math.max(rm.z_offset + m, Math.min(rm.z_offset + rm.d - m, stand.z));
      // Map both the target and the look-at through the room's rotation so they
      // land on the object's actual (rotated) world position.
      var sw = roomWorld(rm, stand.x, stand.z), lw = roomWorld(rm, look.x, look.z);
      stand.set(sw.x, 1.6, sw.z); look.set(lw.x, 1.3, lw.z);
      fly = { from: controls.getObject().position.clone(), to: stand, look: look,
              targetFrom: orbit ? orbit.target.clone() : null, t: 0, dur: 0.9 };
      openPanel(s);
    }
    (function buildNav() {
      var nav = document.getElementById('roomNav');
      if (!nav) return;
      STOPS.forEach(function (s, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-sm btn-outline-secondary flex-shrink-0';
        b.innerHTML = '<span class="badge bg-primary me-1">' + (i + 1) + '</span>' +
          (s.title || ('#' + s.information_object_id)).replace(/[<>&]/g, '');
        b.title = '{{ __('Walk to this object') }}';
        b.addEventListener('click', function () { flyTo(s); });
        nav.appendChild(b);
      });
    })();

    // Click-to-select via centre crosshair while locked.
    var ray = new THREE.Raycaster();
    renderer.domElement.addEventListener('click', function (e) {
      if (window.__annotateMode) { placeGraffiti(e); return; }   // #1165 - graffiti mode: drop text where you look
      if (window.__figureMode && !aimingObject) { placeFigure(e); return; }   // figure tool: drop a person (but let object clicks through)
      // Left click acts like Esc: if a popup is open, close it (and nothing else).
      if (panelOpen) { closeAllPopups(); return; }
      var ndc;
      if (orbit) {
        var rect = renderer.domElement.getBoundingClientRect();
        ndc = { x: ((e.clientX - rect.left) / rect.width) * 2 - 1, y: -((e.clientY - rect.top) / rect.height) * 2 + 1 };
      } else {
        if (!controls.isLocked) return;
        ndc = { x: 0, y: 0 };
      }
      ray.setFromCamera(ndc, camera);
      var stealing = (keys['KeyS'] || window.__stealMode);
      var hits = ray.intersectObjects(pickables, true);
      if (hits.length) {
        var o = hits[0].object;
        while (o && !o.userData.stop && !o.userData.action) o = o.parent;
        if (o && o.userData.action === 'minimap') { toggleMinimap(true); return; }
        if (o && o.userData.action === 'door' && o.userData.doorDest) {   // click a door to jump to the room on the OTHER side
          var dDest = o.userData.doorDest, dHome = o.userData.doorHome;
          var target = (dHome && curRoom && dDest && curRoom.id === dDest.id) ? dHome : dDest;   // already in the dest (e.g. the open garden)? go back to the wall's room
          enterRoom(target); return;
        }
        if (o && o.userData.action === 'stair') {   // #1169 click stairs to change floor
          var cp = controls.getObject(), tp = o.userData.top, bt = o.userData.bot;
          var dest = (Math.abs(cp.position.y - bt.fy) < Math.abs(cp.position.y - tp.fy)) ? tp : bt;
          curFloorY = dest.fy; cp.position.set(dest.x, dest.fy + eyeBase, dest.z); return;
        }
        if (o && o.userData.stop) {
          if (stealing) { setStealBtn(false); stealAlarm(o.userData.stop); return; }   // "steal" -> sets off the alarm
          openPanel(o.userData.stop); if (keys['KeyT'] || keys['KeyG']) narrate(o.userData.stop, keys['KeyG']);   // T = talk; G = force a fresh AI description (#1167)
          return;
        }
      }
      if (stealing) { var ns = nearestStealable(); if (ns) { setStealBtn(false); stealAlarm(ns); } }   // missed: grab the closest object
    });

    // Mouse wheel moves forward / backward through the gallery.
    // When you look up at the ceiling, the viewer naturally lowers/leans back to
    // take it in; returns to standing height when looking level/down.
    var _wd = new THREE.Vector3();
    var eyeBase = 1.6;   // standing eye height (m); hold U + mouse wheel to stand taller / crouch
    var curFloorY = 0;   // #1169 Y of the floor the visitor is standing on (set by stairs)
    function eyeHeight() {
      camera.getWorldDirection(_wd);
      var up = Math.max(0, Math.min(1, (_wd.y - 0.2) / 0.7));
      return curFloorY + Math.max(0.35, eyeBase - up * 1.1);   // floor-aware; looking up leans the view
    }
    // --- Collision (#1169): walls block you; staircases are walkable ramps ---
    var _cray = new THREE.Raycaster(), _vo = new THREE.Vector3(), _vd = new THREE.Vector3();
    function moveBlocked(px, pz, dirx, dirz, dist) {
      if (!colliders.length) return false;
      _vo.set(px, curFloorY + 1.0, pz); _vd.set(dirx, 0, dirz);
      _cray.set(_vo, _vd); _cray.far = dist + 0.45;
      var h = _cray.intersectObjects(colliders, false);
      return h.length > 0 && h[0].distance < dist + 0.45;
    }
    function tryMove(o, dx, dz) {
      if (dx !== 0 && !moveBlocked(o.position.x, o.position.z, dx > 0 ? 1 : -1, 0, Math.abs(dx))) o.position.x += dx;
      if (dz !== 0 && !moveBlocked(o.position.x, o.position.z, 0, dz > 0 ? 1 : -1, Math.abs(dz))) o.position.z += dz;
    }
    function rampAt(x, z) {   // returns {y, x, z} clamped onto a staircase, or null
      for (var i = 0; i < stairRamps.length; i++) {
        var r = stairRamps[i];
        var t = ((x - r.ax) * r.ux + (z - r.az) * r.uz) / r.len;
        if (t < -0.05 || t > 1.05) continue;
        var perp = (x - r.ax) * (-r.uz) + (z - r.az) * r.ux;
        if (Math.abs(perp) > r.half) continue;
        var tc = Math.max(0, Math.min(1, t)), pc = Math.max(-r.half, Math.min(r.half, perp));
        return { y: r.ya + (r.yb - r.ya) * tc, x: r.ax + r.ux * (tc * r.len) - r.uz * pc, z: r.az + r.uz * (tc * r.len) + r.ux * pc };
      }
      return null;
    }
    function clampInRoom(o) {
      var m = 0.6;
      o.position.x = Math.max(BLD_minX + m, Math.min(BLD_maxX - m, o.position.x));
      o.position.z = Math.max(BLD_minZ + m, Math.min(BLD_maxZ - m, o.position.z));
      if (!PLAN_MODE) {   // auto-row: keep within current room's depth band
        var rm = roomAt(o.position.x, o.position.z);
        o.position.z = Math.max(rm.z_offset + m, Math.min(rm.z_offset + rm.d - m, o.position.z));
      }
      var rmp = rampAt(o.position.x, o.position.z);   // on a staircase: ride the slope, locked to its width
      if (rmp) { o.position.x = rmp.x; o.position.z = rmp.z; curFloorY = rmp.y; }
      // Floors change ONLY via stairs - never auto-drop the visitor onto a stacked room below (e.g. a basement).
      o.position.y += (eyeHeight() - o.position.y) * 0.3;   // smooth crouch/stand
    }
    renderer.domElement.addEventListener('wheel', function (e) {
      e.preventDefault();
      if (window.__figureMode) {   // figure tool: roll the wheel to cycle which person the pointer is
        figIdx = (figIdx + (e.deltaY < 0 ? 1 : FIG_KINDS.length - 1)) % FIG_KINDS.length;
        updateFigurePointer();
        return;
      }
      if (keys['KeyU']) {
        // Hold U + wheel: stand taller (roll up) / crouch down (roll down).
        eyeBase = Math.max(0.6, Math.min(2.2, eyeBase + (e.deltaY < 0 ? 1 : -1) * 0.1));
        var o = controls.getObject(); o.position.y = eyeHeight();
        var hh = document.getElementById('wtHeight');
        if (hh) { hh.textContent = '↕ ' + eyeBase.toFixed(2) + ' m'; hh.style.display = 'block'; clearTimeout(window._wtHT); window._wtHT = setTimeout(function () { hh.style.display = 'none'; }, 1200); }
        return;
      }
      controls.moveForward((e.deltaY < 0 ? 1 : -1) * 0.6);
      clampInRoom(controls.getObject());
    }, { passive: false });

    // Right-click releases pointer lock (frees the mouse). Listen on mousedown
    // (button 2) because the browser suppresses contextmenu while pointer-locked.
    renderer.domElement.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    renderer.domElement.addEventListener('mousedown', function (e) {
      if (e.button !== 2) return;
      e.preventDefault();
      if (controls.isLocked) controls.unlock();   // right-click frees the mouse cursor
      var hb = document.getElementById('roomHelp');   // #1166 - right-click shows the help/controls menu
      if (hb) hb.style.display = 'block';
    });

    // Help / controls overlay toggle.
    var helpBox = document.getElementById('roomHelp');
    document.getElementById('roomHelpBtn').addEventListener('click', function (e) {
      e.stopPropagation();
      helpBox.style.display = (helpBox.style.display === 'block') ? 'none' : 'block';
    });

    // ---- Building minimap: top-down plan, tap a room to teleport into it ----
    function enterRoom(rm) {
      var c = roomWorld(rm, rm.x_offset + rm.w / 2, rm.z_offset + rm.d / 2);
      curFloorY = (rm.floor || 0) * FLOOR_H;   // #1169 land on the room's floor
      controls.getObject().position.set(c.x, curFloorY + 1.6, c.z);
      if (orbit) {
        camera.position.set(c.x, curFloorY + 1.6, c.z + Math.min(rm.w, rm.d) * 0.6 + 1);
        orbit.target.set(c.x, curFloorY + 1.3, c.z); orbit.update();
      }
      curRoom = rm; toggleMinimap(false);
    }
    var miniFloor = null;   // which floor the minimap is showing
    function miniFloors() { var s = {}; ROOMS.forEach(function (r) { s[r.floor || 0] = 1; }); return Object.keys(s).map(Number).sort(function (a, b) { return a - b; }); }
    function miniFloorLabel(f) { return f === 0 ? '{{ __('Ground') }}' : (f < 0 ? ('{{ __('Basement') }}' + (f < -1 ? ' ' + (-f) : '')) : ('{{ __('Floor') }} ' + f)); }
    function buildMinimap() {
      var floors = miniFloors();
      var here = findRoomAtWorld(controls.getObject().position.x, controls.getObject().position.z, null);
      if (miniFloor === null || floors.indexOf(miniFloor) < 0) miniFloor = here ? (here.floor || 0) : (floors[0] || 0);
      var fr = ROOMS.filter(function (r) { return (r.floor || 0) === miniFloor; });
      var mnx = 1e9, mxx = -1e9, mnz = 1e9, mxz = -1e9;
      fr.forEach(function (rm) { [[rm.x_offset, rm.z_offset], [rm.x_offset + rm.w, rm.z_offset], [rm.x_offset, rm.z_offset + rm.d], [rm.x_offset + rm.w, rm.z_offset + rm.d]].forEach(function (c) { var w = roomWorld(rm, c[0], c[1]); if (w.x < mnx) mnx = w.x; if (w.x > mxx) mxx = w.x; if (w.z < mnz) mnz = w.z; if (w.z > mxz) mxz = w.z; }); });
      if (!isFinite(mnx)) { mnx = BLD_minX; mxx = BLD_maxX; mnz = BLD_minZ; mxz = BLD_maxZ; }
      var pad = 10, vw = 236, sx = (mxx - mnx) || 1, sz = (mxz - mnz) || 1;
      var sc = (vw - 2 * pad) / Math.max(sx, sz), vh = Math.round(sz * sc + 2 * pad);
      var P = function (x, z) { return ((x - mnx) * sc + pad) + ',' + ((z - mnz) * sc + pad); };
      var btns = floors.map(function (f) { return '<button type="button" class="btn btn-sm ' + (f === miniFloor ? 'btn-primary' : 'btn-outline-light') + ' py-0 px-1 me-1 mb-1 minifloor" data-f="' + f + '" style="font-size:10px">' + miniFloorLabel(f) + '</button>'; }).join('');
      var svg = '<div class="mb-1">' + btns + '</div><svg width="' + vw + '" height="' + vh + '" style="background:#11141a;border-radius:4px;display:block;">';
      fr.forEach(function (rm) {
        var i = ROOMS.indexOf(rm);
        var pts = [[rm.x_offset, rm.z_offset], [rm.x_offset + rm.w, rm.z_offset], [rm.x_offset + rm.w, rm.z_offset + rm.d], [rm.x_offset, rm.z_offset + rm.d]]
          .map(function (c) { var w = roomWorld(rm, c[0], c[1]); return P(w.x, w.z); }).join(' ');
        svg += '<polygon data-i="' + i + '" points="' + pts + '" fill="' + (rm === here ? '#0d6efd' : 'rgba(255,255,255,.16)') + '" stroke="#fff" stroke-width="1" style="cursor:pointer"/>';
        var cc = roomWorld(rm, rm.x_offset + rm.w / 2, rm.z_offset + rm.d / 2);
        svg += '<text x="' + ((cc.x - mnx) * sc + pad) + '" y="' + ((cc.z - mnz) * sc + pad + 3) + '" fill="#fff" font-size="9" text-anchor="middle" style="pointer-events:none">' + (rm.name || '').substring(0, 14) + '</text>';
      });
      svg += '</svg>';
      var el = document.getElementById('wtMiniSvg'); el.innerHTML = svg;
      el.querySelectorAll('polygon').forEach(function (p) { p.addEventListener('click', function () { enterRoom(ROOMS[+p.getAttribute('data-i')]); }); });
      el.querySelectorAll('.minifloor').forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); miniFloor = +b.getAttribute('data-f'); buildMinimap(); }); });
    }
    function toggleMinimap(show) {
      var m = document.getElementById('wtMinimap');
      if (show === undefined) show = (m.style.display === 'none' || !m.style.display);
      if (show) buildMinimap();
      m.style.display = show ? 'block' : 'none';
    }
    document.getElementById('roomMapBtn').addEventListener('click', function (e) { e.stopPropagation(); toggleMinimap(); });
    document.getElementById('wtMiniClose').addEventListener('click', function (e) { e.stopPropagation(); toggleMinimap(false); });

    // ---- Live data overlay: tint rooms by conservation status + readout HUD ----
    var liveOn = false;
    function toggleLive(show) {
      liveOn = (show === undefined) ? !liveOn : show;
      roomTints.forEach(function (t) { t.visible = liveOn; });
      var p = document.getElementById('wtLive'); if (p) p.style.display = liveOn ? 'block' : 'none';
      if (liveOn) updateLive();
    }
    function fmtLive(lv) {
      if (!lv || lv.status === 'none' || !lv.readings) return '<div class="text-white-50">{{ __('No live readings yet.') }}</div>';
      var r = lv.readings, parts = [];
      if (r.lux) parts.push('{{ __('Light') }}: ' + Math.round(r.lux.value) + ' lux' + (lv.lux_target ? ' / ' + Math.round(lv.lux_target) : ''));
      if (r.temp_c) parts.push('{{ __('Temp') }}: ' + r.temp_c.value + ' C');
      if (r.humidity) parts.push('{{ __('Humidity') }}: ' + r.humidity.value + '%');
      if (r.visitors) parts.push('{{ __('Visitors') }}: ' + Math.round(r.visitors.value));
      var col = { ok: '#7bd88f', warn: '#ffd454', alert: '#ff8a8a' }[lv.status] || '#cccccc';
      var reasons = (lv.reasons && lv.reasons.length) ? '<div class="text-white-50 mt-1" style="font-size:11px">' + lv.reasons.join('<br>') + '</div>' : '';
      return '<div style="color:' + col + ';font-weight:bold;text-transform:uppercase">' + lv.status + '</div><div>' + parts.join('<br>') + '</div>' + reasons;
    }
    function updateLive() {
      if (!liveOn) return;
      var pos = controls.getObject().position, r = findRoomAtWorld(pos.x, pos.z, null) || curRoom, body = document.getElementById('wtLiveBody');
      if (body && r) body.innerHTML = '<div class="fw-bold mb-1">' + (r.name || '') + '</div>' + fmtLive(r.live);
    }
    document.getElementById('roomLiveBtn').addEventListener('click', function (e) { e.stopPropagation(); toggleLive(); });

    // Distance-cull far rooms (whole groups) in large buildings to save draw cost.
    var CULL2 = 52 * 52;
    function cullRooms() {
      var p = controls.getObject().position;
      for (var k in roomGroups) { var rg = roomGroups[k]; var dx = p.x - rg.cwx, dz = p.z - rg.cwz; rg.g.visible = (dx * dx + dz * dz) < CULL2; }
    }

    // Header reflects the room the visitor is currently standing in.
    var _lastRoomId = null, _nameEl = document.getElementById('wtSpaceName');
    var _bldBtn = document.getElementById('editBuilderBtn'), _bldTmpl = _bldBtn ? _bldBtn.getAttribute('data-tmpl') : null;
    function updateRoomName() {
      var p = controls.getObject().position, r = findRoomAtWorld(p.x, p.z, null);
      if (r && r.id !== _lastRoomId) {
        _lastRoomId = r.id; if (_nameEl) _nameEl.textContent = r.name || '';
        // Edit-in-Builder follows you: it opens the builder for the room you're standing in.
        if (_bldBtn && _bldTmpl && r.slug) _bldBtn.setAttribute('href', _bldTmpl.replace('__SLUG__', r.slug));
      }
    }
    updateRoomName();

    // Movement loop
    var clock = new THREE.Clock();
    var vel = new THREE.Vector3();
    var _nameTick = 0;
    // heratio#1152 - VR locomotion: left thumbstick moves (headset-relative), right turns.
    function xrMove(dt) {
      var session = renderer.xr.getSession(); if (!session) return;
      var mx = 0, mz = 0, turn = 0;
      session.inputSources.forEach(function (src) {
        if (!src.gamepad) return; var ax = src.gamepad.axes || [];
        var x = (ax[2] !== undefined ? ax[2] : (ax[0] || 0)), y = (ax[3] !== undefined ? ax[3] : (ax[1] || 0));
        if (src.handedness === 'right') { turn += x; } else { mx += x; mz += y; }
      });
      if (Math.abs(mx) < 0.15) mx = 0; if (Math.abs(mz) < 0.15) mz = 0; if (Math.abs(turn) < 0.25) turn = 0;
      var o = controls.getObject();
      if (turn) o.rotation.y -= turn * dt * 1.6;
      if (mx || mz) {
        var cam = renderer.xr.getCamera(camera), dir = new THREE.Vector3(); cam.getWorldDirection(dir); dir.y = 0;
        if (dir.lengthSq() < 1e-4) return; dir.normalize();
        var right = new THREE.Vector3(dir.z, 0, -dir.x), sp = 2.5 * dt;
        o.position.x += (dir.x * (-mz) + right.x * mx) * sp;
        o.position.z += (dir.z * (-mz) + right.z * mx) * sp;
        following = false;
        o.position.x = Math.max(BLD_minX + 0.6, Math.min(BLD_maxX - 0.6, o.position.x));
        o.position.z = Math.max(BLD_minZ + 0.6, Math.min(BLD_maxZ - 0.6, o.position.z));
      }
    }
    function animate() {
      var dt = Math.min(0.05, clock.getDelta());
      if ((_nameTick = (_nameTick + 1) % 12) === 0) { updateRoomName(); cullRooms(); if (liveOn) updateLive(); }   // ~5x/sec
      if (renderer.xr.isPresenting) { xrMove(dt); if (window._wtPresenceFrame) window._wtPresenceFrame(dt); renderer.render(scene, camera); return; }
      if (fly) {
        fly.t += dt / fly.dur;
        var fk = Math.min(1, fly.t);
        var fe = fk * fk * (3 - 2 * fk);            // smoothstep
        var fo = controls.getObject();
        fo.position.lerpVectors(fly.from, fly.to, fe);
        fo.position.y = 1.6;
        if (orbit) {
          if (fly.targetFrom) orbit.target.lerpVectors(fly.targetFrom, fly.look, fe);
          orbit.update();
        } else {
          camera.lookAt(fly.look);
        }
        if (fk >= 1) fly = null;
      } else if (orbit) {
        orbit.update();
      } else if (controls.isLocked) {
        var speed = 4.0;
        vel.set(0, 0, 0);
        if (keys['KeyW'] || keys['ArrowUp']) vel.z += 1;
        if (keys['KeyS'] || keys['ArrowDown']) vel.z -= 1;
        if (keys['KeyA'] || keys['ArrowLeft']) vel.x -= 1;
        if (keys['KeyD'] || keys['ArrowRight']) vel.x += 1;
        if (vel.lengthSq() > 0) {
          following = false;                       // manual movement breaks docent-follow
          vel.normalize();
          var mo = controls.getObject(), b4x = mo.position.x, b4z = mo.position.z;
          controls.moveRight(vel.x * speed * dt);
          controls.moveForward(vel.z * speed * dt);
          var ddx = mo.position.x - b4x, ddz = mo.position.z - b4z;   // walls block; resolve per-axis so you can slide
          mo.position.x = b4x; mo.position.z = b4z;
          tryMove(mo, ddx, ddz);
        }
        clampInRoom(controls.getObject());
      }
      if (window._wtPresenceFrame) window._wtPresenceFrame(dt);
      if (spotObjects.length) {   // #1174 proximity spotlight + surroundings dim
        var scp = controls.getObject().position, near = 1e9, act = null;
        for (var si = 0; si < spotObjects.length; si++) { var so = spotObjects[si], dd = Math.hypot(scp.x - so.x, scp.z - so.z) + Math.abs(scp.y - so.y) * 0.5; if (dd < near) { near = dd; act = so; } }
        var prox = Math.max(0, Math.min(1, (6.5 - near) / 6.5));
        for (var sj = 0; sj < spotObjects.length; sj++) {
          var s2 = spotObjects[sj];
          var base = s2.mode === 2 ? 2.2 : 0; var tgt = base + ((s2 === act) ? prox * 3.0 : 0);
          s2.light.intensity += (tgt - s2.light.intensity) * Math.min(1, dt * 5);
          if (s2.mat) {   // per-item glow: brighten the (unlit) picture so the spotlight is visible on it
            var gTgt = (s2.mode === 2) ? (1.55 + (s2 === act ? prox * 0.5 : 0)) : (1.0 + ((s2 === act) ? prox * 1.0 : 0));
            var nv = s2.mat.color.r + (gTgt - s2.mat.color.r) * Math.min(1, dt * 5);
            s2.mat.color.setRGB(nv, nv, nv);
          }
        }
        // Per-item lighting: the spotlight makes the OBJECT pop without darkening the whole room (gentle ambient dip only).
        hemiLight.intensity += ((hemiBase - prox * hemiBase * 0.22) - hemiLight.intensity) * Math.min(1, dt * 5);
      }
      if (typeof applyZoom === 'function') applyZoom(dt);   // #1163 smooth zoom
      updateAimPointer(dt);   // swap person<->hand pointer depending on whether you aim at an object
      if (sunDyn.intensity > 0) {   // #shadows: keep the sun's shadow frustum centred on the visitor so shadows stay sharp
        var scp = controls.getObject().position, st = SUN_TIMES[sunMode];
        if (st) { sunTarget.position.set(scp.x, 0, scp.z); sunDyn.position.set(scp.x + st.off[0], st.off[1], scp.z + st.off[2]); }
      }
      if (frontDoors.length) {   // #1171 doors swing/slide open as you approach, close as you leave
        var fcp = controls.getObject().position;
        for (var fi = 0; fi < frontDoors.length; fi++) {
          var fd = frontDoors[fi], fdist = Math.hypot(fcp.x - fd.x, fcp.z - fd.z);
          fd.open += ((fdist < 3.4 ? 1 : 0) - fd.open) * Math.min(1, dt * 4);
          for (var li = 0; li < fd.leaves.length; li++) {
            var lf = fd.leaves[li];
            if (fd.slide) lf.pivot.position.x = lf.hingeX - lf.sign * fd.open * lf.lw;   // slide into the wall
            else lf.pivot.rotation.y = lf.sign * fd.open * 1.65;                          // swing on the hinge
          }
        }
      }
      renderer.render(scene, camera);
    }

    // ===== heratio#1150 - multi-user presence + live docent (HTTP polling) =====
    var PRESENCE_BEAT = '{{ route('exhibition-space.presence.beat', ['slug' => $space->slug]) }}';
    var PRESENCE_LEAVE = '{{ route('exhibition-space.presence.leave', ['slug' => $space->slug]) }}';
    var VISIT_EVENT = '{{ route('exhibition-space.visit-event', ['slug' => $space->slug]) }}';   // #1173
    var WT_CSRF = '{{ csrf_token() }}';
    var CAN_DOCENT = {{ ($canDocent ?? false) ? 'true' : 'false' }};
    var MY_TOKEN = sessionStorage.getItem('wt_token');
    if (!MY_TOKEN) { MY_TOKEN = 'p' + Math.random().toString(36).slice(2, 12); sessionStorage.setItem('wt_token', MY_TOKEN); }
    function wtHashHue(s) { var h = 0; for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) % 360; return h; }
    var MY_COLOR = 'hsl(' + wtHashHue(MY_TOKEN) + ',70%,55%)';
    var MY_NAME = sessionStorage.getItem('wt_name') || (CAN_DOCENT ? '{{ __('Docent') }}' : '{{ __('Visitor') }}');
    var myTourActive = false, myFocus = 0, myDocentMsg = '';
    var following = false, followTarget = null, lastFocusSeen = 0;
    var avatars = {};   // token -> { grp, tx, tz, tyaw, role }

    function wtHueColor(hue) { var c = new THREE.Color(); c.setHSL((hue % 360) / 360, 0.7, 0.55); return c; }
    function stopByIo(ioId) { for (var i = 0; i < STOPS.length; i++) { if (STOPS[i].information_object_id === ioId) return STOPS[i]; } return null; }
    function makeAvatar(p) {
      var grp = new THREE.Group();
      var body = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.3, 1.1, 12), new THREE.MeshStandardMaterial({ color: wtHueColor(wtHashHue(p.token || p.name || 'x')) }));
      body.position.y = 0.75; grp.add(body);
      var head = new THREE.Mesh(new THREE.SphereGeometry(0.22, 16, 12), new THREE.MeshStandardMaterial({ color: 0xf1d6b8 })); head.position.y = 1.5; grp.add(head);
      if (p.role === 'docent') { var ring = new THREE.Mesh(new THREE.TorusGeometry(0.3, 0.04, 8, 20), new THREE.MeshStandardMaterial({ color: 0xffd24a, emissive: 0x5a4600 })); ring.rotation.x = Math.PI / 2; ring.position.y = 2.0; grp.add(ring); }
      var lab = makeTextSprite((p.role === 'docent' ? '★ ' : '') + (p.name || 'Visitor'), 0.34); lab.position.y = 2.2; grp.add(lab);
      scene.add(grp); return grp;
    }
    function applyPeers(peers) {
      var seen = {};
      peers.forEach(function (p) {
        seen[p.token] = 1;
        var a = avatars[p.token];
        if (!a) { a = { grp: makeAvatar(p), role: p.role }; avatars[p.token] = a; a.grp.position.set(p.x || 0, 0, p.z || 0); }
        else if (a.role !== p.role) { scene.remove(a.grp); a.grp = makeAvatar(p); a.role = p.role; }   // role changed -> rebuild
        a.tx = (p.x != null ? p.x : a.grp.position.x); a.tz = (p.z != null ? p.z : a.grp.position.z); a.tyaw = (p.yaw != null ? p.yaw : 0);
      });
      Object.keys(avatars).forEach(function (t) { if (!seen[t]) { scene.remove(avatars[t].grp); delete avatars[t]; } });
      var cnt = document.getElementById('wtPeopleCount'); if (cnt) cnt.textContent = (peers.length + 1);
      var list = document.getElementById('wtPeopleList');
      if (list) { var html = '<div>• {{ __('You') }}' + (CAN_DOCENT && myTourActive ? ' ★' : '') + '</div>'; peers.forEach(function (p) { html += '<div>• ' + (p.role === 'docent' ? '★ ' : '') + (p.name || 'Visitor').replace(/[<>&]/g, '') + '</div>'; }); list.innerHTML = html; }
    }
    function applyTour(tour) {
      var banner = document.getElementById('wtDocentBanner'), followBtn = document.getElementById('wtFollowBtn');
      var theirTour = tour && tour.docent_token !== MY_TOKEN;
      if (theirTour) {
        if (followBtn) followBtn.style.display = 'block';
        followTarget = (following && tour.x != null) ? { x: tour.x, z: tour.z } : null;
        if (following && tour.focus_object_id && tour.focus_object_id !== lastFocusSeen) { lastFocusSeen = tour.focus_object_id; var st = stopByIo(tour.focus_object_id); if (st) flyTo(st); }
        if (banner) { banner.textContent = (following ? '{{ __('Following') }} ' : '{{ __('Guided tour live:') }} ') + (tour.docent_name || 'Docent') + (tour.msg ? (' — ' + tour.msg) : ''); banner.style.display = 'block'; }
      } else {
        if (followBtn) followBtn.style.display = 'none';
        following = false; followTarget = null;
        if (banner) banner.style.display = 'none';
      }
      if (CAN_DOCENT && myTourActive && banner) { banner.textContent = '{{ __('You are leading a tour') }}' + (myDocentMsg ? (' — ' + myDocentMsg) : ''); banner.style.display = 'block'; }
    }
    function wtBeat() {
      var pos = controls.getObject().position, rm = findRoomAtWorld(pos.x, pos.z, null);
      var dir = new THREE.Vector3(); camera.getWorldDirection(dir);
      var device = (renderer.xr && renderer.xr.isPresenting) ? 'vr' : (isTouch ? 'mobile' : 'desktop');
      var payload = { token: MY_TOKEN, name: MY_NAME, color: MY_COLOR, role: (CAN_DOCENT && myTourActive ? 'docent' : 'visitor'),
        room_id: (rm ? rm.id : null), x: pos.x, y: pos.y, z: pos.z, yaw: Math.atan2(dir.x, dir.z), device: device,
        tour_active: (myTourActive ? 1 : 0), focus_object_id: myFocus, docent_msg: myDocentMsg };
      fetch(PRESENCE_BEAT, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': WT_CSRF, 'Accept': 'application/json' }, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); }).then(function (d) { if (d && d.ok) { applyPeers(d.peers || []); applyTour(d.tour || null); } }).catch(function () {});
    }
    window._wtPresenceFrame = function (dt) {
      var k = Math.min(1, dt * 8);
      Object.keys(avatars).forEach(function (t) {
        var a = avatars[t]; if (a.tx == null) return;
        a.grp.position.x += (a.tx - a.grp.position.x) * k; a.grp.position.z += (a.tz - a.grp.position.z) * k;
        var d = a.tyaw - a.grp.rotation.y; while (d > Math.PI) d -= 2 * Math.PI; while (d < -Math.PI) d += 2 * Math.PI; a.grp.rotation.y += d * k;
      });
      if (following && followTarget) { var o = controls.getObject(); o.position.x += (followTarget.x - o.position.x) * Math.min(1, dt * 2); o.position.z += (followTarget.z - o.position.z) * Math.min(1, dt * 2); }
    };
    // UI wiring
    (function () {
      var pBtn = document.getElementById('wtPeopleBtn'), panel = document.getElementById('wtPeople');
      if (pBtn) pBtn.addEventListener('click', function (e) { e.stopPropagation(); panel.style.display = (panel.style.display === 'block' ? 'none' : 'block'); });
      var pc = document.getElementById('wtPeopleClose'); if (pc) pc.addEventListener('click', function () { panel.style.display = 'none'; });
      var ni = document.getElementById('wtNameInput'); if (ni) { ni.value = MY_NAME; ni.addEventListener('change', function () { MY_NAME = (this.value || '').slice(0, 40) || 'Visitor'; sessionStorage.setItem('wt_name', MY_NAME); wtBeat(); }); }
      var fb = document.getElementById('wtFollowBtn'); if (fb) fb.addEventListener('click', function () { following = !following; fb.classList.toggle('btn-warning', !following); fb.classList.toggle('btn-secondary', following); fb.innerHTML = following ? '<i class="fas fa-xmark me-1"></i>{{ __('Stop following') }}' : '<i class="fas fa-shoe-prints me-1"></i>{{ __('Follow the docent') }}'; });
      var tb = document.getElementById('wtTourBtn'), dm = document.getElementById('wtDocentMsg');
      if (tb) tb.addEventListener('click', function () { myTourActive = !myTourActive; if (!myTourActive) { myFocus = 0; myDocentMsg = ''; if (dm) dm.value = ''; } tb.classList.toggle('btn-success', !myTourActive); tb.classList.toggle('btn-danger', myTourActive); tb.innerHTML = myTourActive ? '<i class="fas fa-stop me-1"></i>{{ __('Stop tour') }}' : '<i class="fas fa-chalkboard-user me-1"></i>{{ __('Start guided tour') }}'; if (dm) dm.style.display = myTourActive ? 'block' : 'none'; wtBeat(); });
      if (dm) dm.addEventListener('input', function () { myDocentMsg = (this.value || '').slice(0, 200); });
    })();
    setInterval(wtBeat, 450); wtBeat();
    window.addEventListener('pagehide', function () { try { var fd = new FormData(); fd.append('_token', WT_CSRF); fd.append('token', MY_TOKEN); navigator.sendBeacon(PRESENCE_LEAVE, fd); } catch (e) {} });

    // ===== heratio#1163/#1164/#1165 - zoom, torch, wall graffiti =====
    // #1163 Zoom: Z toggles a telephoto FOV so you can inspect detail from where you stand.
    var BASE_FOV = camera.fov, zoomOn = false;
    function toggleZoom() { zoomOn = !zoomOn; }
    function applyZoom(dt) {
      var target = zoomOn ? 26 : BASE_FOV, f = camera.fov + (target - camera.fov) * Math.min(1, dt * 8);
      if (Math.abs(f - camera.fov) > 0.01) { camera.fov = f; camera.updateProjectionMatrix(); }
    }
    // #1164 Torch: F toggles a headlamp spotlight for dark corners.
    var torch = new THREE.SpotLight(0xfff3da, 0, 22, Math.PI / 5, 0.4, 1.2);
    torch.position.set(0, 0, 0); camera.add(torch);
    torch.target.position.set(0, 0, -1); camera.add(torch.target);
    if (!scene.children.includes(camera)) scene.add(camera);   // ensure camera (with torch) is in the graph
    function toggleTorch() { torch.intensity = torch.intensity > 0 ? 0 : 2.4; }
    // #1165 Wall graffiti / annotations.
    var ANNOTATIONS = @json($annotations ?? []);
    var ANNOT_URL = '{{ route('exhibition-space.annotation', ['slug' => $space->slug]) }}';
    var ANNOT_DEL_URL = '{{ route('exhibition-space.annotation.delete', ['slug' => $space->slug, 'id' => '__ID__']) }}';
    var graffitiSprites = [];
    function makeGraffitiSprite(text, color) {
      var cv = document.createElement('canvas'), cx = cv.getContext('2d');
      cx.font = 'bold 64px "Comic Sans MS", "Marker Felt", cursive';
      var w = Math.min(1400, cx.measureText(text).width + 60);
      cv.width = w; cv.height = 110;
      cx.font = 'bold 64px "Comic Sans MS", "Marker Felt", cursive';
      cx.lineWidth = 7; cx.strokeStyle = 'rgba(0,0,0,.55)'; cx.textBaseline = 'middle';
      cx.fillStyle = color || '#e23b3b';
      cx.strokeText(text, 18, 58); cx.fillText(text, 18, 58);
      var tx = new THREE.CanvasTexture(cv); tx.minFilter = THREE.LinearFilter; tx.needsUpdate = true;
      var sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tx, transparent: true, depthWrite: false }));
      sp.scale.set(w / 110 * 0.9, 0.9, 1);
      return sp;
    }
    function addGraffiti(a) {
      var sp = makeGraffitiSprite(a.text, a.color); sp.position.set(a.x, a.y, a.z);
      sp.userData.graffiti = true; sp.userData.graffitiId = a.id || null; scene.add(sp);
      graffitiSprites.push(sp); return sp;
    }
    (ANNOTATIONS || []).forEach(addGraffiti);   // render existing graffiti
    function placeGraffiti(e) {
      var ndc;
      if (orbit) { var r = renderer.domElement.getBoundingClientRect(); ndc = { x: ((e.clientX - r.left) / r.width) * 2 - 1, y: -((e.clientY - r.top) / r.height) * 2 + 1 }; }
      else { if (!controls.isLocked) { setAnnotate(false); return; } ndc = { x: 0, y: 0 }; }
      ray.setFromCamera(ndc, camera);
      // In graffiti mode, clicking an existing tag deletes it.
      var gh = ray.intersectObjects(graffitiSprites, false);
      if (gh.length) {
        var sp = gh[0].object;
        if (window.confirm('{{ __('Delete this graffiti?') }}')) {
          if (sp.userData.graffitiId) { fetch(ANNOT_DEL_URL.replace('__ID__', sp.userData.graffitiId), { method: 'POST', headers: { 'X-CSRF-TOKEN': WT_CSRF, 'Accept': 'application/json' } }).catch(function () {}); }
          scene.remove(sp); var gi = graffitiSprites.indexOf(sp); if (gi >= 0) graffitiSprites.splice(gi, 1);
        }
        setAnnotate(false); return;
      }
      var hits = ray.intersectObjects(scene.children, true).filter(function (h) { return !(h.object.userData && h.object.userData.graffiti) && h.distance > 0.4; });
      if (!hits.length) { setAnnotate(false); return; }
      var p = hits[0].point, txt = window.prompt('{{ __('Graffiti text (leave blank to cancel):') }}', '');
      if (!txt) { setAnnotate(false); return; }
      var rm = findRoomAtWorld(p.x, p.z, null);
      var a = { x: p.x, y: p.y, z: p.z, text: txt.slice(0, 160), room_id: (rm ? rm.id : null), color: '#e23b3b',
        author: (typeof MY_NAME !== 'undefined' ? MY_NAME : '') };
      var newSp = addGraffiti(a);
      var body = 'text=' + encodeURIComponent(a.text) + '&x=' + a.x + '&y=' + a.y + '&z=' + a.z + '&room_id=' + (a.room_id || '') + '&color=' + encodeURIComponent(a.color) + '&author=' + encodeURIComponent(a.author) + '&_token=' + encodeURIComponent(WT_CSRF);
      fetch(ANNOT_URL, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-TOKEN': WT_CSRF, 'Accept': 'application/json' }, body: body })
        .then(function (r) { return r.json(); }).then(function (d) { if (d && d.annotation && d.annotation.id) newSp.userData.graffitiId = d.annotation.id; }).catch(function () {});
      setAnnotate(false);
    }
    function setAnnotate(on) {
      window.__annotateMode = on;
      var b = document.getElementById('wtGraffitiBtn'); if (b) { b.classList.toggle('btn-danger', on); b.classList.toggle('btn-dark', !on); }
      var ch = document.getElementById('roomCrosshair'); if (ch && on && controls.isLocked) ch.style.display = 'block';
    }
    var gBtn = document.getElementById('wtGraffitiBtn');
    if (gBtn) gBtn.addEventListener('click', function (e) { e.stopPropagation(); setAnnotate(!window.__annotateMode); });
    var tBtn = document.getElementById('wtTorchBtn');
    if (tBtn) tBtn.addEventListener('click', function (e) { e.stopPropagation(); toggleTorch(); tBtn.classList.toggle('btn-warning', torch.intensity > 0); tBtn.classList.toggle('btn-dark', torch.intensity === 0); });

    // ---- Figure tool: pointer becomes a person, wheel cycles man/woman, click drops a billboard figure ----
    var FIG_KINDS = ['man', 'woman'], figIdx = 0;
    function updateFigurePointer() { var ov = document.getElementById('figurePointer'); if (ov) ov.src = personCanvas(FIG_KINDS[figIdx]).toDataURL(); }
    function setFigureMode(on) {
      window.__figureMode = on;
      var b = document.getElementById('wtFigureBtn'); if (b) { b.classList.toggle('btn-warning', on); b.classList.toggle('btn-dark', !on); }
      var ov = document.getElementById('figurePointer'); if (ov) ov.style.display = on ? 'block' : 'none';
      var hint = document.getElementById('figureHint'); if (hint) hint.style.display = on ? 'block' : 'none';
      var ch = document.getElementById('roomCrosshair'); if (ch && on) ch.style.display = 'none';
      if (on) updateFigurePointer();
    }
    function placeFigure(e) {
      var ndc = { x: 0, y: 0 };
      if (orbit) { var r = renderer.domElement.getBoundingClientRect(); ndc = { x: ((e.clientX - r.left) / r.width) * 2 - 1, y: -((e.clientY - r.top) / r.height) * 2 + 1 }; }
      else if (!controls.isLocked) { return; }
      ray.setFromCamera(ndc, camera);
      var hits = ray.intersectObjects(scene.children, true).filter(function (h) { return h.distance > 0.5; });
      if (!hits.length) { return; }
      var p = hits[0].point, kind = FIG_KINDS[figIdx], pth = 1.75, ptw = pth * 0.42;
      var pmat = new THREE.MeshStandardMaterial({ map: personTexture(kind), transparent: true, alphaTest: 0.5, side: THREE.DoubleSide, roughness: 1 });
      var grp = new THREE.Group();
      var pm1 = new THREE.Mesh(new THREE.PlaneGeometry(ptw, pth), pmat); grp.add(pm1);
      var pm2 = pm1.clone(); pm2.rotation.y = Math.PI / 2; grp.add(pm2);
      grp.position.set(p.x, p.y + pth / 2, p.z); grp.userData.figure = true;
      if (_sunShadowsApplied) { grp.traverse(function (n) { if (n.isMesh) { n.castShadow = true; } }); }
      scene.add(grp);
    }
    var figBtn = document.getElementById('wtFigureBtn');
    if (figBtn) figBtn.addEventListener('click', function (e) { e.stopPropagation(); setFigureMode(!window.__figureMode); });

    // Pointer styles: person (figure tool) / pointer-hand (over a clickable object) / circle (annotate).
    // Aiming the centre ray at an object swaps the person away for a plain hand so you can interact with it.
    var aimingObject = false, _aimAcc = 0;
    function updateAimPointer(dt) {
      if (orbit || !controls.isLocked) { aimingObject = false; var op0 = document.getElementById('objectPointer'); if (op0) op0.style.display = 'none'; return; }
      _aimAcc += dt; if (_aimAcc < 0.12) return; _aimAcc = 0;
      ray.setFromCamera({ x: 0, y: 0 }, camera);
      var hits = ray.intersectObjects(pickables, true), onObj = false;
      if (hits.length) { var o = hits[0].object; while (o && !o.userData.stop && !o.userData.action) o = o.parent; onObj = !!(o && (o.userData.stop || o.userData.action)); }
      aimingObject = onObj;
      var op = document.getElementById('objectPointer'), fp = document.getElementById('figurePointer');
      if (op) op.style.display = onObj ? 'block' : 'none';
      if (fp) fp.style.display = (window.__figureMode && !onObj) ? 'block' : 'none';   // person hides over an object
    }
    var sunBtn = document.getElementById('wtSunBtn');
    if (sunBtn) sunBtn.addEventListener('click', function (e) { e.stopPropagation(); setSun(sunMode + 1); });

    // Fullscreen the 3D viewer (resizes the renderer to fill the screen).
    (function () {
      var fb = document.getElementById('wtFsBtn'); if (!fb) return;
      var icon = fb.querySelector('i');
      function fsEl() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
      function resize() { var w = room.clientWidth, h = room.clientHeight; if (renderer) renderer.setSize(w, h); if (camera) { camera.aspect = w / h; camera.updateProjectionMatrix(); } }
      function sync() {
        var on = !!fsEl();
        if (icon) icon.className = on ? 'fas fa-compress' : 'fas fa-expand';
        room.style.height = on ? '100vh' : '70vh';
        setTimeout(resize, 120);
      }
      fb.addEventListener('click', function (e) {
        e.stopPropagation();
        if (fsEl()) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); }
        else { var rq = room.requestFullscreen || room.webkitRequestFullscreen; if (rq) rq.call(room); }
      });
      document.addEventListener('fullscreenchange', sync);
      document.addEventListener('webkitfullscreenchange', sync);
    })();

    // ===== authored audio guided tours (guide flies you around + narrates) =====
    var TOURS = @json($guidedTour ?? []);
    var tourState = { i: 0, playing: false, timer: null, stops: [] };
    var tourPlayBtn = document.getElementById('wtTourPlayBtn');
    var tourSelEl = document.getElementById('wtTourSel'), tourPick = document.getElementById('wtTourPick');
    if (tourPlayBtn && TOURS.length) tourPlayBtn.style.display = 'block';
    if (tourSelEl && TOURS.length) {
      TOURS.forEach(function (t, i) { var o = document.createElement('option'); o.value = i; o.textContent = t.name || ('Tour ' + (i + 1)); tourSelEl.appendChild(o); });
      if (TOURS.length > 1 && tourPick) tourPick.style.display = 'block';   // only show the picker when there is a choice
    }
    function tourBanner(txt) { var b = document.getElementById('wtTourBanner'), t = document.getElementById('wtTourText'); if (t) t.textContent = txt; if (b) b.style.display = 'block'; }
    function updateTourBtn() { if (!tourPlayBtn) return; tourPlayBtn.innerHTML = tourState.playing ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>'; tourPlayBtn.classList.toggle('btn-warning', tourState.playing); tourPlayBtn.classList.toggle('btn-success', !tourState.playing); }
    function tourGoto(i) {
      var arr = tourState.stops;
      if (i >= arr.length) { tourStop(); return; }
      tourState.i = i; var s = arr[i], st = stopByIo(s.io_id);
      tourBanner((i + 1) + '/' + arr.length + '   ' + (st ? st.title : '') + (s.narration ? ('   -   ' + s.narration) : ''));
      if (st) flyTo(st);
      var advance = function () { if (!tourState.playing) return; clearTimeout(tourState.timer); tourState.timer = setTimeout(function () { tourGoto(i + 1); }, Math.max(2, (s.dwell || 6)) * 1000); };
      if (s.narration) { speakText((st ? st.title + '. ' : '') + s.narration, advance); }
      else { fetch('/exhibition-space/object/' + s.io_id + '/describe', { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { speakText((st ? st.title + '. ' : '') + ((d && d.description) || ''), advance); }).catch(advance); }
    }
    var tourQuick = document.getElementById('wtTourQuick'), tourQuickSel = document.getElementById('wtTourQuickSel');
    function quickShow(on) { if (tourQuick && isTouch && TOURS.length) tourQuick.style.display = on ? 'block' : 'none'; }
    function tourPlay(forceIdx) {
      if (!TOURS.length) return;
      var idx = (typeof forceIdx === 'number') ? forceIdx : (tourSelEl ? (+tourSelEl.value || 0) : 0);
      tourState.stops = (TOURS[idx] && TOURS[idx].stops) || [];
      if (!tourState.stops.length) return;
      quickShow(false);
      tourState.playing = true; updateTourBtn(); tourGoto(tourState.i || 0);
    }
    function tourPause() { tourState.playing = false; clearTimeout(tourState.timer); stopNarrate(); updateTourBtn(); }
    function tourStop() { tourState.playing = false; tourState.i = 0; clearTimeout(tourState.timer); stopNarrate(); var b = document.getElementById('wtTourBanner'); if (b) b.style.display = 'none'; updateTourBtn(); quickShow(true); }
    if (tourPlayBtn) tourPlayBtn.addEventListener('click', function (e) { e.stopPropagation(); tourState.playing ? tourPause() : tourPlay(); });
    var tourStopBtn = document.getElementById('wtTourStopBtn'); if (tourStopBtn) tourStopBtn.addEventListener('click', function (e) { e.stopPropagation(); tourStop(); });
    if (tourSelEl) tourSelEl.addEventListener('change', function () { tourStop(); });   // switching tour resets to start
    // Mobile quick-launch: walking is hard on touch, so a big button just plays the tour.
    if (tourQuick && isTouch && TOURS.length) {
      tourQuick.style.display = 'block';
      if (TOURS.length > 1 && tourQuickSel) {
        tourQuickSel.classList.remove('d-none');
        TOURS.forEach(function (t, i) { var o = document.createElement('option'); o.value = i; o.textContent = t.name || ('Tour ' + (i + 1)); tourQuickSel.appendChild(o); });
      }
      var qb = document.getElementById('wtTourQuickBtn');
      if (qb) qb.addEventListener('click', function (e) { e.stopPropagation(); tourPlay(tourQuickSel ? (+tourQuickSel.value || 0) : 0); });
    }

    // ===== narration voice selection =====
    function populateVoices() {
      if (!('speechSynthesis' in window)) return;
      var sel = document.getElementById('wtVoiceSel'); if (!sel) return;
      var vs = window.speechSynthesis.getVoices() || []; if (!vs.length) return;
      var saved = sessionStorage.getItem('wt_voice');
      sel.innerHTML = '<option value="">{{ __('Default') }}</option>';
      vs.forEach(function (v) { var o = document.createElement('option'); o.value = v.name; o.textContent = v.name + ' (' + v.lang + ')'; if (v.name === saved) { o.selected = true; WT_VOICE = v; } sel.appendChild(o); });
      sel.onchange = function () { var v = vs.filter(function (x) { return x.name === sel.value; })[0] || null; WT_VOICE = v; if (v) { sessionStorage.setItem('wt_voice', v.name); speakText('{{ __('Voice selected.') }}'); } else { sessionStorage.removeItem('wt_voice'); } };
    }
    if ('speechSynthesis' in window) { populateVoices(); window.speechSynthesis.onvoiceschanged = populateVoices; }

    // ===== "steal" easter egg: click an object in steal mode (or S+click) -> alarm =====
    var alarmState = { on: false, flick: null, timer: null, ac: null, osc: null, pulse: null, gain: null };
    var _stealWp = new THREE.Vector3();
    function nearestStealable() {   // closest placed object to the visitor (forgiving aim, esp. mobile)
      var cp = controls.getObject().position, best = null, bd = Infinity;
      pickables.forEach(function (o) { if (!o.userData || !o.userData.stop) return; o.getWorldPosition(_stealWp); var d = _stealWp.distanceTo(cp); if (d < bd) { bd = d; best = o.userData.stop; } });
      return best;
    }
    function alarmBeep(start) {
      try {
        if (start) {
          if (!alarmState.ac) alarmState.ac = new (window.AudioContext || window.webkitAudioContext)();
          var ac = alarmState.ac; if (ac.state === 'suspended') ac.resume();
          alarmState.osc = ac.createOscillator(); alarmState.gain = ac.createGain();
          alarmState.osc.type = 'square'; alarmState.osc.frequency.value = 820; alarmState.gain.gain.value = 0;
          alarmState.osc.connect(alarmState.gain); alarmState.gain.connect(ac.destination); alarmState.osc.start();
          alarmState.pulse = setInterval(function () { alarmState.gain.gain.value = alarmState.gain.gain.value > 0.01 ? 0 : 0.12; alarmState.osc.frequency.value = alarmState.osc.frequency.value > 800 ? 620 : 920; }, 250);
        } else {
          if (alarmState.pulse) clearInterval(alarmState.pulse);
          if (alarmState.osc) { try { alarmState.osc.stop(); } catch (e) {} alarmState.osc = null; }
        }
      } catch (e) {}
    }
    function stealAlarm(s) {
      if (alarmState.on) return; alarmState.on = true;
      var ov = document.getElementById('wtAlarm'), bar = document.getElementById('wtAlarmBar'), txt = document.getElementById('wtAlarmText');
      if (txt) txt.textContent = '{{ __('ALARM! Put') }} ' + (s && s.title ? s.title : '{{ __('that') }}') + ' {{ __('back!') }}';
      if (ov) ov.style.display = 'block'; if (bar) bar.style.display = 'block';
      var f = false;
      alarmState.flick = setInterval(function () { f = !f; if (ov) ov.style.opacity = f ? '0.4' : '0'; hemiLight.intensity = f ? 0.25 : 0.9; hemiLight.color.setHex(f ? 0xff3030 : 0xffffff); }, 130);
      alarmBeep(true);
      alarmState.timer = setTimeout(stopAlarm, 5000);   // auto switch-off after 5s
    }
    function stopAlarm() {
      if (!alarmState.on) return; alarmState.on = false;
      clearInterval(alarmState.flick); clearTimeout(alarmState.timer); alarmBeep(false);
      var ov = document.getElementById('wtAlarm'), bar = document.getElementById('wtAlarmBar');
      if (ov) { ov.style.opacity = '0'; ov.style.display = 'none'; } if (bar) bar.style.display = 'none';
      hemiLight.intensity = 0.9; hemiLight.color.setHex(0xffffff);
    }
    var alarmOff = document.getElementById('wtAlarmOff'); if (alarmOff) alarmOff.addEventListener('click', function (e) { e.stopPropagation(); stopAlarm(); });
    function setStealBtn(on) { window.__stealMode = on; var b = document.getElementById('wtStealBtn'); if (b) { b.classList.toggle('btn-danger', on); b.classList.toggle('btn-dark', !on); } }
    var stealBtn = document.getElementById('wtStealBtn'); if (stealBtn) stealBtn.addEventListener('click', function (e) { e.stopPropagation(); setStealBtn(!window.__stealMode); });

    renderer.setAnimationLoop(animate);   // #1152 - drives both desktop and WebXR frames

    window.addEventListener('resize', function () {
      W = room.clientWidth || W; H = room.clientHeight || H;
      camera.aspect = W / H; camera.updateProjectionMatrix();
      renderer.setSize(W, H);
    });
  })();
  </script>
  @endif
@endsection
