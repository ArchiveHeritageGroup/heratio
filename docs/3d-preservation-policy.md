# 3D model preservation policy (Heratio)

How Heratio preserves 3D digital objects under OAIS. Scope: the **master mesh**.
(Raw capture data — photo sets, point clouds — and an Archivematica bridge are
future extensions, tracked separately.)

## Principle: master vs access

Every 3D model has two roles, kept distinct:

- **Preservation master** — the authoritative, lossless object. Never the
  Draco-compressed delivery copy (Draco quantises geometry). Where a model was
  optimised to a `-opt.glb` for the web, the **uncompressed original** is the
  master.
- **Access derivative** — the web-delivery copy (glTF/GLB + Draco/meshopt),
  served by the viewer.

## Recommended master formats

| Role | Format |
|------|--------|
| Mesh master | OBJ+MTL (+ lossless PNG/TIFF textures), PLY, X3D (ISO 19775), or **uncompressed** glTF 2.0 |
| Point-cloud master (future) | E57 (ASTM E2807), LAS/LAZ |
| CAD (future) | STEP (ISO 10303) |
| Access / delivery | glTF/GLB + Draco/meshopt |
| Textures (master) | PNG/TIFF lossless (never JPEG) |

## What Heratio records (per master)

`ahg:3d-preserve` enrols each 3D master as a `premis_object` carrying:

- **Fixity** — SHA-256 checksum of the master file (+ a `preservation_checksum`
  row on the access digital object for dashboard coverage).
- **Format identification** — PRONOM PUID + MIME via `PronomIdentificationService`
  (OBJ `fmt/1210`, PLY `fmt/831`, STL ASCII `x-fmt/108` / binary `fmt/865`; glTF
  Text/`.gltf` `fmt/1315`, Binary/`.glb` `fmt/1316`).
- **Significant properties** (extracted from the master file) — geometry
  (vertices, faces), format + version, compression, bounding box, materials/PBR
  maps, animation count, plus curator paradata (real-world dimensions + units,
  coordinate system, colour space).
- `is_preservation_master` flag and a link to the access digital object.

## Significant properties (what must survive migration)

Geometry (vertex/face counts), **scale & units**, topology, colour/material
(PBR), coordinate system, animation, and embedded metadata. Any future format
migration is verified against these recorded values.

## Operations

- Run `php artisan ahg:3d-preserve` to enrol masters (idempotent; `--force` to
  re-extract). Run it after `ahg:optimize-models`, or on a schedule, so newly
  added / optimised models are enrolled.
- Fixity is re-verifiable via the preservation tooling (`preservation_checksum` /
  fixity scan).
- The uncompressed originals kept by `ahg:optimize-models` are the masters — do
  **not** purge them.

## Out of scope (future)

- Raw capture data (photo sets, point clouds) preservation.
- Archivematica FPR rules for 3D normalization.

See also: `docs/3d-in-heratio-metadata-iiif-preservation.md`, `docs/3d-model-erd.md`.
