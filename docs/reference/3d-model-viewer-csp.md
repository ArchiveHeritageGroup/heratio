# 3D / GLB models show blank -> check the CSP (model-viewer + Draco)

If a record's 3D model viewer is blank (no geometry, no error to the naked eye)
on Heratio, the usual cause is **not** a broken file - it is the
**Content-Security-Policy** blocking Google's `<model-viewer>` web component or
its Draco decoder.

## How the viewer loads

- The IO show page renders GLB/GLTF via Google `<model-viewer>` (loaded as an ES
  module from `https://ajax.googleapis.com/.../model-viewer.min.js`). See
  `packages/ahg-information-object-manage/resources/views/partials/_digital-object-viewer.blade.php`.
- Draco-compressed models (produced by `ahg:optimize-models` /
  `ModelCompressionService`, which runs `gltf-transform draco`) make model-viewer
  fetch a Draco decoder from `https://www.gstatic.com/...` and run it as
  **WebAssembly** in a worker.

## Required CSP allowances (app/Csp/HeratioCspPreset.php)

The CSP is a spatie/laravel-csp preset (`app/Csp/HeratioCspPreset.php`), applied
app-wide - editing it does NOT touch the locked IO-show blade. For 3D to render
it must allow:

- `script-src`: `https://ajax.googleapis.com` (the bundle),
  `https://www.gstatic.com` (decoders), and `'wasm-unsafe-eval'`
  (`Keyword::UNSAFE_WEB_ASSEMBLY_EXECUTION`) for the Draco WASM decode.
- `connect-src`: `https://ajax.googleapis.com` + `https://www.gstatic.com`
  (runtime decoder fetch).
- `worker-src`: `https://www.gstatic.com` (Draco runs in a worker).

Symptom seen 2026-06-08: "compressed all 3D, models stopped showing." The master
files were intact; the CSP `script-src` listed only jsdelivr/cdnjs/unpkg, so the
model-viewer script was silently blocked - and the newly Draco-compressed models
additionally needed gstatic + wasm-unsafe-eval. Adding those origins fixed it.

## Not the cause (ruled out first)

- File integrity: confirm the served master is a valid `glTF` (magic bytes
  `glTF`) at the URL the DB `digital_object.path` + `name` resolve to. Note the
  nginx alias `^~ /uploads/ -> /mnt/nas/heratio/uploads/` and the symlink
  `uploads/r -> /mnt/nas/heratio/archive`, so `/uploads/r/<repo>/...` serves from
  the archive tree.
- `ahg:optimize-models` keeps the original on disk and writes a restore `.sql` to
  `storage/app/`; it re-points the `digital_object` row to a `<name>-opt.glb`. A
  model under `--min-mb` (default 20) is skipped entirely.

This is Heratio-specific: the Draco compress lives in `ahg-core`, and AtoM's 3D
viewer loads model-viewer locally - no fix-both twin needed.
