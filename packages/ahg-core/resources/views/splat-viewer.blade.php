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
  @php
    // Orientation presets: each maps a label to the cameraUp axis. Splats commonly load in an
    // arbitrary axis convention, so Up/Down/Front/Back let the viewer pick which world axis is up.
    $orient = ['yp' => __('Up'), 'yn' => __('Down'), 'zp' => __('Front'), 'zn' => __('Back')];
    $curUp = in_array(request()->query('up'), array_keys($orient), true) ? request()->query('up') : 'yn';
  @endphp
  <div id="pc_bar">
    <span class="pc_nav"><a href="javascript:history.back()">&larr; {{ __('Back') }}</a></span>
    <span class="pc_nav t">{{ $splat->title }}</span>
    <span class="pc_nav" style="opacity:.8">{{ strtoupper($splat->format ?? '') }}</span>
    <span id="pc_orient" title="{{ __('Re-orient the scene (splats often load in a different up-axis)') }}">
      @foreach($orient as $key => $label)
        <button type="button" data-up="{{ $key }}" class="{{ $curUp === $key ? 'active' : '' }}">{{ $label }}</button>
      @endforeach
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
    import * as GaussianSplats3D from '@mkkellogg/gaussian-splats-3d';

    const url = @json($fileUrl);
    const fmt = @json(strtolower($splat->format ?? ''));
    const fail = () => { document.getElementById('pc_err').style.display = 'block'; };

    // Up-axis presets (must match the PHP $orient keys). Default y-down matches the
    // ai-demo / TRELLIS output; the buttons reload with ?up=<key> to switch axis.
    const UPS = { yp: [0, 1, 0], yn: [0, -1, 0], zp: [0, 0, 1], zn: [0, 0, -1] };
    const upKey = (new URLSearchParams(location.search).get('up')) || 'yn';
    const upVec = UPS[upKey] || UPS.yn;
    document.querySelectorAll('#pc_orient button').forEach((b) => {
      b.addEventListener('click', function () {
        const u = new URL(location.href);
        u.searchParams.set('up', b.dataset.up);
        location.href = u.toString();
      });
    });

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
        cameraUp: upVec,
        initialCameraPosition: [0, 0, 2],
        initialCameraLookAt: [0, 0, 0],
      });
      viewer.addSplatScene(url, { format: sceneFormat, progressiveLoad: false, showLoadingUI: true, splatAlphaRemovalThreshold: 5 })
        .then(() => { viewer.start(); })
        .catch(fail);
    } catch (e) { fail(); }
  </script>
</body>
</html>
