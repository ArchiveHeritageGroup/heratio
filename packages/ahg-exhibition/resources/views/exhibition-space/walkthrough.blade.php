{{-- heratio#1138 — Digital Twin: first-person 3D walkthrough (Phase 3, Three.js). --}}
@extends('theme::layouts.1col')

@section('title', __('3D Walkthrough') . ' — ' . $space->name)
@section('body-class', 'exhibition-space walkthrough-3d')

@section('content')
  <div class="d-flex flex-wrap align-items-baseline mb-2 gap-2">
    <h1 class="mb-0 flex-grow-1">
      <i class="fas fa-vr-cardboard me-2"></i>{{ __('3D Walkthrough') }}
      <small class="text-muted">{{ $space->name }}</small>
    </h1>
    <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to space') }}
    </a>
    @auth
      <a href="{{ route('exhibition-space.builder', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-primary">
        <i class="fas fa-cubes me-1"></i>{{ __('Edit in Builder') }}
      </a>
    @endauth
  </div>

  @if(count($stops) === 0)
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
          <div id="roomLoading" style="position:absolute;bottom:8px;left:8px;z-index:4;color:#ccc;font-size:.8rem;">{{ __('Loading gallery...') }}</div>
          <button id="roomHelpBtn" type="button" class="btn btn-sm btn-dark" style="position:absolute;top:8px;right:8px;z-index:6;opacity:.85;" title="{{ __('Controls') }}"><i class="fas fa-question"></i></button>
          <div id="roomHelp" class="bg-dark text-white p-3 rounded small" style="position:absolute;top:46px;right:8px;z-index:6;max-width:260px;display:none;">
            <div class="fw-bold mb-2"><i class="fas fa-gamepad me-1"></i>{{ __('Controls') }}</div>
            <ul class="mb-0 ps-3">
              <li>{{ __('Click gallery to enter') }}</li>
              <li>{{ __('Move: W A S D or arrow keys') }}</li>
              <li>{{ __('Forward / back: mouse wheel') }}</li>
              <li>{{ __('Look around: move the mouse') }}</li>
              <li>{{ __('Open info: click an object (or a numbered button)') }}</li>
              <li>{{ __('Open full record (new tab): V') }}</li>
              <li>{{ __('Close info: click or Esc') }}</li>
              <li>{{ __('Exit gallery: Esc') }}</li>
              <li class="mt-1 text-info">{{ __('Touch: drag to look, pinch to zoom, tap an object, tap a numbered button to travel') }}</li>
            </ul>
          </div>
          {{-- In-canvas detail inlay (replaces the side panel). --}}
          <div id="wtInlay" style="position:absolute;left:50%;bottom:14px;transform:translateX(-50%);z-index:6;max-width:520px;width:92%;display:none;background:rgba(20,22,26,.92);color:#fff;border-radius:.5rem;padding:14px 16px;box-shadow:0 4px 16px rgba(0,0,0,.45);">
            <button type="button" id="inlayClose" class="btn-close btn-close-white" style="position:absolute;top:8px;right:10px;" aria-label="{{ __('Close') }}"></button>
            <h6 id="inlayTitle" class="fw-bold mb-1 pe-4"></h6>
            <p id="inlayDesc" class="small mb-2" style="max-height:120px;overflow:auto;"></p>
            <a id="inlayRec" href="#" target="_blank" rel="noopener" class="btn btn-sm btn-light"><i class="fas fa-external-link-alt me-1"></i>{{ __('View full record') }} <span class="badge bg-secondary ms-1">V</span></a>
          </div>
        </div>
        {{-- Walk-to navigator: click an object to travel to it. --}}
        <div id="roomNav" class="d-flex gap-1 p-2 overflow-auto border-top bg-light" style="white-space:nowrap;"></div>
      </div>
    </div>
  @endif


  @if(count($stops) > 0)
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/OBJLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/STLLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/PLYLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/PointerLockControls.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/OrbitControls.js"></script>
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
    // Stack index: objects sharing a wall + (near) the same point cascade as layers (#1140).
    (function () {
      var seen = {};
      STOPS.forEach(function (s) {
        var key = (s._room ? s._room.id : 0) + '|' + (s.wall_or_zone || 'auto') + '|' +
          Math.round((s.pos_x || 0) * 12) + '|' + Math.round((s.pos_y || 0) * 12);
        s._stack = seen[key] || 0;
        seen[key] = s._stack + 1;
      });
    })();
    var WALL_H = (BUILDING && BUILDING.max_h) ? BUILDING.max_h : 4;
    var BLD_minX = Infinity, BLD_maxX = -Infinity, BLD_minZ = Infinity, BLD_maxZ = -Infinity;
    ROOMS.forEach(function (rm) {
      if (rm.z_offset === null || rm.z_offset === undefined) rm.z_offset = -rm.d / 2;
      BLD_minX = Math.min(BLD_minX, rm.x_offset); BLD_maxX = Math.max(BLD_maxX, rm.x_offset + rm.w);
      BLD_minZ = Math.min(BLD_minZ, rm.z_offset); BLD_maxZ = Math.max(BLD_maxZ, rm.z_offset + rm.d);
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
    camera.position.set(curRoom.x_offset + curRoom.w / 2, 1.6, curRoom.z_offset + curRoom.d - 1.5);

    var renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(window.devicePixelRatio || 1);
    renderer.setSize(W, H);
    room.appendChild(renderer.domElement);

    scene.add(new THREE.HemisphereLight(0xffffff, 0x666677, 0.9));
    var dir = new THREE.DirectionalLight(0xffffff, 0.7);
    dir.position.set(5, 10, 7);
    scene.add(dir);

    // Per-room floors, perimeter walls (with doorways between rooms) and dividers.
    var wallMat = new THREE.MeshStandardMaterial({ color: 0xf2f2f0, roughness: 1, side: THREE.DoubleSide });
    var DOOR = 1.6;   // doorway width between connected rooms
    function wallSeg(len, x, z, ry, mat) {
      if (len <= 0.05) return;
      var m = new THREE.Mesh(new THREE.PlaneGeometry(len, WALL_H), mat || wallMat);
      m.position.set(x, WALL_H / 2, z); m.rotation.y = ry; scene.add(m);
    }
    // Doorway openings where another room adjoins this wall (plan mode).
    function doorsOnWall(rm, vertical, edge) {
      var a0 = vertical ? rm.z_offset : rm.x_offset;
      var a1 = vertical ? rm.z_offset + rm.d : rm.x_offset + rm.w;
      var doors = [];
      ROOMS.forEach(function (rj) {
        if (rj === rm) return;
        var jMin = vertical ? rj.x_offset : rj.z_offset;
        var jMax = vertical ? rj.x_offset + rj.w : rj.z_offset + rj.d;
        if (Math.abs(jMax - edge) > 0.3 && Math.abs(jMin - edge) > 0.3) return;   // not adjoining this plane
        var b0 = vertical ? rj.z_offset : rj.x_offset;
        var b1 = vertical ? rj.z_offset + rj.d : rj.x_offset + rj.w;
        var oa = Math.max(a0, b0), ob = Math.min(a1, b1);
        if (ob - oa > 0.8) { var mid = (oa + ob) / 2, dw = Math.min(DOOR, (ob - oa) * 0.7); doors.push([mid - dw / 2, mid + dw / 2]); }
      });
      return doors;
    }
    // Render a wall along [a,b] (at fixed coord, inset slightly), cutting door gaps.
    function planWall(rm, vertical, edge, insetDir, ry, mat) {
      var a = vertical ? rm.z_offset : rm.x_offset;
      var b = vertical ? rm.z_offset + rm.d : rm.x_offset + rm.w;
      var fixed = edge + insetDir * 0.05;
      var doors = doorsOnWall(rm, vertical, edge).sort(function (p, q) { return p[0] - q[0]; });
      var cur = a;
      function seg(s, e) {
        var len = e - s; if (len <= 0.05) return;
        var mid = (s + e) / 2;
        if (vertical) wallSeg(len, fixed, mid, ry, mat); else wallSeg(len, mid, fixed, ry, mat);
      }
      doors.forEach(function (dd) { var ds = Math.max(a, dd[0]), de = Math.min(b, dd[1]); if (ds > cur) seg(cur, ds); cur = Math.max(cur, de); });
      if (cur < b) seg(cur, b);
    }
    ROOMS.forEach(function (rm, i) {
      var cx = rm.x_offset + rm.w / 2, cz = rm.z_offset + rm.d / 2;
      // Decorated/painted wall material for this room (if a wall image is set).
      var rwMat = wallMat;
      if (rm.wall_image) {
        rwMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 1, side: THREE.DoubleSide });
        new THREE.TextureLoader().load(rm.wall_image, function (tex) { rwMat.map = tex; rwMat.needsUpdate = true; });
      }
      var fmat = new THREE.MeshStandardMaterial({ color: 0x8a8f96, roughness: 0.95 });
      var fl = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), fmat);
      fl.rotation.x = -Math.PI / 2; fl.position.set(cx, 0, cz); scene.add(fl);
      if (rm.floorplan) { new THREE.TextureLoader().load(rm.floorplan, function (tex) { fmat.map = tex; fmat.color.set(0xffffff); fmat.needsUpdate = true; }); }
      if (rm.ceiling) {                               // painted ceiling image
        var cmat = new THREE.MeshBasicMaterial({ side: THREE.DoubleSide });
        var cl = new THREE.Mesh(new THREE.PlaneGeometry(rm.w, rm.d), cmat);
        cl.rotation.x = Math.PI / 2; cl.position.set(cx, rm.h || WALL_H, cz);   // faces down
        scene.add(cl);
        new THREE.TextureLoader().load(rm.ceiling, function (tex) { cmat.map = tex; cmat.needsUpdate = true; });
      }
      var rx = rm.x_offset + rm.w;
      if (PLAN_MODE) {   // plan layout: auto-doorways where rooms adjoin
        planWall(rm, false, rm.z_offset, 1, 0, rwMat);            // back
        planWall(rm, false, rm.z_offset + rm.d, -1, Math.PI, rwMat); // front
        planWall(rm, true, rm.x_offset, 1, Math.PI / 2, rwMat);   // left
        planWall(rm, true, rx, -1, -Math.PI / 2, rwMat);          // right
      } else {           // auto-row: back+front full, doorways between consecutive rooms
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
      (rm.walls || []).forEach(function (w) {          // interior dividers (normalized within room)
        var ax = rm.x_offset + w.x1 * rm.w, az = rm.z_offset + w.z1 * rm.d, bx = rm.x_offset + w.x2 * rm.w, bz = rm.z_offset + w.z2 * rm.d;
        var len = Math.hypot(bx - ax, bz - az); if (len < 0.1) return;
        var ang = Math.atan2(bz - az, bx - ax);
        var m = new THREE.Mesh(new THREE.PlaneGeometry(len, WALL_H), wallMat);
        m.position.set((ax + bx) / 2, WALL_H / 2, (az + bz) / 2); m.rotation.y = -ang; scene.add(m);
      });
    });

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
      return new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ color: 0xcfcfcf, roughness: 0.7, metalness: 0.05 }));
    }
    function loadModel(url, ext, onLoad, onError) {
      try {
        if (ext === 'glb' || ext === 'gltf') { new THREE.GLTFLoader().load(url, function (g) { onLoad(g.scene); }, undefined, onError); }
        else if (ext === 'obj') { new THREE.OBJLoader().load(url, function (o) { onLoad(o); }, undefined, onError); }
        else if (ext === 'stl') { new THREE.STLLoader().load(url, function (geo) { onLoad(greyMesh(geo)); }, undefined, onError); }
        else if (ext === 'ply') { new THREE.PLYLoader().load(url, function (geo) { onLoad(greyMesh(geo)); }, undefined, onError); }
        else if (onError) { onError(); }
      } catch (e) { if (onError) onError(); }
    }

    // Objects
    var pickables = [];
    var pedestalMat = new THREE.MeshStandardMaterial({ color: 0x3a3f47, roughness: 0.8 });
    var pending = STOPS.length;
    function doneOne() { pending--; if (pending <= 0) loading.style.display = 'none'; }

    function worldPos(s) {
      var rm = s._room || curRoom;
      return { x: rm.x_offset + s.pos_x * rm.w, z: rm.z_offset + s.pos_y * rm.d };
    }

    function addPedestal(x, z, h) {
      var p = new THREE.Mesh(new THREE.BoxGeometry(0.7, h, 0.7), pedestalMat);
      p.position.set(x, h / 2, z); scene.add(p); return h;
    }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    // Hang a framed picture flat on the nearest wall (pictures on walls, not pedestals).
    // Choose where a picture hangs: a specific wall (perimeter key or interior id)
    // when assigned, otherwise the nearest wall (perimeter + interior dividers).
    function wallSpot(wallKey, px, pz, hw, inset, rm) {
      var x0 = rm.x_offset, x1 = rm.x_offset + rm.w, zN = rm.z_offset, zS = rm.z_offset + rm.d;
      var cands = [];
      cands.push({ key: 'north', dist: pz - zN, get: function () { return { x: clamp(px, x0 + hw, x1 - hw), z: zN + inset, ry: 0 }; } });
      cands.push({ key: 'south', dist: zS - pz, get: function () { return { x: clamp(px, x0 + hw, x1 - hw), z: zS - inset, ry: Math.PI }; } });
      cands.push({ key: 'west', dist: px - x0, get: function () { return { x: x0 + inset, z: clamp(pz, zN + hw, zS - hw), ry: Math.PI / 2 }; } });
      cands.push({ key: 'east', dist: x1 - px, get: function () { return { x: x1 - inset, z: clamp(pz, zN + hw, zS - hw), ry: -Math.PI / 2 }; } });
      (rm.walls || []).forEach(function (w) {
        var ax = rm.x_offset + w.x1 * rm.w, az = rm.z_offset + w.z1 * rm.d, bx = rm.x_offset + w.x2 * rm.w, bz = rm.z_offset + w.z2 * rm.d;
        var ex = bx - ax, ez = bz - az, L2 = ex * ex + ez * ez;
        if (L2 < 0.01) return;
        var t = Math.max(0, Math.min(1, ((px - ax) * ex + (pz - az) * ez) / L2));
        var projx = ax + t * ex, projz = az + t * ez;
        cands.push({ key: w.id, dist: Math.hypot(px - projx, pz - projz), get: function () {
          var ang = Math.atan2(ez, ex), nx = -Math.sin(ang), nz = Math.cos(ang);
          var side = ((px - projx) * nx + (pz - projz) * nz) >= 0 ? 1 : -1;
          var len = Math.sqrt(L2), tt = Math.max(hw / len, Math.min(1 - hw / len, t));
          var cx = ax + tt * ex, cz = az + tt * ez;
          return { x: cx + nx * inset * side, z: cz + nz * inset * side, ry: Math.atan2(nx * side, nz * side) };
        } });
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
      var w = (rm.walls || []).filter(function (ww) { return ww.id === wallKey; })[0];
      if (w) {
        var ax = rm.x_offset + w.x1 * rm.w, az = rm.z_offset + w.z1 * rm.d, bx = rm.x_offset + w.x2 * rm.w, bz = rm.z_offset + w.z2 * rm.d;
        var ex = bx - ax, ez = bz - az, len = Math.hypot(ex, ez) || 1;
        var tt = clamp(u, hw / len, 1 - hw / len);
        var ang = Math.atan2(ez, ex), nx = -Math.sin(ang), nz = Math.cos(ang);
        return { x: ax + tt * ex + nx * inset, z: az + tt * ez + nz * inset, ry: Math.atan2(nx, nz) };
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
      var hasUV = (s.wall_u !== null && s.wall_u !== undefined && s.wall_or_zone);
      if (hasUV) {
        spot = wallSpotUV(s.wall_or_zone, s.wall_u, hw, inset, rmh);
        cy = clamp((s.wall_v != null ? s.wall_v : 0.5) * WALL_H, hgt / 2 + 0.1, WALL_H - hgt / 2 - 0.1);
      }
      if (!spot) {
        spot = wallSpot(s.wall_or_zone, wp.x, wp.z, hw, inset, rmh);
        cy = clamp(1.6, hgt / 2 + 0.1, WALL_H - hgt / 2 - 0.1);
      }
      var frame = new THREE.Mesh(new THREE.BoxGeometry(wdt + 0.12, hgt + 0.12, 0.06), new THREE.MeshStandardMaterial({ color: 0x222222 }));
      var pic = new THREE.Mesh(new THREE.PlaneGeometry(wdt, hgt), new THREE.MeshBasicMaterial({ map: tex }));
      pic.position.z = 0.045;
      var grp = new THREE.Group();
      grp.add(frame); grp.add(pic);
      // Cascade stacked layers (skip for objects placed precisely in the wall editor).
      var st = hasUV ? 0 : (s._stack || 0);
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
      scene.add(grp); pickables.push(grp);
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
        var ph = addPedestal(wp.x, wp.z, 0.6);
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
          pivot.scale.setScalar((1.5 / maxd) * (s.scale || 1));   // display size from Builder Bigger/Smaller
          pivot.rotation.y = (s.rotation_deg || 0) * Math.PI / 180;
          pivot.updateMatrixWorld(true);
          box = new THREE.Box3().setFromObject(pivot);
          var c = box.getCenter(new THREE.Vector3());
          pivot.position.x += wp.x - c.x;
          pivot.position.z += wp.z - c.z;
          pivot.position.y += ph - box.min.y;
          pivot.traverse(function (n) { if (n.isMesh) { n.userData.stop = s; } });
          pivot.userData.stop = s;
          scene.add(pivot); pickables.push(pivot); doneOne();
        }, function () {
          addPlaceholder(wp, s, ph); doneOne();
        });
      } else if (s.image_url) {
        new THREE.TextureLoader().load(s.image_url, function (tex) {
          var aspect = (tex.image && tex.image.width ? tex.image.width : 1) / (tex.image && tex.image.height ? tex.image.height : 1);
          hangOnWall(wp, s, tex, aspect); doneOne();
        }, undefined, function () { addPlaceholder(wp, s, addPedestal(wp.x, wp.z, 0.4)); doneOne(); });
      } else if (s.kind === 'pdf' && s.doc_url) {
        renderPdfTexture(s.doc_url, function (tex, aspect) {
          hangOnWall(wp, s, tex, aspect); doneOne();
        }, function () { addPlaceholder(wp, s, addPedestal(wp.x, wp.z, 0.4)); doneOne(); });
      } else {
        var ph3 = addPedestal(wp.x, wp.z, 0.4);
        addPlaceholder(wp, s, ph3); doneOne();
      }
    });

    function addPlaceholder(wp, s, ph) {
      var m = new THREE.Mesh(new THREE.BoxGeometry(0.8, 0.8, 0.8), new THREE.MeshStandardMaterial({ color: 0x6c757d }));
      m.position.set(wp.x, ph + 0.4, wp.z); m.userData.stop = s;
      scene.add(m); pickables.push(m);
    }

    // Detail inlay (in-canvas info block; click anywhere closes it; V opens record).
    var inlay = document.getElementById('wtInlay');
    var panelOpen = false;
    var currentStop = null;
    function openPanel(s) {
      document.getElementById('inlayTitle').textContent = s.title;
      document.getElementById('inlayDesc').textContent = s.description || '{{ __('No description available.') }}';
      var rec = document.getElementById('inlayRec');
      if (s.record_url) { rec.href = s.record_url; rec.style.display = ''; } else { rec.style.display = 'none'; }
      inlay.style.display = 'block';
      panelOpen = true;
      currentStop = s;
    }
    function closeAllPopups() {
      inlay.style.display = 'none';
      panelOpen = false;
      currentStop = null;
    }
    function viewFullDetails() {
      if (currentStop && currentStop.record_url) {
        window.open(currentStop.record_url, '_blank');   // new tab so the gallery stays open (#1142)
      }
    }
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
      var hits = ray.intersectObjects(pickables, true);
      if (hits.length) {
        var o = hits[0].object;
        while (o && !o.userData.stop) o = o.parent;
        if (o && o.userData.stop) openPanel(o.userData.stop);
      }
    });

    // Mouse wheel moves forward / backward through the gallery.
    // When you look up at the ceiling, the viewer naturally lowers/leans back to
    // take it in; returns to standing height when looking level/down.
    var _wd = new THREE.Vector3();
    function eyeHeight() {
      camera.getWorldDirection(_wd);
      var up = Math.max(0, Math.min(1, (_wd.y - 0.2) / 0.7));
      return 1.6 - up * 1.1;   // down to ~0.5 m when looking straight up
    }
    function clampInRoom(o) {
      var m = 0.6;
      o.position.x = Math.max(BLD_minX + m, Math.min(BLD_maxX - m, o.position.x));
      o.position.z = Math.max(BLD_minZ + m, Math.min(BLD_maxZ - m, o.position.z));
      if (!PLAN_MODE) {   // auto-row: keep within current room's depth band
        var rm = roomAt(o.position.x, o.position.z);
        o.position.z = Math.max(rm.z_offset + m, Math.min(rm.z_offset + rm.d - m, o.position.z));
      }
      o.position.y += (eyeHeight() - o.position.y) * 0.3;   // smooth crouch/stand
    }
    renderer.domElement.addEventListener('wheel', function (e) {
      e.preventDefault();
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
    });

    // Help / controls overlay toggle.
    var helpBox = document.getElementById('roomHelp');
    document.getElementById('roomHelpBtn').addEventListener('click', function (e) {
      e.stopPropagation();
      helpBox.style.display = (helpBox.style.display === 'block') ? 'none' : 'block';
    });

    // Movement loop
    var clock = new THREE.Clock();
    var vel = new THREE.Vector3();
    function animate() {
      requestAnimationFrame(animate);
      var dt = Math.min(0.05, clock.getDelta());
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
          vel.normalize();
          controls.moveRight(vel.x * speed * dt);
          controls.moveForward(vel.z * speed * dt);
        }
        clampInRoom(controls.getObject());
      }
      renderer.render(scene, camera);
    }
    animate();

    window.addEventListener('resize', function () {
      W = room.clientWidth || W; H = room.clientHeight || H;
      camera.aspect = W / H; camera.updateProjectionMatrix();
      renderer.setSize(W, H);
    });
  })();
  </script>
  @endif
@endsection
