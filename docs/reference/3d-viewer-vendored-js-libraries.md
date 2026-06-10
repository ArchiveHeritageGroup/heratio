# 3D viewer JavaScript libraries are self-hosted (no CDN)

As of 2026-06-10, all three Heratio 3D viewers load their JS **only** from `public/vendor/`
- there are no external CDN dependencies (jsdelivr / googleapis / cdnjs / unpkg / gstatic).
Do NOT reintroduce a CDN `<script>` or importmap URL for these; add the file under
`public/vendor/` and point at it with an absolute `/vendor/...` path.

## Vendored layout (`public/vendor/`)

| Path | What | Used by |
|---|---|---|
| `three/0.169.0/three.module.min.js` | three.js r169 (ESM) | splat viewer |
| `three/0.169.0/addons/controls/TrackballControls.js` | trackball (free-tumble) | splat viewer |
| `gaussian-splats-3d/0.4.7/gaussian-splats-3d.module.js` | @mkkellogg GaussianSplats3D | splat viewer |
| `three/0.137.5/three.min.js` + `examples/js/{loaders,controls,webxr}/*` | three r137 non-module globals (GLTFLoader, DRACOLoader, OBJ/STL/PLY/PCDLoader, PointerLock/OrbitControls, VRButton) | exhibition walkthrough |
| `three/0.137.5/{draco,basis}/`, `three/0.137.5/loaders/KTX2Loader.js` | Draco + KTX2/Basis decoders | walkthrough + model-viewer |
| `pdfjs/3.11.174/{pdf.min.js,pdf.worker.min.js}` | pdf.js (PDF object pages) | walkthrough |
| `model-viewer/3.3.0/model-viewer.min.js` | Google model-viewer | record / sector / museum show |
| `three/0.160.0/three.module.js` + `addons/{controls/OrbitControls,loaders/OBJLoader,loaders/STLLoader}.js` | three r160 ESM + addons (OBJ/STL fallback viewer) | record show partial |

Three different three.js versions are intentional: r137 (walkthrough needs the non-module
`examples/js` globals + EXT_texture_webp), r160 (record-viewer ESM addons), r169 (the splat
viewer's GaussianSplats3D build). Keep them side by side; don't try to unify.

## Wiring notes

- **Importmaps** map bare `three` / `three/addons/` to the local files; vendored addon files
  (TrackballControls, OBJ/STLLoader, OrbitControls) import bare `'three'`, which the importmap
  resolves - so a single local `three` entry covers them.
- **VRButton**: the r137 non-module global does NOT exist upstream (the old jsdelivr
  `examples/js/webxr/VRButton.js` 404s, so VR was silently dead). We vendor a global build by
  converting the ESM `examples/jsm/webxr/VRButton.js` (`export { VRButton }` ->
  `THREE.VRButton = VRButton`). Regenerate the same way if bumping three.
- **model-viewer** decoder locations are set once via the static
  `ModelViewerElement.dracoDecoderLocation` / `ktx2TranscoderLocation` (pointing at
  `/vendor/three/0.137.5/{draco,basis}/`); static = applies to every `<model-viewer>`.
- **CSP** (`app/Csp/HeratioCspPreset.php`) already allows `self` + WASM + blob workers, so
  self-hosted libs/decoders need no CSP change. Removing the CDN hosts from CSP is now safe
  if desired (defence in depth).

## Files that reference these (where to edit if bumping a version)

- `packages/ahg-core/resources/views/splat-viewer.blade.php` (importmap)
- `packages/ahg-exhibition/resources/views/exhibition-space/walkthrough.blade.php` (script tags + pdf worker)
- `packages/ahg-information-object-manage/resources/views/partials/_digital-object-viewer.blade.php` (importmap + model-viewer; **locked path** - unlock first)
