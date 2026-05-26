# Annotations Phase 1 - W3C Web Annotation compliance

**Issue:** #648 (Phase 1 of 4) - Annotation system gap closure
**Package:** `packages/ahg-annotations/`
**Status:** Shipped

## Summary

Phase 1 widens the existing `/api/annotations` surface (closed #100) to full
W3C Web Annotation Data Model + Web Annotation Protocol (WAP) compliance.
The Annotot-shaped legacy client (`HeratioAnnotationAdapter` in
`tools/mirador-build/src/index.js`) continues to work unchanged.

Out of scope (later phases): threading (Phase 2), moderation (Phase 3),
revisions + system consolidation (Phase 4).

## What changed

### SpecificResource body shape

The body of an annotation can now be a SpecificResource - a resource with
its own selector. Typical use: pin a comment to the second paragraph of an
external URL.

```json
{
  "type": "Annotation",
  "motivation": "commenting",
  "target": "https://heratio.host/iiif/3/<id>/canvas/1",
  "body": {
    "type": "SpecificResource",
    "source": "https://example.org/source-doc",
    "selector": {
      "type": "TextQuoteSelector",
      "exact": "the quick brown fox",
      "prefix": "lazy dog ",
      "suffix": " jumped over"
    }
  }
}
```

The body-side selector is stored verbatim in `body_json` AND denormalised
into a new `body_selector_json` column so future faceted-search work
(quoted-text filters) does not need to JSON_EXTRACT on every query.

### New selector types on `target.selector`

- **TextQuoteSelector** - `{type, exact, prefix?, suffix?}`. Anchors a
  quoted string inside a text body.
- **TextPositionSelector** - `{type, start, end}`. Character offsets.
- **TimeSelector** - `{type, t}`. Time range in a media file, typically
  expressed as Normal Play Time (`npt=0.0,10.0`).
- **MediaFragmentSelector** - `{type, value}`. RFC 5870 / Media Fragments
  URI; `value: "t=10,20"` for audio/video.
- **GeoSelector / PointSelector** - `{type, value}` where value is WKT
  (`POINT(28.0473 -26.2041)`). RFC 5870 `geo:` URIs are also accepted via
  FragmentSelector.

Known selector types are auto-tagged with `conformsTo` when the client did
not supply one (so spec-validating downstream consumers do not need to
guess). Unknown / future selector types are round-tripped losslessly via
`body_json`.

### Web Annotation Protocol (WAP) header conformance

Every annotation response now carries:

```
Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"
Link: <http://www.w3.org/ns/ldp#Resource>; rel="type",
      <http://www.w3.org/TR/annotation-protocol/>; rel="http://www.w3.org/ns/ldp#constrainedBy"
Vary: Accept
Allow: GET, PUT, DELETE, HEAD, OPTIONS
ETag: "<sha1>"
```

Container responses (`/api/annotations/search`) additionally carry:

```
Link: ...; <http://www.w3.org/ns/ldp#BasicContainer>; rel="type"
Accept-Post: application/ld+json; profile="..."
Vary: Accept, Prefer
Allow: GET, POST, HEAD, OPTIONS
```

POST responses additionally include `Location: <url-to-new-annotation>`.

Header injection lives in
`packages/ahg-annotations/src/Http/Middleware/AnnotationContentTypeMiddleware.php`
and is bound to every annotation route via the route group in
`packages/ahg-annotations/routes/web.php`.

### Optimistic concurrency (ETag)

Every individual annotation carries an `ETag: "<sha1>"` header. The hash is
of the stored `body_json` plus `updated_at`, recomputed on every write,
and persisted in the new `etag` column for the hot-path lookup.

- `PUT /api/annotations/{uuid}` with `If-Match: "<etag>"`:
  - matches current etag, write proceeds.
  - mismatches, 412 Precondition Failed returned with the live `ETag` and
    `currentEtag` body field for debugging.
- `DELETE /api/annotations/{uuid}` with `If-Match` works the same way.
- `GET /api/annotations/{uuid}` with `If-None-Match: "<etag>"`:
  - matches, 304 Not Modified returned with the live `ETag`.
  - mismatches, full annotation returned.

`If-Match: *` matches any existing resource (RFC 7232 §3.1). Weak validators
(`W/"..."`) are accepted and compared identically to strong validators
because the etag is content-hash based.

### Container Prefer support

`/api/annotations/search` honours the WAP / RFC 7240 `Prefer` request
header:

```
Prefer: return=representation; include="http://www.w3.org/ns/oa#PreferContainedIRIs"
```

When this is set, `first.items` is a list of bare annotation IRIs instead
of full annotation envelopes. The response echoes the applied preference
in a `Preference-Applied` response header.

Default (no Prefer header, or `contained-descriptions`) returns full
annotation envelopes.

### Container envelope upgrade

`/api/annotations/search` now returns BOTH the W3C-spec AnnotationPage
shape AND the legacy Annotot `resources` array:

```json
{
  "@context": ["http://www.w3.org/ns/anno.jsonld", "http://iiif.io/api/presentation/3/context.json"],
  "id": "https://host/api/annotations/search?targetId=...",
  "type": ["BasicContainer", "AnnotationCollection"],
  "total": 2,
  "first": {
    "id": "https://host/api/annotations/page?targetId=...",
    "type": "AnnotationPage",
    "partOf": "https://host/api/annotations/search?targetId=...",
    "items": [ ... ]
  },
  "resources": [ ... ]
}
```

The Annotot-shaped client in `tools/mirador-build/src/index.js` reads
`resources`, which is unchanged. WAP-conformant clients read `first.items`.

## Database

New columns on `ahg_iiif_annotation`:

| Column                | Type     | Purpose |
|-----------------------|----------|---------|
| `body_selector_json`  | JSON     | Denormalised body-side selector (SpecificResource) |
| `etag`                | CHAR(40) | sha1 hash for HTTP ETag |

Both are added idempotently via `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`
in `packages/ahg-annotations/database/install.sql`. Older annotations
without a stored `etag` fall back to a computed hash on read.

## Code changes

| File | Purpose |
|---|---|
| `packages/ahg-annotations/src/Controllers/AnnotationsController.php` | SpecificResource + selector support, ETag, Prefer, container envelope |
| `packages/ahg-annotations/src/Http/Middleware/AnnotationContentTypeMiddleware.php` | WAP header decorator (new) |
| `packages/ahg-annotations/routes/web.php` | Middleware wired into route group |
| `packages/ahg-annotations/database/install.sql` | `body_selector_json` + `etag` columns |
| `packages/ahg-annotations/README.md` | Endpoint + selector reference |
| `tests/Feature/Annotations/AnnotationsW3cTest.php` | Round-trip + header + concurrency tests |
| `docs/help/annotations-w3c.md` | User-facing help article |

## Backwards compatibility

- Annotot-shaped clients (HeratioAnnotationAdapter): parse JSON regardless
  of Content-Type, ignore unknown headers, read `resources`. No change.
- Rows created before this release: `body_selector_json` and `etag` are
  populated on the next PUT. Reads fall back to a computed hash so ETags
  still work even before any write.

## Phase roadmap

- Phase 2 - Threading. New `annotation_parent_id` column + REST surface.
- Phase 3 - Moderation. `status` enum (pending / approved / rejected) +
  admin queue.
- Phase 4 - Revisions + system consolidation (merging the multiple
  existing annotation surfaces).
