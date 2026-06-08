# 3D in Heratio — Metadata, IIIF 3D & Long-Term Preservation

*The Archive and Heritage Group · Heratio platform · June 2026*

This document explains how Heratio handles 3D digital objects across three
dimensions that matter for galleries, libraries, archives and museums (GLAM):
**descriptive/technical metadata**, **IIIF interoperability**, and
**long-term preservation**. It is written to be shared with partners and
peers.

---

## 1. Overview

A 3D model in a collection is two things at once: a faithful **surrogate** of a
physical object, and a **born-digital record** in its own right. To be useful
for scholarship — and trustworthy over decades — it needs more than a viewer. It
needs to carry its scale, how it was made, who made it, what it is allowed to be
used for, and it needs to survive format change.

Heratio treats 3D as a first-class record type with:

- a 3D viewer (Google `<model-viewer>`, WebGL/WebXR, AR-capable) embedded on the
  record page, with hotspots, camera bookmarks and turntable previews;
- a structured **metadata + paradata** model;
- **IIIF** manifest generation aligned to the emerging IIIF 3D specification;
- an **OAIS** preservation toolkit (BagIt, PREMIS, PRONOM, fixity).

Two ingest paths converge on one metadata model: 3D files uploaded through the
dedicated 3D module, and 3D files attached to any record as ordinary digital
objects. A registry service ensures both carry the same metadata, so the same
information appears wherever the model is shown.

---

## 2. 3D metadata

### 2.1 What is captured

**Technical / structural**
- Real-world **dimensions and units** (mm/cm/m/in/ft) and **scale** — so a model
  is measurable, not just viewable.
- **Coordinate system / up-axis**, model-space **bounding box**.
- **Format and version** (e.g. glTF 2.0, OBJ, PLY, STL), **compression**
  (none / Draco / meshopt / KTX2), lossless-master flag.
- Geometry counts (vertices, faces), texture and animation counts, materials,
  rig presence, **PBR map set** (baseColor / normal / metal-rough / occlusion /
  emissive), texture colour space, level-of-detail count, watertight flag.

**Capture & processing paradata** (the "how it was made", for transparency and reuse)
- **Capture method**: photogrammetry, laser scan, structured-light, CT/volumetric,
  CAD, procedural, manual modelling.
- Capture **device**, date, operator; number of source items (e.g. photos),
  point density, **stated accuracy (mm)**.
- **Processing software + version**, processing notes, georeference.

**Provenance & rights**
- Model **author/creator**, derivation chain (raw scan → master → access copy).
- A **licence for the 3D model itself**, distinct from the physical object's
  rights; licence holder; attribution.
- Accessibility: alternative text / described tour (supported by hotspots).

### 2.2 Automatic extraction

On upload, Heratio parses the model file and auto-populates what it can without
a GPU or external tools — reading only the glTF JSON chunk (or OBJ/PLY/STL
headers), never the heavy binary geometry, so it is fast even for large models:

- format version, compression (detects Draco / meshopt / KTX2 extensions),
- model-space bounding box, vertex and face counts,
- texture / animation counts, materials and rig flags, PBR map set.

A backfill command applies the same extraction to existing models. *(Example:
on a 22 MB Rosetta Stone glTF this yields glTF 2.0, 343,447 vertices, 480,264
faces, the bounding box and the PBR set — in milliseconds.)*

The curator then adds the human judgement fields (real-world scale and units,
capture method and device, accuracy, licence, attribution) via a metadata panel.

### 2.3 Controlled vocabularies

Capture method, units, coordinate system, compression and licence are driven by
Heratio's **Dropdown Manager** (no hard-coded lists, no database ENUMs), so
institutions can extend or localise the terms.

### 2.4 Where it appears

- The dedicated **3D model page** (full technical/paradata table).
- A **"3D technical metadata" panel on the record show page** — beside the
  viewer — so researchers see provenance and scale in context. (Injected through
  a shared, non-invasive view layer so it appears consistently across GLAM, DAM
  and sector record pages.)

### 2.5 Standards alignment

- **CIDOC-CRM / CRMdig** — provenance of the digitisation process.
- **CARARE / 3D-ICONS** and the **Europeana 3D Task Force** recommendations —
  cultural-heritage 3D metadata.
- **London Charter** and **Seville Principles** — paradata and transparency for
  computer-based visualisation of heritage.

---

## 3. IIIF and 3D

### 3.1 Why IIIF

The International Image Interoperability Framework (IIIF) lets institutions share
deep-zoom images, audio and video through open, viewer-independent manifests.
Extending that interoperability to 3D means a model — and its annotations,
cameras and structure — can be shared and consumed by any compliant viewer,
without lock-in to one vendor's platform.

### 3.2 Specification status (June 2026)

The **IIIF 3D Technical Specification Group** has advanced a 3D specification to
**release-candidate stage, targeted for January 2026**, folding into **IIIF
version 4**, which formally adds 3D. A feedback/experimentation window follows.
It is near-final but still evolving, so implementations should track it and
expect refinement.

### 3.3 How IIIF models 3D

- The **Canvas gains a depth (z) axis** — a 3D coordinate space alongside the
  familiar width/height.
- A 3D model (glTF/GLB) is a **"Model" content resource** painted onto the
  canvas — referenced as a **static file**, not tiled by an image server.
- **Cameras and lights** are scene elements; **annotations live in 3D space**
  via a point selector — a natural home for **hotspots**.

### 3.4 Heratio's position

- Heratio already **generates and versions an IIIF manifest per 3D model**.
- The roadmap aligns that manifest to the release-candidate shape (Canvas depth,
  Model content resource, cameras, point-selector hotspots) and round-trips
  Heratio's hotspots as IIIF 3D annotations.
- **Clarification on tiling:** the IIIF *Image* server (Cantaloupe in Heratio)
  is for 2D imagery; 3D models are served as static glTF/GLB files referenced
  from the manifest. Very large scenes (heritage sites, dense scans) are a
  separate concern addressed with 3D-Tiles / streaming approaches, not base IIIF.

---

## 4. Long-term preservation

### 4.1 The toolkit

Heratio ships an OAIS-aligned preservation layer:

- **BagIt** packaging, **PREMIS** events + rights, **PRONOM** format
  identification, **fixity** (checksums + scheduled verification), OAIS
  information packages, migration pathways and replication.

### 4.2 Principles for 3D

- **Separate the preservation master from the access derivative.** The
  web-delivery copy is typically compressed (Draco) and lossy in geometry
  quantisation; it must never be the only thing kept. The **uncompressed
  original is the preservation object**, with the compressed copy recorded as a
  derivative.
- **Recommended formats**

  | Role | Format |
  |------|--------|
  | Mesh master | OBJ+MTL (+ lossless PNG/TIFF textures), PLY, or X3D (ISO 19775); uncompressed glTF 2.0 acceptable |
  | Point-cloud master | E57 (ASTM E2807), LAS/LAZ |
  | CAD | STEP (ISO 10303) |
  | Access / delivery | glTF/GLB + Draco/meshopt |
  | Textures (master) | PNG/TIFF lossless (never JPEG) |

- **Significant properties** to declare and verify across any future migration:
  geometry (vertices/faces), **scale and units**, topology, colour/material
  (PBR), coordinate system, animation, and embedded metadata.

### 4.3 Status & roadmap

Heratio's preservation toolkit is mature; the 3D-specific work in progress is to:

1. enrol every uncompressed 3D **master** as a managed OAIS object with fixity +
   PREMIS (not merely a file on disk);
2. record the **3D significant properties** in PREMIS;
3. confirm/extend **PRONOM** identification for glTF, OBJ, PLY, STL, E57;
4. document a **normalization / migration policy**;
5. (future) preserve **raw capture data** (photo sets, point clouds) and add
   Archivematica format-policy rules for 3D.

---

## 5. Architecture in one picture

```
Physical object ──► Information Object (the record)
                         │
        ┌────────────────┼─────────────────────────────┐
        │                │                              │
   3D module        digital object                 IIIF manifest
   upload           (attached file)                (per model, versioned)
        └──────┬─────────┘
               ▼
        object_3d_model  ◄── auto-extracted technical metadata
        (one metadata home)   + curator paradata/provenance/rights
               │
        ┌──────┴───────────────┬───────────────────┐
        ▼                      ▼                    ▼
   3D model page       record show panel       OAIS preservation
                        (GLAM/DAM/sector)      (master + PREMIS + fixity)
```

A single metadata model serves description, display, IIIF and preservation —
captured once, shown everywhere, and preserved with its meaning intact.

---

## 6. References

- IIIF 3D Technical Specification Group — https://iiif.io/community/groups/3d/tsg/
- IIIF specification roadmap — https://iiif.io/news/2025/08/11/roadmap/
- CIDOC-CRM / CRMdig — http://www.cidoc-crm.org/
- CARARE / 3D-ICONS metadata schema
- Europeana 3D Task Force recommendations
- The London Charter; the Seville Principles
- OAIS (ISO 14721); PREMIS Data Dictionary; BagIt (RFC 8493); PRONOM (UK National Archives)
- Khronos glTF 2.0; X3D (ISO 19775); E57 (ASTM E2807); STEP (ISO 10303)

---

*Heratio is built for the international GLAM market; jurisdiction-specific
compliance is pluggable per market. Contact: johan@theahg.co.za*
