---
title: REST API - Embedded EXIF / IPTC / XMP
slug: api-embedded-metadata
category: Integration
---

# REST API - Embedded EXIF / IPTC / XMP

Heratio extracts the EXIF, IPTC, and XMP metadata that photographers and scanners embed in every image / PDF / video file. This guide explains how to read that data over the REST API.

## The short version

Add `?include=embedded_metadata` to either:

- `GET /api/v1/digital-object/{id}` (anonymous, rate-limited)
- `GET /api/v2/descriptions/{slug}` (bearer token required)

or call the standalone endpoint:

- `GET /api/v2/digital-object/{id}/embedded-metadata`

The response gains an `embedded_metadata` block with three sub-keys: `exif`, `iptc`, and `xmp`.

## What you get back

```json
{
  "embedded_metadata": {
    "exif": {
      "Make": "Canon",
      "Model": "EOS R5",
      "DateTimeOriginal": "2024-01-15 14:32:18",
      "GPSLatitude": "-25.7479",
      "GPSLongitude": "28.2293"
    },
    "iptc": {
      "By-line": "Aslam Levy",
      "Headline": "Voortrekker Monument east elevation",
      "CopyrightNotice": "(c) 2024 Plain Sailing Information Systems",
      "Keywords": "monument, heritage, granite"
    },
    "xmp": {
      "dc:creator": "Aslam Levy",
      "dc:rights": "All rights reserved"
    }
  }
}
```

Only fields that actually have a value appear - empty fields are dropped, not nulled. Tag names follow the ExifTool / Adobe XMP camel-case naming so you can map them straight into a third-party EXIF / IPTC library.

## Default-off opt-in

The block is **not** part of the default response shape. Existing clients that hit `/api/v1/digital-object/{id}` or `/api/v2/descriptions/{slug}` see the same JSON they always have. You only get `embedded_metadata` when you explicitly opt in with `?include=embedded_metadata` (or the hyphenated alias `?include=embedded-metadata`).

This keeps catalogue browse traffic lean - the embedded block is multi-kilobyte for image-heavy descriptions.

The standalone endpoint at `/api/v2/digital-object/{id}/embedded-metadata` is always-on - the block IS the response there.

## Worked examples

Inline include over v2 (recommended for description detail views):

```bash
curl -H "Authorization: Bearer ahg_live_XXX" \
  "https://heratio.example.org/api/v2/descriptions/voortrekker-monument?include=embedded_metadata"
```

Standalone endpoint (lazy-load from a description page that already loaded the rest):

```bash
curl -H "Authorization: Bearer ahg_live_XXX" \
  "https://heratio.example.org/api/v2/digital-object/12345/embedded-metadata"
```

v1 anonymous (the hyphenated alias works on every path):

```bash
curl "https://heratio.example.org/api/v1/digital-object/12345?include=embedded_metadata"
```

## Rights protection (ODRL)

When the parent archival description has an active ODRL `odrl:use` prohibition for the calling user / API key, the block is suppressed:

- **Inline include** (`/api/v1/digital-object`, `/api/v2/descriptions`): the `embedded_metadata` key is silently dropped from the response. You still get the base description because that level of access is separately governed.
- **Standalone endpoint**: returns `403 Forbidden` with an explicit error message - because the block IS the resource, silent suppression would be misleading.

If your client receives the description but no `embedded_metadata` key after you set the include flag, this is the most likely cause. Check the description's policies in **Admin > Research > Rights Policies**.

## Privacy protection (PII)

Embedded EXIF GPS coordinates are personal information under GDPR, POPIA, CCPA, and CDPA - the photograph's capture location often identifies a sitter, archaeological site, or witness location. Heratio's privacy scanner ([Embedded PII findings](https://heratio.example.org/admin/privacy/embedded-findings)) flags every digital object whose sidecar tables hold GPS coordinates.

When a digital object has a pending or escalated GPS finding, every GPS field in the API response is replaced with `null` and a sibling `_pii_redacted: true` flag is added:

```json
{
  "embedded_metadata": {
    "exif": {
      "Make": "Apple",
      "GPSLatitude": null,
      "GPSLongitude": null,
      "_pii_redacted": true
    }
  }
}
```

Non-GPS fields (creator, headline, camera model) pass through untouched. Once a privacy officer marks the finding as `cleared` (not PII in this context) or `redacted` (purged from the source), the gate clears and GPS values are returned again. Re-scanning the file via the Embedded PII Backfill job creates a fresh finding if new GPS coordinates appear.

## Auth and rate limiting

| Endpoint | Auth | Rate limit |
| --- | --- | --- |
| `/api/v1/digital-object/{id}` | None (anonymous) | 60 req / min / IP |
| `/api/v2/descriptions/{slug}` | Bearer token OR session | Per-key from API key profile |
| `/api/v2/digital-object/{id}/embedded-metadata` | Bearer token OR session | Per-key from API key profile |

The bearer token must have the `read` scope. Browse-quality API keys created in **Admin > API > API Keys** ship with `read` by default.

## Common questions

**Q. Why is `embedded_metadata` returned as `{}` even though my image has EXIF?**

The sidecar tables (`digital_object_metadata`, `dam_iptc_metadata`, `media_metadata`) are populated by the metadata-extraction pipeline. Newly uploaded files take a few minutes for the queue worker to extract. If `embedded_metadata` stays `{}` after that, check **Admin > Settings > Extraction Pipeline** for the job log.

**Q. Why are GPS fields `null` even though the image has them?**

Either the PII gate is active (look for `_pii_redacted: true` in the sub-block) or the extraction didn't capture them. The PII gate clears as soon as the finding moves out of `pending` / `escalated` state.

**Q. Can I edit metadata via this API?**

Not yet - this endpoint is read-only. Write support is tracked under a separate issue.

**Q. Where do I see the underlying SQL columns each tag maps to?**

See the internal reference at `docs/reference/api-embedded-metadata.md`.
