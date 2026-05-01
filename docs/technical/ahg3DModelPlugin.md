# ahg3DModelPlugin - Technical Documentation

**Version:** 1.3.0
**Category:** Digital Asset Management
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

A comprehensive 3D model viewing plugin for Access to Memory (AtoM) providing WebGL-based visualization, augmented reality (AR) capabilities, interactive hotspot annotations, Gaussian Splat support, and IIIF 3D manifest generation for museum and archival collections.

---

## Architecture

```
+---------------------------------------------------------------------+
|                      ahg3DModelPlugin                                |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |                  Symfony Plugin Layer                         |  |
|  |  * ahg3DModelPluginConfiguration (routes, modules)            |  |
|  |  * model3d module (CRUD actions)                              |  |
|  |  * model3dSettings module (admin configuration)               |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                   Service Layer                               |  |
|  |  * Model3DService (CRUD, viewer HTML generation)              |  |
|  |  * Ar3dService (format detection, config)                     |  |
|  |  * ThreeDThumbnailService (thumbnail generation)              |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                  Provider Layer                               |  |
|  |  * Model3DProvider (framework integration)                    |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                 Frontend Viewers                              |  |
|  |  * Google Model Viewer (WebXR/AR)                             |  |
|  |  * Three.js (fallback)                                        |  |
|  |  * GaussianSplats3D (point cloud)                             |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |                   Database Layer                              |  |
|  |  * object_3d_model, object_3d_hotspot                         |  |
|  |  * viewer_3d_settings, iiif_3d_manifest                       |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Supported Formats

| Format | Extension | MIME Type | AR Support | Notes |
|--------|-----------|-----------|------------|-------|
| glTF Binary | `.glb` | `model/gltf-binary` | Yes | Recommended. Single file with embedded textures |
| glTF JSON | `.gltf` | `model/gltf+json` | Yes | Multiple files, external textures |
| Apple AR | `.usdz` | `model/vnd.usdz+zip` | iOS only | Required for iOS Quick Look |
| Wavefront | `.obj` | `model/obj` | Limited | No animations, widely supported |
| Stereolithography | `.stl` | `model/stl` | Limited | 3D printing standard, no textures |
| Polygon File | `.ply` | `application/x-ply` | No | Point cloud support |
| Gaussian Splat | `.splat`, `.ksplat` | N/A | No | Neural radiance fields |

---

## Database Schema

### ERD Diagram

```
+----------------------------------+
|         object_3d_model          |
+----------------------------------+
| PK id INT                        |
| FK object_id INT                 |---> information_object
|                                  |
| -- File Info --                  |
|    filename VARCHAR(255)         |
|    original_filename VARCHAR(255)|
|    file_path VARCHAR(500)        |
|    file_size BIGINT              |
|    mime_type VARCHAR(100)        |
|    format VARCHAR(47)            |
|                                  |
| -- Model Metadata --             |
|    vertex_count INT              |
|    face_count INT                |
|    texture_count INT             |
|    animation_count INT           |
|    has_materials TINYINT         |
|                                  |
| -- Viewer Settings --            |
|    auto_rotate TINYINT           |
|    rotation_speed DECIMAL(3,2)   |
|    camera_orbit VARCHAR(100)     |
|    field_of_view VARCHAR(20)     |
|    exposure DECIMAL(3,2)         |
|    shadow_intensity DECIMAL(3,2) |
|    background_color VARCHAR(20)  |
|                                  |
| -- AR Settings --                |
|    ar_enabled TINYINT            |
|    ar_scale VARCHAR(20)          |
|    ar_placement ENUM(floor,wall) |
|                                  |
| -- Display --                    |
|    poster_image VARCHAR(500)     |
|    thumbnail VARCHAR(500)        |
|    is_primary TINYINT            |
|    is_public TINYINT             |
|    display_order INT             |
|                                  |
| FK created_by INT                |
| FK updated_by INT                |
|    created_at TIMESTAMP          |
|    updated_at TIMESTAMP          |
+----------------------------------+
         |
         | 1:N
         v
+----------------------------------+
|      object_3d_model_i18n        |
+----------------------------------+
| PK id INT                        |
| FK model_id INT                  |
|    culture VARCHAR(10)           |
|    title VARCHAR(255)            |
|    description TEXT              |
|    alt_text VARCHAR(500)         |
+----------------------------------+
         |
         | 1:N (from object_3d_model)
         v
+----------------------------------+
|       object_3d_hotspot          |
+----------------------------------+
| PK id INT                        |
| FK model_id INT                  |
|    hotspot_type ENUM             |
|    position_x DECIMAL(10,6)      |
|    position_y DECIMAL(10,6)      |
|    position_z DECIMAL(10,6)      |
|    normal_x DECIMAL(10,6)        |
|    normal_y DECIMAL(10,6)        |
|    normal_z DECIMAL(10,6)        |
|    icon VARCHAR(50)              |
|    color VARCHAR(20)             |
|    link_url VARCHAR(500)         |
|    link_target ENUM              |
|    display_order INT             |
|    is_visible TINYINT            |
|    created_at TIMESTAMP          |
+----------------------------------+
         |
         | 1:N
         v
+----------------------------------+
|     object_3d_hotspot_i18n       |
+----------------------------------+
| PK id INT                        |
| FK hotspot_id INT                |
|    culture VARCHAR(10)           |
|    title VARCHAR(255)            |
|    description TEXT              |
+----------------------------------+

+----------------------------------+
|       object_3d_texture          |
+----------------------------------+
| PK id INT                        |
| FK model_id INT                  |
|    texture_type ENUM             |
|    filename VARCHAR(255)         |
|    file_path VARCHAR(500)        |
|    width INT                     |
|    height INT                    |
|    created_at TIMESTAMP          |
+----------------------------------+

+----------------------------------+
|       viewer_3d_settings         |
+----------------------------------+
| PK id INT                        |
|    setting_key VARCHAR(100)      |
|    setting_value TEXT            |
|    setting_type ENUM             |
|    description VARCHAR(500)      |
|    updated_at TIMESTAMP          |
+----------------------------------+

+----------------------------------+
|       iiif_3d_manifest           |
+----------------------------------+
| PK id INT                        |
| FK model_id INT (UNIQUE)         |
|    manifest_json LONGTEXT        |
|    manifest_hash VARCHAR(64)     |
|    generated_at TIMESTAMP        |
+----------------------------------+

+----------------------------------+
|      object_3d_audit_log         |
+----------------------------------+
| PK id INT                        |
| FK model_id INT                  |
| FK object_id INT                 |
| FK user_id INT                   |
|    user_name VARCHAR(255)        |
|    action ENUM                   |
|    details JSON                  |
|    ip_address VARCHAR(45)        |
|    user_agent VARCHAR(500)       |
|    created_at TIMESTAMP          |
+----------------------------------+
```

### Hotspot Types

| Type | Color | Icon | Use Case |
|------|-------|------|----------|
| annotation | `#1a73e8` (Blue) | info | General notes and comments |
| info | `#34a853` (Green) | info-circle | Information points |
| damage | `#ea4335` (Red) | exclamation-triangle | Condition documentation |
| detail | `#fbbc04` (Yellow) | search | Highlight features |
| link | `#4285f4` (Blue) | link | External URL links |

### Texture Types

| Type | Description |
|------|-------------|
| diffuse | Base color/albedo map |
| normal | Normal/bump map |
| roughness | Surface roughness map |
| metallic | Metallic/reflectivity map |
| ao | Ambient occlusion map |
| emissive | Self-illumination map |
| environment | Environment/reflection map |

---

## Service Methods

### Model3DService

```php
namespace Ahg3DModel\Services;

class Model3DService
{
    // Model CRUD
    public function getModelsForObject(int $objectId): array
    public function getPrimaryModel(int $objectId): ?object
    public function getModel(int $modelId): ?object
    public function createModel(int $objectId, array $data): int
    public function updateModel(int $modelId, array $data): bool
    public function deleteModel(int $modelId): bool

    // Viewer HTML Generation
    public function getModelViewerHtml(int $modelId, array $options = []): string
    public function getThreeJsViewerHtml(int $modelId, array $options = []): string

    // Hotspots
    public function getHotspots(int $modelId): array
    public function addHotspot(int $modelId, array $data): int
    public function updateHotspot(int $hotspotId, array $data): bool
    public function deleteHotspot(int $hotspotId): bool

    // Settings
    public function getSetting(string $key, $default = null): mixed
    public function saveSetting(string $key, $value, string $type = 'string'): bool

    // IIIF
    public function generateIiif3dManifest(int $modelId): array
}
```

### Ar3dService

```php
namespace AtomExtensions\Extensions\Ar3dViewer\Services;

class Ar3dService implements Ar3dServiceInterface
{
    public function is3dModel(int $digitalObjectId): bool
    public function getViewerConfig(int $digitalObjectId): array
    public function getSupportedFormats(): array
}
```

### ThreeDThumbnailService

```php
namespace AtomExtensions\Services;

class ThreeDThumbnailService
{
    // Model detection
    public function is3DModel(string $filename): bool
    public function is3DMimeType(string $mime): bool
    public function getSupportedMimeTypes(): array

    // Single thumbnail generation
    public function generateThumbnail(string $inputPath, string $outputPath, int $width = 512, int $height = 512): bool

    // Derivative generation (thumbnail + reference image)
    public function createDerivatives(int $digitalObjectId): bool

    // Multi-angle rendering (6 views via Blender)
    public function generateMultiAngle(string $inputPath, string $outputDir, int $size = 1024): array
    // Returns: ['front' => '/path/front.png', 'back' => '...', 'left' => '...', 'right' => '...', 'top' => '...', 'detail' => '...']

    // Multi-angle directory for a digital object
    public function getMultiAngleDir(int $digitalObjectId): string

    // Batch processing
    public function batchProcessExisting(): array
    // Returns: ['processed' => N, 'success' => N, 'failed' => N]
}
```

#### Supported MIME Types

| MIME Type | Format |
|-----------|--------|
| `model/obj` | Wavefront OBJ |
| `model/gltf-binary` | GLB |
| `model/gltf+json` | GLTF |
| `model/stl` | STL |
| `application/x-tgif` | OBJ (common mislabel) |
| `model/vnd.usdz+zip` | USDZ |
| `application/x-ply` | PLY |

#### Multi-Angle Camera Positions

| View | Azimuth | Elevation | Purpose |
|------|---------|-----------|---------|
| Front | 0° | 15° | Primary view |
| Back | 180° | 15° | Reverse view |
| Left | 270° | 15° | Left profile |
| Right | 90° | 15° | Right profile |
| Top | 0° | 80° | Bird's-eye |
| Detail | 45° | 35° | Close-up detail |

### Model3DProvider

```php
namespace ahg3DModelPlugin\Provider;

class Model3DProvider implements Model3DProviderInterface
{
    public function is3dModel(int $digitalObjectId): bool
    public function getViewerConfig(int $digitalObjectId): array
    public function getSupportedFormats(): array
    public function generateThumbnail(int $digitalObjectId, array $options = []): array
}
```

---

## Helper Functions

Include the helper file in templates:

```php
include_once sfConfig::get('sf_plugins_dir') . '/ahg3DModelPlugin/lib/helper/Model3DHelper.php';
```

| Function | Description | Returns |
|----------|-------------|---------|
| `has_3d_model($resource)` | Check if object has 3D models | `bool` |
| `get_3d_models($resource)` | Get all 3D models for object | `array` |
| `get_primary_3d_model($resource)` | Get primary 3D model | `?object` |
| `render_3d_model($resource, $options)` | Render primary model viewer | `string` |
| `render_3d_model_viewer($modelId, $options)` | Render viewer by model ID | `string` |
| `render_3d_model_gallery($resource, $options)` | Render tabbed gallery | `string` |
| `get_3d_model_upload_url($resource)` | Get upload URL for object | `string` |
| `get_iiif_3d_manifest_url($modelId)` | Get IIIF manifest URL | `string` |
| `is_3d_format($extension)` | Check if extension is 3D format | `bool` |
| `is_splat_format($extension)` | Check if extension is Gaussian Splat | `bool` |
| `render_splat_viewer($url, $options)` | Render Gaussian Splat viewer | `string` |
| `get_3d_format_label($format)` | Get human-readable format label | `string` |
| `render_3d_upload_button($resource)` | Render upload button (editors) | `string` |

### Viewer Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `height` | string | `'500px'` | Viewer container height |
| `viewer_type` | string | `'model-viewer'` | `'model-viewer'` or `'threejs'` |
| `show_hotspots` | bool | `true` | Display hotspot annotations |
| `base_url` | string | auto | Base URL for model files |

---

## Routes

### Web Routes

| Route | Method | URL | Description |
|-------|--------|-----|-------------|
| `ar3d_model_index` | GET | `/ahg3DModel/index` | List all 3D models |
| `ar3d_model_view` | GET | `/ahg3DModel/view/:id` | View single model |
| `ar3d_model_upload` | GET/POST | `/ahg3DModel/upload` | Upload new model |
| `ar3d_model_edit` | GET/POST | `/ahg3DModel/edit/:id` | Edit model settings |
| `ar3d_model_delete` | POST | `/ahg3DModel/delete/:id` | Delete model |
| `ar3d_viewer_embed` | GET | `/ahg3DModel/embed/:id` | Embeddable viewer |
| `ar3d_settings` | GET/POST | `/ahg3DSettings/index` | Global settings |

### API Routes

| Route | Method | URL | Response |
|-------|--------|-----|----------|
| `ar3d_api_models` | GET | `/api/3d/models/:object_id` | JSON array of models |
| `ar3d_api_hotspots` | GET | `/api/3d/hotspots/:model_id` | JSON array of hotspots |
| `ar3d_hotspot_add` | POST | `/ahg3DModel/addHotspot/:id` | JSON `{success, id}` |
| `ar3d_hotspot_delete` | POST | `/ahg3DModel/deleteHotspot/:id` | JSON `{success}` |
| `ar3d_iiif_manifest` | GET | `/iiif/3d/:id/manifest.json` | IIIF 3D manifest |

---

## IIIF 3D Manifest

Each model has an IIIF 3D-compliant manifest at `/iiif/3d/{model_id}/manifest.json`:

```json
{
  "@context": [
    "http://iiif.io/api/presentation/3/context.json",
    "http://iiif.io/api/extension/3d/context.json"
  ],
  "id": "https://example.com/iiif/3d/1/manifest.json",
  "type": "Manifest",
  "label": { "en": ["Bronze Statue"] },
  "summary": { "en": ["19th century bronze figure"] },
  "metadata": [
    { "label": { "en": ["Format"] }, "value": { "en": ["GLB"] } },
    { "label": { "en": ["File Size"] }, "value": { "en": ["5.2 MB"] } }
  ],
  "items": [
    {
      "id": "https://example.com/iiif/3d/1/scene/1",
      "type": "Scene",
      "items": [
        {
          "id": "https://example.com/iiif/3d/1/annotation/1",
          "type": "Annotation",
          "motivation": "painting",
          "body": {
            "id": "https://example.com/uploads/3d/1/model.glb",
            "type": "Model",
            "format": "model/gltf-binary"
          },
          "target": "https://example.com/iiif/3d/1/scene/1"
        }
      ]
    }
  ],
  "annotations": [
    {
      "id": "https://example.com/iiif/3d/1/annotations/1",
      "type": "AnnotationPage",
      "items": [
        {
          "id": "https://example.com/iiif/3d/1/hotspot/1",
          "type": "Annotation",
          "motivation": "commenting",
          "body": {
            "type": "TextualBody",
            "value": "Surface detail annotation",
            "format": "text/plain"
          },
          "target": {
            "type": "PointSelector",
            "x": 0.5,
            "y": 0.2,
            "z": 0.3
          }
        }
      ]
    }
  ],
  "extensions": {
    "viewer": {
      "autoRotate": true,
      "rotationSpeed": 30,
      "cameraOrbit": "0deg 75deg 105%",
      "fieldOfView": "30deg",
      "arEnabled": true
    }
  }
}
```

---

## JavaScript API

The `Model3D` namespace provides client-side utilities:

```javascript
// Initialize all viewers on page
Model3D.initViewers();

// Setup a single viewer
Model3D.setupViewer(viewerElement);

// Toggle fullscreen
Model3D.toggleFullscreen(containerElement);

// Check AR support
Model3D.isARSupported();  // returns boolean

// Hotspot management
Model3D.addHotspot(viewer, {
    id: 'hotspot-1',
    position_x: 0.5,
    position_y: 0.2,
    position_z: 0.3,
    normal_x: 0,
    normal_y: 1,
    normal_z: 0,
    hotspot_type: 'annotation',
    color: '#1a73e8',
    title: 'Surface Detail',
    description: 'Notable feature'
});

Model3D.removeHotspot(viewer, 'hotspot-1');
Model3D.focusHotspot(viewer, 'hotspot-1');

// AJAX operations
Model3D.loadModel(modelId, callback);
Model3D.loadHotspots(modelId, callback);
Model3D.saveHotspot(modelId, hotspotData, callback);
Model3D.deleteHotspot(hotspotId, callback);

// Gaussian Splat viewer
Model3D.initSplatViewer(container, splatUrl, {
    cameraUp: [0, 1, 0],
    cameraPosition: [0, 0, 5],
    cameraLookAt: [0, 0, 0],
    alphaThreshold: 5,
    onLoad: function(viewer) { },
    onError: function(err) { }
});

// Format utilities
Model3D.getSupportedExtensions();  // ['glb', 'gltf', ...]
Model3D.isSupportedFormat('model.glb');  // true
Model3D.isSplatFormat('ksplat');  // true
Model3D.formatBytes(5242880);  // '5.00 MB'
```

---

## Configuration

### Global Settings (viewer_3d_settings table)

| Setting Key | Type | Default | Description |
|-------------|------|---------|-------------|
| `default_viewer` | string | `model-viewer` | Default viewer type |
| `default_background` | string | `#f5f5f5` | Background color |
| `default_exposure` | string | `1.0` | Light exposure |
| `default_shadow_intensity` | string | `1.0` | Shadow darkness |
| `rotation_speed` | integer | `30` | Degrees per second |
| `enable_auto_rotate` | boolean | `1` | Auto-rotate default |
| `enable_fullscreen` | boolean | `1` | Show fullscreen button |
| `enable_ar` | boolean | `1` | Enable AR viewing |
| `enable_download` | boolean | `0` | Allow downloads |
| `enable_annotations` | boolean | `1` | Enable hotspots |
| `max_file_size_mb` | integer | `100` | Upload size limit |
| `allowed_formats` | json | `["glb","gltf","usdz"]` | Permitted formats |
| `watermark_enabled` | boolean | `0` | Show watermark |
| `watermark_text` | string | `''` | Watermark text |

### Per-Model Settings (object_3d_model table)

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `auto_rotate` | boolean | `1` | Enable rotation |
| `rotation_speed` | decimal | `1.00` | Rotation multiplier |
| `camera_orbit` | string | `0deg 75deg 105%` | Initial camera position |
| `min_camera_orbit` | string | null | Minimum orbit bounds |
| `max_camera_orbit` | string | null | Maximum orbit bounds |
| `field_of_view` | string | `30deg` | Camera FOV |
| `exposure` | decimal | `1.00` | Light exposure |
| `shadow_intensity` | decimal | `1.00` | Shadow darkness |
| `shadow_softness` | decimal | `1.00` | Shadow blur |
| `background_color` | string | `#f5f5f5` | Background |
| `environment_image` | string | null | HDR environment |
| `skybox_image` | string | null | Skybox image |
| `ar_enabled` | boolean | `1` | Enable AR |
| `ar_scale` | string | `auto` | AR scale mode |
| `ar_placement` | enum | `floor` | AR placement (`floor`/`wall`) |

---

## Vendor Libraries (Local)

All frontend libraries are served locally - no external CDN dependencies:

| Library | Path | Version |
|---------|------|---------|
| Google model-viewer | `/plugins/ahgCorePlugin/web/js/vendor/model-viewer.min.js` | 3.4.0 |
| Three.js | `/plugins/ahg3DModelPlugin/web/vendor/threejs/three.min.js` | r128 |
| OBJLoader | `/plugins/ahg3DModelPlugin/web/vendor/threejs/OBJLoader.js` | r128 |
| STLLoader | `/plugins/ahg3DModelPlugin/web/vendor/threejs/STLLoader.js` | r128 |
| OrbitControls | `/plugins/ahg3DModelPlugin/web/vendor/threejs/OrbitControls.js` | r128 |
| GLTFLoader | `/plugins/ahg3DModelPlugin/web/vendor/threejs/GLTFLoader.js` | r128 |
| GaussianSplats3D | `/plugins/ahg3DModelPlugin/web/vendor/gaussian-splats3d/` | - |

### FBX Format Note

FBX is **not supported** in the browser viewer (no FBXLoader.js is bundled). FBX is only supported in the Blender thumbnail pipeline (`blender_thumbnail.py`, `render_multiangle.py`) which can import FBX natively for offline rendering.

---

## Condition Assessment Integration

Damage-type hotspots (`hotspot_type='damage'`) automatically link to the object's condition assessment page via the ahgConditionPlugin:

- When a damage hotspot is created without an explicit `link_url`, the system auto-generates: `/{slug}/condition`
- Clicking a red damage hotspot in the viewer navigates to the condition assessment page
- The condition plugin route `/:slug/condition` displays condition checks for that object
- No database foreign key is required - linking is by URL convention

---

## Viewer Integration

### Model Viewer (Google WebXR)

Primary viewer using `<model-viewer>` web component:

```html
<model-viewer
    id="viewer-1"
    src="/uploads/3d/1/model.glb"
    ios-src="/uploads/3d/1/model.usdz"
    alt="3D Model"
    camera-controls
    auto-rotate
    rotation-per-second="30deg"
    camera-orbit="0deg 75deg 105%"
    field-of-view="30deg"
    exposure="1"
    shadow-intensity="1"
    ar
    ar-modes="webxr scene-viewer quick-look"
    style="width: 100%; height: 500px;">

    <!-- AR Button -->
    <button slot="ar-button" class="ar-button">
        View in AR
    </button>

    <!-- Hotspots -->
    <button class="hotspot"
            slot="hotspot-1"
            data-position="0.5m 0.2m 0.3m"
            data-normal="0m 1m 0m">
        <div class="hotspot-annotation">
            <strong>Title</strong>
            <p>Description</p>
        </div>
    </button>
</model-viewer>
```

### Three.js Fallback

Alternative viewer for older browsers:

```html
<div id="threejs-viewer" style="width: 100%; height: 500px;"></div>
<script>
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(30, w/h, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    const controls = new THREE.OrbitControls(camera, renderer.domElement);
    const loader = new THREE.GLTFLoader();

    loader.load('/uploads/3d/1/model.glb', function(gltf) {
        scene.add(gltf.scene);
    });
</script>
```

### Gaussian Splat Viewer

For neural radiance field (NeRF) captured models:

```html
<div id="splat-viewer" style="width: 100%; height: 500px;"></div>
<script src="/plugins/ahg3DModelPlugin/web/vendor/gaussian-splats3d/gaussian-splats-3d.umd.js"></script>
<script>
    const viewer = new GaussianSplats3D.Viewer({
        rootElement: document.getElementById('splat-viewer'),
        selfDrivenMode: true
    });
    viewer.addSplatScene('/uploads/3d/1/model.splat').then(() => {
        viewer.start();
    });
</script>
```

---

## Template Integration

### Display 3D Model in Object View

```php
<?php include_partial('model3d/model3dViewer', ['resource' => $resource]); ?>
```

### Manual Integration

```php
<?php
include_once sfConfig::get('sf_plugins_dir') . '/ahg3DModelPlugin/lib/helper/Model3DHelper.php';

if (has_3d_model($resource)): ?>
    <div class="model-3d-section">
        <?php echo render_3d_model($resource, [
            'height' => '500px',
            'viewer_type' => 'model-viewer'
        ]); ?>
    </div>
<?php endif; ?>
```

### Embed in External Site

```html
<iframe
    src="https://your-atom-site/ahg3DModel/embed/123"
    width="100%"
    height="500"
    frameborder="0"
    allowfullscreen
    allow="xr-spatial-tracking">
</iframe>
```

---

## AR Requirements

| Platform | Requirements |
|----------|--------------|
| iOS | Safari 12+, HTTPS, `.usdz` file for Quick Look |
| Android | Chrome 79+, HTTPS, `.glb`/`.gltf` for Scene Viewer |
| WebXR | WebXR-capable browser, HTTPS, secure context |

### AR Modes Priority

1. **WebXR** - Immersive AR in browser (emerging standard)
2. **Scene Viewer** - Android native AR viewer
3. **Quick Look** - iOS native AR viewer

---

## Audit Trail Integration

Actions logged to `object_3d_audit_log`:

| Action | Trigger |
|--------|---------|
| `upload` | New model uploaded |
| `update` | Model settings changed |
| `delete` | Model deleted |
| `view` | Model viewed |
| `ar_view` | AR session started |
| `download` | Model file downloaded |
| `hotspot_add` | Hotspot created |
| `hotspot_delete` | Hotspot removed |

Integration with ahgAuditTrailPlugin for central logging is automatic when available.

---

## NGINX Configuration

Add MIME types for 3D files. GLB and GLTF are included in default nginx `mime.types`; add the remaining formats in your server block:

```nginx
types {
    model/obj                         obj;
    model/stl                         stl;
    model/vnd.usdz+zip               usdz;
    application/x-ply                 ply;
}
```

Also add 3D file extensions to your static asset serving pattern:

```nginx
location ~* ^/(plugins|web)/.*\.(glb|gltf|obj|stl|usdz|ply)$ {
    root /usr/share/nginx/archive;
    try_files $uri =404;
    expires 30d;
    add_header Cache-Control "public, immutable";
}
```

Increase upload limits:

```nginx
client_max_body_size 100M;
```

CORS for IIIF manifests (optional):

```nginx
location ~ ^/iiif/3d/ {
    add_header Access-Control-Allow-Origin "*";
    add_header Access-Control-Allow-Methods "GET, OPTIONS";
}
```

---

## File Size Guidelines

| Model Type | Recommended Size | Max Polygons |
|------------|-----------------|--------------|
| Simple objects | < 5 MB | 50,000 |
| Detailed objects | 5-20 MB | 200,000 |
| High-fidelity scans | 20-50 MB | 500,000 |
| Museum quality | 50-100 MB | 1,000,000 |

### Optimization Tips

1. Use GLB format with Draco compression
2. Reduce texture resolution (2K max for web)
3. Decimate high-poly meshes
4. Merge materials where possible
5. Remove hidden geometry

---

## CLI Commands

### 3d:derivatives

Generates thumbnail and reference image derivatives for 3D model files using Blender.

```bash
php atom-framework/bin/atom 3d:derivatives              # Process all 3D objects missing derivatives
php atom-framework/bin/atom 3d:derivatives --id=123     # Process specific digital object
php atom-framework/bin/atom 3d:derivatives --force      # Regenerate even if derivatives exist
php atom-framework/bin/atom 3d:derivatives --dry-run    # Preview without generating
```

| Option | Description |
|--------|-------------|
| `--id=N` | Process only the specified digital object ID |
| `--force` | Regenerate even if derivatives already exist |
| `--dry-run` | List objects that would be processed without generating |

### 3d:multiangle

Generates 6 multi-angle renders (front, back, left, right, top, detail) using Blender. Optionally sends renders to LLM for AI description.

```bash
php atom-framework/bin/atom 3d:multiangle               # Render all 3D objects missing multi-angle views
php atom-framework/bin/atom 3d:multiangle --id=123      # Render specific digital object
php atom-framework/bin/atom 3d:multiangle --describe    # Render + generate AI description
php atom-framework/bin/atom 3d:multiangle --force       # Regenerate even if renders exist
php atom-framework/bin/atom 3d:multiangle --dry-run     # Preview without rendering
```

| Option | Description |
|--------|-------------|
| `--id=N` | Process only the specified digital object ID |
| `--force` | Regenerate even if renders already exist |
| `--describe` | After rendering, send images to LLM and output AI description |
| `--dry-run` | List objects that would be processed without rendering |

### Cron Examples

```bash
# Nightly derivative generation
0 2 * * * cd /usr/share/nginx/archive && php atom-framework/bin/atom 3d:derivatives >> /var/log/atom/3d-derivatives.log 2>&1

# Nightly multi-angle rendering
0 3 * * * cd /usr/share/nginx/archive && php atom-framework/bin/atom 3d:multiangle >> /var/log/atom/3d-multiangle.log 2>&1

# Weekly multi-angle + AI description batch
0 4 * * 0 cd /usr/share/nginx/archive && php atom-framework/bin/atom 3d:multiangle --describe >> /var/log/atom/3d-describe.log 2>&1
```

---

## Voice Integration

### AI 3D Object Description

The voice command system (ahgVoice module in ahgThemeB5Plugin) supports AI-powered description of 3D objects via multi-angle renders.

#### Endpoint

```
POST /ahgVoice/describeObject
```

| Parameter | Description |
|-----------|-------------|
| `digital_object_id` | Direct digital object ID |
| `information_object_id` | Information object ID (resolves to digital object) |
| `slug` | Record slug (resolves to information object, then digital object) |

**Response:**
```json
{
    "success": true,
    "description": "This object is a...",
    "source": "local",
    "model": "llava:7b",
    "render_count": 6,
    "cached": false,
    "information_object_id": 456
}
```

#### Voice Commands

| Command | Action |
|---------|--------|
| "describe object" / "describe 3D" | Auto-detects 3D viewer, generates multi-angle renders, sends to LLM |
| "describe model" / "what is this object" | Same as above |
| "save to description" | Save AI description to scope_and_content |
| "save to alt text" | Save as digital object alt text |
| "save to both" | Save to both fields |
| "discard" | Discard the AI description |

#### LLM Configuration

Uses the same hybrid LLM configuration as image description:

| Provider | Model | Timeout | Notes |
|----------|-------|---------|-------|
| Local (Ollama) | llava:7b | 180s | All 6 images sent in single request |
| Cloud (Anthropic) | claude-sonnet | 120s | 6 image content blocks per request |

Context-aware prompts adjust vocabulary for GLAM sector: CCO (museum), ISAD(G) (archive), MARC (library), VRA (gallery), IPTC (DAM).

#### Multi-Angle Gallery Partial

```php
<?php include_partial('model3d/multiAngleGallery', [
    'digitalObject' => $digitalObject,
    'informationObject' => $informationObject,
]); ?>
```

Renders a thumbnail row with lightbox modal for the 6 views if renders exist. Purely additive - shows nothing if `{derivative_dir}/multiangle/` doesn't exist.

---

## Blender Integration

### Requirements

- **Blender 4.2+** (5.0+ recommended)
- Installed via snap (`/snap/bin/blender`) or system package

### Scripts

| Script | Location | Purpose |
|--------|----------|---------|
| `blender_thumbnail.py` | `tools/3d-thumbnail/` | Single thumbnail render |
| `render_multiangle.py` | `tools/3d-thumbnail/` | 6-angle orbit renders |
| `generate-thumbnail.sh` | `tools/3d-thumbnail/` | Shell wrapper for single thumbnail |
| `generate-multiangle.sh` | `tools/3d-thumbnail/` | Shell wrapper for multi-angle renders |

### EEVEE Compatibility

Blender 4.2+ renamed the render engine from `BLENDER_EEVEE` to `BLENDER_EEVEE_NEXT`. Both Python scripts handle this automatically with version detection.

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Model not loading | Check MIME types in nginx, verify file path |
| AR button not showing | Ensure HTTPS, check device compatibility |
| AR session fails | Verify GLB/USDZ format, check file integrity |
| Hotspots not visible | Verify `is_visible=1`, check z-index |
| Slow performance | Optimize model, reduce polygon count |
| CORS errors | Add Access-Control headers to nginx |
| Manifest 404 | Check route configuration, clear cache |
| Thumbnails not generating | Check Blender installed (`/snap/bin/blender --version`), verify script permissions |
| Multi-angle renders missing | Run `php atom-framework/bin/atom 3d:multiangle --id=N`, check Blender logs |
| AI describe returns empty | Check Ollama running (`curl localhost:11434`), verify LLM model pulled |
| OBJ MIME type wrong | OBJ files often mislabeled as `application/x-tgif` - handled automatically |
| Blender snap permission | Snap Blender may need `--no-sandbox` or run as correct user |

---

## Related Plugins

| Plugin | Integration |
|--------|-------------|
| ahgConditionPlugin | Damage hotspots linked to condition reports |
| ahgAuditTrailPlugin | Central audit logging |
| ahgCorePlugin | Database initialization, helper functions |
| ahgThemeB5Plugin | Voice AI 3D description (ahgVoice module) |
| atom-framework | ThreeDThumbnailService, CLI commands (3d:derivatives, 3d:multiangle) |

---

*Part of the AtoM AHG Framework*
