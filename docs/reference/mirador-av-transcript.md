> Heratio reference doc. Tracks issue #701 (Mirador A/V plugin + transcript panel).

# Mirador A/V playback + transcript panel

This Heratio Mirador 4 plugin adds a window-level toggle that turns the active OSD viewer into a `<video>` or `<audio>` element when the active canvas carries a `painting` annotation with a body of type `Video`, `Sound` or `Audio`, and shows a side panel containing the canvas-level transcript with click-to-seek word-by-word.

Heratio currently has no upstream "mirador-video-extension" pin (the npm package under that name is unstable / not maintained on the registry), so the A/V surface is implemented inline rather than wrapped around an external module. The IIIF Presentation 3 conventions for A/V content are followed exactly, so any future swap to an upstream plugin will only need to delete this file plus its registration in `index.js`.

## Where it lives

- Plugin source: `tools/mirador-build/src/heratio-av-plugin.js`
- Registered in: `tools/mirador-build/src/index.js` (Heratio additions block for issues #700 + #701)
- Shipped bundle: `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`

## Toolbar entry

A `WindowTopMenu` MenuItem labelled "A/V playback + transcript" with a `MovieIcon`. The right-edge `<Switch>` reflects the per-window state and is stored on `window.AHG_IIIF_STATE.av[windowId]`.

## A/V detection

`detectAvBody(canvas)` walks the canvas JSON-LD via the conventional shape:

```
canvas.items[].items[].body
```

For each painting annotation body it checks `body.type` (or `@type`) against `Video`, `Sound`, `Audio` and returns the first match as `{ kind: 'video' | 'audio', url, format }`. If no painting body matches, the toggle surfaces a one-shot hint (`[HeratioAv] no Video/Sound body on the active canvas; A/V mode not applicable.`) and auto-flips off.

## Element mounting

On match the plugin:

1. Hides the OSD canvas wrapper (`display: none` on `.openseadragon-canvas`) and restores it on detach.
2. Inserts a `.heratio-av-overlay` containing a `<video>` or `<audio>` element with `controls`, `preload="metadata"`, `crossOrigin="anonymous"`. `src` is the painting body's `id`, `type` attribute comes from `body.format` when present.
3. Inserts a `.heratio-av-transcript` side panel at the right edge, 300 px wide, with a Loading... empty state.

## Transcript endpoint

The panel fires:

```
GET /api/iiif/transcript?canvasId=<urlencoded-canvas-id>
Accept: application/json
credentials: same-origin
```

Expected response (mirrors the W3C Annotation List shape we already use for image OCR / `iiif_ocr_text`):

```json
{
  "ok": true,
  "items": [
    {
      "id": "https://.../annotations/<uuid>",
      "text": "the brown fox",
      "target": {
        "id": "<canvas-id>",
        "selector": { "type": "FragmentSelector", "value": "t=12.3,15.6" }
      }
    }
  ]
}
```

Accepted aliases for the row array: `items` or `resources`. The plugin treats a 404 or `{ ok: false }` response as "no transcript shipped yet" and shows a quiet `No transcript for this canvas.` empty-state - it does NOT block A/V playback.

## Word-level seek

Each transcript row carries a Media-Fragment selector `t=START` or `t=START,END` (seconds, per W3C Media Fragments URI 1.0). Clicking a row calls `media.currentTime = START` and `media.play()`. As the media plays the plugin highlights the active row via a `timeupdate` listener that picks the row whose interval contains `currentTime`; the panel auto-scrolls so the active line stays in view.

When a row has no `end` we infer it from the next row's `start`, or `start + 5s` for the last row.

## Backend status

The plugin is intentionally tolerant of the endpoint being absent. The matching server-side route (controller + `iiif_ocr_text` -> A/V transcript adapter) is tracked separately - see the IIIF backend agent's working notes. Until that ships the panel will show its empty-state on every A/V canvas while playback still works fine.

## CSS + z-index map

The A/V overlay sits at z-index 1150 (below the comparison overlay at 1180, below the magnifier at 1200). The transcript panel is at 1160. This keeps the comparison glass usable on top of A/V playback for visual A/B-ing of two A/V canvases (audio waveform comparisons via two `<audio>` elements is not a planned scenario but the layering allows it).

## Known limitations

- Cue-by-cue subtitle overlay (WebVTT painted onto `<video>`) is not yet wired. Manifests that publish a `seeAlso` of type `VTT` could feed `<track>` elements - candidate enhancement.
- Audio with no visible viewport drawing (`<audio>` only) leaves a black `.heratio-av-overlay` background; future polish would shrink it to a centred player without the surrounding dark area.
- Multi-body painting annotations (e.g. video + alternate audio track) only resolve the first matching body.
