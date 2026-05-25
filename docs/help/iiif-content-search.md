# IIIF Content Search

Search inside any digitised manifest in Heratio and jump straight to the
matching region on the page.

## Overview

Heratio supports the [IIIF Content Search API 2.0](https://iiif.io/api/search/2.0/).
When you open a manifest in the IIIF viewer (Mirador), the search panel
in the side bar can full-text query every transcribed canvas in that
manifest and highlight matches over the image. Click a hit, the viewer
pans and zooms to the bounding box.

## What gets searched

For each digitised record, the search service queries:

- The OCR / HTR text stored against the record's digital objects
  (`iiif_ocr_text.full_text`).
- The per-block bounding boxes captured during indexing
  (`iiif_ocr_block`), so each hit is positioned on the right page at
  the right coordinates.

If a manifest has no OCR yet, the search box still appears but returns
zero results. Run the OCR / HTR pipeline against the digital objects to
populate the index.

## Using the search box in Mirador

1. Open any archival record's IIIF viewer page (`/iiif-viewer/<slug>`).
2. Open the side panel (left edge of the window).
3. Pick the **Search** tab.
4. Type a term and press Enter.
5. The viewer lists every hit, grouped by canvas. Click a hit to jump.

Tips:

- Multi-word phrases are matched in natural-language mode (stop-words
  are dropped, common variants are folded).
- Terms shorter than 3 characters fall back to a slower substring scan.
- The autocomplete panel shows the most common terms in the manifest's
  OCR text that start with what you've typed.

## Programmatic access

The search service is also a plain HTTP endpoint that any IIIF-aware
client can call:

```
GET /iiif-manifest/<slug>/search?q=<term>
GET /iiif-manifest/<slug>/autocomplete?q=<prefix>
```

Both return W3C Web Annotation JSON-LD with the
`http://iiif.io/api/search/2/context.json` context. The search response
is a standard `AnnotationPage`; each annotation has a `FragmentSelector`
with the `xywh=` value pointing at the matching region on the canvas.

The endpoint URL is also advertised in the manifest's `service` block
(type `SearchService2`), so any compliant viewer discovers it without
configuration.

## Privacy

Search reads the same OCR text the manifest already exposes via its
canvas annotations. No additional access control is layered on top of
manifest visibility - if the manifest is open, the search index of that
manifest is open too. Restricted manifests should be gated by the IIIF
Auth pipeline.

## Related

- IIIF Collections - `/help/iiif-collection-user-guide`
- IIIF Validation - `/help/iiif-compliance-validation`
- Cantaloupe deep-zoom - `/help/iiif-cantaloupe-setup`
- AI - HTR / OCR pipeline that populates the search index

## References

- Source: `packages/ahg-iiif-collection/src/Services/IiifContentSearchService.php`
- Spec: https://iiif.io/api/search/2.0/
- Issue: [GH #694](https://github.com/ArchiveHeritageGroup/heratio/issues/694)
