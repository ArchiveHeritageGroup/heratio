{{-- heratio#1153 — WebGPU renderer SPIKE (proof page). NOT the live walkthrough.
     Modern three.js (r169) ES modules + WebGPURenderer, which renders with the WebGPU
     backend on capable devices and AUTO-FALLS-BACK to WebGL2 otherwise. Validates the
     renderer stack + importmap-under-CSP + jsm loaders/controls + scan-shell loading,
     to de-risk a future migration of the live r137 walkthrough. --}}
@extends('theme::layouts.1col')

@section('title', __('3D Walkthrough (WebGPU spike)') . ' — ' . $space->name)
@section('body-class', 'exhibition-space walkthrough-3d-webgpu')

@section('content')
  <div class="alert alert-info d-flex flex-wrap align-items-center gap-2 py-2">
    <i class="fas fa-flask"></i>
    <strong>{{ __('WebGPU renderer spike (#1153)') }}</strong>
    <span class="small">{{ __('A proof page on modern three.js + WebGPURenderer (WebGPU where available, WebGL2 fallback). The live walkthrough is unchanged.') }}</span>
    <a href="{{ route('exhibition-space.walkthrough', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary ms-auto">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to the live walkthrough') }}
    </a>
  </div>

  <div id="wgRoom" style="position:relative;width:100%;height:72vh;min-height:420px;background:#0b0d10;border-radius:8px;overflow:hidden;">
    <canvas id="wgCanvas" style="display:block;width:100%;height:100%;"></canvas>

    {{-- Backend + FPS badge: the headline evidence for the spike. --}}
    <div id="wgBadge" class="bg-dark text-white px-2 py-1 rounded small" style="position:absolute;top:8px;left:8px;z-index:5;opacity:.92;">
      <i class="fas fa-microchip me-1"></i><span id="wgBackend">{{ __('starting…') }}</span>
      <span class="ms-2 text-muted" id="wgFps"></span>
    </div>

    {{-- Click-to-enter blocker (pointer lock). --}}
    <div id="wgBlocker" style="position:absolute;inset:0;z-index:6;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);cursor:pointer;">
      <div class="text-center text-white">
        <div class="fs-4 mb-1"><i class="fas fa-person-walking me-2"></i>{{ __('Click to walk') }}</div>
        <div class="small opacity-75">{{ __('W A S D / arrows to move · mouse to look · Esc to release') }}</div>
      </div>
    </div>

    <div id="wgError" class="bg-danger text-white px-3 py-2 rounded small" style="position:absolute;bottom:8px;left:8px;right:8px;z-index:6;display:none;"></div>
  </div>

  {{-- ES-module import map. MUST carry the CSP nonce and precede the module script.
       'three' is mapped to the three.webgpu build so addons (which import from 'three')
       and WebGPURenderer resolve from one module graph. --}}
  <script type="importmap" nonce="{{ $cspNonce ?? '' }}">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.webgpu.min.js",
      "three/webgpu": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.webgpu.min.js",
      "three/tsl": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.tsl.min.js",
      "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.169.0/examples/jsm/"
    }
  }
  </script>

  <script type="module" nonce="{{ $cspNonce ?? '' }}">
    import * as THREE from 'three';
    import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
    import { PointerLockControls } from 'three/addons/controls/PointerLockControls.js';

    const ROOMS = @json($building['rooms'] ?? []);
    const EYE = 1.6;                      // eye height (m)
    const blocker = document.getElementById('wgBlocker');
    const badgeEl = document.getElementById('wgBackend');
    const fpsEl = document.getElementById('wgFps');
    const errEl = document.getElementById('wgError');
    const host = document.getElementById('wgRoom');
    const canvas = document.getElementById('wgCanvas');

    function fail(msg) { errEl.textContent = msg; errEl.style.display = 'block'; }

    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x12161c);
    const camera = new THREE.PerspectiveCamera(70, host.clientWidth / host.clientHeight, 0.1, 500);

    // WebGPURenderer: uses the WebGPU backend where available, else WebGL2. init() is async.
    const renderer = new THREE.WebGPURenderer({ canvas, antialias: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    renderer.setSize(host.clientWidth, host.clientHeight, false);

    // Lighting (kept simple - this is a renderer spike, not the full walkthrough).
    scene.add(new THREE.HemisphereLight(0xffffff, 0x404048, 1.1));
    const sun = new THREE.DirectionalLight(0xffffff, 1.4);
    sun.position.set(8, 20, 6);
    scene.add(sun);

    // Build each room as a floor + four low walls so you have something to walk through,
    // then drop in the photoreal scan shell (#1156) if the room has one.
    const wallMat = new THREE.MeshStandardMaterial({ color: 0xb9c0c9, roughness: 0.92 });
    const floorMat = new THREE.MeshStandardMaterial({ color: 0x6f7681, roughness: 0.96 });
    const loader = new GLTFLoader();
    let spawnX = 0, spawnZ = 5, haveSpawn = false;

    function box(w, h, d, mat, x, y, z) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
      m.position.set(x, y, z); scene.add(m); return m;
    }

    (ROOMS || []).forEach(function (rm) {
      const w = rm.w || 6, d = rm.d || 6, h = rm.h || 3;
      const ox = rm.x_offset || 0, oz = rm.z_offset || 0;
      const cx = ox + w / 2, cz = oz + d / 2;
      const t = 0.12;
      box(w, t, d, floorMat, cx, 0, cz);                       // floor
      box(w, h, t, wallMat, cx, h / 2, oz);                    // back wall
      box(w, h, t, wallMat, cx, h / 2, oz + d);                // front wall
      box(t, h, d, wallMat, ox, h / 2, cz);                    // left wall
      box(t, h, d, wallMat, ox + w, h / 2, cz);                // right wall
      if (!haveSpawn || rm.is_current) { spawnX = cx; spawnZ = cz; haveSpawn = true; }

      if (rm.scan_shell) {
        const ext = String(rm.scan_shell).split('?')[0].split('.').pop().toLowerCase();
        if (ext === 'glb' || ext === 'gltf') {
          loader.load(rm.scan_shell, function (g) {
            const o = g.scene; o.scale.setScalar(rm.scan_shell_scale || 1);
            o.position.set(ox, 0, oz); scene.add(o);
          }, undefined, function () { fail('Scan shell failed to load: ' + rm.scan_shell); });
        }
        // Non-glTF mesh shells (obj/stl/ply) + point clouds are out of scope for the spike.
      }
    });

    camera.position.set(spawnX, EYE, spawnZ);

    // First-person controls. Recent PointerLockControls move the camera directly
    // (no getObject()); we read/clamp camera.position and use moveForward/moveRight.
    const controls = new PointerLockControls(camera, document.body);
    blocker.addEventListener('click', function () { controls.lock(); });
    controls.addEventListener('lock', function () { blocker.style.display = 'none'; });
    controls.addEventListener('unlock', function () { blocker.style.display = 'flex'; });

    const keys = Object.create(null);
    function code(e) { return e.code || e.key; }
    document.addEventListener('keydown', function (e) { keys[code(e)] = true; });
    document.addEventListener('keyup', function (e) { keys[code(e)] = false; });

    function moving() {
      const f = (keys.KeyW || keys.ArrowUp ? 1 : 0) - (keys.KeyS || keys.ArrowDown ? 1 : 0);
      const r = (keys.KeyD || keys.ArrowRight ? 1 : 0) - (keys.KeyA || keys.ArrowLeft ? 1 : 0);
      return { f: f, r: r };
    }

    let last = performance.now(), frames = 0, fpsAccum = 0, badgeDone = false;
    function tick() {
      const now = performance.now(), dt = Math.min(0.1, (now - last) / 1000); last = now;
      if (controls.isLocked) {
        const sp = 3.2 * dt, mv = moving();
        if (mv.f) controls.moveForward(sp * mv.f);
        if (mv.r) controls.moveRight(sp * mv.r);
        camera.position.y = EYE;   // stay at eye height (flat floor)
      }
      renderer.renderAsync(scene, camera);
      // FPS readout
      frames++; fpsAccum += dt;
      if (fpsAccum >= 0.5) { fpsEl.textContent = Math.round(frames / fpsAccum) + ' fps'; frames = 0; fpsAccum = 0; }
    }

    // init() picks/initialises the backend; only then is renderer.backend populated.
    renderer.init().then(function () {
      // Report which backend actually came up - the headline evidence for #1153.
      let label = 'WebGL2';
      try { if (renderer.backend && renderer.backend.isWebGPUBackend) label = 'WebGPU'; } catch (e) {}
      if (label === 'WebGL2' && !navigator.gpu) label = 'WebGL2 (no navigator.gpu)';
      badgeEl.textContent = label;
      badgeDone = true;
      renderer.setAnimationLoop(tick);
    }).catch(function (e) {
      fail('Renderer init failed: ' + (e && e.message ? e.message : e));
      badgeEl.textContent = 'failed';
    });

    function onResize() {
      const w = host.clientWidth, h = host.clientHeight;
      camera.aspect = w / h; camera.updateProjectionMatrix();
      renderer.setSize(w, h, false);
    }
    window.addEventListener('resize', onResize);
  </script>
@endsection
