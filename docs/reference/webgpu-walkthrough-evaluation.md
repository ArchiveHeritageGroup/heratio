# WebGPU renderer for the exhibition walkthrough - evaluation & migration plan (#1153)

Assessment of moving the exhibition 3D walkthrough from its current WebGL renderer to
three.js's **WebGPURenderer**, plus the spike that proves the stack. Rendering-performance
track of the #1145 digital-twin roadmap.

## TL;DR

- The live walkthrough is pinned to **three.js r137**, loaded with the legacy
  `examples/js` **global-script** pattern (classic `<script>`, everything is `THREE.*`).
- `WebGPURenderer` only exists in **modern three.js (r16x+)**, is **ES-module-only**, and
  cannot be added to r137. So "adopt WebGPU" = migrate the walkthrough to ES modules.
- The big win: modern `WebGPURenderer` has a **built-in automatic WebGL2 fallback**, so one
  renderer covers WebGPU (capable devices) AND WebGL2 (everything else) - exactly the
  "graceful WebGL fallback" the issue asks for, with no maintenance fork.
- **Spike shipped** (this change): a standalone proof page validates the full stack
  (importmap-under-CSP, jsm loaders/controls, WebGPURenderer init + backend reporting,
  scan-shell loading) **without touching** the live walkthrough.
- Recommendation: **migrate after the spike confirms parity on target devices** - the cost
  is the module migration of one large, fragile file, not the renderer swap itself.

## The spike (what shipped here)

- Route: `GET /exhibition-space/{slug}/walkthrough-webgpu`
  (`exhibition-space.walkthrough-webgpu`), controller `walkthroughWebgpu()`. Public, reuses
  `getWalkthroughBuilding()` so it sees the same rooms (incl. #1156 scan shells).
- View: `resources/views/exhibition-space/walkthrough-webgpu.blade.php` - a minimal
  first-person renderer: each room as floor + four walls, WASD + PointerLock, a scan-shell
  load (glTF/GLB), and a **backend badge** (WebGPU vs WebGL2) + FPS readout.
- three.js **r169** via an **import map** (CSP-nonced), `three` mapped to
  `build/three.webgpu.min.js` so addons (which import from `three`) and `WebGPURenderer`
  share one module graph. Loaders/controls from `examples/jsm/`.
- Deliberately NOT a clone of the live walkthrough - just enough to de-risk the renderer.

Open it on a WebGPU-capable browser (recent Chrome/Edge, Safari 18+) and the badge reads
**WebGPU**; elsewhere it reads **WebGL2** from the same code path.

## What the migration of the live walkthrough involves

| Area | r137 today | After migration | Effort/risk |
|---|---|---|---|
| Module system | global `<script>` + `THREE.*` | `<script type=module>` + import map | Medium - one-time, mechanical but pervasive |
| three.js | r137 build/three.min.js | r169 build/three.webgpu.min.js | Low |
| Renderer | `new THREE.WebGLRenderer()` | `new THREE.WebGPURenderer()` + `await init()` | Low (but loop must start after init) |
| Loaders/controls | examples/js globals (`new THREE.GLTFLoader()`) | `import {GLTFLoader} from 'three/addons/...'` | Medium - swap every loader/control + DRACO path |
| Materials | MeshStandard/Basic/Sprite/Canvas etc. | unchanged (node system maps them transparently) | Low |
| CSP | nonce on the one inline script | nonce on **import map + module script**; jsdelivr already allowed | Low |
| Scan shells (#1156) | `loadModel` meshes glTF/OBJ/STL/PLY | same loaders exist as jsm addons | Low |
| PointerLockControls | `controls.getObject()` | `getObject()` removed in r16x - use `controls.object` / move the camera | Medium - touch movement/teleport/tour-fly code |

### Known gotchas (call them out before migrating)

- **`PointerLockControls.getObject()` is gone** in modern three. The live walkthrough uses
  `controls.getObject().position` heavily (movement clamp, room detection, tour fly-to). All
  of those must read `controls.object` / the camera. This is the single biggest mechanical
  risk in the file.
- **Async init**: `WebGPURenderer.init()` is a promise; nothing may render before it
  resolves. The animation loop and any first-frame work must move into the `.then()`.
- **`renderer.renderAsync()`** is the WebGPU-friendly call (the spike uses it). `render()`
  still works but `setAnimationLoop` + `renderAsync` is the documented pattern.
- **Import map = one per document, before the first module script.** Under the theme layout
  this is fine (the theme bundle is a classic script), but ordering must be respected.
- **`EXT_texture_webp`** (used since #1181) decodes fine on the WebGPU backend; no change.
- **VRButton/WebXR** - the live page guards a 404 on VRButton; the jsm `VRButton` exists in
  r169, and WebXR over WebGPU is still maturing - validate XR separately, keep WebGL for XR
  if needed.

## Performance expectation

WebGPU's gains show most with **many draw calls / instancing / compute** (large buildings,
crowds of avatars, particle/graffiti systems). For small single rooms the two backends are
comparable; the spike's FPS badge is there to measure real deltas on target hardware before
committing. WebGPU is not a free speed-up for trivial scenes - the case strengthens as the
twin scales (more rooms, more placements, more live avatars).

## Recommendation

1. Land the spike (done) and exercise it on the real target devices/browsers (desktop
   Chrome/Edge, Safari 18, Android Chrome, the kiosk hardware).
2. If WebGPU initialises and renders scan shells + rooms with acceptable FPS and clean
   WebGL2 fallback, schedule the live migration as **one focused pass** on
   `walkthrough.blade.php`: import map + module script, swap loaders/controls to jsm, move
   the loop behind `init()`, and replace every `getObject()` with `controls.object`.
3. Keep `#1153` open until the live walkthrough is migrated; the spike is the evaluation
   deliverable, not the adoption.

## Update - live migration landed (pending browser verification)

The live `walkthrough.blade.php` has now been migrated in one pass:

- r137 examples/js globals -> r169 **import map** (`three` -> `three.webgpu` build) + a single
  `<script type="module">`; addon loaders/controls imported from `three/addons/`.
- A mutable `THREE` is built via `Object.assign({}, THREE_NS, { GLTFLoader, ... })` so the
  ~212 existing `THREE.*` references (core + loaders + controls) work unchanged.
- `WebGLRenderer` -> `WebGPURenderer`; the animation loop starts behind `await renderer.init()`;
  both `render()` calls -> `renderAsync()`.
- All 23 `controls.getObject()` -> `controls.object` (getObject warns 60x/s in r169).
- DRACO decoder path -> r169 jsm.
- **All `renderer.xr` use is guarded** (`if (renderer.xr) ...`) and `VRButton` is a guarded
  dynamic import, so an absent/immature WebGPU XR manager can't crash desktop - it just
  hides VR.

**Verify in a browser** (revert via git if a regression shows): backend (DevTools - WebGPU
vs WebGL2), room/walls/floor textures + colour, **shadows** (sun toggle), **PBR metals**
(gold models reflect, not black -> PMREM env still applies), scan shells (#1156), pictures/
labels (canvas/sprite textures), graffiti, night/torch, the live overlay + avatars, and
**VR** on a headset (the highest-risk area). If colours look washed, it is the r169 default
colour-space change on canvas/sprite textures - set `.colorSpace = THREE.SRGBColorSpace` on
those, not a renderer problem.

## Files

- `packages/ahg-exhibition/resources/views/exhibition-space/walkthrough-webgpu.blade.php` - spike view
- `ExhibitionSpaceController::walkthroughWebgpu()` + route `exhibition-space.walkthrough-webgpu`
- `walkthrough.blade.php` - **now migrated** to r169 + WebGPURenderer (was the r137 WebGL target)
