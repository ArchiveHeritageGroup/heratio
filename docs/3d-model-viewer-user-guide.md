# 3D Model Viewer

## User Guide

View and interact with 3D models of objects in your collection directly in your web browser.

---

## Overview
```
+-------------------------------------------------------------+
|                    3D MODEL VIEWER                           |
+-------------------------------------------------------------+
|                                                              |
|         Rotate         Zoom         Pan                      |
|           |              |           |                       |
|           v              v           v                       |
|        Click &       Scroll       Shift +                    |
|        Drag          Wheel        Drag                       |
|                                                              |
+-------------------------------------------------------------+
```

---

## Supported Formats
```
+-------------------------------------------------------------+
|                    3D FILE FORMATS                            |
+-------------------------------------------------------------+
|  GLB/GLTF    - Standard web 3D format (recommended)         |
|  USDZ        - Apple AR format                              |
|  OBJ         - Common 3D format                             |
|  STL         - 3D printing format                           |
|  PLY         - Polygon file / point cloud format             |
+-------------------------------------------------------------+
```

All viewer libraries (model-viewer, Three.js, loaders) are served from local vendor files. No external CDN dependencies are required.

---

## Viewing a 3D Model

### Step 1: Find a Record with 3D Model

Browse or search for a record that has a 3D model attached.

Look for the 3D cube icon on the record page.

### Step 2: Open the Viewer

Click on the 3D model thumbnail or the **View 3D** button.
```
+-------------------------------------------------------------+
|  +-------------------------------------------------------+  |
|  |                                                        |  |
|  |                                                        |  |
|  |                    [3D Model                           |  |
|  |                     Loading...]                        |  |
|  |                                                        |  |
|  |                                                        |  |
|  +-------------------------------------------------------+  |
|                                                              |
|  Rotate  |  Zoom  |  Measure  |  Fullscreen                 |
+-------------------------------------------------------------+
```

---

## Controls

### Mouse Controls
```
+-------------------------------------------------------------+
|  ACTION              |  HOW TO                               |
+----------------------+---------------------------------------+
|  Rotate              |  Click and drag                       |
|  Zoom in/out         |  Scroll wheel                         |
|  Pan (move)          |  Shift + click and drag               |
|  Reset view          |  Double-click                         |
+----------------------+---------------------------------------+
```

### Touch Controls (Mobile/Tablet)
```
+-------------------------------------------------------------+
|  ACTION              |  HOW TO                               |
+----------------------+---------------------------------------+
|  Rotate              |  One finger drag                      |
|  Zoom                |  Pinch in/out                         |
|  Pan                 |  Two finger drag                      |
|  Reset               |  Double tap                           |
+----------------------+---------------------------------------+
```

---

## Viewer Features

### Toolbar Options
```
+-------------------------------------------------------------+
|  TOOLBAR                                                     |
+-------------------------------------------------------------+
|                                                              |
|  Auto-Rotate    - Spin model automatically                   |
|  Lighting       - Adjust light direction                     |
|  Background     - Change background color                    |
|  Wireframe      - Show mesh structure                        |
|  Fullscreen     - Expand to full screen                      |
|  Screenshot     - Save current view as image                 |
|  AR View        - View in augmented reality (mobile)         |
|                                                              |
+-------------------------------------------------------------+
```

---

## Augmented Reality (AR)

### On iPhone/iPad

1. Open the record on your device
2. Tap the **AR** button
3. Point camera at a flat surface
4. The object appears in your space!

### On Android

1. Open the record in Chrome
2. Tap the **AR** button
3. Follow prompts to place object
```
+-------------------------------------------------------------+
|                    AR REQUIREMENTS                            |
+-------------------------------------------------------------+
|  iPhone/iPad    - iOS 12+ with ARKit support                 |
|  Android        - ARCore compatible device + Chrome          |
+-------------------------------------------------------------+
```

---

## Interactive Hotspots

3D models can have interactive annotation points (hotspots) placed on their surface. Click a hotspot to view its title and description.

### Hotspot Types
```
+-------------------------------------------------------------+
|  TYPE          |  COLOR   |  USE CASE                        |
+----------------+----------+----------------------------------+
|  Annotation    |  Blue    |  General notes and comments      |
|  Info          |  Green   |  Information points              |
|  Damage        |  Red     |  Condition documentation         |
|  Detail        |  Yellow  |  Highlight features              |
|  Link          |  Blue    |  External URL links              |
+----------------+----------+----------------------------------+
```

**Damage hotspots** automatically link to the object's condition assessment page when the ahgConditionPlugin is installed.

### Managing Hotspots (Editors)

Editors and administrators can add, edit, and delete hotspots from the **3D Model Edit** page:

1. Open a 3D model and click **Edit**
2. Click on the model surface to detect a position
3. Fill in the hotspot form (type, title, description, position)
4. Click **Add Hotspot**

---

## Multi-Angle Gallery

When multi-angle renders have been generated for a 3D model, a gallery of 6 views appears below the viewer on the record view page:

- **Front** - straight-on front view
- **Back** - rear view
- **Left** - left side profile
- **Right** - right side profile
- **Top** - bird's-eye view from above
- **Detail** - close-up at 45-degree angle

Click any thumbnail to open a full-size lightbox view. These renders are generated automatically by Blender and cached for fast access.

---

## AI 3D Description

### Voice Command
Say **"describe object"** or **"describe 3D"** while viewing a record with a 3D model. The system will:
1. Generate 6 multi-angle renders of the object using Blender
2. Send all 6 views to an AI model for analysis
3. Read the description aloud
4. Offer to save the description to the record

### Save Options
After hearing the AI description, you can say:
- **"save to description"** - save to the record's scope and content field
- **"save to alt text"** - save as the digital object's alt text
- **"save to both"** - save to both fields
- **"discard"** - discard the AI description

---

## IIIF 3D Manifests

Each 3D model has an IIIF Presentation API 3.0 manifest available at:

```
https://your-site/iiif/3d/{model_id}/manifest.json
```

This manifest includes the model, its metadata, viewer settings, and any hotspot annotations. It can be used by external IIIF-compatible viewers and repositories.

---

## Thumbnails & Derivatives

3D models (GLB, GLTF, OBJ, STL, PLY) automatically get thumbnail and reference images generated via Blender when uploaded. If thumbnails are missing, an administrator can regenerate them:

```
php atom-framework/bin/atom 3d:derivatives           # Process all
php atom-framework/bin/atom 3d:derivatives --id=123  # Specific object
php atom-framework/bin/atom 3d:derivatives --dry-run  # Preview only
```

---

## Type-a-Command

If you prefer typing to speaking, **right-click** the floating microphone button (bottom-right corner). A text input will appear where you can type any voice command and press Enter. This is useful in noisy environments or when speech recognition is unavailable.

---

## Tips for Best Experience
```
+----------------------------------------+--------------------+
|  DO                                    |  DON'T             |
+----------------------------------------+--------------------+
|  Use a modern browser (Chrome/Firefox) |  Use old browsers  |
|  Wait for model to fully load          |  Interact while    |
|                                        |  loading           |
|  Use fullscreen for detail             |  View in small     |
|                                        |  window            |
|  Try AR on supported devices           |  Expect AR on all  |
|                                        |  devices           |
|  Allow time for large models           |  Give up on slow   |
|                                        |  load              |
+----------------------------------------+--------------------+
```

---

## Troubleshooting
```
Problem                          Solution
---------------------------------------------------------------
Model won't load              -> Refresh the page
                                 Try a different browser
                                 Check internet connection

Viewer is slow                -> Close other browser tabs
                                 Model may be very detailed

AR not available              -> Check device compatibility
                                 Use Safari (iOS) or
                                 Chrome (Android)

Model looks wrong             -> Try resetting the view
                                 Report to administrator

Hotspots not showing          -> Check the model has hotspots
                                 configured (editors can add
                                 them via Edit page)
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the Heratio Framework - The Archive and Heritage Group (Pty) Ltd*
