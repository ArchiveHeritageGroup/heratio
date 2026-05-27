# IIIF Presentation 3.0 manifest IPTC/EXIF/XMP enrichment

**Status**: Live as of issue #748 (Heratio v1.89+).

Heratio's IIIF Presentation 3.0 manifest emitter
(`AhgIiifCollection\Services\IiifCollectionService::generateObjectManifestV3`)
augments the top-level manifest body with values extracted from the
file-level IPTC sidecar (`dam_iptc_metadata`) and the EXIF blob stored in
`digital_object_metadata.raw_metadata`. The legacy v2 emitter does NOT
enrich today; the same helper (`IiifMetadataEnricher::fromIptc`) is
available for the v2 path when its operator wants the same surface.

## What gets added

For each information object served at `/iiif-manifest/{slug}`:

| Manifest field | Source column | Precedence rule |
|---|---|---|
| `metadata[].label="Creator"` | `dam_iptc_metadata.creator` (IPTC 2:80 By-line) | Always added when present. |
| `metadata[].label="Keywords"` | `dam_iptc_metadata.keywords` | Always added when present; split on `,`, `;`, `\|`, newline; JSON arrays parsed. |
| `metadata[].label="Date of capture"` | `digital_object_metadata.raw_metadata.DateTimeOriginal` (EXIF) | Only when the IO has no ISAD `dateCreated`. |
| `requiredStatement` | `dam_iptc_metadata.copyright_notice` (IPTC 2:116) | Only when the IO has no `information_object_i18n.reproduction_conditions`. ISAD always wins. |
| `provider[]` (second agent) | `dam_iptc_metadata.creator` | Added as a secondary `Agent` so the IPTC creator is surfaced without displacing the publishing institution. |

## Precedence summary

1. ISAD `reproduction_conditions` beats IPTC `copyright_notice` for the `requiredStatement`.
2. The default Heratio `provider` (organisation) is always first; the IPTC byline is appended as a secondary `Agent`, never substituted.
3. The IPTC `creator` row is additive to any ISAD-level author the description carries - both surface for downstream viewers.
4. EXIF `DateTimeOriginal` only fills in when no ISAD `dateCreated` exists (Heratio has no ISAD date column today, so EXIF currently fills whenever present).

## Failure mode

The whole enrichment pass is wrapped in a `try / catch (\Throwable)`. If
either sidecar is absent, malformed, JSON-broken, or the column-schema
drifts, the warning is logged and manifest serving continues without the
extra rows. The IIIF manifest must never 500 because of IPTC parsing.

## Helper class

`AhgIiifCollection\Services\IiifMetadataEnricher` is framework-free and
side-effect free. It exposes:

```php
IiifMetadataEnricher::fromIptc(array $iptc): array
IiifMetadataEnricher::buildRequiredStatement(array $iptc, ?string $ioRightsStatement): ?array
IiifMetadataEnricher::fromExifDateTimeOriginal(?array $exif, bool $ioHasDateCreated): ?array
IiifMetadataEnricher::bylineFromIptc(array $iptc): ?string
```

Pure transforms - the caller is responsible for the DB lookups and merging
into the manifest array.

## Verification

```bash
curl -s https://your-heratio/iiif-manifest/<slug> | jq '
  {
    creator: (.metadata[] | select(.label.en[0]=="Creator") | .value.en[0]),
    keywords: (.metadata[] | select(.label.en[0]=="Keywords") | .value.en),
    rights: .requiredStatement.value.en[0],
    providers: [.provider[].label.en[0]]
  }'
```

If the JSON probe is empty, check:

1. `SELECT object_id, creator, copyright_notice, keywords FROM dam_iptc_metadata WHERE object_id = <io_id>;`
2. `SELECT raw_metadata FROM digital_object_metadata WHERE digital_object_id = <first_do_id>;`
3. `tail -50 storage/logs/laravel-$(date +%F).log | grep "IPTC/EXIF enrichment"` for the best-effort warning.

## Related

- Issue #748: this enrichment
- Issue #697: annotations[] block on canvases (untouched by #748)
- Issue #695: A/V canvas emission
- Issue #738: Content Search 2.0 service block on manifest
