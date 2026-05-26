# Annotations - W3C Web Annotation support

Heratio stores annotations using the
[W3C Web Annotation Data Model](https://www.w3.org/TR/annotation-model/)
and exposes them via the
[Web Annotation Protocol (WAP)](https://www.w3.org/TR/annotation-protocol/)
on the `/api/annotations` surface. This article covers what you can target,
what you can annotate with, and how third-party tools interact with the
endpoint.

## What can I annotate?

| Target type      | Selector                                | Use case |
|------------------|------------------------------------------|----------|
| IIIF canvas region | `FragmentSelector` (`xywh=x,y,w,h`)   | Mark a region on a scanned page or image |
| Freeform shape   | `SvgSelector`                           | Mirador-annotation-editor drawings |
| Text quote       | `TextQuoteSelector` (exact + prefix/suffix) | Anchor a comment to a specific phrase |
| Text position    | `TextPositionSelector` (start + end)    | Pin to character offsets |
| Audio / video range | `TimeSelector` (`npt=0.0,10.0`) or `MediaFragmentSelector` (`t=0,10`) | Comment on a time range in a media file |
| Geographic point | `GeoSelector` / `PointSelector` (WKT)   | Pin an annotation to a map coordinate |
| HTML region      | `RangeSelector` / `CssSelector` / `XPathSelector` | DOM-based anchoring |

You can combine selectors using the spec's `refinedBy` chain (e.g.
"the first paragraph, then the third sentence within it") and Heratio
will round-trip them losslessly.

## Body shapes

- **TextualBody** - plain text comment.
- **SpecificResource** - point at an external resource, optionally with
  its own selector. Useful when you want to say "annotate the second
  paragraph of this URL with a tag from this vocabulary".
- **Multiple bodies** - the spec allows an array of bodies; Heratio
  stores it verbatim.

Example SpecificResource body:

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
      "exact": "the quick brown fox"
    }
  }
}
```

## REST endpoints

| Verb   | Path                                            | Auth |
|--------|-------------------------------------------------|------|
| GET    | `/api/annotations/search?targetId=<canvas>`     | public |
| GET    | `/api/annotations/{uuid}`                       | public |
| POST   | `/api/annotations`                              | session |
| PUT    | `/api/annotations/{uuid}`                       | session |
| DELETE | `/api/annotations/{uuid}`                       | session |

All responses use `Content-Type: application/ld+json; profile="http://www.w3.org/ns/anno.jsonld"`
and carry the WAP `Link` header advertising LDP resource + WAP
constrainedBy.

### Container preferences

The search endpoint honours `Prefer` (RFC 7240). Pass
`Prefer: return=representation; include="http://www.w3.org/ns/oa#PreferContainedIRIs"`
to receive a list of annotation IRIs in `first.items` instead of full
annotation envelopes. The response advertises the applied preference in
`Preference-Applied`.

### Optimistic concurrency

Every individual annotation carries an `ETag`. To safely edit:

1. `GET /api/annotations/{uuid}` and read the `ETag` header.
2. `PUT /api/annotations/{uuid}` with `If-Match: "<etag>"`. If the
   annotation changed since you read it, the server returns 412
   Precondition Failed with the current ETag in the response body so you
   can re-fetch and resolve.

`GET` with `If-None-Match: "<etag>"` returns 304 Not Modified when the
annotation is unchanged - useful for caching.

## Clients

- **Mirador-annotation-editor** is wired into the Heratio Mirador viewer
  via `HeratioAnnotationAdapter` (in `tools/mirador-build/src/index.js`).
  Drawing with MAE saves SVG selectors which are read back by Mirador's
  stock canvas overlay.
- **Any WAP-conformant client** (Hypothesis, Annotorious 3, custom code)
  can talk to the endpoint directly. Use the W3C envelope and standard
  Web Annotation JSON-LD shape.
- **Annotot-shaped clients** read the legacy `resources` array which
  remains alongside the W3C-spec `first.items` shape.

## Auth

Reads are public. Writes require an authenticated session. Anonymous
writes receive a JSON 401 (not a 302 redirect) so single-page clients can
surface a real error rather than a confusing HTML response.

`/api/annotations*` is exempt from Laravel CSRF: the session-auth gate
already blocks cross-site forgery.

## Compliance roadmap (issue #648)

- **Phase 1 (current)** - W3C Data Model + WAP REST conformance.
- Phase 2 - Threading (annotation replies).
- Phase 3 - Moderation queue (pending / approved / rejected).
- Phase 4 - Revisions + consolidation across legacy annotation surfaces.
