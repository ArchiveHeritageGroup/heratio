> Heratio reference doc. Tracks issue #701 (Mirador A/V plugin - internals). Companion doc: `mirador-av-transcript.md` covers the transcript panel; this one covers the plugin internals + media-element lifecycle.

# Mirador A/V plugin internals

Plugin that turns a Mirador 4 OpenSeadragon window into an HTML5 `<video>` or `<audio>` element when the active canvas carries a `painting` annotation with a `Video`, `Sound` or `Audio` body. The plugin is an internal Heratio component - we did not pull an external mirador-video-extension into the bundle because the npm package under that name is unmaintained and Mirador 4 ships no first-party A/V surface.

This document covers the implementation contract for future agents touching the plugin. End-user behaviour is documented in `docs/help/iiif-av-mirador.md`.

## File layout

- Plugin source: `tools/mirador-build/src/heratio-av-plugin.js`
- Registered alongside other Heratio plugins in: `tools/mirador-build/src/index.js`
- Shipped bundle (compiled by webpack): `public/vendor/ahg-theme-b5/js/vendor/mirador/mirador.min.js`
- Loaded automatically by every page that initialises Mirador via the Heratio embed wrapper (`ahg-iiif-viewer.js`)

The compiled bundle was last refreshed in v1.104.0. The plugin source itself is unlocked under `.locked-paths` but the bundle directory is also unlocked - any source change must be followed by a `npm run deploy` from `tools/mirador-build/` to refresh the bundle before the change reaches users.

## Manifest-shape detection (`detectAvBody`)

The exported `detectAvBody(canvas)` walks the canvas JSON-LD via the standard IIIF Presentation 3 shape:

```
canvas.items[].items[].body
```

`canvas.items` is an array of `AnnotationPage` objects; each page's `items` is an array of `Annotation` objects; each annotation has a `body` (object or array). The function looks for the first annotation with `motivation === 'painting'` whose body's `type` (or `@type`) is `Video`, `Sound`, or `Audio`. Match returns `{ kind: 'video' | 'audio', url: body.id, format: body.format || null }`. No match returns `null`.

Tolerated input shapes (the function falls back through them in order):

1. `canvas.__jsonld` - Mirador's parsed JSON-LD container shape
2. `canvas.jsonld` - older alias
3. `canvas` itself, treated as raw JSON-LD

This redundancy exists because Mirador's manifest store mutates the canvas object between fetch and render; we want the same function to work in both fetched and rendered states.

Edge cases the function handles by returning `null`:

- A canvas with no `items` array (still-image only or completely empty)
- A painting annotation with a body that is neither Video nor Sound nor Audio (regular image canvases)
- A painting annotation whose body has no `id` (malformed manifest; we never trust an undefined URL)

## Media-fragment parsing (`parseMediaFragment`)

The exported `parseMediaFragment(value)` parses the W3C Media Fragments URI 1.0 `t=` parameter format used by transcript-row target selectors:

```
t=12.3,15.6  -> { start: 12.3, end: 15.6 }
t=12.3       -> { start: 12.3, end: null }
t=12         -> { start: 12,   end: null }
t=.5         -> { start: 0.5,  end: null }
```

Returns `null` for any input that does not contain a `t=` directive with at least one numeric value. The regex is tolerant of integer or decimal seconds.

## Lifecycle: mount and detach

`mountAv(osdViewer, av, canvasId)` returns a handle with a single method `detach()`. The mount sequence:

1. Inject the plugin's stylesheet (idempotent - guarded by element id `heratio-av-styles`).
2. Hide the OpenSeadragon image canvas wrapper (`.openseadragon-canvas`); remember its `display` value for restore.
3. Append a `.heratio-av-overlay` div containing a `<video>` or `<audio>` element with `controls`, `preload="metadata"`, `crossOrigin="anonymous"`, `src=av.url`, optional `type=av.format`.
4. Append a `.heratio-av-transcript` panel docked right (300 px wide, z-index 1160).
5. Best-effort fire `GET /api/iiif/transcript?canvasId=<urlencoded>` and render rows on success, empty-state on 404 or `{ ok:false }`.
6. Register a `timeupdate` listener that picks the row whose `[start, end)` interval contains `media.currentTime` and auto-scrolls the panel.

`detach()` reverses every step: removes the `timeupdate` listener, pauses media, removes both overlay and panel, restores `.openseadragon-canvas` display.

Per-window bookkeeping lives at:

- `window.AHG_IIIF_STATE.av[windowId]` - boolean enable state (so embedders can probe / restore)
- `window.AHG_IIIF_STATE.avHandles[windowId]` - the active detach handle (or `null`)
- `window.__heratioMiradorOsdRegistry[windowId]` - OSD viewer instance (populated by `ahg-iiif-viewer.js`)
- `window.__heratioMiradorStore` - the Redux store the menu item consults to read the active canvas

This mirrors the convention used by the magnifier (`magHandles`) and comparison-glass (`compareHandles`) plugins so a future "reset all overlays" hook can iterate one namespace.

## Captions track loading

The plugin currently relies entirely on browser-native captions UI. The `<video>` element exposes the standard `textTracks` collection; any track embedded in the media container (e.g. an MP4 with `mov_text`) is auto-discovered by the browser and shown under the "CC" button in the native controls.

External `<track src=".vtt">` elements are not yet wired. Adding them requires:

1. Parsing the manifest's `seeAlso` references for entries with `type: "Text"` and `format: "text/vtt"` (or `format: "application/vnd.ms-vtt"`).
2. Inserting `<track kind="subtitles" src=... srclang=... label=... default>` children inside the `<video>` element before the first `play()`.
3. (Optional) Routing the captions through the transcript panel so a `cuechange` event on the active track highlights the matching transcript row.

Issue #701 closed at the manifest-detection + native-controls scope; the VTT wiring is on the candidate-enhancement backlog and should track its own follow-up.

## Z-index map

The Heratio Mirador plugins layer overlays carefully so they can coexist in the same window:

| Layer | z-index | Owner |
|---|---|---|
| A/V overlay | 1150 | `heratio-av-plugin.js` |
| A/V transcript panel | 1160 | `heratio-av-plugin.js` |
| Comparison glass overlay | 1180 | `heratio-comparison-plugin.js` |
| Magnifier loupe | 1200 | `heratio-magnifier-plugin.js` |

A future overlay should pick the next free band (e.g. 1220+) and document it here.

## Build and deploy

The plugin is part of the webpack bundle; it is NOT a runtime-loaded script. Any change to `heratio-av-plugin.js` requires:

```
cd /usr/share/nginx/heratio/tools/mirador-build
npm run deploy
```

`npm run deploy` runs webpack in production mode and copies `dist/mirador.min.js` over the deployed artifact. Verify the artifact size grew sensibly (the current v1.104.0 bundle is approximately 2.9 MB).

## Known limitations

- Cue-by-cue subtitle overlay via external WebVTT `<track>` elements is not yet wired (see Captions track loading above).
- Audio-only canvases render the audio controls centred on a black background filling the window. Polish to swap in a slimmer audio-only chrome is on the backlog.
- Multi-body painting annotations resolve only the first matching body; we do not yet present alternate audio tracks or alternate-resolution video as a chooser.
- The plugin uses `crossOrigin="anonymous"` so the upstream media URL must serve `Access-Control-Allow-Origin: *`. Heratio's own `uploads` proxy already adds the header; external manifest sources that point at third-party media hosts depend on those hosts being CORS-friendly.

## Test harness

`tools/mirador-build/__tests__/av-plugin.test.js` covers the two pure helpers (`detectAvBody`, `parseMediaFragment`). It runs under Node's built-in test runner (`node --test`) without any extra dependencies and asserts:

- A canvas with a `Video` painting body returns `{ kind: 'video', ... }`
- A canvas with a `Sound` body returns `{ kind: 'audio', ... }`
- A canvas with an `Audio` body returns `{ kind: 'audio', ... }`
- A canvas with only `Image` painting bodies returns `null`
- An empty / malformed canvas returns `null`
- Media-Fragment parsing handles `t=12.3,15.6`, `t=12.3`, integer seconds, and invalid input

Run the suite with:

```
cd /usr/share/nginx/heratio/tools/mirador-build
node --test __tests__/av-plugin.test.js
```

The test is intentionally dependency-free (no jest, no babel) so it does not pull React/MUI into the test process. It re-defines the two pure helpers inline using the exact source from `heratio-av-plugin.js`, which doubles as a contract test - if the source diverges, the test must be updated in lockstep.
