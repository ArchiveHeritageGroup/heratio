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
              <li>{{ __('Open details: click an object or a numbered button') }}</li>
              <li>{{ __('Scroll the details panel: right-click') }}</li>
              <li>{{ __('View full details: V') }}</li>
              <li>{{ __('Close panel: left-click or Esc') }}</li>
              <li>{{ __('Exit gallery: Esc') }}</li>
            </ul>
          </div>
        </div>
        {{-- Walk-to navigator: click an object to travel to it. --}}
        <div id="roomNav" class="d-flex gap-1 p-2 overflow-auto border-top bg-light" style="white-space:nowrap;"></div>
      </div>
    </div>
  @endif

  {{-- Detail side panel (vanilla; theme bundle has no bootstrap Offcanvas) --}}
  <div id="wtPanel" tabindex="-1" style="position:fixed;top:0;right:0;height:100%;width:360px;max-width:85vw;background:#fff;z-index:1080;transform:translateX(100%);transition:transform .3s ease;overflow-y:auto;box-shadow:-4px 0 16px rgba(0,0,0,.15);outline:none;">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
      <h5 class="mb-0" id="wtPanelTitle">{{ __('Object') }}</h5>
      <button type="button" class="btn-close" id="wtClose" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="p-3">
      <div id="wtImgWrap" class="text-center mb-3"></div>
      <h6 id="wtTitle" class="fw-bold"></h6>
      <p id="wtDesc" class="small text-muted"></p>
      <a id="wtRecord" href="#" class="btn btn-sm btn-outline-primary d-none"><i class="fas fa-external-link-alt me-1"></i>{{ __('View full details') }} <span class="badge bg-secondary ms-1">V</span></a>
    </div>
  </div>

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
    var STOPS = @json($stops);
    var FLOORPLAN = @json($space->floorplan_image_path);
    var room = document.getElementById('room');
    var loading = document.getElementById('roomLoading');
    if (typeof THREE === 'undefined' || !THREE.PointerLockControls) {
      room.innerHTML = '<div class="p-4 text-light">{{ __('3D engine failed to load.') }}</div>';
      return;
    }
    if (window.pdfjsLib) {
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    var ROOM_W = 18, ROOM_D = 14, WALL_H = 4;
    var W = room.clientWidth || 800, H = room.clientHeight || 480;

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(0x20242a);

    var camera = new THREE.PerspectiveCamera(70, W / H, 0.05, 200);
    camera.position.set(0, 1.6, ROOM_D / 2 - 1.5);

    var renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setPixelRatio(window.devicePixelRatio || 1);
    renderer.setSize(W, H);
    room.appendChild(renderer.domElement);

    scene.add(new THREE.HemisphereLight(0xffffff, 0x666677, 0.9));
    var dir = new THREE.DirectionalLight(0xffffff, 0.7);
    dir.position.set(5, 10, 7);
    scene.add(dir);

    // Floor (uses the uploaded floorplan as a texture when available).
    var floorMat = new THREE.MeshStandardMaterial({ color: 0xcfd3d8, roughness: 0.95 });
    var floor = new THREE.Mesh(new THREE.PlaneGeometry(ROOM_W, ROOM_D), floorMat);
    floor.rotation.x = -Math.PI / 2;
    scene.add(floor);
    if (FLOORPLAN) {
      new THREE.TextureLoader().load(FLOORPLAN, function (tex) {
        floorMat.map = tex; floorMat.color.set(0xffffff); floorMat.needsUpdate = true;
      });
    } else {
      scene.add(new THREE.GridHelper(Math.max(ROOM_W, ROOM_D), 24, 0x556, 0x445));
    }

    // Walls
    var wallMat = new THREE.MeshStandardMaterial({ color: 0xf2f2f0, roughness: 1, side: THREE.DoubleSide });
    function wall(w, x, z, ry) {
      var m = new THREE.Mesh(new THREE.PlaneGeometry(w, WALL_H), wallMat);
      m.position.set(x, WALL_H / 2, z); m.rotation.y = ry; scene.add(m);
    }
    wall(ROOM_W, 0, -ROOM_D / 2, 0);
    wall(ROOM_W, 0, ROOM_D / 2, Math.PI);
    wall(ROOM_D, -ROOM_W / 2, 0, Math.PI / 2);
    wall(ROOM_D, ROOM_W / 2, 0, -Math.PI / 2);

    // Controls
    var controls = new THREE.PointerLockControls(camera, renderer.domElement);
    scene.add(controls.getObject());
    var blocker = document.getElementById('roomBlocker');
    var cross = document.getElementById('roomCrosshair');
    blocker.addEventListener('click', function () { controls.lock(); });
    controls.addEventListener('lock', function () { blocker.style.display = 'none'; cross.style.display = 'block'; });
    controls.addEventListener('unlock', function () { blocker.style.display = 'flex'; cross.style.display = 'none'; });

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

    function worldPos(s) { return { x: (s.pos_x - 0.5) * ROOM_W, z: (s.pos_y - 0.5) * ROOM_D }; }

    function addPedestal(x, z, h) {
      var p = new THREE.Mesh(new THREE.BoxGeometry(0.7, h, 0.7), pedestalMat);
      p.position.set(x, h / 2, z); scene.add(p); return h;
    }
    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    // Hang a framed picture flat on the nearest wall (pictures on walls, not pedestals).
    function hangOnWall(wp, s, tex, aspect) {
      var hgt = 1.5, wdt = hgt * (aspect || 1);
      if (wdt > 2.6) { wdt = 2.6; hgt = wdt / (aspect || 1); }
      var hw = wdt / 2;
      var dL = wp.x + ROOM_W / 2, dR = ROOM_W / 2 - wp.x, dB = wp.z + ROOM_D / 2, dF = ROOM_D / 2 - wp.z;
      var mind = Math.min(dL, dR, dB, dF);
      var inset = 0.08, cy = clamp(1.6, hgt / 2 + 0.1, WALL_H - hgt / 2 - 0.1);
      var x = wp.x, z = wp.z, ry = 0;
      if (mind === dB) { z = -ROOM_D / 2 + inset; ry = 0; x = clamp(wp.x, -ROOM_W / 2 + hw, ROOM_W / 2 - hw); }
      else if (mind === dF) { z = ROOM_D / 2 - inset; ry = Math.PI; x = clamp(wp.x, -ROOM_W / 2 + hw, ROOM_W / 2 - hw); }
      else if (mind === dL) { x = -ROOM_W / 2 + inset; ry = Math.PI / 2; z = clamp(wp.z, -ROOM_D / 2 + hw, ROOM_D / 2 - hw); }
      else { x = ROOM_W / 2 - inset; ry = -Math.PI / 2; z = clamp(wp.z, -ROOM_D / 2 + hw, ROOM_D / 2 - hw); }
      var frame = new THREE.Mesh(new THREE.BoxGeometry(wdt + 0.12, hgt + 0.12, 0.06), new THREE.MeshStandardMaterial({ color: 0x222222 }));
      var pic = new THREE.Mesh(new THREE.PlaneGeometry(wdt, hgt), new THREE.MeshBasicMaterial({ map: tex }));
      pic.position.z = 0.045;
      var grp = new THREE.Group();
      grp.add(frame); grp.add(pic);
      grp.position.set(x, cy, z); grp.rotation.y = ry;
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
          var box = new THREE.Box3().setFromObject(obj);
          var size = box.getSize(new THREE.Vector3());
          var maxd = Math.max(size.x, size.y, size.z) || 1;
          obj.scale.setScalar(1.5 / maxd);
          box = new THREE.Box3().setFromObject(obj);
          var c = box.getCenter(new THREE.Vector3());
          obj.position.set(wp.x - c.x, ph - box.min.y, wp.z - c.z);
          obj.traverse(function (n) { if (n.isMesh) { n.userData.stop = s; } });
          obj.userData.stop = s;
          scene.add(obj); pickables.push(obj); doneOne();
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

    // Detail side panel
    var panel = document.getElementById('wtPanel');
    var panelOpen = false;
    var currentStop = null;
    function openPanel(s) {
      document.getElementById('wtTitle').textContent = s.title;
      document.getElementById('wtDesc').textContent = s.description || '{{ __('No description available.') }}';
      var iw = document.getElementById('wtImgWrap');
      stopMiniViewer();
      if (s.kind === '3d' && s.model_url) {
        iw.innerHTML = '<div id="wtMini" style="width:100%;height:280px;background:#f1f3f5;border-radius:.375rem;"></div>' +
          '<div class="small text-muted mt-1">{{ __('Drag to rotate, scroll to zoom') }}</div>';
        startMiniViewer(document.getElementById('wtMini'), s);
      } else if (s.kind === 'pdf' && s.doc_url) {
        iw.innerHTML = '<iframe src="' + s.doc_url + '" style="width:100%;height:300px;border:1px solid #ddd;border-radius:.375rem;" title="PDF"></iframe>';
      } else if (s.image_url) {
        iw.innerHTML = '<img src="' + s.image_url + '" class="img-fluid rounded" style="max-height:260px" alt="">';
      } else {
        iw.innerHTML = '<div class="text-muted py-3"><i class="fas fa-cube fa-2x"></i></div>';
      }
      var rec = document.getElementById('wtRecord');
      if (s.record_url) { rec.href = s.record_url; rec.classList.remove('d-none'); } else { rec.classList.add('d-none'); }
      panel.style.transform = 'translateX(0)';
      panelOpen = true;
      currentStop = s;
    }
    function closeAllPopups() {
      panel.style.transform = 'translateX(100%)';
      panelOpen = false;
      currentStop = null;
      stopMiniViewer();
    }
    function viewFullDetails() {
      if (currentStop && currentStop.record_url) {
        window.location.href = currentStop.record_url;
      }
    }
    // Rotating 3D preview inside the popout (any format via loadModel).
    var mini = null;
    function stopMiniViewer() {
      if (!mini) return;
      cancelAnimationFrame(mini.raf);
      try { mini.renderer.dispose(); } catch (e) {}
      if (mini.renderer && mini.renderer.domElement && mini.renderer.domElement.parentNode) {
        mini.renderer.domElement.parentNode.removeChild(mini.renderer.domElement);
      }
      mini = null;
    }
    function startMiniViewer(el, s) {
      if (!el) return;
      var w = el.clientWidth || 320, h = 280;
      var sc = new THREE.Scene(); sc.background = new THREE.Color(0xf1f3f5);
      var cam = new THREE.PerspectiveCamera(45, w / h, 0.01, 100); cam.position.set(0, 0, 3);
      var rn = new THREE.WebGLRenderer({ antialias: true });
      rn.setPixelRatio(window.devicePixelRatio || 1); rn.setSize(w, h); el.appendChild(rn.domElement);
      sc.add(new THREE.HemisphereLight(0xffffff, 0x888888, 1.2));
      var dl = new THREE.DirectionalLight(0xffffff, 0.8); dl.position.set(2, 3, 4); sc.add(dl);
      var oc = new THREE.OrbitControls(cam, rn.domElement); oc.enablePan = false; oc.autoRotate = true; oc.autoRotateSpeed = 2.6;
      mini = { renderer: rn, raf: 0 };
      loadModel(s.model_url, modelExt(s), function (obj) {
        var box = new THREE.Box3().setFromObject(obj);
        var size = box.getSize(new THREE.Vector3());
        var maxd = Math.max(size.x, size.y, size.z) || 1;
        obj.scale.setScalar(1.7 / maxd);
        box = new THREE.Box3().setFromObject(obj);
        obj.position.sub(box.getCenter(new THREE.Vector3()));
        sc.add(obj);
      }, function () {
        el.innerHTML = '<div class="text-muted py-4 text-center"><i class="fas fa-cube fa-2x"></i><div class="small mt-2">{{ __('3D preview unavailable') }}</div></div>';
      });
      (function loop() { if (!mini) return; mini.raf = requestAnimationFrame(loop); oc.update(); rn.render(sc, cam); })();
    }
    document.getElementById('wtClose').addEventListener('click', closeAllPopups);

    // ---- Walk-to navigator: travel the camera to an object and open its panel ----
    var fly = null;
    function flyTo(s) {
      var wp = worldPos(s);
      var look = new THREE.Vector3(wp.x, 1.3, wp.z);
      var toC = new THREE.Vector3(-wp.x, 0, -wp.z);          // direction toward room centre
      if (toC.lengthSq() < 0.01) toC.set(0, 0, 1);
      toC.normalize();
      var stand = new THREE.Vector3(wp.x + toC.x * 2.6, 1.6, wp.z + toC.z * 2.6);
      var m = 0.6;
      stand.x = Math.max(-ROOM_W / 2 + m, Math.min(ROOM_W / 2 - m, stand.x));
      stand.z = Math.max(-ROOM_D / 2 + m, Math.min(ROOM_D / 2 - m, stand.z));
      fly = { from: controls.getObject().position.clone(), to: stand, look: look, t: 0, dur: 0.9 };
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
    renderer.domElement.addEventListener('click', function () {
      // Left click acts like Esc: if a popup is open, close it (and nothing else).
      if (panelOpen) { closeAllPopups(); return; }
      if (!controls.isLocked) return;
      ray.setFromCamera({ x: 0, y: 0 }, camera);
      var hits = ray.intersectObjects(pickables, true);
      if (hits.length) {
        var o = hits[0].object;
        while (o && !o.userData.stop) o = o.parent;
        if (o && o.userData.stop) openPanel(o.userData.stop);
      }
    });

    // Mouse wheel moves forward / backward through the gallery.
    function clampInRoom(o) {
      var m = 0.6;
      o.position.x = Math.max(-ROOM_W / 2 + m, Math.min(ROOM_W / 2 - m, o.position.x));
      o.position.z = Math.max(-ROOM_D / 2 + m, Math.min(ROOM_D / 2 - m, o.position.z));
      o.position.y = 1.6;
    }
    renderer.domElement.addEventListener('wheel', function (e) {
      e.preventDefault();
      controls.moveForward((e.deltaY < 0 ? 1 : -1) * 0.6);
      clampInRoom(controls.getObject());
    }, { passive: false });

    // Right-click releases pointer lock and jumps to the open detail panel so the
    // mouse can scroll / click it. We listen on mousedown (button 2) because the
    // browser suppresses the contextmenu event while the pointer is locked.
    renderer.domElement.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    renderer.domElement.addEventListener('mousedown', function (e) {
      if (e.button !== 2) return;
      e.preventDefault();
      if (controls.isLocked) controls.unlock();   // frees the mouse cursor
      if (panelOpen) { panel.focus(); }
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
        camera.lookAt(fly.look);
        if (fk >= 1) fly = null;
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
        var o = controls.getObject();
        var m = 0.6;
        o.position.x = Math.max(-ROOM_W / 2 + m, Math.min(ROOM_W / 2 - m, o.position.x));
        o.position.z = Math.max(-ROOM_D / 2 + m, Math.min(ROOM_D / 2 - m, o.position.z));
        o.position.y = 1.6;
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
