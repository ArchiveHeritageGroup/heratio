# Mirador Image Viewer

## User Guide

Advanced IIIF viewer for comparing images, viewing annotations, and working with multi-page documents.

---

## Overview
```
┌─────────────────────────────────────────────────────────────┐
│                      MIRADOR VIEWER                         │
├─────────────────────────────────────────────────────────────┤
│  ┌────────────────────┬────────────────────┐               │
│  │                    │                    │               │
│  │     Image 1        │     Image 2        │               │
│  │                    │                    │               │
│  │   (Before)         │   (After)          │               │
│  │                    │                    │               │
│  └────────────────────┴────────────────────┘               │
│                                                             │
│  Compare multiple images side by side                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## What Makes Mirador Special?
```
┌─────────────────────────────────────────────────────────────┐
│  MIRADOR FEATURES                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📊 Side-by-Side     - Compare multiple images              │
│  📝 Annotations      - View notes and highlights            │
│  📖 Multi-page       - Browse books and documents           │
│  🔗 External Links   - Load images from other archives      │
│  💾 Workspace        - Save your viewing session            │
│  🎯 Synchronized     - Link zoom across windows             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## The Mirador Interface
```
┌─────────────────────────────────────────────────────────────┐
│ ☰ Menu │                                    │ ⚙️ Settings │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                                                     │   │
│  │                   Main Viewing Area                 │   │
│  │                                                     │   │
│  │                                                     │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐  Page Thumbnails │
│  │  1  │ │  2  │ │  3  │ │  4  │ │  5  │                   │
│  └─────┘ └─────┘ └─────┘ └─────┘ └─────┘                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Basic Navigation

### Zooming and Panning
```
┌─────────────────────────────────────────────────────────────┐
│  ACTION              │  HOW TO                              │
├──────────────────────┼──────────────────────────────────────┤
│  Zoom in             │  Scroll up or click +                │
│  Zoom out            │  Scroll down or click -              │
│  Pan / Move          │  Click and drag                      │
│  Reset view          │  Double-click or Home button         │
│  Fullscreen          │  Click expand icon                   │
└──────────────────────┴──────────────────────────────────────┘
```

---

## Opening the Side Panel

Click the **☰ Menu** button to access:
```
┌─────────────────────────────────────────────────────────────┐
│  SIDE PANEL OPTIONS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📋 Index            - Table of contents (if available)     │
│  🖼️  Thumbnails       - Page/image grid                     │
│  ℹ️  Information      - Metadata about the item             │
│  📝 Annotations      - Notes and highlights                 │
│  🔍 Search           - Find text (if available)             │
│  📚 Layers           - Multiple versions of same image      │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Comparing Images Side by Side

### Step 1: Open First Image

Load your first image in the viewer.

### Step 2: Add a Window

Click **Add Window** or the **+** button.

### Step 3: Load Second Image

Browse to and select the second image.

### Step 4: Arrange Windows
```
┌─────────────────────────────────────────────────────────────┐
│  COMPARISON LAYOUTS                                         │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Side by Side:                                              │
│  ┌────────────────┬────────────────┐                       │
│  │    Image 1     │    Image 2     │                       │
│  └────────────────┴────────────────┘                       │
│                                                             │
│  Stacked:                                                   │
│  ┌─────────────────────────────────┐                       │
│  │           Image 1               │                       │
│  ├─────────────────────────────────┤                       │
│  │           Image 2               │                       │
│  └─────────────────────────────────┘                       │
│                                                             │
│  Grid (4 images):                                          │
│  ┌───────────┬───────────┐                                 │
│  │     1     │     2     │                                 │
│  ├───────────┼───────────┤                                 │
│  │     3     │     4     │                                 │
│  └───────────┴───────────┘                                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Synchronized Viewing

Lock windows so they zoom and pan together:

### Enable Sync

1. Click on a window's menu (⋮)
2. Select **Sync windows**
3. Choose which windows to link
```
┌────────────────────┬────────────────────┐
│                    │                    │
│   Window 1         │   Window 2         │
│   🔗 Synced        │   🔗 Synced        │
│                    │                    │
│   Zoom here ──────────▶ Zooms here too │
│                    │                    │
└────────────────────┴────────────────────┘
```

---

## Viewing Annotations

If an image has annotations:
```
┌─────────────────────────────────────────────────────────────┐
│  ANNOTATIONS                                                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Annotations appear as:                                     │
│                                                             │
│  📍 Markers      - Points of interest                       │
│  ⬜ Rectangles   - Highlighted areas                        │
│  ✏️  Notes        - Text comments                            │
│  🏷️  Tags         - Labels and categories                   │
│                                                             │
│  Click on annotation to see details                         │
│  Toggle annotations on/off in side panel                    │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Viewing Annotation Details
```
┌─────────────────────────────────────────────────────────────┐
│  📝 ANNOTATION                                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  "This signature matches the one found                      │
│   in the 1892 letter collection."                           │
│                                                             │
│  Added by: J. Smith                                         │
│  Date: 15 December 2025                                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Multi-Page Documents

### Page Navigation
```
┌─────────────────────────────────────────────────────────────┐
│  PAGE NAVIGATION                                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ◀ Previous  │  Page 5 of 42  │  Next ▶                    │
│                                                             │
│  Or use thumbnail strip at bottom:                          │
│                                                             │
│  ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐ ┌───┐                │
│  │ 1 │ │ 2 │ │ 3 │ │ 4 │ │[5]│ │ 6 │ │ 7 │ ...            │
│  └───┘ └───┘ └───┘ └───┘ └───┘ └───┘ └───┘                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Book View Mode

View facing pages like an open book:
```
┌─────────────────────────────────────────────────────────────┐
│                      BOOK VIEW                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│           ┌────────────┬────────────┐                       │
│           │            │            │                       │
│           │   Page 4   │   Page 5   │                       │
│           │   (Left)   │   (Right)  │                       │
│           │            │            │                       │
│           └────────────┴────────────┘                       │
│                                                             │
│              ◀◀  │  ◀  │  ▶  │  ▶▶                         │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Loading External IIIF Manifests

Mirador can display images from other institutions:

### Step 1: Get the IIIF Manifest URL
```
Example: https://other-archive.org/iiif/item123/manifest.json
```

### Step 2: Add Resource

1. Click **Add Resource** (+ icon)
2. Paste the manifest URL
3. Click **Add**

### Step 3: View the Item

The external image appears in your workspace.

---

## Workspace Features

### Save Your Session

Save your current view to return later:

1. Click **⚙️ Settings**
2. Select **Save Workspace**
3. Give it a name

### Export Workspace

Share your comparison setup:

1. Click **Export**
2. Copy the JSON or link
3. Send to colleague

---

## Keyboard Shortcuts
```
┌─────────────────────────────────────────────────────────────┐
│  KEY              │  ACTION                                 │
├───────────────────┼─────────────────────────────────────────┤
│  + or =           │  Zoom in                                │
│  -                │  Zoom out                               │
│  0                │  Reset view                             │
│  ← →              │  Previous / Next page                   │
│  Home             │  First page                             │
│  End              │  Last page                              │
│  F                │  Toggle fullscreen                      │
│  I                │  Toggle information panel               │
│  T                │  Toggle thumbnails                      │
└───────────────────┴─────────────────────────────────────────┘
```

---

## Common Uses
```
┌─────────────────────────────────────────────────────────────┐
│  USE MIRADOR FOR:                                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📊 Comparing      - Before/after restoration               │
│                    - Different editions of a text           │
│                    - Original vs. copy                      │
│                                                             │
│  📖 Reading        - Multi-page manuscripts                 │
│                    - Bound volumes                          │
│                    - Newspapers                             │
│                                                             │
│  🔍 Researching    - Cross-referencing sources              │
│                    - Examining annotations                  │
│                    - Collaborative analysis                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## Tips
```
┌────────────────────────────────┬────────────────────────────┐
│  ✓ DO                          │  ✗ DON'T                   │
├────────────────────────────────┼────────────────────────────┤
│  Use sync for comparisons      │  Pan each window separately│
│  Save workspaces for projects  │  Recreate layouts each time│
│  Check for annotations         │  Miss scholarly notes      │
│  Try book view for documents   │  View pages one at a time  │
│  Use external manifests        │  Only view local images    │
└────────────────────────────────┴────────────────────────────┘
```

---

## Troubleshooting
```
Problem                          Solution
───────────────────────────────────────────────────────────
Windows won't sync            →  Check sync is enabled for both
                                 Try closing and re-adding
                                 
External manifest fails       →  Check URL is correct
                                 Source may not allow CORS
                                 
Annotations not showing       →  Toggle annotations panel
                                 Image may not have any
                                 
Workspace won't save          →  Check browser allows storage
                                 Try exporting instead
```

---

## Need Help?

Contact your system administrator if you experience issues.

---

*Part of the AtoM AHG Framework*
