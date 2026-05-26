# ahg-annotations

W3C Web Annotation Data Model + IIIF Web Annotation REST persistence for Heratio.

- Closes #100 (storage backend + Annotot-shaped REST surface)
- Phase 1 of #648 (W3C compliance widening: SpecificResource bodies, TextQuote/Time/Geo/MediaFragment selectors, full Web Annotation Protocol header conformance, ETag-based optimistic concurrency, Prefer: contained-iris)

## Endpoints

| Verb   | Path                                          | Auth |
|--------|-----------------------------------------------|------|
| POST   | `/api/annotations`                            | session |
| GET    | `/api/annotations/search?targetId=<canvas>`   | public |
| GET    | `/api/annotations/{uuid}`                     | public |
| PUT    | `/api/annotations/{uuid}`                     | session |
| DELETE | `/api/annotations/{uuid}`                     | session |

All responses carry the WAP header set:

```
Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"
Link: <http://www.w3.org/ns/ldp#Resource>; rel="type",
      <http://www.w3.org/TR/annotation-protocol/>; rel="http://www.w3.org/ns/ldp#constrainedBy"
Vary: Accept[, Prefer on containers]
Allow: GET, PUT, DELETE, HEAD, OPTIONS  (individual annotations)
       GET, POST, HEAD, OPTIONS         (containers)
ETag: "<sha1-of-body+updated_at>"       (individual annotations)
Accept-Post: application/ld+json; profile="..."  (containers only)
```

## Selector support

- `FragmentSelector` (xywh=...) - IIIF canvas regions
- `SvgSelector` - Mirador-annotation-editor freeform shapes
- `TextQuoteSelector` - exact / prefix / suffix anchoring inside a text body
- `TextPositionSelector` - start / end character offsets
- `TimeSelector` / `MediaFragmentSelector` - audio + video time ranges (npt=, t=)
- `GeoSelector` / `PointSelector` - WKT-based geographic anchors
- `RangeSelector` / `CssSelector` / `XPathSelector` - DOM-based anchors

Selectors are round-tripped losslessly via `body_json`. Body-side selectors
(on `SpecificResource` bodies) are also denormalised into `body_selector_json`
so future faceted-search work can filter by quoted text without unwrapping
the full envelope.

## Database

- `ahg_iiif_annotation` (created on first boot by the service provider)
- Columns added in Phase 1 of #648: `body_selector_json` (JSON), `etag` (CHAR(40))

## Storage

`body_json` holds the full W3C JSON-LD document verbatim. The columns
alongside it (`target_iri`, `information_object_id`, `project_id`,
`visibility`, `body_selector_json`, `etag`) are denormalised for query.

## Auth

- Anonymous: read only
- Authenticated session: full CRUD
- CSRF: `/api/annotations*` is exempt (see `bootstrap/app.php`); session
  auth blocks cross-site forgery at the gate

## Phase roadmap (issue #648)

- Phase 1 (this release) - W3C compliance widening
- Phase 2 - Threading (`annotation_parent_id` + reply REST surface)
- Phase 3 - Moderation (pending / approved / rejected + admin queue)
- Phase 4 - Revisions (history of annotation edits) + system consolidation
