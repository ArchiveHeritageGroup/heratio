# IIIF Presentation 3.0 manifest endpoint (per record)

Heratio serves a IIIF Presentation API 3.0 Manifest for each published archival
record, so any IIIF viewer (Mirador, Universal Viewer) can open the record's
images and IIIF aggregators can harvest it. This is the PRESENTATION side; it is
distinct from the IIIF Image API (Cantaloupe) that serves the actual tiles.

## Endpoint

- `GET /iiif-presentation/{idOrSlug}/manifest.json` - `application/ld+json`,
  CORS-open. `{idOrSlug}` is the record slug or its numeric `information_object`
  id.
- Controller: `packages/ahg-api/src/Controllers/IiifPresentationController.php`
  (package `ahg-api`, named route `iiif-presentation.manifest`).
- This endpoint is SEPARATE from the existing `/iiif-manifest/{slug}` route in
  the locked `ahg-iiif-collection` package and from the locked Image API
  delegate (`ahg-core` `IiifController` / Cantaloupe `delegates.rb`). It touches
  neither.

## Resolution + publication gate (reused)

Resolution mirrors `EntityController::loadNode()` and `CitationController::resolve()`:

- join `information_object` -> `slug` -> `information_object_i18n` (active culture).
- published-only gate: `status.type_id = 158` AND `status_id = 160` (Published).
- synthetic root `information_object.id = 1` is excluded.
- a numeric `{idOrSlug}` is treated as the `information_object` id; otherwise a slug.
- any schema variance yields `null` (a clean 404), never a 500, never a draft leak.

## Cantaloupe IIIF Image API identifier (reused, not invented)

The image `service` id is built EXACTLY as the deployed viewer
(`ahg-iiif-viewer.js`) and the existing `IiifCollectionService` build it:

```
$id = str_replace('/', '_SL_', ltrim($digitalObject->path, '/')) . $digitalObject->name;
$serviceId = $publicBase . '/iiif/3/' . $id;
```

`_SL_` is the path separator the Cantaloupe delegate decodes back to `/`, with
hostname-based path resolution. The service block is `ImageService3` /
`profile: level2` under the `/iiif/3/` prefix.

## Public IIIF base (derived, never hardcoded)

The public origin for the manifest id, the homepage, and the image `service` id
all come from `url('/')` (the request host), the SAME origin the viewer uses
(`location.origin + '/iiif/3/'`). No private host is hardcoded, so a fresh
install on its own domain emits its own URLs.

## Manifest shape (IIIF Presentation 3.0)

```
Manifest
  @context = http://iiif.io/api/presentation/3/context.json
  label / summary / metadata / rights / requiredStatement / provider / homepage
  items[]  = Canvas (height, width)
    items[] = AnnotationPage
      items[] = Annotation (motivation: painting, target: canvas)
        body = Image (format image/jpeg, service[] = ImageService3 level2)
    thumbnail = Image (service[] = ImageService3)
```

- One Canvas per IMAGE master digital object (`digital_object.object_id = io.id`;
  derivatives carry `parent_id` instead, so masters are returned naturally).
  Only image MIME types / extensions are emitted (audio / video / PDF are skipped).
- Canvas dimensions come from the `property` / `property_i18n` sidecar
  (`name = width` / `height` keyed by the digital object id) when present, else
  sane defaults (1000x1000). Best-effort; absent dims never error.
- `requiredStatement` attributes the holding repository (authorised name from
  `repository` -> `actor_i18n`).
- `rights` is emitted only when reproduction-conditions is itself a recognised
  rights URI (Creative Commons / RightsStatements.org), per the IIIF spec.

## Edge cases

- A published record with NO images yields a valid Manifest with `items: []`
  (never a 500).
- An unknown / unpublished / root record yields a clean `404` JSON.

## Catch-all safety

The route is multi-segment and ends in the literal `/manifest.json`. The
single-segment `/{slug}` archival-record catch-all
(`ahg-information-object-manage`, constraint `[a-z0-9][a-z0-9-]*$` - one segment,
no slash) can never capture a multi-segment path, so a normal record slug still
resolves. The `{idOrSlug}` matcher permits multi-segment slugs, with
`/manifest.json` pinned as a literal suffix.

## Discovery

Enumerated as the `iiif-manifest` surface in
`ProtocolController::surfaces()` (`/open-data/protocol`), so an agent can
discover it from the one capabilities document.

## Constraints honoured

Read-only (SELECT only; no writes, no DDL, no new table). AHG / Plain Sailing /
AGPL header; `@copyright "Plain Sailing Information Systems"`. International /
jurisdiction-neutral. Open CORS.
