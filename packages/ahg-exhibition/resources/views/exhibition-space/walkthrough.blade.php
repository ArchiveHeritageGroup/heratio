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
        <div id="room" style="position:relative;width:100%;height:70vh;min-height:420px;background:#1a1d21;border-radius:0 0 .375rem .375rem;overflow:hidden;">
          <div id="roomBlocker" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);z-index:5;cursor:pointer;">
            <div class="text-center text-white">
              <div style="font-size:2rem;"><i class="fas fa-vr-cardboard"></i></div>
              <div class="fw-bold mt-2">{{ __('Click to enter the gallery') }}</div>
              <div class="small text-white-50 mt-1">{{ __('W A S D to walk, mouse to look, click an object for details, Esc to exit') }}</div>
            </div>
          </div>
          <div id="roomCrosshair" style="position:absolute;top:50%;left:50%;width:8px;height:8px;margin:-4px 0 0 -4px;border-radius:50%;background:rgba(255,255,255,.7);z-index:4;display:none;pointer-events:none;"></div>
          <div id="roomLoading" style="position:absolute;bottom:8px;left:8px;z-index:4;color:#ccc;font-size:.8rem;">{{ __('Loading gallery...') }}</div>
        </div>
      </div>
    </div>
  @endif

  {{-- Detail side panel (vanilla; theme bundle has no bootstrap Offcanvas) --}}
  <div id="wtPanel" style="position:fixed;top:0;right:0;height:100%;width:360px;max-width:85vw;background:#fff;z-index:1080;transform:translateX(100%);transition:transform .3s ease;overflow-y:auto;box-shadow:-4px 0 16px rgba(0,0,0,.15);">
    <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
      <h5 class="mb-0" id="wtPanelTitle">{{ __('Object') }}</h5>
      <button type="button" class="btn-close" id="wtClose" aria-label="{{ __('Close') }}"></button>
    </div>
    <div class="p-3">
      <div id="wtImgWrap" class="text-center mb-3"></div>
      <h6 id="wtTitle" class="fw-bold"></h6>
      <p id="wtDesc" class="small text-muted"></p>
      <a id="wtRecord" href="#" class="btn btn-sm btn-outline-primary d-none"><i class="fas fa-external-link-alt me-1"></i>{{ __('View full record') }}</a>
    </div>
  </div>

  @if(count($stops) > 0)
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/loaders/GLTFLoader.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/three@0.128.0/examples/js/controls/PointerLockControls.js"></script>
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
    document.addEventListener('keydown', function (e) { keys[e.code] = true; });
    document.addEventListener('keyup', function (e) { keys[e.code] = false; });

    // Objects
    var pickables = [];
    var pedestalMat = new THREE.MeshStandardMaterial({ color: 0x3a3f47, roughness: 0.8 });
    var gltf = new THREE.GLTFLoader();
    var pending = STOPS.length;
    function doneOne() { pending--; if (pending <= 0) loading.style.display = 'none'; }

    function worldPos(s) { return { x: (s.pos_x - 0.5) * ROOM_W, z: (s.pos_y - 0.5) * ROOM_D }; }

    function addPedestal(x, z, h) {
      var p = new THREE.Mesh(new THREE.BoxGeometry(0.7, h, 0.7), pedestalMat);
      p.position.set(x, h / 2, z); scene.add(p); return h;
    }

    STOPS.forEach(function (s) {
      var wp = worldPos(s);
      if (s.kind === '3d' && s.model_url) {
        var ph = addPedestal(wp.x, wp.z, 0.6);
        gltf.load(s.model_url, function (g) {
          var obj = g.scene;
          var box = new THREE.Box3().setFromObject(obj);
          var size = box.getSize(new THREE.Vector3());
          var maxd = Math.max(size.x, size.y, size.z) || 1;
          var sc = 1.5 / maxd; obj.scale.setScalar(sc);
          box = new THREE.Box3().setFromObject(obj);
          var c = box.getCenter(new THREE.Vector3());
          var min = box.min;
          obj.position.set(wp.x - c.x, ph - min.y, wp.z - c.z);
          obj.traverse(function (n) { if (n.isMesh) { n.userData.stop = s; } });
          obj.userData.stop = s;
          scene.add(obj); pickables.push(obj); doneOne();
        }, undefined, function () {
          addPlaceholder(wp, s, ph); doneOne();
        });
      } else if (s.image_url) {
        var ph2 = addPedestal(wp.x, wp.z, 0.4);
        new THREE.TextureLoader().load(s.image_url, function (tex) {
          var iw = tex.image && tex.image.width ? tex.image.width : 1;
          var ih = tex.image && tex.image.height ? tex.image.height : 1;
          var aspect = iw / ih, hgt = 1.4, wdt = hgt * aspect;
          if (wdt > 2.2) { wdt = 2.2; hgt = wdt / aspect; }
          var frame = new THREE.Mesh(new THREE.BoxGeometry(wdt + 0.12, hgt + 0.12, 0.06), new THREE.MeshStandardMaterial({ color: 0x222222 }));
          var pic = new THREE.Mesh(new THREE.PlaneGeometry(wdt, hgt), new THREE.MeshBasicMaterial({ map: tex }));
          pic.position.z = 0.035;
          var grp = new THREE.Group();
          grp.add(frame); grp.add(pic);
          grp.position.set(wp.x, ph2 + hgt / 2 + 0.1, wp.z);
          grp.lookAt(0, grp.position.y, 0);
          grp.traverse(function (n) { n.userData.stop = s; });
          grp.userData.stop = s;
          scene.add(grp); pickables.push(grp); doneOne();
        }, undefined, function () { addPlaceholder(wp, s, ph2); doneOne(); });
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
    function openPanel(s) {
      document.getElementById('wtTitle').textContent = s.title;
      document.getElementById('wtDesc').textContent = s.description || '{{ __('No description available.') }}';
      var iw = document.getElementById('wtImgWrap');
      iw.innerHTML = s.image_url ? '<img src="' + s.image_url + '" class="img-fluid rounded" style="max-height:240px" alt="">' : '<div class="text-muted py-3"><i class="fas fa-cube fa-2x"></i></div>';
      var rec = document.getElementById('wtRecord');
      if (s.record_url) { rec.href = s.record_url; rec.classList.remove('d-none'); } else { rec.classList.add('d-none'); }
      panel.style.transform = 'translateX(0)';
    }
    document.getElementById('wtClose').addEventListener('click', function () { panel.style.transform = 'translateX(100%)'; });

    // Click-to-select via centre crosshair while locked.
    var ray = new THREE.Raycaster();
    renderer.domElement.addEventListener('click', function () {
      if (!controls.isLocked) return;
      ray.setFromCamera({ x: 0, y: 0 }, camera);
      var hits = ray.intersectObjects(pickables, true);
      if (hits.length) {
        var o = hits[0].object;
        while (o && !o.userData.stop) o = o.parent;
        if (o && o.userData.stop) openPanel(o.userData.stop);
      }
    });

    // Movement loop
    var clock = new THREE.Clock();
    var vel = new THREE.Vector3();
    function animate() {
      requestAnimationFrame(animate);
      var dt = Math.min(0.05, clock.getDelta());
      if (controls.isLocked) {
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
