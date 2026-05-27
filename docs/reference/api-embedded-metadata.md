# Embedded EXIF / IPTC / XMP over the REST API (issue #747)

Reference for the REST surface that exposes embedded image metadata to integrators. Before #747 the `digital_object_metadata`, `dam_iptc_metadata`, and `media_metadata` sidecar tables were populated by extraction pipelines but never reached the v1 or v2 read API. This change wires them into three endpoints with a single, audit-friendly response shape.

## Endpoints

| Verb | Path | Auth | Behaviour |
| --- | --- | --- | --- |
| GET | `/api/v1/digital-object/{id}` | none | Description of a digital object. Adds `embedded_metadata` when `?include=embedded_metadata` is set. |
| GET | `/api/v2/descriptions/{slug}` | bearer / session | Information-object detail. Adds `embedded_metadata` when `?include=embedded_metadata` is set. |
| GET | `/api/v2/digital-object/{id}/embedded-metadata` | bearer / session | Standalone endpoint - returns ONLY the embedded block. |

The hyphenated `embedded-metadata` alias is accepted on all three paths to match JSON:API conventions.

## Response shape

When `embedded_metadata` is present the block looks like this:

```json
{
  "embedded_metadata": {
    "exif": {
      "Make": "Canon",
      "Model": "EOS R5",
      "DateTimeOriginal": "2024-01-15 14:32:18",
      "GPSLatitude": "-25.7479",
      "GPSLongitude": "28.2293",
      "Artist": "Test Photographer"
    },
    "iptc": {
      "By-line": "Test Photographer",
      "Headline": "Voortrekker Monument east elevation",
      "CopyrightNotice": "(c) 2024 Plain Sailing Information Systems",
      "Keywords": "monument, heritage, granite"
    },
    "xmp": {
      "dc:creator": "Test Photographer",
      "dc:rights": "All rights reserved",
      "xmp:CreateDate": "2024-01-15T14:32:18+02:00"
    }
  }
}
```

Sub-blocks only contain keys that actually have a value in the sidecar tables. Empty sub-blocks render as `{}`. When every sub-block is empty the entire `embedded_metadata` value is `{}` (rather than `null`), so client code can rely on the key existing whenever the include flag is honoured.

## Source-of-truth mapping

| API key (per sub-block) | DB column |
| --- | --- |
| `exif.Make`, `exif.Model` | `digital_object_metadata.camera_make` / `camera_model` |
| `exif.DateTimeOriginal` | `digital_object_metadata.date_created` |
| `exif.GPSLatitude`, `GPSLongitude`, `GPSAltitude` | `digital_object_metadata.gps_*` (overridden by `media_metadata.gps_coordinates` when only ffprobe has the hit) |
| `exif.VideoCodec`, `AudioCodec`, `Bitrate`, `SampleRate` | `digital_object_metadata.*` then `media_metadata.*` (extraction sidecar wins) |
| `iptc.By-line`, `Headline`, `Caption-Abstract`, `Keywords`, `CopyrightNotice`, `RightsUsageTerms` | `dam_iptc_metadata.*` (keyed on `object_id`, the parent information_object id) |
| `xmp.dc:*`, `xmp.xmp:*`, `xmp.Iptc4xmpCore:*` | `digital_object_metadata.raw_metadata` then `media_metadata.consolidated_metadata` (first match wins) |

Tag names use the ExifTool / Adobe XMP camel-case conventions so an integrator can copy keys directly into a third-party library.

## Default-off behaviour

The block is opt-in. A v1 or v2 call without `?include=embedded_metadata` returns the legacy response shape unchanged. This keeps the existing public catalogue browse traffic lean - the embedded block is multi-kilobyte for image-heavy descriptions and the catalogue listing has no use for it.

The standalone endpoint at `/api/v2/digital-object/{id}/embedded-metadata` is always-on by design - the block IS the response there.

## ODRL gate (rights enforcement)

Before any sidecar data is queried the service consults the parent information object's ODRL policies. When an active `odrl:use` prohibition denies the caller:

- v1 and v2 inline include: the `embedded_metadata` key is silently dropped from the response (the base description remains readable).
- v2 standalone endpoint: returns `403 Forbidden` with `error: "Forbidden"`, `message: "Embedded metadata access denied by ODRL policy."` - the block IS the resource, so silent suppression would be misleading.

The gate is fail-open when the research package is not installed (no `OdrlService` class). Any throwable inside the policy check is also fail-open with a logged warning - a misconfigured `research_rights_policy` row must never break the read API.

## PII redaction gate (issue #751)

Embedded metadata commonly leaks GPS coordinates that pinpoint a sitter, archaeological site, or witness location. The privacy package's PII scanner (issue #751) tracks pending findings in `ahg_pii_finding_embedded`. When a `gps_coordinate` finding for the digital object has `resolution_status IN ('pending', 'escalated')`:

- Every GPS-flavoured key (`GPSLatitude`, `GPSLongitude`, `GPSAltitude`, `GPSCoordinates`, `GPSPosition`, `GPSTimeStamp`, `GPSDateStamp`, and any other prefix-matching key) is replaced with `null`.
- A sibling `_pii_redacted: true` flag is added to the sub-block so the client can tell suppression from absence.
- Non-GPS fields (creator, headline, camera model) pass through untouched.

```json
{
  "embedded_metadata": {
    "exif": {
      "Make": "Apple",
      "Model": "iPhone 15 Pro",
      "GPSLatitude": null,
      "GPSLongitude": null,
      "GPSAltitude": null,
      "_pii_redacted": true
    }
  }
}
```

The gate is defensive: if `ahg_pii_finding_embedded` is absent (privacy package not yet installed on this host) the service logs one warning per call and proceeds without redaction. Cleared / redacted findings (status `cleared` or `redacted`) do not trigger suppression - they exist precisely because the operator already reviewed them.

## Auth scopes

The v1 read path is anonymous (rate-limited via `throttle:60,1`). The v2 read paths require `api.auth:read`, which both session-authenticated admins and bearer-token API keys satisfy.

## Examples

Inline include on v2 description show:

```bash
curl -H "Authorization: Bearer ahg_live_XXX" \
  "https://heratio.example.org/api/v2/descriptions/voortrekker-monument?include=embedded_metadata"
```

Standalone endpoint:

```bash
curl -H "Authorization: Bearer ahg_live_XXX" \
  "https://heratio.example.org/api/v2/digital-object/12345/embedded-metadata"
```

v1 hyphenated alias:

```bash
curl "https://heratio.example.org/api/v1/digital-object/12345?include=embedded_metadata"
```

## Cross-references

- Service implementation: `packages/ahg-api/src/Services/EmbeddedMetadataService.php`
- v1 controller: `packages/ahg-api/src/Controllers/V1/DigitalObjectApiController.php`
- v2 inline controller: `packages/ahg-api/src/Controllers/V2/DescriptionController.php`
- v2 standalone controller: `packages/ahg-api/src/Controllers/V2/DigitalObjectController.php`
- Sibling AI consumer (same sidecar tables): `docs/reference/ai-embedded-metadata-context.md`
- PII gate table (issue #751): `packages/ahg-privacy/database/install-phase2.sql`
