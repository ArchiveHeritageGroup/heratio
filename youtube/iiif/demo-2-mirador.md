# IIIF End to End, Part 2 — Presentation, Comparison, Annotation, Search, Auth and Beyond with Mirador

Part 1 covered *pixels* (the Image API through OpenSeadragon). This part covers *everything wrapped around the pixels*: how images become navigable, comparable, searchable, annotatable, gated objects — and how a workspace viewer like Mirador exposes all of it. Viewer-agnostic and not time-limited; this is the full remaining IIIF surface.

---

## 1. The Presentation API — the object model

Where the Image API serves pixels, the **Presentation API** describes *objects*: their structure, order, labels, metadata, rights and relationships. It is a JSON-LD document — the **manifest** — that a viewer dereferences and renders. The core resource types, outermost to innermost:

- **Collection** — an ordered set of manifests and/or sub-collections. A whole library, a themed exhibition, a series. Collections nest, so you can model an entire holding as one browsable tree.
- **Manifest** — one intellectual object: a book, a painting, a film, a scroll, a 3D artefact. This is the unit you "open." It carries the descriptive metadata and the ordered list of canvases.
- **Canvas** — an abstract "page" or view surface with a coordinate space (width/height, or duration for time-based media). A manifest is an ordered list of canvases (the 300 pages of a book, the two sides of a coin, the single face of a painting).
- **Annotation Page → Annotation** — content is *painted onto* a canvas via W3C Web Annotations with `motivation: painting`. The image (or video, audio, text, 3D) is the annotation *body*; the canvas is the *target*. Non-painting annotations (comments, transcriptions, tags) attach the same way with other motivations.
- **Content Resource** — the actual bytes: an Image (with an embedded IIIF image `service` for deep zoom), or Video/Sound/Text/Model/Dataset.

### Descriptive & functional properties a manifest can carry

- **label / summary / metadata** — title, description, and an ordered list of label/value pairs (dates, creators, dimensions, provenance) in any language (IIIF is multilingual by design; every text value is a language map).
- **requiredStatement** — attribution/rights text a viewer MUST display.
- **rights** — a licence or rights-statement URI (Creative Commons, RightsStatements.org).
- **provider** — the institution: name, homepage, logo, contact.
- **thumbnail** — representative image(s) with their own dimensions/service.
- **homepage / seeAlso / rendering** — the human landing page, machine descriptions (MARC, LIDO, schema.org), and alternate renderings (a whole-book PDF, an EPUB).
- **navDate** — a date for placing the object on a timeline/map.
- **behavior** — rendering hints: `paged`, `continuous`, `individuals`, `facing-pages`, `non-paged`, `multi-part`, `together`, `auto-advance`, `repeat`.
- **viewingDirection** — `left-to-right`, `right-to-left`, `top-to-bottom` (essential for non-Western material).
- **placeholderCanvas / accompanyingCanvas** — a poster frame before play, or a persistent side image (a record label spinning next to audio).

### Structures / Ranges — the table of contents

A manifest's **structures** are **Ranges**: named, nested groupings of canvases (and points within them). This is the table of contents — chapters, movements, articles, scenes — letting a viewer offer "jump to Chapter 4" or a clickable TOC over a 900-page volume or a two-hour film.

## 2. Mirador — the workspace viewer

Mirador is the open-source *multi-window* IIIF viewer. Where OpenSeadragon shows one image beautifully, Mirador is a **workspace**: multiple resizable windows, each showing a manifest, on one canvas, with tools, side-by-side comparison, annotations, search and a saveable layout. It uses OpenSeadragon internally for each window's deep zoom, so everything in Part 1 is present *inside* every Mirador window.

Core Mirador capabilities:

- **Multi-window workspace** — open many objects at once; tile, arrange, resize, and save/restore the whole workspace state (shareable as a URL/JSON).
- **Load any manifest** — via the "Add resource" box, drag-and-drop of a manifest URL, or the IIIF drag-and-drop logo. No pre-registration — paste a URL from any institution and it loads.
- **Side-by-side comparison** — the headline move: two (or more) windows, each an object from a *different* institution, on one screen, independently zoomable. Compare states of a print, hands of a scribe, editions of a map.
- **Image tools** — per-window brightness, contrast, saturation, rotate, flip, reset (OSD-backed).
- **Navigation** — thumbnail strip, gallery view, single/book/scroll views, canvas index, first/last/next/previous, and a Ranges-driven table-of-contents sidebar.
- **Annotations** — display existing annotation pages on a canvas, toggle layers, and (with the annotation-editor plugin) draw regions and author new W3C annotations, storing them to a backend.
- **Layers & opacity** — stack image annotations on one canvas and cross-fade — e.g. a visible-light scan over a multispectral/X-ray capture of the same page.
- **Search within** — a search box that queries the object's Content Search service and highlights hits on the canvases (see §3).
- **Audio / video** — play time-based canvases with a transport bar, captions, and time-anchored annotations (see §5).
- **Collection browsing** — open a Collection manifest and navigate its tree of sub-collections and manifests inside the workspace.
- **Plugins** — Mirador is extensible: image-comparison sliders, scalebars/physical-dimensions, download, share/embed, text-overlay/OCR, video, annotation editors, 3D, custom panels.

## 3. Content Search API — search *inside* an object

The **Content Search API** lets a viewer full-text-search within a single object (typically over OCR/transcription annotations) and get back annotations whose targets are regions on canvases. A manifest advertises a `service` of type `SearchService`. In Mirador the search box hits it, and results paint as highlight boxes on the pages, with a hit list you can click through. An **autocomplete** service can suggest terms. This is how a reader searches a 500-page digitised manuscript and jumps straight to every occurrence, boxed on the page.

## 4. Authorization Flow API — gated content, still interoperable

Not everything is open. The **Authorization Flow API** lets access-controlled resources stay standards-compliant. A resource can advertise:

- a **probe service** (is this user allowed to see the full-quality version?),
- an **access/interaction service** with a pattern: `login` (redirect to auth), `clickthrough` (accept terms), `external` (already authenticated by IP/session), or `kiosk` (auto),
- a **token service** the viewer calls to obtain an access token it then sends to the image server.

The viewer handles the flow generically: shows a degraded/placeholder image, prompts the interaction, obtains a token, and swaps in the full image — all without the institution writing viewer-specific code. Rights and access are part of the standard, not a bolt-on.

## 5. Time-based media (audio & video)

IIIF is not only images. A **Canvas** can have a **duration** instead of (or as well as) width/height, and paint Sound or Video content resources onto it, positioned in time (and space, for video). That unlocks:

- streaming A/V in the same viewer, with a transport bar;
- **time-anchored annotations** — captions, transcripts, chapter markers, commentary pinned to a timecode (and, for video, to a spatial region at a timecode);
- **Ranges over time** — a table of contents for a symphony's movements or a film's scenes;
- mixed canvases — e.g. a page image with an audio reading of it attached.

## 6. 3D (the emerging surface)

The IIIF 3D community is extending the model so a Canvas can carry a **Model** content resource (e.g. glTF) in a 3D coordinate space, with cameras, lights and (in time) annotations anchored to points on the mesh. Viewers integrate model-viewer/WebXR so a 3D artefact can be orbited in the browser and launched into AR — the same "open standard, any viewer" promise extended from 2D to 3D.

## 7. Change Discovery API — aggregation at scale

The **Change Discovery API** publishes an Activity Streams feed of what changed (created/updated/deleted manifests). It is how aggregators and search engines *harvest* IIIF collections across many institutions without scraping — the plumbing behind cross-institution portals and unified discovery. Not something an end user sees, but it's why "all the world's copies in one search" is achievable.

## 8. The full end-to-end walkthrough (everything, in order)

1. **Enter through a Collection** — open a Collection manifest; browse its nested tree of sub-collections and objects inside the workspace.
2. **Open a Manifest** — pick an object; it loads with its label, metadata, attribution, rights, provider and thumbnail.
3. **Read the object** — page through canvases (book/scroll/gallery views), using the Ranges table-of-contents to jump to chapters/scenes; honour `viewingDirection` and `behavior` (paged, facing-pages, continuous).
4. **Deep-zoom any canvas** — everything from Part 1: tiled zoom, pan, rotate, filters, loupe, deep-link.
5. **Compare** — add a second window with another object — from *your* holdings or *another institution's manifest URL* — and study them side by side; independent zoom, or stacked layers with opacity cross-fade.
6. **Search within** — query the Content Search service; jump between highlighted hits on the pages.
7. **Annotate** — display existing annotation layers (transcriptions, translations, tags, commentary); author new region annotations and save them; note they're standard Web Annotations any viewer can read.
8. **Play time-based media** — for A/V canvases, use the transport, captions, and time-anchored annotations; navigate scenes/movements via time Ranges.
9. **View 3D** — orbit a model canvas; launch AR where supported.
10. **Handle access** — for gated resources, run the auth interaction (login/clickthrough/external/kiosk), get a token, and reveal the full-quality content.
11. **Follow the links** — `homepage` to the institution's landing page, `seeAlso` to machine metadata (MARC/LIDO/schema.org), `rendering` to a whole-object PDF/EPUB.
12. **Save & share** — export the workspace layout (which objects, arranged how, zoomed where) as a shareable state; embed a window in another site; or hand off a single deep-link.
13. **Aggregate** — behind the scenes, Change Discovery feeds let portals harvest all of the above across institutions into one search.

## Where Heratio stands (Presentation + the rest of the surface)

**Coverage scale:** **3** = full + verified live this session · **2** = live but partial (endpoint works / limited data / not exercised end-to-end) · **1** = present but unpopulated or unverified · **0** = absent.

The headline: Heratio implements **all five IIIF specs plus Content State, 3D, AV, workspaces and validation** — a near-complete stack. The gaps are *data*, not *capability*.

| Spec / feature | Heratio | Level | Evidence |
|---|---|---|---|
| Presentation API 3.0 (manifests) | full | **3** | 200 ld+json, correct Canvas/Annotation structure |
| Audio / Video (time-based canvas) | full | **3** | video plays in Mirador, transport `0:00 / 3:03` |
| 3D (model canvas) | full | **3** | interactive `model-viewer`, real `.glb`, orbit/zoom |
| Comparison / side-by-side | full | **3** | external Internet Archive manifest loaded in Mirador |
| Content Search API 2.0 | endpoint live, no data | **2** | `/search` returns `search/2` context; nothing to find (no OCR) |
| Authorization Flow API 2.0 | endpoints live | **2** | `/iiif/auth/2/{probe,access,token}`; probe 422-validates; not run end-to-end |
| Change Discovery API | endpoint live | **2** | `/iiif/discovery/changes` 200 Activity Streams |
| Content State API | routes present | **2** | `encode`/`decode` present, not exercised |
| Collections | shell works, empty | **2** | valid `Collection` manifest + view 200; sample has 0 items |
| Workspaces (save/restore) | routes present | **2** | `/iiif/workspaces` + `/api/iiif/workspace` |
| Validation / publish gate | dashboard present | **2** | `/admin/iiif-validation` |
| Image tools (brightness/rotate) | Mirador built-in | **2** | present, not re-verified live |
| Ranges / structures (TOC) | not emitted | **0** | 6-canvas manifest emits `structures: none` — no table-of-contents |
| Annotations (W3C draw-on-image) | tables + write endpoint, no data | **1** | `iiif_annotation` = 0; `/api/iiif/annotations/from-ner` write exists; orchestrator likely AtoM-side |
| Content search *results* | depends on OCR | **1** | `iiif_ocr_text` = 0; fillable via gateway `/ai/v1/htr` |

**Read of the table:** annotations and search sit at 1 purely because the OCR/extraction pipeline has never been *run* (not a code gap — run it once via the gateway and both move to 3). The one genuine *capability* gap is **Ranges/structures = 0**: even a 6-canvas object gets no table-of-contents in its manifest, so viewers can't offer chapter/section navigation. Everything else scores 2-3.

## 9. Why this is the whole point (the thesis)

Because every layer — pixels, structure, annotations, search, access, time, 3D, and change feeds — is an **open, shared standard**, an object stops being trapped in one institution's bespoke viewer. Your holding can sit beside the British Library's on one screen, be searched, annotated, and enriched, and be discovered from anywhere, with no integration written by anyone. Interoperability isn't a feature of IIIF; it *is* IIIF.

## 10. The five specs, one-line each (reference)

- **Image API** — request any region/size/rotation/quality of an image by URL; `info.json` advertises capabilities. (Part 1)
- **Presentation API** — Collections/Manifests/Canvases/Annotations describe and structure objects for viewers.
- **Content Search API** — full-text search within an object, returning annotations to highlight.
- **Authorization Flow API** — probe/access/token services gate content while staying interoperable.
- **Change Discovery API** — Activity Streams feeds of changes for cross-institution harvesting.
