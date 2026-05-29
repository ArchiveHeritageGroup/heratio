# IIIF Manifest Enrichment Parity (issue #1101)

**Summary:** Heratio's IIIF Presentation 3.0 manifest enrichment now matches the PSIS/AtoM side (atom-ahg-plugins v3.46.2). The manifest gains four additional `metadata` rows when the underlying EXIF/IPTC data is present: **Camera**, **GPS Coordinates**, **Location**, and a standalone **Copyright** row. All additions are null-safe and additive - no existing behaviour was removed or changed.

## Where it lives

- Pure transformer: `packages/ahg-iiif-collection/src/Services/IiifMetadataEnricher.php` (framework-free, side-effect free, unit-tested).
- Wiring: `IiifCollectionService::applyIptcExifEnrichment()` in `packages/ahg-iiif-collection/src/Services/IiifCollectionService.php`.
- Data sources: `dam_iptc_metadata` (one row per IO) and `digital_object_metadata.raw_metadata` (EXIF/XMP JSON blob for the first digital object).
- Tests: `packages/ahg-iiif-collection/tests/Unit/IiifMetadataEnricherTest.php`.

## New manifest metadata fields

Each field follows the IIIF Presentation 3.0 language-map shape:
`{ "label": {"en": ["..."]}, "value": {"en": ["..."]} }`.

| Field | Label | Value format | Source |
|---|---|---|---|
| Camera | `Camera` | `Make Model` (space-joined, either part optional) | EXIF `Make` + `Model` from `raw_metadata` |
| GPS Coordinates | `GPS Coordinates` | `lat, long` decimal, six places (`%.6f, %.6f`) | EXIF GPS from `raw_metadata` |
| Location | `Location` | `City, State, Country` (missing parts skipped) | IPTC sidecar, falls back to consolidated EXIF/XMP block |
| Copyright | `Copyright` | verbatim copyright notice | IPTC `copyright_notice` |

## Field mapping detail

### Camera - `fromCamera(?array $exif)`
Reads EXIF `Make` and `Model`. Probes four raw_metadata shapes per tag: top-level (`Make`), nested (`exif.Make`, `EXIF.Make`), and prefixed (`EXIF:Make`). Returns `"Make Model"` trimmed; null when neither is present. Mirrors AtoM's `consolidated['camera']` join.

### GPS Coordinates - `fromGpsCoordinates(?array $exif)`
Three input shapes, tried in order:
1. Pre-built decimal string from the extractor (`gps.decimal` or `decimal`).
2. Consolidated numeric pair (`gps.latitude`/`gps.longitude` or top-level `latitude`/`longitude`), formatted to six decimals.
3. Raw EXIF DMS rationals (`GPSLatitude`/`GPSLongitude` as `["deg/1","min/1","sec/100"]`) with `GPSLatitudeRef`/`GPSLongitudeRef` hemisphere tags - converted to signed decimal (S/W negative).

Returns null unless a complete lat+long pair is resolvable. Output format `%.6f, %.6f` matches AtoM's `sprintf('%.6f, %.6f', ...)`.

### Location - `fromLocation(array $source)`
Accepts a flat IPTC row or a nested `{location: {...}}` consolidated block. State tolerates three column names: `state`, `province_state` (IPTC Core), `province`. Empty parts are dropped; the surviving parts are comma-joined. Null when no part is present. Mirrors AtoM's `array_filter([city, state, country])` join.

### Copyright - `buildCopyrightMetadata(array $iptc)`
Surfaces IPTC `copyright_notice` as a discrete metadata row. This is **unconditional** - distinct from `buildRequiredStatement()`, which yields to ISAD-level rights. AtoM emits Copyright both in `requiredStatement` and as its own metadata row; Heratio now does the same.

## AtoM reference

Source method: `enrichManifestWithEmbeddedMetadata()` in
`/usr/share/nginx/archive/atom-ahg-plugins/ahgIiifPlugin/lib/Services/IiifManifestV3Service.php`.
The consolidated metadata shape (camera make/model, gps latitude/longitude/decimal, location city/state/country, date_created, copyright) is produced by
`ahgMetadataExtractionPlugin/lib/Services/ahgUniversalMetadataExtractor.php`.

## Parity status

Fully at parity for the four target fields plus the pre-existing Creator, Keywords, Date of capture, requiredStatement, and provider promotion.

One intentional difference: Heratio's IO has no archival `dateCreated` column, so the EXIF capture date is always surfaced as "Date of capture" (AtoM suppresses it when an ISAD date exists). When/if Heratio grows that column, flip `$ioHasDateCreated` in `applyIptcExifEnrichment()`.

## Tests

`IiifMetadataEnricherTest` covers Camera (make+model, single part, nested/prefixed shapes, absent), GPS (decimal string, numeric pair, DMS rationals with hemisphere, partial), Location (full/partial/nested/column-variants/empty), and Copyright (present/absent). 29 tests, 73 assertions, all green under PHPUnit 11.
