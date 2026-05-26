# NER -> IIIF Annotation Bridge

**Status:** Production surface complete. Bridge job shipped in v1.104.0; tests + docs landed in v1.104.3; provenance columns, manifest §3.3 `annotations` wiring, the canvas-annotations read endpoint, the `/api/iiif/annotations/from-ner` ingestion endpoint, and the intra-run dedup guard land in the #697 finishing pass. Issue [#697](https://github.com/ArchiveHeritageGroup/heratio/issues/697).

## What the bridge does

For an information_object that has OCR text (from `iiif_ocr_text` + per-word `iiif_ocr_block` rows), the bridge runs the AHG AI NER service over the full text, then emits one W3C Web Annotation per (entity, matching word block) pair into the `ahg_iiif_annotation` table. The resulting annotations show up automatically in Mirador's annotation companion window because Mirador dereferences the same `/api/annotations/search?targetId=<canvas>` endpoint that the bridge writes into.

End-to-end loop:

```
iiif_ocr_text.full_text
   |
   v
NerService::extract()  -- buckets: persons / organizations / places / dates
   |
   v
BuildNerAnnotationsForCanvas::handle()
   |
   |--- match entity first-word against iiif_ocr_block.text (case-insensitive)
   |--- skip blocks below MIN_BLOCK_CONFIDENCE (30.0)
   |--- cap at MAX_PER_TYPE_PER_CANVAS (100) per (canvas, type) pair
   |
   v
ahg_iiif_annotation row
   body_json = W3C Web Annotation document with FragmentSelector xywh
   target_iri = canvas IRI (https://host/iiif-manifest/<slug>/canvas/<n>)
   _heratio.source = "ner"
   _heratio.run_id = uuid of this job run
   |
   v
GET /api/annotations/search?targetId=<canvas IRI>
   |
   v
Mirador annotation companion window
```

## On-disk surface

| Surface | File | Notes |
|---|---|---|
| Job | `packages/ahg-iiif-collection/src/Jobs/BuildNerAnnotationsForCanvas.php` | The bridge. Queued via `ShouldQueue`. `persistAnnotation()` is now public so HTTP ingestion can reuse it. |
| Persistence | `ahg_iiif_annotation` (existing table from #100) | Provenance is mirrored from `body_json['_heratio']` into denormalised columns `ner_entity_type`, `ner_confidence`, `ner_run_id` (added in the #697 finishing pass; backfill via `php artisan ahg:annotations:backfill-ner-columns`). |
| Read surface (Mirador / Annotot) | `GET /api/annotations/search?targetId=<canvas IRI>` (from `ahg-annotations`) | Mirador-compatible (Annotot-shaped) container. |
| Read surface (IIIF Pres 3 strict) | `GET /iiif-manifest/{slug}/canvas/{n}/annotations` | Canvas-scoped W3C AnnotationPage. Linked from each canvas via Pres 3 §3.3 `annotations[]`. Honours `odrl:use` so private records gate access. |
| Ingestion surface | `POST /api/iiif/annotations/from-ner` | API-key auth (`api.auth`: X-API-Key / Authorization: Bearer / session). Body shape below. |
| Manifest wiring | `IiifCollectionService::buildSingleCanvasV3()` + `buildAvCanvasV3()` | Attaches `annotations:[{ id, type:'AnnotationPage' }]` to each canvas iff at least one annotation row exists for that canvas IRI. |

## Annotation document shape

Example annotation document persisted into `body_json`:

```json
{
  "@context": "http://www.w3.org/ns/anno.jsonld",
  "id": "https://heratio.example.org/api/annotations/<uuid>",
  "type": "Annotation",
  "motivation": "tagging",
  "body": [
    { "type": "TextualBody", "value": "Nelson Mandela",
      "language": "en", "format": "text/plain", "purpose": "tagging" },
    { "type": "SpecificResource",
      "source": "http://www.wikidata.org/entity/Q8023",
      "purpose": "identifying" },
    { "type": "TextualBody", "value": "Person",
      "format": "text/plain", "purpose": "classifying" }
  ],
  "target": {
    "source": "https://heratio.example.org/iiif-manifest/<slug>/canvas/1",
    "selector": {
      "type": "FragmentSelector",
      "conformsTo": "http://www.w3.org/TR/media-frags/",
      "value": "xywh=50,60,120,28"
    }
  },
  "_heratio": {
    "source": "ner",
    "run_id": "<run-uuid>",
    "entity_index": 0,
    "entity_type": "Person"
  }
}
```

Conformance notes:

- **W3C Web Annotation Data Model** - `@context`, `type`, `motivation`, `body`, `target` follow the spec. `motivation: tagging` is the canonical motivation for NER-style entity tags.
- **IIIF Presentation 3.0** - `target` is a SpecificResource with a FragmentSelector xywh against a Canvas. This is the IIIF-recommended target shape for region-pinned annotations.
- **SpecificResource bodies** - when NerService resolves an entity to a stable URI (Wikidata QID, GeoNames, AAT), we emit it as a `SpecificResource` body with `purpose: identifying`. The plain-text TextualBody stays for human-readable display.
- **Provenance** - `_heratio.source`, `_heratio.run_id`, `_heratio.entity_index`, `_heratio.entity_type` are namespaced under `_heratio` so they round-trip with the annotation but do not interfere with the W3C envelope. Admin tooling can delete every annotation produced by a given run by querying `JSON_EXTRACT(body_json, '$._heratio.run_id')`.

## Behavioural pins

The unit tests in `tests/Unit/Iiif/NerAnnotationBridgeTest.php` pin these:

| Pin | Behaviour |
|---|---|
| Block confidence floor | `MIN_BLOCK_CONFIDENCE = 30.0`. Blocks below this are skipped. `null` confidence is treated as "unknown, keep". |
| Per-type cap | `MAX_PER_TYPE_PER_CANVAS = 100`. Stops runaway emissions on watermarked / repetitive OCR. |
| First-word prefix match | Multi-word entities ("Cape Town") match per-word OCR blocks ("Cape") via case-insensitive first-word prefix. Body label is the FULL entity string. |
| Body classifier | Each annotation carries a `purpose: classifying` TextualBody with the entity type (`Person` / `Organization` / `Place` / `Date`). |
| Provenance | `_heratio.source = 'ner'`, `_heratio.run_id` = uuid per job dispatch. |

## Shipped (issue #697 finishing pass)

All four follow-ups originally documented as gaps now land in the production code:

| Gap | Resolution |
|---|---|
| Intra-run dedup | `BuildNerAnnotationsForCanvas::persistAnnotation()` keys an in-memory set on `sha1("{canvas}|{x}|{y}|{w}|{h}|{value}")`. Second identical emission returns `false` and the row is not inserted. Cross-run dedup remains admin-driven via the embedded `run_id` / new `ner_run_id` column. |
| Pres 3 §3.3 manifest wiring | `IiifCollectionService::buildSingleCanvasV3()` and `buildAvCanvasV3()` attach `annotations:[{id, type:'AnnotationPage'}]` per canvas iff `ahg_iiif_annotation` has at least one row pinned to the canvas IRI. Empty arrays are not emitted (strict-validator clean). |
| HTTP ingestion endpoint | `POST /api/iiif/annotations/from-ner` (in `ahg-iiif-collection`). API-key or bearer or session auth via the `api.auth` middleware. Body shape below. |
| Denormalised provenance | `ner_entity_type`, `ner_confidence`, `ner_run_id` columns on `ahg_iiif_annotation` (added by the service-provider Schema-builder backfill so older installs migrate on first boot). `body_json['_heratio'].*` is preserved verbatim. Backfill existing rows with `php artisan ahg:annotations:backfill-ner-columns [--dry-run] [--force]`. |

## POST /api/iiif/annotations/from-ner

Request body:

```json
{
  "canvas_id": "https://heratio.example.org/iiif-manifest/<slug>/canvas/3",
  "run_id":    "uuid-or-arbitrary-stable-string",
  "model":     "ahg-ner-v1",
  "model_version": "2026.05",
  "language": "en",
  "entities": [
    {
      "text":  "Nelson Mandela",
      "type":  "Person",
      "confidence": 0.94,
      "start": 102,
      "end":   116,
      "bbox":  { "x": 50, "y": 60, "w": 120, "h": 28 },
      "uri":   "http://www.wikidata.org/entity/Q8023"
    }
  ]
}
```

Response (201 Created):

```json
{
  "success": true,
  "run_id": "<echoed>",
  "canvas_id": "<echoed>",
  "inserted": 1,
  "deduped": 0,
  "skipped": 0,
  "model": "ahg-ner-v1",
  "model_version": "2026.05"
}
```

- Auth: `X-API-Key`, `Authorization: Bearer <token>`, or a logged-in session.
- `entities[].bbox` is required - the bridge's FragmentSelector pins to xywh and refuses entities without a box (counted under `skipped`).
- `confidence` is clamped to `[0, 1]`. `null` and missing values are accepted.
- Duplicate `(canvas, xywh, text)` inside one request body is deduped to a single row and counted under `deduped`.

## GET /iiif-manifest/{slug}/canvas/{n}/annotations

Returns a W3C AnnotationPage of every NER-tagged row pinned to that canvas. Honours ODRL via the `odrl:use` middleware so non-public records reject anonymous reads.

## Operator workflow

1. Make sure NER is enabled in AI Services settings (`ner_enabled = '1'` in `ahg_settings`).
2. Make sure the target information_object has OCR text - run the OCR pipeline (`OcrService`) first if it doesn't.
3. Dispatch the bridge job: `BuildNerAnnotationsForCanvas::dispatch($ioId)` (or `dispatchSync($ioId)` to run it inline).
4. Open the IO show page or hit `https://host/viewer.html?manifest=<manifest-url>` - Mirador's annotations companion window will list the new entities. The companion-window expands them into pinned overlays on the canvas.

## Related issues / docs

- #100 - W3C Web Annotation persistence backend (the table this bridge writes into).
- #648 - W3C Web Annotation Protocol compliance (headers, ETag, container shape).
- #694 - IIIF Content Search 2.0 (searches the same `ahg_iiif_annotation` rows on demand).
- `docs/help/ner-annotations-on-iiif.md` - user-facing /help page.
