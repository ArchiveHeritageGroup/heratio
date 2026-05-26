# NER -> IIIF Annotation Bridge

**Status:** Shipped in v1.104.0 as a queued job (no standalone service class). Tests + docs landing in v1.104.x. Issue [#697](https://github.com/ArchiveHeritageGroup/heratio/issues/697).

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
| Job | `packages/ahg-iiif-collection/src/Jobs/BuildNerAnnotationsForCanvas.php` | The bridge. Queued via `ShouldQueue`. |
| Persistence | `ahg_iiif_annotation` (existing table from #100) | No new columns. Provenance lives inside `body_json['_heratio']`. |
| Read surface | `GET /api/annotations/search?targetId=<canvas IRI>` (from `ahg-annotations`) | Mirador-compatible (Annotot-shaped) container. |
| Write surface | The job itself, via `DB::table('ahg_iiif_annotation')->insert(...)`. There is no HTTP ingestion endpoint - dispatch the job from PHP. |

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

## Known gaps (issue #697 follow-ups)

The current bridge is **append-only within a single run**. Two NER entities that resolve to the same word block at the same xywh box will produce two annotation rows. Cross-run dedup is handled separately - admins can delete every row from a previous run via the embedded `run_id`. Intra-run dedup needs a `(target_iri, x, y, w, h, body_value)` guard inside `persistAnnotation()`; the unit test for it is in place but marked `skipped`.

The bridge does NOT yet wire the canvas-level `annotations` list into the served IIIF manifest (Pres 3.0 §3.3). Mirador picks the annotations up via its companion window today because the companion window calls `/api/annotations/search?targetId=<canvas>` directly, but a strict IIIF client that only reads `canvas.annotations[]` will miss them until the manifest is updated. Tracked separately.

There is no dedicated HTTP ingestion endpoint (`POST /api/iiif/annotations/from-ner`). Dispatch the job from PHP - `BuildNerAnnotationsForCanvas::dispatch($ioId, $digitalObjectId)` - or from the artisan command that issue #697 also ships.

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
