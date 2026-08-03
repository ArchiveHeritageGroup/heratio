> Heratio Help Center article. Category: Viewers & Media.

# 3D Model Viewer

## User Guide

View and interact with 3D models of objects in your collection directly in your web browser.

---

## Overview

<div style="text-align:center;margin:1rem 0">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 170" role="img" aria-label="The three ways to interact with a 3D model: rotate, zoom and pan" style="max-width:100%;height:auto;font-family:Arial,Helvetica,sans-serif">
  <rect x="1" y="1" width="598" height="168" rx="10" fill="#f4f8f7" stroke="#10373E" stroke-width="1.5"/>
  <text x="300" y="30" text-anchor="middle" font-size="15" font-weight="bold" fill="#10373E">Interacting with a 3D model</text>
  <!-- Rotate -->
  <g>
    <circle cx="110" cy="90" r="26" fill="#10373E"/>
    <path d="M110 74 a16 16 0 1 1 -11 4" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
    <path d="M99 78 l-2 -9 l9 3 z" fill="#fff"/>
    <text x="110" y="140" text-anchor="middle" font-size="13" font-weight="bold" fill="#10373E">Rotate</text>
    <text x="110" y="158" text-anchor="middle" font-size="11" fill="#5a6b68">Click &amp; drag</text>
  </g>
  <!-- Zoom -->
  <g>
    <circle cx="300" cy="90" r="26" fill="#10373E"/>
    <circle cx="296" cy="86" r="10" fill="none" stroke="#fff" stroke-width="3"/>
    <line x1="303" y1="93" x2="311" y2="101" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
    <line x1="292" y1="86" x2="300" y2="86" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
    <line x1="296" y1="82" x2="296" y2="90" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
    <text x="300" y="140" text-anchor="middle" font-size="13" font-weight="bold" fill="#10373E">Zoom</text>
    <text x="300" y="158" text-anchor="middle" font-size="11" fill="#5a6b68">Scroll wheel</text>
  </g>
  <!-- Pan -->
  <g>
    <circle cx="490" cy="90" r="26" fill="#10373E"/>
    <line x1="490" y1="76" x2="490" y2="104" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
    <line x1="476" y1="90" x2="504" y2="90" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
    <path d="M490 74 l-5 6 h10 z M490 106 l-5 -6 h10 z M474 90 l6 -5 v10 z M506 90 l-6 -5 v10 z" fill="#fff"/>
    <text x="490" y="140" text-anchor="middle" font-size="13" font-weight="bold" fill="#10373E">Pan</text>
    <text x="490" y="158" text-anchor="middle" font-size="11" fill="#5a6b68">Shift + drag</text>
  </g>
</svg>
</div>

---

## Supported Formats

| Format | Description |
|---|---|
| **GLB / GLTF** | Standard web 3D format (recommended) |
| **USDZ** | Apple AR format |
| **OBJ** | Common 3D format (auto-converted to glTF) |
| **FBX** | Autodesk format (auto-converted to glTF) |
| **STL** | 3D printing format |
| **PLY** | Polygon file / point-cloud format |

All viewer libraries (model-viewer, Three.js, loaders) are served from local vendor files. No external CDN dependencies are required.

### Compressed glTF (Draco / KTX2)

The viewers ship with self-hosted **Draco** (geometry) and **KTX2/Basis** (texture)
decoders, so Draco-compressed meshes and KTX2-textured glTF/GLB load without any external
CDN. This is transparent: uncompressed models work exactly as before, and compressed ones
(for example after the optimiser runs - see below) just work in both the record viewer and
the exhibition walkthrough.

### OBJ and FBX are converted for you

OBJ and FBX uploads are kept as the preservation master and a web-ready **GLB** is generated
from them (FBX via the FBX2glTF tool, OBJ via obj2gltf), then texture-capped and
Draco-compressed by the model optimiser (`ahg:optimize-models`). You always view the GLB;
the original OBJ/FBX is preserved alongside the record.

---

## Viewing a 3D Model

<div style="text-align:center;margin:1rem 0">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 620 90" role="img" aria-label="Steps to view a 3D model: find a record, open the viewer, then interact" style="max-width:100%;height:auto;font-family:Arial,Helvetica,sans-serif">
  <g font-size="12.5" fill="#10373E">
    <rect x="2" y="25" width="170" height="40" rx="8" fill="#e6efed" stroke="#10373E"/>
    <text x="87" y="43" text-anchor="middle" font-weight="bold">1. Find a record</text>
    <text x="87" y="58" text-anchor="middle" font-size="10.5" fill="#5a6b68">look for the 3D cube icon</text>
    <path d="M176 45 h30" stroke="#10373E" stroke-width="2"/><path d="M206 45 l-8 -5 v10 z" fill="#10373E"/>
    <rect x="212" y="25" width="170" height="40" rx="8" fill="#e6efed" stroke="#10373E"/>
    <text x="297" y="43" text-anchor="middle" font-weight="bold">2. Open the viewer</text>
    <text x="297" y="58" text-anchor="middle" font-size="10.5" fill="#5a6b68">click thumbnail / View 3D</text>
    <path d="M386 45 h30" stroke="#10373E" stroke-width="2"/><path d="M416 45 l-8 -5 v10 z" fill="#10373E"/>
    <rect x="422" y="25" width="176" height="40" rx="8" fill="#10373E"/>
    <text x="510" y="43" text-anchor="middle" font-weight="bold" fill="#fff">3. Rotate, zoom, pan</text>
    <text x="510" y="58" text-anchor="middle" font-size="10.5" fill="#cfe0dc">measure &amp; fullscreen</text>
  </g>
</svg>
</div>

### Step 1: Find a Record with a 3D Model

Browse or search for a record that has a 3D model attached, and look for the **3D cube icon** on the record page.

### Step 2: Open the Viewer

Click the 3D model thumbnail or the **View 3D** button. The model loads inside the viewer, with the interaction toolbar (Rotate, Zoom, Measure, Fullscreen) along the bottom.

<div style="text-align:center;margin:1rem 0">
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 300" role="img" aria-label="Layout of the 3D viewer: a large model canvas above a toolbar" style="max-width:100%;height:auto;font-family:Arial,Helvetica,sans-serif">
  <rect x="1" y="1" width="478" height="298" rx="10" fill="#ffffff" stroke="#10373E" stroke-width="1.5"/>
  <rect x="20" y="20" width="440" height="210" rx="6" fill="#f4f8f7" stroke="#cdd8d5"/>
  <circle cx="240" cy="118" r="34" fill="none" stroke="#10373E" stroke-width="2" stroke-dasharray="5 5"/>
  <text x="240" y="123" text-anchor="middle" font-size="13" fill="#10373E">3D model</text>
  <g font-size="12" fill="#10373E" text-anchor="middle">
    <rect x="40" y="250" width="90" height="30" rx="15" fill="#e6efed" stroke="#10373E"/><text x="85" y="270">Rotate</text>
    <rect x="145" y="250" width="90" height="30" rx="15" fill="#e6efed" stroke="#10373E"/><text x="190" y="270">Zoom</text>
    <rect x="250" y="250" width="90" height="30" rx="15" fill="#e6efed" stroke="#10373E"/><text x="295" y="270">Measure</text>
    <rect x="355" y="250" width="105" height="30" rx="15" fill="#10373E"/><text x="407" y="270" fill="#fff">Fullscreen</text>
  </g>
</svg>
</div>

---

## Controls

### Mouse Controls

| Action | How to |
|---|---|
| Rotate | Click and drag |
| Zoom in / out | Scroll wheel |
| Pan (move) | Shift + click and drag |
| Reset view | Double-click |

### Touch Controls (Mobile / Tablet)

| Action | How to |
|---|---|
| Rotate | One-finger drag |
| Zoom | Pinch in / out |
| Pan | Two-finger drag |
| Reset | Double tap |

---

## Viewer Features

### Toolbar Options

| Tool | What it does |
|---|---|
| **Auto-Rotate** | Spin the model automatically |
| **Lighting** | Adjust the light direction |
| **Background** | Change the background colour |
| **Wireframe** | Show the mesh structure |
| **Fullscreen** | Expand to full screen |
| **Screenshot** | Save the current view as an image |
| **AR View** | View in augmented reality (mobile) |

---

## Augmented Reality (AR)

### On iPhone / iPad

1. Open the record on your device
2. Tap the **AR** button
3. Point the camera at a flat surface
4. The object appears in your space.

### On Android

1. Open the record in Chrome
2. Tap the **AR** button
3. Follow the prompts to place the object

| Platform | AR requirement |
|---|---|
| iPhone / iPad | iOS 12+ with ARKit support |
| Android | ARCore-compatible device + Chrome |

---

## Interactive Hotspots

3D models can have interactive annotation points (hotspots) placed on their surface. Click a hotspot to view its title and description.

### Hotspot Types

| Type | Colour | Use case |
|---|---|---|
| Annotation | <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#1e73be;vertical-align:middle"></span> Blue | General notes and comments |
| Info | <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#2e9e5b;vertical-align:middle"></span> Green | Information points |
| Damage | <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#c0392b;vertical-align:middle"></span> Red | Condition documentation |
| Detail | <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#e1b12c;vertical-align:middle"></span> Yellow | Highlight features |
| Link | <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#1e73be;vertical-align:middle"></span> Blue | External URL links |

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

| Do | Don't |
|---|---|
| Use a modern browser (Chrome / Firefox) | Use old browsers |
| Wait for the model to fully load | Interact while it is still loading |
| Use fullscreen for detail | View in a small window |
| Try AR on supported devices | Expect AR on every device |
| Allow time for large models | Give up on a slow load |

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Model won't load | Refresh the page · try a different browser · check your internet connection |
| Viewer is slow | Close other browser tabs · the model may be very detailed |
| AR not available | Check device compatibility · use Safari (iOS) or Chrome (Android) |
| Model looks wrong | Try resetting the view · report to an administrator |
| Hotspots not showing | Check the model has hotspots configured (editors can add them via the Edit page) |

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the Heratio Framework - The Archive and Heritage Group (Pty) Ltd*
