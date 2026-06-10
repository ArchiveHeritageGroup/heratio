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
    </span>
  </div>
  <div id="pc_err">{{ __('This scene could not be loaded. Your browser may not support WebGL2, or the file may be incomplete.') }}</div>
  <div id="splat-root"></div>

  <script type="importmap" nonce="{{ $cspNonce ?? '' }}">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.169.0/build/three.module.min.js",
      "@mkkellogg/gaussian-splats-3d": "https://cdn.jsdelivr.net/npm/@mkkellogg/gaussian-splats-3d@0.4.7/build/gaussian-splats-3d.module.js"
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
    const bump = (name, cur) => { const u = new URL(location.href); u.searchParams.set(name, String((cur + 1) % 4)); location.href = u.toString(); };
    document.getElementById('pc_ud').addEventListener('click', () => bump('rx', rx));
    document.getElementById('pc_fb').addEventListener('click', () => bump('ry', ry));

    // Build the object rotation quaternion: turn-right (Y) applied in world, then tilt-front (X).
    const euler = new THREE.Euler(-rx * Math.PI / 2, -ry * Math.PI / 2, 0, 'YXZ');
    const q = new THREE.Quaternion().setFromEuler(euler);
    const rotation = [q.x, q.y, q.z, q.w];

    // Mirror the proven ai-demo /viewer config: pass the format EXPLICITLY (a .ply won't
    // auto-detect/progressive-load reliably as a splat) and disable progressive load.
    const sceneFormat = fmt === 'ply'    ? GaussianSplats3D.SceneFormat.Ply
                      : fmt === 'ksplat' ? GaussianSplats3D.SceneFormat.KSplat
                      :                    GaussianSplats3D.SceneFormat.Splat;

    try {
      const viewer = new GaussianSplats3D.Viewer({
        rootElement: document.getElementById('splat-root'),
        sharedMemoryForWorkers: false,   // no COOP/COEP isolation on the host
        dynamicScene: false,
        cameraUp: [0, -1, 0],            // proven ai-demo / TRELLIS default
        initialCameraPosition: [0, 0, 2],
        initialCameraLookAt: [0, 0, 0],
      });
      viewer.addSplatScene(url, { format: sceneFormat, rotation: rotation, progressiveLoad: false, showLoadingUI: true, splatAlphaRemovalThreshold: 5 })
        .then(() => { viewer.start(); })
        .catch(fail);
    } catch (e) { fail(); }
  </script>
</body>
</html>
