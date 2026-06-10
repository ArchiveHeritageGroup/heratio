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
    @if(request()->boolean('embed')) #pc_bar { display:none; } #splat-root { inset:0; } @endif
    #pc_bar { position:absolute; top:0; left:0; right:0; z-index:1000; padding:.5rem .75rem;
      background:rgba(20,20,20,.72); color:#fff; font:14px/1.3 system-ui,sans-serif; display:flex; gap:.75rem; align-items:center; }
    #pc_bar a { color:#9ec5ff; text-decoration:none; }
    #pc_bar .t { font-weight:600; }
    #pc_err { position:absolute; top:48px; left:0; right:0; z-index:1001; margin:1rem; padding:.75rem 1rem;
      background:#5c1620; color:#fff; font:14px/1.4 system-ui,sans-serif; border-radius:6px; display:none; }
  </style>
</head>
<body>
  <div id="pc_bar">
    <a href="javascript:history.back()">&larr; {{ __('Back') }}</a>
    <span class="t">{{ $splat->title }}</span>
    <span style="opacity:.8">{{ strtoupper($splat->format ?? '') }}</span>
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
    const fail = () => { document.getElementById('pc_err').style.display = 'block'; };

    try {
      const viewer = new GaussianSplats3D.Viewer({
        rootElement: document.getElementById('splat-root'),
        cameraUp: [0, -1, 0],
        initialCameraPosition: [0, 0, 4],
        initialCameraLookAt: [0, 0, 0],
        // No COOP/COEP isolation on the host, so don't require SharedArrayBuffer.
        sharedMemoryForWorkers: false,
      });
      viewer.addSplatScene(url, { showLoadingUI: true, splatAlphaRemovalThreshold: 5 })
        .then(() => { viewer.start(); })
        .catch(fail);
    } catch (e) { fail(); }
  </script>
</body>
</html>
