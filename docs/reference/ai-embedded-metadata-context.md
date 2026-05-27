# AI services consume embedded EXIF / IPTC / XMP as context (issue #750)

Reference for the `EmbeddedMetadataContextService` surface that grounds NER, HTR, Donut, and raw-LLM prompts in the real-world coordinates already embedded in every digital object - dates from EXIF DateTimeOriginal, places from GPS, creator from IPTC By-line, subjects from IPTC Keywords / XMP dc:subject.

The EXIF audit found zero references to embedded image metadata in any AI service. NER was hallucinating dates and places that EXIF + GPS would have grounded for free. This change closes that gap: each AI service now accepts an optional `digitalObjectId` parameter and, when supplied, fetches the hints once per request and prepends them to the LLM prompt as a structured disambiguation prefix.

## What gets injected

`EmbeddedMetadataContextService::forDigitalObject($id)` returns an `AiContextHints` DTO with four fields:

| Field         | Source priority                                                                                                |
| ------------- | -------------------------------------------------------------------------------------------------------------- |
| `dateHint`    | EXIF DateTimeOriginal -> EXIF DateTime -> IPTC date_created -> XMP date_time_original / create_date            |
| `placeHint`   | reverse-geocoded `gps:place` -> `gps:decimal` ("lat,lon") -> `gps:latitude` + `gps:longitude`                  |
| `creatorHint` | IPTC By-line -> XMP dc:creator -> EXIF Artist                                                                  |
| `subjectHints`| IPTC Keywords -> XMP dc:subject (sequential arrays from `flattenMetadata` are JSON-decoded and deduplicated)   |

The source rows live in the `property` table with `scope='metadata_extraction'`, written by `ahg-metadata-extraction`. No new tables are introduced by #750.

## Prompt prefix shape

The DTO renders to a fixed-order line:

```
Hints from image metadata: date=1969-07-20 20:17:40; location=28.0473,-26.2041; creator=Neil Armstrong; subjects=Apollo, Moon, NASA. Use these to disambiguate entities.
```

Order is fixed (date, location, creator, subjects) so receipts are deterministic over the same input regardless of how the consumer constructed the DTO. Empty fields are dropped entirely - the prefix is never padded with placeholder text.

## How each service wires it in

| Service        | Entry point                                                              | Injection                                                                                                                                                                                                                                       |
| -------------- | ------------------------------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `NerService`   | `extract(string $text, ?int $digitalObjectId = null)`                    | Hint prefix is prepended to the input text before the API call and before the LLM fallback. Both `extractAndRecord(...)` (which has both IO and DO ids) and direct `extract()` callers can pass the DO id through.                              |
| `HtrService`   | `extract(string $filePath, string $docType, string $format, ?int $do)`   | Hint prefix is forwarded to the upstream HTR service as a `context_hints` form-data field. HTR adapters that ignore the field cost nothing; future LLM-corrected HTR variants can consume it directly.                                          |
| `DonutService` | `extract(string $filePath, ?int $digitalObjectId = null)`                | Same `context_hints` form-data forwarding pattern.                                                                                                                                                                                              |
| `LlmService`   | `complete(string $prompt, array $options, ?int $digitalObjectId = null)` | Hint prefix is prepended to the prompt before the quota gate, before the cloud-mode override, and before the local-provider dispatch - every dispatch path sees the same grounded prompt.                                                       |

## Caching

The service is a Laravel singleton (registered in `AhgAiServicesServiceProvider::register`). Hints for a given digital_object id are computed at most once per request: a second NER call followed by an LLM call on the same DO id will both hit the in-memory cache, not the database.

## Privacy gate (issue #751)

GPS hints are gated through the `ahg_pii_finding_embedded` table from issue #751. When a finding exists for the digital_object with `pii_type='gps_coordinate'` and `resolution_status` is `pending` or `escalated`, the GPS hint is suppressed entirely and a human-readable reason (`GPS suppressed by PII finding #42`) is added to `AiContextHints::suppressedReasons`.

Defensive: when the `ahg_pii_finding_embedded` table is absent (issue #751 not yet shipped), GPS proceeds without the gate but a `Log::warning` is emitted so operators notice. The policy is "fail open, leave an audit trail" rather than "fail closed and silently drop the feature".

## Audit hook

Every successful inference that consumed hints emits an `inference_context_used` event to the tamper-evident inference receipt chain (issue #693 / `ahg-ai-compliance/InferenceLogger`). The payload is `{digital_object_id, hints: {date, place, creator, subjects, suppressed_reasons}}`.

Operators can audit, per-call, exactly what context the model was given:

```sql
SELECT * FROM ahg_inference_receipt
WHERE service = 'inference_context_used'
  AND JSON_EXTRACT(payload, '$.digital_object_id') = 12345
ORDER BY id DESC;
```

## What this does not do

- It does not write to or modify `digital_object` / `property` / any other AtoM-owned tables.
- It does not invoke a geocoder. `gps:place` is read when written by another agent; bare coordinates are emitted as "lat,lon" otherwise.
- It does not retry or re-extract metadata - it consumes whatever `MetadataExtractionService::extractFromDigitalObject` already persisted.
- It does not bundle any model. Hint injection is a prompt augmentation, consistent with the project rule that AI is remote-only.

## Files

- DTO: `packages/ahg-ai-services/src/DTO/AiContextHints.php`
- Service: `packages/ahg-ai-services/src/Services/EmbeddedMetadataContextService.php`
- Provider wiring: `packages/ahg-ai-services/src/Providers/AhgAiServicesServiceProvider.php`
- Service edits: NerService.php, HtrService.php, DonutService.php, LlmService.php
- Tests: `packages/ahg-ai-services/tests/Unit/AiContextHintsTest.php`, `EmbeddedMetadataContextServiceTest.php`
