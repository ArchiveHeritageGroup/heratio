# Gaussian-splat viewer (#1193)

Photoreal radiance-field captures (`.ply` / `.splat` / `.ksplat`) viewed in the browser - far
higher fidelity than meshes or raw point clouds. Pairs with the LiDAR point-cloud viewer
(#1183); both serve the rock-art deployment (photoreal panel/shelter capture).

## First slice: standalone viewer (decoupled from the walkthrough)

Embedding splats *in* the live walkthrough is **blocked**: that page is pinned to three.js
**r137** (legacy global scripts) and splat renderers need modern three.js (see
`docs/reference/webgpu-walkthrough-evaluation.md`). So this slice is a **standalone** viewer,
exactly parallel to the point-cloud tool, with zero risk to the walkthrough. Walkthrough-embed
follows the r137 -> modern renderer migration (#1153) and is captured as a follow-up.

Training (photos -> splat) is a GPU job done off-platform (Postshot / Luma / nerfstudio); this
feature **views** the result - no server-side conversion.

## Where it lives (ahg-core, Admin -> Media)

- `GaussianSplatService` - `store()` (validate ext, move to `splats_path/<id>.<ext>`, record
  row), `getBySlug()`, `list()`, `fileUrl()` (`/splats/<file>`). Formats: ply/splat/ksplat.
- `GaussianSplatController` - `/admin/splats` (manage + upload, auth), `/splat/{slug}` (public
  viewer for ready captures; two-segment path, safe from the `/{slug}` catch-all). Menu:
  Admin -> Media -> Gaussian splats.
- Table `ahg_gaussian_splat` (provider auto-creates): `slug, title, source_filename, file_name,
  format, size_bytes, status, error, created_by`.
- `ahg:splat-setup` publishes `public/splats -> splats_path` (git-ignored, per host; chowns to
  www-data when run as root so web uploads can write).
- View `splat-viewer.blade.php` - standalone full-screen page loading **three.js r169** +
  **@mkkellogg/gaussian-splats-3d 0.4.7** via a CSP-nonced import map (same CDN the walkthrough
  uses). `sharedMemoryForWorkers:false` so it works without COOP/COEP cross-origin isolation.

## Config

`heratio.splats_path` (default `<storage>/splats`). The viewer pins three + GaussianSplats3D
versions in the import map; no host binary needed (unlike PotreeConverter).

## Demo

A public sample scene is seeded as **`/splat/demo-train-scene`** ("Demo: Train scene") - the
classic 3DGS `train.splat` (~33 MB), to demonstrate the viewer without needing a capture.

## Verified / caveats

Verified server-side: routes, table, static serving of the 33 MB seed (HTTP 200 + range 206),
viewer page renders with the import map + loader. The **WebGL render itself needs a browser to
confirm** (can't be checked server-side). If a scene fails to load the page shows a clear
message (WebGL2 / incomplete file). Offline note: the viewer pulls three + the splat lib from
the CDN (same as the walkthrough) - vendor them if a fully offline deployment is required.

## Follow-up

Embed splat shells in the walkthrough once it migrates to modern three.js (#1153) - then a room
can be *backed* by a splat capture (the issue's full acceptance).
