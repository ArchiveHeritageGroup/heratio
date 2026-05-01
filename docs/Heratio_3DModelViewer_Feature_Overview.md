# Heratio - 3D Model Viewer

## Feature Overview

**Component:** ahg3DModelPlugin
**Version:** 1.3.0
**Category:** Digital Asset Management / Viewer
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Summary

The Heratio 3D Model Viewer provides GLAM and DAM institutions with comprehensive 3D digital object visualization directly within the AtoM archival platform. Museum objects, archaeological finds, architectural models, and other 3D-scanned items can be uploaded, viewed, annotated, and shared using industry-standard web technologies.

---

## Key Features

### Interactive 3D Viewing
- Browser-based WebGL rendering using Google's model-viewer component
- Rotate, zoom, and pan with mouse or touch controls
- Auto-rotation with configurable speed
- Fullscreen mode for detailed inspection
- Dark gradient background with customizable colours

### Augmented Reality (AR)
- View 3D models in your physical space via mobile devices
- iOS support via Apple Quick Look (Safari 12+)
- Android support via Google Scene Viewer (Chrome 79+)
- WebXR for immersive browser-based AR

### Interactive Hotspot Annotations
- Place interactive annotation points on 3D model surfaces
- Five hotspot types: Annotation, Info, Damage, Detail, Link
- Colour-coded by type for visual distinction
- Click-to-navigate with popup titles and descriptions
- Damage hotspots automatically link to condition assessment pages

### IIIF 3D Compliance
- IIIF Presentation API 3.0 manifest generation per model
- Scene-type items with Model body annotations
- Hotspots serialized as IIIF PointSelector annotations
- CORS-enabled manifest endpoints for cross-repository sharing
- Manifest URL: `/iiif/3d/{model_id}/manifest.json`

### Gaussian Splat Support
- Neural radiance field (NeRF) captured models
- `.splat` and `.ksplat` format viewing
- Dedicated GaussianSplats3D viewer integration

### Multi-Angle Gallery
- Automated 6-view rendering via Blender (front, back, left, right, top, detail)
- Thumbnail gallery with lightbox
- AI-powered description generation from multi-angle renders

### Blender Thumbnail Pipeline
- Automatic thumbnail and reference image generation
- Supports GLB, GLTF, OBJ, STL, PLY, FBX (via Blender import)
- EEVEE renderer with Blender 4.2+ compatibility
- CLI commands for batch processing

---

## Supported Formats

| Format | Extension | AR Support | Browser Viewer |
|--------|-----------|------------|----------------|
| glTF Binary | `.glb` | Yes | model-viewer |
| glTF JSON | `.gltf` | Yes | model-viewer |
| Apple AR | `.usdz` | iOS only | model-viewer |
| Wavefront OBJ | `.obj` | Limited | Three.js |
| Stereolithography | `.stl` | Limited | Three.js |
| Polygon File | `.ply` | No | Three.js |
| Gaussian Splat | `.splat`, `.ksplat` | No | GaussianSplats3D |

GLB is the recommended format for web viewing - it provides a single file with embedded textures and optimal loading performance.

---

## Architecture

- **Frontend:** Google model-viewer (WebXR/AR), Three.js (OBJ/STL fallback), GaussianSplats3D
- **Backend:** Laravel Query Builder, Symfony 1.x plugin architecture
- **Database:** 8 tables (models, translations, hotspots, textures, settings, manifests, audit log)
- **All libraries served locally** - no external CDN dependencies
- **HTTPS required** for AR features

---

## Administration

### Global Settings
Configurable at Admin > 3D Viewer Settings:
- Default viewer type (model-viewer or Three.js)
- AR enable/disable
- Allowed upload formats and maximum file size
- Auto-rotate, fullscreen, hotspot, and download toggles
- Background colour and lighting defaults

### Per-Model Settings
Each model can be individually configured:
- Camera orbit, field of view, and exposure
- Shadow intensity and softness
- AR scale mode and placement (floor/wall)
- Environment and skybox images
- Primary model and visibility flags

### Audit Trail
All actions are logged to a dedicated audit table:
- Upload, update, delete, view, AR view, download
- Hotspot add and delete
- Integration with central ahgAuditTrailPlugin

---

## Integration Points

| Plugin | Integration |
|--------|-------------|
| ahgConditionPlugin | Damage hotspots auto-link to condition assessments |
| ahgAuditTrailPlugin | Central audit logging |
| ahgCorePlugin | Database initialization, vendor libraries |
| ahgThemeB5Plugin | Voice AI 3D description, viewer embedding |
| ahgIiifPlugin | IIIF manifest ecosystem |

---

## Technical Requirements

| Requirement | Minimum |
|-------------|---------|
| AtoM | 2.9+ or 2.10 |
| PHP | 8.1+ |
| MySQL | 8.0+ |
| atom-framework | Required (Laravel Query Builder) |
| HTTPS | Required for AR features |
| Blender | 4.2+ (for thumbnail generation) |
| Nginx MIME types | `model/obj`, `model/stl`, `model/vnd.usdz+zip`, `application/x-ply` |

---

## CLI Commands

| Command | Description |
|---------|-------------|
| `php bin/atom 3d:derivatives` | Generate thumbnail and reference images for 3D models |
| `php bin/atom 3d:multiangle` | Generate 6-angle renders with optional AI description |

---

## Standards Compliance

- **IIIF Presentation API 3.0** - 3D manifest generation with Scene type
- **IIIF 3D Extension** - Draft specification for 3D content
- **WebXR** - Immersive AR standard
- **Spectrum 5.1** - Condition documentation via damage hotspots

---

*The Archive and Heritage Group (Pty) Ltd*
*https://theahg.co.za*
