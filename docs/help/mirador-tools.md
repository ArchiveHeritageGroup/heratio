> Heratio Help Center article. Category: Viewers & Media.

# Mirador Tools

## User Guide

Two power-user toggles in the Mirador window menu - a "Comparison glass" for before/after-style slider review, and "A/V playback + transcript" for video and audio canvases.

---

## Overview

```
+-------------------------------------------------------------+
|   MIRADOR WINDOW MENU                                       |
+-------------------------------------------------------------+
|                                                             |
|   [Magnifier]                                  [O]          |
|   [Scalebar]                                   [O]          |
|   [Comparison glass]                           [O]   <- #700|
|   [A/V playback + transcript]                  [O]   <- #701|
|                                                             |
+-------------------------------------------------------------+
```

Open the window menu (the "..." or hamburger icon at the top of any Mirador window) to see the toggles. Each switch is per-window.

---

## Comparison glass (#700)

The comparison glass lays a second open Mirador window's canvas over the right half of the active window, with a draggable vertical seam. Drag the yellow handle left or right to reveal more of either side.

### How to use it

1. In Mirador, open the first canvas you want to compare. (Add Resource -> pick the manifest -> open the canvas.)
2. Open a SECOND Mirador window in the same workspace, on the OTHER canvas. They can be from the same manifest (e.g. before / after photographs of the same artefact) or different manifests.
3. In the FIRST window's top-menu, flip on "Comparison glass". A yellow vertical seam with a circular knob appears.
4. Drag the knob left or right. The right-hand side of the active window is now painted with the second window's current view. Zoom and pan in EITHER window - the comparison repaints continuously.
5. Flip the switch off to dismiss. Each window remembers its own state.

### When the toggle does nothing

If you don't have a second window open the switch will flick off again and the browser console will log `[HeratioComparison] open a second Mirador window in this workspace to compare against.` Open another window and try again.

### Tips

- The comparison uses INDEPENDENT navigation. That's by design - it lets you line up a detail at one zoom level on the right against the full image on the left.
- The seam plays nicely with the magnifier loupe and the scalebar - all three can be on at once.
- Cross-origin manifests sometimes block canvas reading; if the right side is empty check the browser console for a `partner canvas not readable` warning.

---

## A/V playback + transcript (#701)

When a canvas has a video or audio file attached (a `Video`, `Sound` or `Audio` painting body in the IIIF manifest), this toggle replaces the image viewer with a real `<video>` or `<audio>` element and shows the canvas-level transcript on the right.

### How to use it

1. Open a manifest that contains an A/V canvas. (Typical Heratio path: an oral-history MP3, a born-digital lecture MP4, or a digitised reel-to-reel WAV.)
2. Navigate to the A/V canvas in the Mirador window.
3. Open the window menu and flip on "A/V playback + transcript". A media player appears in place of the image; a Transcript panel slides in on the right.
4. Press Play. As the media advances, the transcript highlights the active line and auto-scrolls.
5. CLICK any transcript line to seek the media to that timestamp. Useful for jumping to a specific quote in a long oral history.

### When the toggle does nothing

If the active canvas has no A/V body (it is an image-only canvas) the switch flicks back off and the browser console logs `[HeratioAv] no Video/Sound body on the active canvas; A/V mode not applicable.` Pick an A/V canvas from the manifest's canvas list and try again.

### What the transcript shows

The panel reads `/api/iiif/transcript?canvasId=<the-canvas-id>` on the Heratio backend. Each row carries a Media-Fragment timestamp (e.g. `t=12.3,15.6`). If no transcript has been generated yet the panel shows `No transcript for this canvas.` - playback still works.

### Tips

- Comparison glass + A/V can be combined: open two A/V canvases in two windows, flip A/V on in both, then flip Comparison on in the first - useful for waveform A/B between two recordings.
- The transcript respects ODRL rights policies the same as the image OCR layer; canvases under restricted access return an empty transcript even when the underlying media plays.

---

## Issue references

- #700 - Comparison glass / dual-pane slider
- #701 - A/V plugin bundling + manifest detection + transcript panel

## Reference docs

- `docs/reference/mirador-comparison-glass.md` - implementation notes for the comparison glass
- `docs/reference/mirador-av-transcript.md` - implementation notes for the A/V plugin + transcript endpoint contract
