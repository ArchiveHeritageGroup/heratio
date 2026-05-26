# IIIF A/V + Content State + Authorization 2.0

Reference for the three Presentation API 3.0 family standards shipped in
Heratio v1.105.x (issues #695 + #696). Pairs with the existing
`iiif-content-search-2.md`, `iiif-scalebar-magnifier-pres3.md`, and
`iiif-workspace-persistence.md` references.

## Scope

| Sub-issue | Surface | Spec |
|---|---|---|
| #695 A/V canvases | `IiifCollectionService::generateObjectManifestV3` emits Sound + Video canvases | [Presentation 3.0 §3.6 temporal canvases](https://iiif.io/api/presentation/3.0/#54-canvas) |
| #695 Change Discovery | `GET /iiif/discovery/changes` (+ `?page=N`) | [Change Discovery 1.0](https://iiif.io/api/discovery/1.0/) |
| #696 Content State | `POST /iiif/content-state/encode`, `GET /iiif/content-state/decode` | [Content State 1.0](https://iiif.io/api/content-state/1.0/) |
| #696 Auth Flow 2.0 | `GET /iiif/auth/2/{probe,access,token}` | [Authorization Flow 2.0](https://iiif.io/api/auth/2.0/) |

## A/V canvas emission (#695)

Audio and video digital objects bypass Cantaloupe (image-only) and emit
spec-correct temporal canvases:

- Painting body `type` is `Sound` for audio mime / extension, `Video` for
  video.
- Body `format` carries the source mime type (e.g. `audio/mpeg`,
  `video/mp4`).
- Canvas + body both carry `duration` (seconds). Read from
  `digital_object_property.{duration|duration_seconds}` when present,
  fallback to `1.0` so the manifest still validates against the IIIF
  Presentation Validator.
- Video canvases also carry `width` + `height` (default 1920x1080).
  Operators populate via `digital_object_property.{width,height}` for
  accuracy.
- Each body has a `MediaFragmentSelector` service block
  (`conformsTo = http://www.w3.org/TR/media-frags/`) so viewers can
  request `#t=ss,ee` ranges.
- Poster frame: `digital_object_property.{poster_url|poster_frame}` is
  emitted as a `thumbnail` and a `placeholderCanvas`. Mirador 4 + UV 4
  read `placeholderCanvas` to render the still before play.

## Change Discovery (#695)

`GET /iiif/discovery/changes` returns an Activity Streams 2.0
`OrderedCollection` document with `first` + `last` page IRIs. `?page=N`
returns an `OrderedCollectionPage` with `orderedItems[]` activities and
`prev` / `next` link chain.

Each activity:

```json
{
  "id":   "https://heratio.example/iiif/discovery/changes/activity/123",
  "type": "Create",
  "object": { "id": "https://heratio.example/iiif-manifest/foo", "type": "Manifest" },
  "endTime": "2026-05-26 14:32:17"
}
```

Backed by `iiif_manifest_change` (`object_id`, `slug`, `manifest_uri`,
`change_type` in {Create, Update, Delete}, `actor`, `created_at`).
`IiifChangeDiscoveryService::recordChange()` is the entry point;
`IiifCollectionService::generateObjectManifest()` calls it on read-through
cache writes (Update). Operators trigger Create / Delete explicitly.

Page size: 100 (constant `IiifChangeDiscoveryService::PAGE_SIZE`).

## Content State (#696)

`POST /iiif/content-state/encode` with JSON body:

```json
{
  "manifest": "https://heratio.example/iiif-manifest/foo",
  "canvas":   "https://heratio.example/iiif-manifest/foo/canvas/3",
  "selector": { "xywh": "100,200,400,300" }
}
```

returns:

```json
{ "token": "<url-safe base64>", "annotation": { ... } }
```

`GET /iiif/content-state/decode?token=<token>` returns the annotation.

Encoding rules (per spec):

1. Serialise the Annotation envelope (`@context = Presentation 3 context`,
   `motivation = contentState`) to JSON.
2. Base64-encode with `+` -> `-`, `/` -> `_`.
3. Strip trailing `=` padding.

Decoder reverses all three. Malformed tokens return HTTP 400 with an
error envelope (never 500).

## Authorization Flow 2.0 (#696)

| Endpoint | Method | Purpose |
|---|---|---|
| `/iiif/auth/2/probe?resource=<iri>` | GET | ProbeService; 200 + AuthProbeResult2 if allowed, 401 + AccessService block if not. |
| `/iiif/auth/2/access` | GET | AccessService; redirects to Heratio login when anonymous, returns the close-window page on auth success. |
| `/iiif/auth/2/token` | GET | AccessTokenService; mints opaque token tied to session id, CORS-friendly. |

**Clearance integration.** Probe consults
`iiif_auth_resource.classification_id_required` (populated by the
`ahg-security-clearance` package). When set, the probe returns 401 unless
the logged-in user's clearance level meets or exceeds the required level.
The level is resolved via `SecurityClearanceService::getUserClearanceLevel()`.
Fail-closed semantics: if the clearance service throws, the probe denies
access rather than granting it.

**Token format.** Opaque - the spec doesn't constrain the format. We
hash `session_id|user_id` with SHA-256 and persist a row in
`iiif_auth_token` for audit. Revoking the session revokes every token
minted under it.

## Test coverage

`packages/ahg-iiif-collection/tests/`:

- `Unit/IiifContentStateServiceTest.php` - URL-safe base64 round-trip,
  selector defaults, garbage-input rejection.
- `Unit/IiifAuthFlow2ServiceTest.php` - probe response shape,
  AccessService profile validation, anonymous-with-clearance deny.
- `Unit/IiifAvCanvasEmissionTest.php` - Sound / Video painting body
  emission, duration / width / height assertions, MediaFragmentSelector
  service block, mime-detection coverage. Exercised via reflection so
  the test doesn't need an information_object fixture.
- `Feature/IiifChangeDiscoveryTest.php` - OrderedCollection +
  OrderedCollectionPage HTTP shape, seeded-activity round-trip,
  out-of-range 404.

Run: `php artisan test --filter=Iiif`.

## Routes summary

```
GET  /iiif/discovery/changes              IiifChangeDiscoveryController
GET  /iiif/discovery/changes?page=N       IiifChangeDiscoveryController
POST /iiif/content-state/encode           IiifContentStateController
GET  /iiif/content-state/decode           IiifContentStateController
GET  /iiif/auth/2/probe                   IiifAuthFlow2Controller
GET  /iiif/auth/2/access                  IiifAuthFlow2Controller
GET  /iiif/auth/2/token                   IiifAuthFlow2Controller
```

Nginx note: `/iiif/` is hard-routed to Cantaloupe on this host, but the
`^~ /iiif/discovery/`, `^~ /iiif/content-state/`, and `^~ /iiif/auth/2/`
prefixes are exempted to the Laravel app via explicit `location` blocks.

## Related issues

- #694 IIIF Content Search 2.0 (shipped v1.103.x).
- #695 A/V + Change Discovery (this doc, shipped v1.104.0).
- #696 Content State + Auth 2.0 (this doc, shipped v1.104.0).
- #697 NER bridge into Content Search annotation pipeline (parallel work).
- #698 Presentation 2 / 3 dual-shape manifests (shipped v1.103.x).
- #699 Mirador workspace persistence (shipped v1.103.x).
