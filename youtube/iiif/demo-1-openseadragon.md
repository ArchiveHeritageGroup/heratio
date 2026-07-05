# IIIF End to End, Part 1 — The Image API and Deep Zoom with OpenSeadragon

A complete, viewer-agnostic explanation of the *image* half of IIIF: what the Image API is, everything it can do, and how a deep-zoom viewer like OpenSeadragon turns it into an experience. This is the foundation. Part 2 (`demo-2-mirador.md`) builds the *presentation* layer on top of it.

Not tied to any one product and not time-limited — this is the full surface.

---

## 1. What IIIF is, in one paragraph

IIIF (International Image Interoperability Framework, "triple-eye-eff") is a set of open, HTTP-based API specifications that let any institution serve images and their descriptions in a standard way, so that **any compliant viewer can display any compliant resource from anywhere**, with no bespoke integration. It is maintained by the IIIF Consortium (founded 2015 by Oxford/Bodleian, the British Library, Stanford, BnF, and others). There are five specs; this file covers the **Image API**. The **Presentation API**, **Content Search API**, **Authorization Flow API**, and **Change Discovery API** are covered in Part 2.

The mental model: IIIF is the shipping-container standard for cultural-heritage media. Agree on the box, and the whole world's cranes, ships and trucks can move it.

## 2. The Image API — what it actually is

The Image API is a URL contract for requesting *pixels*. Every image served by a IIIF image server (Cantaloupe, IIP, SIPI, Loris, serverless, cloud) answers two kinds of request:

### 2a. The image request URL

```
{scheme}://{server}/{prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
```

Each segment is a live transformation the server performs on demand:

- **identifier** — opaque id of the source image (often URL-encoded path or an ARK/handle).
- **region** — `full`, `square`, `x,y,w,h` (pixels), or `pct:x,y,w,h` (percent). This is *which part* of the image — the tile.
- **size** — `max`, `w,`, `,h`, `w,h`, `!w,h` (fit within), `pct:n`, `^...` (allow upscaling in 3.0). This is *how big* to render that region.
- **rotation** — degrees `0`–`360`; prefix `!` mirrors first (e.g. `!90`).
- **quality** — `default`, `color`, `gray`, `bitonal`.
- **format** — `jpg`, `png`, `tif`, `gif`, `jp2`, `pdf`, `webp`.

Example — a 256px-wide tile from a sub-region, greyscale:
```
.../ark:12345/1024,2048,512,512/256,/0/gray.jpg
```

The server generates that exact derivative on the fly. Nothing is pre-cut (though servers cache). This single URL grammar is the whole reason deep zoom works: a viewer just asks for the tiles and sizes it needs, when it needs them.

### 2b. The image information document (`info.json`)

Every image also exposes its capabilities at:
```
.../{identifier}/info.json      (content-type application/ld+json, IIIF Image 3.0 context)
```

`info.json` tells a viewer everything it needs to render efficiently:

- **width / height** — full pixel dimensions of the source.
- **sizes** — a list of pre-renderable widths/heights (useful for thumbnails).
- **tiles** — the tile `width`/`height` and the **scaleFactors** (the pyramid: 1, 2, 4, 8, 16…). This is the zoom pyramid the viewer walks.
- **profile / level** — compliance level 0, 1, or 2 (see below).
- **maxWidth / maxHeight / maxArea** — server-imposed ceilings on a single request.
- **preferredFormats**, **extraFormats**, **extraQualities**, **extraFeatures** — optional capabilities (mirroring, arbitrary rotation, size-by-forced-width, region-by-percent, etc.).
- **rights** — a licence/rights-statement URI for the pixels.
- **service** — links to *other* services attached to the image (Content Search, auth, a physical-dimensions service, etc.).
- **partOf / seeAlso** — links back up to a manifest or out to related descriptions.

### 2c. Compliance levels (why some viewers do more than others)

- **Level 0** — static tiles only; the server pre-generates fixed derivatives, no on-the-fly params. Cheapest to host (even a plain file bucket / GitHub Pages can serve level 0).
- **Level 1** — supports region + size + basic quality/format.
- **Level 2** — the full grammar: arbitrary regions, sizes, rotation, quality, format. What most dynamic servers offer.

A viewer reads the level and adapts. Level 0 still deep-zooms; it just can't do live rotation/greyscale/arbitrary crops.

## 3. OpenSeadragon — turning the Image API into an experience

OpenSeadragon (OSD) is the de-facto open-source deep-zoom viewer. It consumes a IIIF `info.json` as a **tile source** and manages a *viewport* over the tile pyramid. It is also the deep-zoom engine embedded *inside* Mirador, so understanding OSD explains both.

What OSD does with the Image API:

- **Tiled, on-demand loading** — only the tiles intersecting the current viewport, at the nearest pyramid level, are fetched. Pan/zoom triggers new tile requests; off-screen tiles are dropped. This is why a 2-gigapixel image opens instantly.
- **Smooth viewport** — continuous zoom, pan, kinetic/inertial gestures, springs and animation; not discrete zoom steps.
- **Rotation & flip** — rotate the viewport in-browser, or (level 2) request server-side rotation.
- **Multi-image / sequence** — load many tile sources into one viewer (pages of a book, or stacked layers).
- **Navigator** — the little overview thumbnail with a viewport rectangle.
- **Reference strip** — a filmstrip of pages for sequence mode.
- **Overlays** — position HTML/SVG elements in image coordinates that pan and zoom *with* the image (the basis for annotations and hotspots).
- **Drawers** — a WebGL drawer (fast, GPU) or a Canvas drawer (needed when you must read pixels back, e.g. live filters or a magnifier loupe).
- **Filtering** (via plugin) — brightness, contrast, greyscale, invert, threshold, gamma, applied per-tile on the canvas.
- **Home / full-page controls**, keyboard nav, configurable gesture bindings.

## 4. The full deep-zoom walkthrough, start to end

This is the complete arc of an image-only IIIF experience, independent of any product.

1. **Discovery** — the viewer is handed an image identifier or an `info.json` URL (embedded in a page, passed as a param, or dereferenced from a manifest — see Part 2).
2. **Capability handshake** — the viewer GETs `info.json`, reads width/height, tile size, scaleFactors, level, maxArea, and any `rights`/`service` links.
3. **Initial render** — it requests the lowest-resolution tiles that cover the frame (often a single `full/{small},/0/default.jpg`) so *something* appears immediately.
4. **Zoom in** — as the user zooms, OSD computes which pyramid level and which tiles the viewport now needs and requests exactly those (`x,y,w,h/{size}/0/default.jpg`). Detail sharpens progressively.
5. **Pan** — dragging shifts the viewport; newly exposed tiles load, off-screen ones are released. Memory stays bounded regardless of source size.
6. **Manipulate** — rotate/flip the viewport; apply filters to read faded text; drop a magnifier loupe to inspect without changing zoom.
7. **Inspect precisely** — because every view is expressible as a region+size URL, you can copy a link to *this exact framing* — a deep-link that reloads to the same pixels. Shareability is native, not bolted on.
8. **Annotate / overlay** — place SVG/point overlays in image space to mark regions (a bridge into the Web Annotation model, expanded in Part 2).
9. **Respect rights** — surface the `rights` URI (licence / RightsStatements.org) and any required attribution the info doc or manifest carries.
10. **Reset / exit** — return to full view. The source never moved; only tiles were streamed.

## 5. What the Image API deliberately does *not* do

It serves *pixels and image capabilities* — nothing about sequence, structure, metadata, labels, table-of-contents, annotations, search, or access control. Those belong to the **Presentation**, **Content Search**, and **Authorization** APIs, which wrap one or many images into an actual object you can read, navigate, compare, search and gate.

That wrapping is Part 2. →  `demo-2-mirador.md`

## Where Heratio stands (Image API surface)

**Scale 0-3.** **Highest** = the level a complete, best-in-class IIIF implementation reaches (the ceiling). **Heratio** = verified this session. **3** = full + verified live · **2** = live but partial (works / limited data / not exercised end-to-end) · **1** = present but unpopulated or unverified · **0** = absent.

Note: the IIIF Image API's own official compliance tops out at **Level 2** (Cantaloupe reaches it) — that's a different scale from the 0-3 maturity below.

| Capability | Highest | Heratio | Gap? | Evidence |
|---|:---:|:---:|:---:|---|
| Image API server (Cantaloupe, IIIF L2) | 3 | **3** | — | live tiles verified |
| Deep zoom (tiled, on-demand) | 3 | **3** | — | OSD 6.0.2, rendered 2339x1698, tiles 200 |
| `info.json` capability doc | 3 | **3** | — | verified 200 |
| region / size / rotation / quality / format | 3 | **3** | — | full grammar (Cantaloupe L2) |
| Navigator / home / full-page | 3 | **3** | — | in viewer |
| Multi-image / sequence | 3 | **3** | — | via Mirador (Part 2) |
| Magnifier loupe | 3 | **2** | ▲1 | in code, not re-verified live |
| Live image filters (brightness/contrast/…) | 3 | **2** | ▲1 | in code, not re-verified live |
| Deep-link to a view (Content State) | 3 | **2** | ▲1 | `/iiif/content-state/{encode,decode}` present, not exercised |
| Encrypted-at-rest masters render | 3 | **1** | ▲2 | 501s (heratio#1396) |

## 6. Operator notes (not for an audience)

- If `info.json` returns 501/415, the source isn't a decodable image the server can read (wrong format, or encrypted-at-rest) — deep zoom will be blank.
- Level 0 sources can't rotate/greyscale/arbitrary-crop; don't script those moves against a static-tile source.
- The Canvas drawer is required for pixel-readback features (loupe, filters); the WebGL drawer silently no-ops them.
- Tile size, scaleFactors and maxArea in `info.json` govern performance far more than raw pixel count.
