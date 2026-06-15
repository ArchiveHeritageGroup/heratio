> Heratio Help Center article. Category: Digital Media.

# 3D Models

The 3D Models module manages interactive 3D digital objects (GLB, glTF, OBJ, STL, PLY, USDZ) attached to archival descriptions. It handles upload, an in-browser deep-zoom viewer with annotation hotspots and augmented reality, automatic thumbnail and turntable rendering, research-grade technical metadata, IIIF 3D manifests, and optional 2D-to-3D generation from a single photograph.

---

## Overview

Each 3D model is linked to an archival description (information object) and stored in the `object_3d_model` table. When a record has a public 3D model, visitors can rotate, zoom and pan it in the browser using a model-viewer widget, open hotspot annotations, and (on supported devices) place the object in their own space with augmented reality.

Staff manage models from an admin area under `/admin/3d-models`. The module supports two ways to get a model into the system:

- Upload an existing 3D file produced by photogrammetry, laser/structured-light scanning, CT scanning, CAD or manual modelling.
- Generate a model from a single 2D image using the optional TripoSR 2D-to-3D pipeline.

The module is jurisdiction-neutral and ships with Dropdown Manager taxonomies for units, coordinate systems, capture methods, compression and licence so no values are hardcoded.

---

## Key features

- **In-browser 3D viewer** with auto-rotate, configurable camera orbit, field of view, exposure, shadow intensity and softness, and background colour (`/admin/3d-models/{id}/view`).
- **Augmented reality (AR)** placement on floor or wall, with adjustable scale, for devices that support it.
- **Annotation hotspots** of five types (annotation, info, link, damage, detail), each colour-coded and positioned in 3D space, with optional link targets.
- **Automatic thumbnails and multi-angle turntable renders** generated through a Blender-based tool (six-view stills plus a turntable MP4).
- **Research-grade technical metadata and paradata** (issue #1178): real-world dimensions and unit, coordinate system, bounding box, format version, compression, PBR maps, level-of-detail count, watertight/rigged flags, capture method/device/date/operator, source count, point density, accuracy, processing software and notes, georeference, model author, derivation note, licence, licence holder and attribution.
- **IIIF 3D manifest** endpoint for interoperable delivery (`/iiif/3d/{id}/manifest.json`), enriched with the technical metadata and cached.
- **Public JSON API** for viewers to list a record's models and a model's hotspots.
- **Optional 2D-to-3D generation (TripoSR)** with a preview-then-save workflow, available both as an admin tool and as an opt-in button on record pages.
- **Audit logging** of views, AR views, uploads, edits, deletes and hotspot changes (`object_3d_audit_log`).
- **Multi-format support**: GLB, glTF, OBJ, STL, PLY and USDZ for upload; the thumbnail pipeline additionally recognises FBX and DAE.

---

## How to use

### Browse and manage 3D objects

1. Go to **`/admin/3d-models`** (admin access required). This derivative-management view lists every 3D digital object found in the archive, with counts for those that have and do not have thumbnails.
2. For any row you can generate a thumbnail, generate multi-angle renders, view, edit, embed or delete.
3. To see the list of models registered in the dedicated `object_3d_model` table (the records driving the viewer), go to **`/admin/3d-models/index`**.

### Upload a 3D model to a record

1. Open the upload form at **`/admin/3d-models/upload/{objectId}`**, where `{objectId}` is the archival description's id.
2. Choose a file. Allowed formats default to GLB, glTF, USDZ, OBJ, STL and PLY, and the default maximum file size is 100 MB. Both limits are configurable in settings.
3. Enter an optional title, description and alt text, and tick **Public** if the model should be visible to visitors.
4. Submit. The module auto-extracts technical metadata (format version, compression, bounding box, vertex/face/texture/animation counts and PBR maps) on upload, stores the file under the uploads path in `3d/{objectId}/`, and redirects you to the model's view page. The first model uploaded for a record is marked primary automatically.

### View and tune a model

1. Go to **`/admin/3d-models/{id}/view`** to open the interactive viewer. Viewing is logged to the audit trail.
2. To change how the model is presented, go to **`/admin/3d-models/{id}/edit`**. Here you can set auto-rotate and rotation speed, camera orbit, field of view, exposure, shadow intensity and softness, background colour, AR enable/scale/placement, and the primary/public flags.
3. The same edit form captures the research metadata and paradata. Dropdown fields (units, coordinate system, capture method, compression, licence) are populated from the Dropdown Manager.

### Add annotation hotspots

1. On the model view or edit page, place a hotspot directly on the 3D surface. The chosen type (annotation, info, link, damage or detail) determines its colour automatically.
2. Hotspots are saved via the hotspot endpoints (`/admin/3d-models/{modelId}/hotspot` to add, `/admin/3d-models/hotspot/{hotspotId}/delete` to remove) and can carry a title, description and an optional link with a target.

### Generate thumbnails and turntable renders

- Generate a single thumbnail for one model from the browse view, or run **Batch thumbnails** (`/admin/3d-models/batch-thumbnails`) to process every 3D object that is missing one.
- Generate a six-view multi-angle set from the browse view. Rendering uses a Blender-based tool; failures are written to `storage/logs/3d-thumbnail.log`.
- The same work can be driven from the command line: `php artisan ahg:3d-derivatives` (thumbnails) and `php artisan ahg:3d-multiangle` (turntable MP4).

### Embed and share

- Use **`/admin/3d-models/{id}/embed`** for an iframe-friendly viewer of a public model.
- Serve an interoperable manifest from **`/iiif/3d/{id}/manifest.json`** (public, CORS-enabled, cached in `iiif_3d_manifest`).
- Viewers on public pages can call the JSON API at **`/api/3d/models/{objectId}`** (models for a record) and **`/api/3d/hotspots/{modelId}`** (hotspots for a model).

### Generate a 3D model from a 2D image (optional)

1. This feature is off by default. An administrator must enable it in settings (see Configuration).
2. When enabled, a **Generate 3D** button appears on a record's page for signed-in users. The flow is: generate to a staged file, preview it in an in-page viewer, then **Save** to attach it or **Discard** to throw it away.
3. Behind the button, the module runs `php artisan ahg:triposr-generate` against a source image, holding the result in session until you confirm. Generation is refused if the record already has a 3D model or has no usable image.
4. Administrators can manage the pipeline and review recent jobs at **`/admin/3d-models/triposr`**, and check the service with `php artisan ahg:triposr-health`.

---

## Configuration

Settings live at **`/admin/3d-models/settings`** and are stored in the `viewer_3d_settings` table:

- **Viewer defaults**: default viewer, enable AR, fullscreen, download, annotations and auto-rotate; default background colour, exposure, shadow intensity and rotation speed.
- **Uploads**: maximum file size (MB) and the list of allowed formats.
- **Watermark**: enable and set watermark text.
- **TripoSR 2D-to-3D**: master enable, the public-facing **Generate 3D** user button toggle (both must be on for the button to show), demo mode, local or remote mode, API/remote URL, timeout, background removal, foreground ratio, marching-cubes resolution and texture baking. A remote API key field is masked and only overwritten when changed.

Dropdown taxonomies used by the metadata fields are seeded automatically on first boot and editable in the Dropdown Manager: `model_3d_units`, `model_3d_coordinate_system`, `model_3d_capture_method`, `model_3d_compression` and `model_3d_licence`.

Storage uses the central paths in `config/heratio.php` (no hardcoded paths). Files are written under the configured uploads path. Thumbnail and render failures are logged to `storage/logs/3d-thumbnail.log`.

Additional command-line tools:

- `php artisan ahg:3d-extract-metadata` backfills technical metadata onto existing models.
- `php artisan ahg:3d-preserve` enrols 3D masters as OAIS preservation objects (fixity, PRONOM, PREMIS, significant properties).
- `php artisan ahg:triposr-preload` warms the TripoSR service.

---

## References

- Source: `packages/ahg-3d-model/`
- Issue: [GH #538](https://github.com/ArchiveHeritageGroup/heratio/issues/538)
- Admin entry point: `/admin/3d-models`
- IIIF manifest: `/iiif/3d/{id}/manifest.json`
- Public API: `/api/3d/models/{objectId}`, `/api/3d/hotspots/{modelId}`
