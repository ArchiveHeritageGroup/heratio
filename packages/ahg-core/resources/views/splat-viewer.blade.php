{{-- heratio#1193 Gaussian-splat viewer: standalone full-screen page (modern three.js + GaussianSplats3D). --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <title>{{ $splat->title }} - {{ __('Gaussian splat') }}</title>
  <style nonce="{{ $cspNonce ?? '' }}">
    html, body { margin:0; height:100%; width:100%; overflow:hidden; background:#0b0b0b; }
    #splat-root { position:absolute; inset:0; }
    #pc_bar { position:absolute; top:0; left:0; right:0; z-index:1000; padding:.5rem .75rem;
      background:rgba(20,20,20,.72); color:#fff; font:14px/1.3 system-ui,sans-serif; display:flex; gap:.75rem; align-items:center; }
    #pc_bar a { color:#9ec5ff; text-decoration:none; }
    #pc_bar .t { font-weight:600; }
    #pc_orient { margin-left:auto; display:flex; gap:.35rem; }
    #pc_orient button { background:transparent; border:1px solid #9ec5ff; color:#9ec5ff; border-radius:4px;
      padding:2px 9px; cursor:pointer; font:inherit; line-height:1.4; }
    #pc_orient button.active { background:#9ec5ff; color:#16213e; }
    {{-- In embed mode (inline iframe on a record), drop the back/title chrome but KEEP the
         orientation buttons so the user can re-orient the splat without going full screen. --}}
    @if(request()->boolean('embed'))
      #pc_bar { background:transparent; padding:.4rem .5rem; pointer-events:none; }
      #pc_bar .pc_nav { display:none; }
      #pc_orient { pointer-events:auto; background:rgba(20,20,20,.55); border-radius:6px; padding:.25rem .35rem; }
    @endif
    #pc_err { position:absolute; top:48px; left:0; right:0; z-index:1001; margin:1rem; padding:.75rem 1rem;
      background:#5c1620; color:#fff; font:14px/1.4 system-ui,sans-serif; border-radius:6px; display:none; }
    {{-- #1193 control bar: live TRELLIS-appropriate controls along the bottom. --}}
    #pc_ctrl { position:absolute; bottom:0; left:0; right:0; z-index:1000; padding:.5rem .6rem;
      background:rgba(20,20,20,.72); color:#fff; font:13px/1.3 system-ui,sans-serif;
      display:flex; flex-wrap:wrap; gap:.5rem .8rem; align-items:center; justify-content:center; }
    #pc_ctrl button, #pc_ctrl label { background:transparent; border:1px solid #9ec5ff; color:#9ec5ff;
      border-radius:4px; padding:3px 10px; cursor:pointer; font:inherit; line-height:1.4; display:inline-flex;
      align-items:center; gap:.35rem; }
    #pc_ctrl button.active { background:#9ec5ff; color:#16213e; }
    #pc_ctrl .grp { display:inline-flex; align-items:center; gap:.4rem; border:1px solid #9ec5ff33;
      border-radius:4px; padding:2px 8px; }
    #pc_ctrl .grp span { color:#9ec5ff; }
    #pc_ctrl input[type=range] { width:96px; accent-color:#9ec5ff; cursor:pointer; }
    #pc_ctrl input[type=color] { width:28px; height:22px; border:none; background:none; padding:0; cursor:pointer; }
    #pc_ctrl .val { min-width:2.2em; text-align:right; font-variant-numeric:tabular-nums; color:#cfe3ff; }
  </style>
</head>
<body>
  <div id="pc_bar">
    <span class="pc_nav"><a href="javascript:history.back()">&larr; {{ __('Back') }}</a></span>
    <span class="pc_nav t">{{ $splat->title }}</span>
    <span class="pc_nav" style="opacity:.8">{{ strtoupper($splat->format ?? '') }}</span>
    <span id="pc_orient">
      <button type="button" id="pc_ud" title="{{ __('Turn the object 90° towards the front') }}">&#x2191;</button>
      <button type="button" id="pc_fb" title="{{ __('Turn the object 90° to the right') }}">&#x2192;</button>
      <button type="button" id="pc_zo" title="{{ __('Zoom out (make smaller)') }}">&#x2212;</button>
      <button type="button" id="pc_zi" title="{{ __('Zoom in (make bigger)') }}">+</button>
      <button type="button" id="pc_up" title="{{ __('Move up') }}">&#x21E7;</button>
      <button type="button" id="pc_dn" title="{{ __('Move down') }}">&#x21E9;</button>
    </span>
  </div>
  <div id="pc_err">{{ __('This scene could not be loaded. Your browser may not support WebGL2, or the file may be incomplete.') }}</div>
  <div id="splat-root"></div>

  {{-- #1193 control bar (live): auto-rotate / reset-fit / point-cloud / splat-scale /
       alpha-cull / background / trackball / fullscreen / screenshot.
       SH-degree omitted on purpose - TRELLIS scenes are spherical-harmonics degree 0. --}}
  <div id="pc_ctrl">
    <button type="button" id="pc_auto"  title="{{ __('Auto-rotate the scene') }}">&#x21BB; {{ __('Auto-rotate') }}</button>
    <button type="button" id="pc_reset" title="{{ __('Reset the camera to the framed view') }}">&#x2316; {{ __('Reset / fit') }}</button>
    <button type="button" id="pc_pc"    title="{{ __('Toggle point-cloud rendering') }}">&#x22EF; {{ __('Point cloud') }}</button>
    <span class="grp"><span>{{ __('Splat scale') }}</span><input type="range" id="pc_scale" min="0.1" max="2" step="0.05" value="1"><span class="val" id="pc_scale_v">1.00</span></span>
    <span class="grp"><span>{{ __('Alpha cull') }}</span><input type="range" id="pc_alpha" min="1" max="60" step="1"><span class="val" id="pc_alpha_v"></span></span>
    <label title="{{ __('Background colour') }}">{{ __('Background') }} <input type="color" id="pc_bg"></label>
    <button type="button" id="pc_track" title="{{ __('Trackball: free-tumble rotation (no fixed up-axis)') }}">&#x1F500; {{ __('Trackball') }}</button>
    <button type="button" id="pc_full"  title="{{ __('Fullscreen') }}">&#x26F6; {{ __('Fullscreen') }}</button>
    <button type="button" id="pc_shot"  title="{{ __('Save a PNG screenshot') }}">&#x1F4F7; {{ __('Screenshot') }}</button>
  </div>

  <script type="importmap" nonce="{{ $cspNonce ?? '' }}">
  {
    "imports": {
      "three": "/vendor/three/0.169.0/three.module.min.js",
      "three/addons/": "/vendor/three/0.169.0/addons/",
      "@mkkellogg/gaussian-splats-3d": "/vendor/gaussian-splats-3d/0.4.7/gaussian-splats-3d.module.js"
    }
  }
  </script>
  <script type="module" nonce="{{ $cspNonce ?? '' }}">
    import * as THREE from 'three';
    import * as GaussianSplats3D from '@mkkellogg/gaussian-splats-3d';

    const url = @json($fileUrl);
    const fmt = @json(strtolower($splat->format ?? ''));
    const fail = () => { document.getElementById('pc_err').style.display = 'block'; };

    // Two 90-degree object rotations, accumulated across presses and persisted in the URL.
    // rx = up-arrow (tilt towards the front, around X); ry = right-arrow (turn right, around Y).
    // Each press reloads with the step bumped (mod 4); the scene is rotated by a quaternion.
    const params = new URLSearchParams(location.search);
    const rx = ((parseInt(params.get('rx') || '0', 10) % 4) + 4) % 4;
    const ry = ((parseInt(params.get('ry') || '0', 10) % 4) + 4) % 4;
    const z  = parseInt(params.get('z')  || '0', 10) || 0;   // zoom steps (+ = further/smaller)
    const py = parseInt(params.get('py') || '0', 10) || 0;   // vertical pan steps (+ = object up)
    const go = (name, val) => { const u = new URL(location.href); u.searchParams.set(name, String(val)); location.href = u.toString(); };
    document.getElementById('pc_ud').addEventListener('click', () => go('rx', (rx + 1) % 4));
    document.getElementById('pc_fb').addEventListener('click', () => go('ry', (ry + 1) % 4));
    document.getElementById('pc_zo').addEventListener('click', () => go('z', z + 1));
    document.getElementById('pc_zi').addEventListener('click', () => go('z', z - 1));
    document.getElementById('pc_up').addEventListener('click', () => go('py', py + 1));
    document.getElementById('pc_dn').addEventListener('click', () => go('py', py - 1));

    // Build the object rotation quaternion: turn-right (Y) applied in world, then tilt-front (X).
    const euler = new THREE.Euler(-rx * Math.PI / 2, -ry * Math.PI / 2, 0, 'YXZ');
    const q = new THREE.Quaternion().setFromEuler(euler);
    const rotation = [q.x, q.y, q.z, q.w];

    // Frame the camera. When the scene bounds are known (computed server-side), aim at the real
    // centre at a fitting distance - so the object is never oversized or half off-screen - and
    // pivot rotation about that centre so it spins in place. Otherwise fall back to a wide default.
    // cameraUp is [0,-1,0] (screen-up == world -Y), so raising the camera in +Y moves it UP.
    const bounds = @json($bounds ?? null);
    let camPos, camLook, position = [0, 0, 0];
    if (bounds && bounds.radius > 0) {
      const C = new THREE.Vector3(bounds.center[0], bounds.center[1], bounds.center[2]);
      const P = C.clone().sub(C.clone().applyQuaternion(q));   // scene.position = C - Q*C (pivot about C)
      position = [P.x, P.y, P.z];
      const fov = 50 * Math.PI / 180;                          // gs3d default camera fov
      const fit = (bounds.radius / Math.sin(fov / 2)) * 1.4;
      const dist = fit * Math.pow(1.25, z);
      const hh = py * 0.15 * bounds.radius;                    // pan step scales with object size
      camPos = [C.x, C.y + hh, C.z + dist];
      camLook = [C.x, C.y + hh, C.z];
    } else {
      const dist = 4 * Math.pow(1.3, z);
      const hh = py * 0.5;
      camPos = [0, hh, dist];
      camLook = [0, hh, 0];
    }

    // Mirror the proven ai-demo /viewer config: pass the format EXPLICITLY (a .ply won't
    // auto-detect/progressive-load reliably as a splat) and disable progressive load.
    const sceneFormat = fmt === 'ply'    ? GaussianSplats3D.SceneFormat.Ply
                      : fmt === 'ksplat' ? GaussianSplats3D.SceneFormat.KSplat
                      :                    GaussianSplats3D.SceneFormat.Splat;

    const ac = Math.max(0, parseInt(params.get('ac') || '5', 10));   // alpha-cull threshold (load-time)
    let bg = params.get('bg'); if (!bg || !/^#[0-9a-fA-F]{6}$/.test(bg)) bg = '#0b0b0b';

    try {
      const root = document.getElementById('splat-root');
      // External renderer so we can (a) save PNG screenshots (preserveDrawingBuffer) and
      // (b) recolour the background live. With an external renderer the Viewer skips its own
      // sizing/append/resize, so we do those here.
      const renderer = new THREE.WebGLRenderer({ antialias: true, precision: 'highp', preserveDrawingBuffer: true });
      renderer.setPixelRatio(window.devicePixelRatio || 1);
      renderer.setSize(root.offsetWidth, root.offsetHeight);
      renderer.setClearColor(new THREE.Color(bg), 1);
      root.appendChild(renderer.domElement);
      document.body.style.background = bg;

      const viewer = new GaussianSplats3D.Viewer({
        rootElement: root,
        renderer: renderer,
        sharedMemoryForWorkers: false,   // no COOP/COEP isolation on the host
        dynamicScene: false,
        cameraUp: [0, -1, 0],            // proven ai-demo / TRELLIS default
        initialCameraPosition: camPos,
        initialCameraLookAt: camLook,
      });

      window.addEventListener('resize', () => {
        const w = root.offsetWidth, h = root.offsetHeight;
        renderer.setSize(w, h);
        const cam = viewer.camera;
        if (cam && cam.isPerspectiveCamera) { cam.aspect = w / h; cam.updateProjectionMatrix(); }
      });

      viewer.addSplatScene(url, { format: sceneFormat, rotation: rotation, position: position, progressiveLoad: false, showLoadingUI: true, splatAlphaRemovalThreshold: ac })
        .then(() => { viewer.start(); wireControls(viewer, renderer, bg, ac); })
        .catch(fail);
    } catch (e) { fail(); }

    // ---- #1193 control-bar wiring (runs once the scene + splatMesh exist) ----
    function wireControls(viewer, renderer, bg, ac) {
      const $ = (id) => document.getElementById(id);
      const mesh = viewer.splatMesh;
      const orbit = viewer.controls;                 // active OrbitControls
      const fitPos = viewer.camera.position.clone();
      const fitTarget = (orbit && orbit.target) ? orbit.target.clone() : new THREE.Vector3(camLook[0], camLook[1], camLook[2]);

      // Auto-rotate (OrbitControls.autoRotate; the viewer calls controls.update() each frame).
      let autoOn = false;
      $('pc_auto').addEventListener('click', () => {
        autoOn = !autoOn;
        if (orbit) { orbit.autoRotate = autoOn; orbit.autoRotateSpeed = 2.0; }
        $('pc_auto').classList.toggle('active', autoOn);
      });

      // Reset / fit: restore the framed camera (works for whichever controls is active).
      $('pc_reset').addEventListener('click', () => {
        viewer.camera.position.copy(fitPos);
        const c = viewer.controls;
        if (c && c.target) { c.target.copy(fitTarget); if (c.update) c.update(); }
      });

      // Point-cloud toggle.
      let pcOn = false;
      $('pc_pc').addEventListener('click', () => {
        pcOn = !pcOn;
        if (mesh && mesh.setPointCloudModeEnabled) mesh.setPointCloudModeEnabled(pcOn);
        $('pc_pc').classList.toggle('active', pcOn);
      });

      // Splat scale (live).
      const sc = $('pc_scale'), scv = $('pc_scale_v');
      sc.addEventListener('input', () => {
        const v = parseFloat(sc.value);
        if (mesh && mesh.setSplatScale) mesh.setSplatScale(v);
        scv.textContent = v.toFixed(2);
      });

      // Alpha cull (load-time): reload with the new threshold on release.
      const al = $('pc_alpha'), alv = $('pc_alpha_v');
      al.value = ac; alv.textContent = ac;
      al.addEventListener('input', () => { alv.textContent = al.value; });
      al.addEventListener('change', () => go('ac', Math.round(parseFloat(al.value))));

      // Background colour (live).
      const bgi = $('pc_bg');
      bgi.value = bg;
      bgi.addEventListener('input', () => {
        renderer.setClearColor(new THREE.Color(bgi.value), 1);
        document.body.style.background = bgi.value;
      });

      // Trackball: free-tumble rotation. Hand the viewer's render loop to a TrackballControls
      // instance (and silence OrbitControls' input) while active; restore orbit on toggle-off.
      let trackball = null;
      $('pc_track').addEventListener('click', async () => {
        if (!trackball) {
          const { TrackballControls } = await import('three/addons/controls/TrackballControls.js');
          trackball = new TrackballControls(viewer.camera, renderer.domElement);
          trackball.rotateSpeed = 3.0; trackball.panSpeed = 0.8; trackball.zoomSpeed = 1.2;
          trackball.target.copy((orbit && orbit.target) ? orbit.target : fitTarget);
          if (orbit) orbit.enabled = false;
          viewer.controls = trackball;       // viewer loop now calls trackball.update()
          $('pc_track').classList.add('active');
        } else {
          viewer.controls = orbit;
          if (orbit) orbit.enabled = true;
          trackball.dispose(); trackball = null;
          $('pc_track').classList.remove('active');
        }
      });

      // Fullscreen.
      $('pc_full').addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen();
        else document.documentElement.requestFullscreen().catch(() => {});
      });

      // Screenshot (PNG). preserveDrawingBuffer keeps toDataURL from coming back blank.
      $('pc_shot').addEventListener('click', () => {
        try {
          const a = document.createElement('a');
          const base = (@json($splat->slug ?? $splat->title ?? 'splat') + '').replace(/[^a-z0-9._-]+/gi, '-').slice(0, 60) || 'splat';
          a.download = base + '.png';
          a.href = renderer.domElement.toDataURL('image/png');
          a.click();
        } catch (e) {}
      });
    }
  </script>
</body>
</html>
