> Heratio Help Center article. Category: Digital Objects / IIIF.

# IIIF Collections and Manifests

IIIF Collections let you group and organise your digitised material into shareable, standards-based sets that any IIIF-compatible viewer can open. Heratio builds IIIF Presentation API 3.0 manifests for individual records and hierarchical collections for grouping them, complete with deep-zoom imagery, full-text search, access control, and a built-in viewer.

---

## Overview

IIIF (the International Image Interoperability Framework) is an open set of standards for delivering images and their descriptions so they work across institutions and viewers. Heratio implements the IIIF Presentation API (version 3.0, with legacy 2.1 output available) and ties it to the deep-zoom image server.

The package provides:

- **Manifests** for individual archival descriptions, with one canvas per digital object or per page.
- **Collections** that group manifests and nest within one another for browsing.
- **A built-in viewer** (Mirador) plus a side-by-side comparison view.
- **Full-text search** over OCR text inside a manifest, and autocomplete.
- **Access control** through IIIF Authorization, deep-linking through Content State, change tracking through Change Discovery, and saved viewer workspaces.

---

## Key features

| Feature | Description |
|---------|-------------|
| Hierarchical collections | Group manifests into collections that can nest inside one another, with multilingual labels. |
| Standards-based manifests | Presentation API 3.0 output (and 2.1 on request) for every record with digital objects. |
| Built-in viewer | A Mirador viewer page and a multi-manifest comparison view. |
| Full-text search | IIIF Content Search over OCR text, returning highlighted regions, plus autocomplete. |
| Access control | IIIF Authorization (Auth Flow 2.0) integrated with security clearance. |
| Deep linking | Content State tokens capture a viewer pose (manifest, canvas, and zoom region). |
| Change discovery | A feed of manifest create, update, and delete activity for harvesters. |
| Saved workspaces | Per-user saved Mirador viewer configurations. |
| OCR and annotations | OCR export (TXT, ALTO, hOCR, PAGE-XML) and named-entity annotation surfaces. |

---

## How to use

### Browse and view collections

These pages are public:

1. Go to `/manifest-collections` to browse all collections, including nested subcollections.
2. Open a collection to see its items and breadcrumbs.
3. Open the viewer at `/iiif-viewer/{record}` to explore a record's images with deep zoom.
4. Use `/iiif-compare` to view several manifests side by side.

### Get the standards-based output

- A record's manifest is served at `/iiif-manifest/{record}`.
- A collection's IIIF JSON is served at `/manifest-collection/{collection}/manifest.json`.
- Add `?version=2` to either to request the legacy Presentation API 2.1 shape for older viewers.

### Create and manage a collection

These actions require an authenticated account with the matching permission:

1. Choose **New collection** (route `/manifest-collection/new`). To create a subcollection, start from within a parent collection.
2. Give it a name, description, attribution, viewing hint, and public flag, then save.
3. Open the collection and use **Add items** to include digital objects from your catalogue (optionally pulling in their children) or to reference an external manifest by URL.
4. Reorder items by dragging, and remove items you no longer want.
5. Edit or delete the collection from its management actions.

### Search inside a manifest

- Query OCR text with `/iiif-manifest/{record}/search?q=yourterm`. Matching text regions are returned so a viewer can highlight them on the page.
- Autocomplete suggestions come from `/iiif-manifest/{record}/autocomplete?q=prefix`.

### Save a viewer workspace

When viewing in Mirador, you can save your current arrangement of windows. Manage your saved workspaces at `/iiif/workspaces`, where you can rename, delete, or set one as your default on load.

---

## Configuration

Administrator pages live under `/admin` and `/iiif`:

- **Carousel and viewer settings** at `/admin/ahgSettings/carousel` control the viewer type, height, zoom and fullscreen controls, background, and the homepage featured-collection carousel.
- **Validation dashboard** at `/admin/iiif-validation` reports IIIF specification compliance.
- **Media queue and test** at `/admin/iiif-media/queue` and `/admin/iiif-media/test` show the media processing queue and let you test image-server connectivity.
- **3D reports** at `/admin/iiif-3d-reports` cover 3D digital objects, hotspots, models, thumbnails, and settings.

Other configuration notes:

- **Collection options:** a viewing hint (individuals, paged, continuous, multi-part, or top) and a public flag are set per collection.
- **Image delivery:** image tiles are served by the deep-zoom image server; manifest endpoints stay within Heratio so both work side by side.
- **Physical dimensions:** when a digital object records real-world width and height, the manifest carries that information so viewers can show a scale bar.
- **OCR pairing:** OCR text and per-region bounding boxes attach to the matching canvas, including page-by-page mapping for multi-page images.

---

## References

- Source: `packages/ahg-iiif-collection/`
- Issue: [GH #582](https://github.com/ArchiveHeritageGroup/heratio/issues/582)
