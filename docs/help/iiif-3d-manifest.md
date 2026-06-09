# IIIF 3D Manifests

Heratio publishes an IIIF Presentation 3 manifest for each public 3D model, so
the model and its annotations can be shared with and consumed by IIIF-aware
tools.

## Where it is

`/iiif/3d/{model-id}/manifest.json` (linked from the 3D model page). The URL is
served by Heratio (not the image server).

## What it contains

Aligned to the IIIF 3D Technical Specification (release-candidate, folding into
IIIF v4):

- A **Manifest** with one **Canvas** that carries a `depth` axis alongside
  width/height (a 3D coordinate space). Canvas extents come from the model's
  bounding box.
- A painting annotation whose body is a **Model** content resource (the
  glTF/GLB), plus a **PerspectiveCamera** and an **AmbientLight**.
- **Hotspots** are exposed as annotations targeting the canvas through a
  **PointSelector** (x, y, z).
- Descriptive **metadata** (format, dimensions, vertices/faces, compression,
  capture method, author) drawn from the model's technical metadata, and the
  model **licence** mapped to a rights statement where applicable.

## Viewer

On a record page the 3D viewer consumes this manifest: it applies the camera
field-of-view and renders the hotspots from the PointSelector annotations.

## Note

The IIIF 3D specification is still at release-candidate stage; property names may
change as it finalises, at which point the manifest will be revised.

## References

- Source: `packages/ahg-3d-model/` (`Iiif3dManifestBuilder`)
- Issue: [GH #1180](https://github.com/ArchiveHeritageGroup/heratio/issues/1180)
