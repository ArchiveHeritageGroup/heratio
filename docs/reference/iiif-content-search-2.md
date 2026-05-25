# IIIF Content Search 2.0

Heratio ships an [IIIF Content Search API 2.0](https://iiif.io/api/search/2.0/)
service that lets a viewer (Mirador, Universal Viewer, any harvester)
full-text search inside a manifest's transcribed canvases and highlight
matching regions on the page.

## Endpoints

| Method | Path | Returns |
|---|---|---|
| GET | `/iiif-manifest/{slug}/search?q=<term>[&motivation=highlighting]` | `AnnotationPage` of W3C Web Annotations targeting `Canvas#xywh=...` |
| GET | `/iiif-manifest/{slug}/autocomplete?q=<prefix>` | `AnnotationCollection` of `TextualBody` term suggestions |

Both responses use `application/ld+json` with the Search 2 context.
Empty queries return an empty `AnnotationPage` envelope (HTTP 200).
Unknown slugs return HTTP 404 with an Error document.

Path-prefix note: the IIIF spec example URL is `/iiif/{manifestId}/search`
but nginx on this host hard-routes `/iiif/` to Cantaloupe (the Image API
proxy). The search service therefore lives under `/iiif-manifest/{slug}/...`,
the same prefix used by the manifest endpoint itself. Mirador finds the
URL from the `service` block in the manifest, so the prefix difference is
transparent to clients.

## Manifest service block

Every manifest emitted by `IiifCollectionService::generateObjectManifest()`
now carries a top-level `service` entry pointing at the search endpoint:

```json
{
  "service": [
    {
      "@id": "https://heratio.example/iiif-manifest/my-doc/search",
      "id": "https://heratio.example/iiif-manifest/my-doc/search",
      "@type": "SearchService2",
      "type": "SearchService2",
      "profile": "http://iiif.io/api/search/2/search",
      "service": [
        {
          "@id": "https://heratio.example/iiif-manifest/my-doc/autocomplete",
          "id": "https://heratio.example/iiif-manifest/my-doc/autocomplete",
          "@type": "AutoCompleteService2",
          "type": "AutoCompleteService2",
          "profile": "http://iiif.io/api/search/2/autocomplete"
        }
      ]
    }
  ]
}
```

The block is built by `IiifContentSearchService::buildServiceBlock($slug)`
and attached via the static helper
`IiifCollectionService::appendSearchService(&$manifest, $slug)`. The
Presentation 3 manifest emitter (issue #698) calls the same helper so the
block stays in one place.

## Data sources

The service queries data that's already populated by the discovery
pipeline:

- `iiif_ocr_text.full_text` (FULLTEXT-indexed): the natural-language MATCH
  AGAINST query lives here.
- `iiif_ocr_block.{text, x, y, width, height, page_number}`: per-block
  bounding boxes for the FragmentSelector `xywh=` value.

Canvas indexing is mirrored from the manifest emitter: outer order is
ascending `digital_object.id`, multi-page TIFFs expand to one canvas per
page (probed against Cantaloupe `info.json`). `iiif_ocr_block.page_number`
maps to the corresponding canvas index for multi-page TIFFs; single-page
digital objects map 1:1.

## Response shape

```json
{
  "@context": [
    "http://iiif.io/api/search/2/context.json",
    "http://iiif.io/api/presentation/3/context.json"
  ],
  "id": "https://heratio.example/iiif-manifest/my-doc/search?q=fire",
  "type": "AnnotationPage",
  "partOf": { "id": "https://heratio.example/iiif-manifest/my-doc", "type": "Manifest" },
  "items": [
    {
      "id": "https://heratio.example/iiif-manifest/my-doc/search/annotation/1",
      "type": "Annotation",
      "motivation": "highlighting",
      "body": {
        "type": "TextualBody",
        "value": "fire",
        "format": "text/plain",
        "language": "en"
      },
      "target": {
        "type": "SpecificResource",
        "source": {
          "id": "https://heratio.example/iiif-manifest/my-doc/canvas/3",
          "type": "Canvas",
          "partOf": { "id": "https://heratio.example/iiif-manifest/my-doc", "type": "Manifest" }
        },
        "selector": {
          "type": "FragmentSelector",
          "conformsTo": "http://www.w3.org/TR/media-frags/",
          "value": "xywh=412,978,84,32"
        }
      }
    }
  ]
}
```

When `iiif_ocr_block` rows are missing (full_text exists but no per-block
coordinates), the service emits one whole-canvas annotation per matching
page so the user at least sees which canvases contain the term.

## Limits & current caps

- Hard cap of 200 hits per request. Search API 2 pagination support is
  reserved for a later phase.
- Block-level matches use a plain `LIKE %term%` after the FULLTEXT row
  filter; word-boundary matching is approximate. Acceptable for OCR
  output which is already tokenised one-word-per-block.
- Terms shorter than `innodb_ft_min_token_size` (default 3) bypass
  FULLTEXT via a whole-table LIKE fallback. This is slow on large
  corpora; tune the InnoDB FT settings if short-term search is hot.

## Files

| Path | Role |
|---|---|
| `packages/ahg-iiif-collection/src/Services/IiifContentSearchService.php` | Query + AnnotationPage builder + service-block factory |
| `packages/ahg-iiif-collection/src/Controllers/IiifContentSearchController.php` | HTTP layer |
| `packages/ahg-iiif-collection/routes/web.php` | Routes `/iiif-manifest/{slug}/search` + `/autocomplete` |
| `packages/ahg-iiif-collection/src/Services/IiifCollectionService.php` | Calls `appendSearchService()` in the manifest emitter |
| `tools/mirador-build/src/heratio-search-plugin.js` | Window.HeratioSearchPlugin helpers + plugin slot |
| `tools/mirador-build/src/index.js` | Wires the search plugin into the Mirador bundle |

## Cross-refs

- Issue #694 (this work)
- Issue #646 IIIF spec umbrella
- Issue #698 Presentation 3 manifest emitter (shares the
  `appendSearchService()` helper)
- Issue #665 OCR pipeline gap (ensures `iiif_ocr_block` coverage)
- Issue #100 W3C Web Annotations persistence (same target IRI scheme)
