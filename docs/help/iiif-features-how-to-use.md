> Heratio Help Center article. Category: Viewers & Media.

# IIIF Features: How to Use Each One

A practical, step-by-step guide to every IIIF feature in Heratio - what it does and exactly how to use it. For the conceptual overview see the "IIIF Integration" user guide; for the standards detail see the per-feature articles (compare mode, content search, collections, 3D, content state, validation).

Heratio implements the full IIIF stack: the Image API (deep zoom), the Presentation API (manifests, collections), Content Search, Authorization Flow 2.0, Change Discovery, and Content State - plus audio/video, 3D, annotations, comparison and workspaces.

---

## 1. Deep zoom (high-resolution images)

**What:** Explore a scanned image at full resolution without downloading it - only the tiles you view are fetched.

**How to use:**
1. Open any archival record that has a digital image object.
2. The viewer appears on the record page. For the full-screen experience click the image or use **IIIF Viewer**.
3. Scroll or double-click to zoom in; drag to pan.
4. Use the on-screen zoom controls (+ / - / reset) at the bottom of the viewer.
5. The navigator thumbnail (bottom corner) shows where you are in the whole image.

**Tip:** deep zoom works on a phone exactly as on a desktop - it only loads the detail on screen.

## 2. The full viewer (Mirador)

**What:** A workspace viewer for paging, comparing and annotating.

**How to use:**
1. From a record, open the dedicated viewer at **IIIF Viewer** (URL `/iiif-viewer/{record}`).
2. Use the thumbnail strip / next-previous controls to move between pages.
3. Use the window menu (top-right of the window) for image tools - brightness, contrast, rotate.
4. Use **Add resource** (the + button, top-left) to open another object alongside.

## 3. Side-by-side comparison

**What:** Put two objects on one screen and study them together - including objects from other institutions.

**How to use:**
1. Open the IIIF Viewer, or go to **Compare** (`/iiif-compare`).
2. Click **Add resource** and either pick another Heratio record or paste an external IIIF manifest URL (from any institution).
3. Each window zooms independently; arrange them side by side.
4. See the "IIIF Compare Mode" article for layer/opacity comparison.

## 4. Audio and video

**What:** Play audio and video in the same viewer, with a transport bar.

**How to use:**
1. Open a record whose digital object is audio or video.
2. In the IIIF Viewer the media loads with a play bar (position, play/pause, volume, full-screen).
3. Where captions or time-based notes exist, they appear against the timeline.

## 5. 3D models

**What:** Orbit a 3D object in the browser and, on supported devices, view it in AR.

**How to use:**
1. Open a record with a 3D digital object (for example a `.glb` model).
2. The model loads on the record page. **Drag to rotate, scroll to zoom.**
3. On a phone/tablet, use the AR button to place the model in your space.
4. Admins can add hotspots and thumbnails under **Admin > 3D Models**.

## 6. Collections

**What:** A curated, shareable set of manifests that travels as one link.

**How to use:**
1. Go to **IIIF Collections** (`/manifest-collections`).
2. Open a collection to browse its objects, or **New collection** to build one.
3. Add records, reorder them, then share the collection's manifest URL or viewer link.
4. See the "IIIF Collection" user guide for full detail.

## 7. Search within an object

**What:** Full-text search inside a digitised document, with hits highlighted on the pages.

**How to use:**
1. Open a record that has searchable text (OCR/transcription).
2. Use the **Search within** box in the viewer sidebar.
3. Matching regions are boxed on the pages; click a result to jump to it.
4. See "IIIF Content Search". Note: text must have been generated for the object first (see section 12).

## 8. Annotations

**What:** Notes, transcriptions and tags pinned to exact regions of an image - in the open W3C standard.

**How to use:**
1. Open the IIIF Viewer and open the annotations panel.
2. Existing annotation layers can be toggled on/off.
3. To create one, draw a region and add your note (sign-in required to save).
4. Annotations are standard, so other IIIF viewers can read them too.

## 9. Share a specific view (deep-linking / Content State)

**What:** Link to an exact zoomed/paged view, not just the record.

**How to use:**
1. Frame the view you want in the viewer.
2. Use the share/link control to copy the view link (Heratio encodes it as a IIIF Content State).
3. Anyone opening the link lands on the same frame. See "IIIF Content State".

## 10. Workspaces

**What:** Save a whole multi-window arrangement (which objects, positioned how) and return to it.

**How to use:**
1. Arrange your windows in the viewer.
2. Save the workspace; reopen it later or share the state link.

## 11. Access-controlled content

**What:** Some objects require sign-in or accepting terms; Heratio uses the IIIF Authorization Flow so access stays standards-based.

**How to use:**
1. Open a gated object - you will see a placeholder plus a prompt (login or accept terms).
2. Complete the prompt; the full-quality image then loads automatically.

## 12. Generate text and enrichment (for annotations & search)

**What:** AI reads an image (OCR/handwriting recognition), which powers search-within and can be pinned as annotations.

**How to use (admin):**
1. Ensure the object has an image digital object.
2. Run the AI extraction for the object (OCR -> entities). Text is stored and becomes searchable; entities can be written back as annotations.
3. This uses the AHG AI gateway transcription service. If an instance shows no search results or annotations, extraction has not been run yet for those records.

## 13. Quality and publishing (admins)

**What:** Heratio checks every manifest against IIIF Presentation 3.0 before it goes public.

**How to use:**
1. Go to **Admin > IIIF Validation**.
2. Review pass/fail/warning counts; click **Validate** on any object for inline results.
3. The publish gate blocks objects that fail IIIF checks or lack a rights statement.

## 14. Harvesting / discovery (integrators)

**What:** A change feed lets external aggregators harvest what changed, so your objects can be discovered across portals.

**How to use:** point an aggregator at the Change Discovery endpoint (`/iiif/discovery/changes`); it returns an Activity Streams feed of created/updated/deleted manifests.

---

## Current availability at a glance

- **Fully working:** deep zoom, full viewer, comparison, audio/video, 3D, collections (mechanism), validation, discovery, content state, image tools.
- **Works once data exists:** search-within and annotations require AI text extraction to be run first (section 12).
- **Not yet available:** table-of-contents / chapter navigation (Ranges) is not produced in manifests, so multi-page objects have no section index.
