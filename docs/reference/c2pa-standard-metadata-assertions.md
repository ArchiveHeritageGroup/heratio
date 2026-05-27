# C2PA 2.1 Standard Metadata Assertions (stds.exif / stds.iptc / stds.xmp)

Issue: heratio#749. Lives in `packages/ahg-c2pa/`. Shipped on top of the
shared Ed25519 signing key already used by #693 / #676.

## What it does

The C2PA 2.1 specification reserves three "Standard Metadata Assertion"
labels for embedded image metadata that the manifest should carry through
the preservation chain so it survives reformat / migration / transcoding:

| Label       | Source                  | Subset Heratio emits |
|-------------|-------------------------|----------------------|
| `stds.exif` | `digital_object_metadata` + `media_metadata` | DateTimeOriginal, Make, Model, ImageWidth/Height, Artist, Copyright, ImageDescription, GPSLatitude/Longitude (and Ref), Software, Duration |
| `stds.iptc` | `dam_iptc_metadata`     | By-line, By-lineTitle, CopyrightNotice, Headline, Caption-Abstract, ObjectName, City, Province-State, Country-PrimaryLocationName, Sub-location, Credit, Source, Keywords (list), DateCreated, IntellectualGenre, SubjectReference, Scene, SpecialInstructions |
| `stds.xmp`  | both above              | dc:creator, dc:rights, dc:title, dc:subject, dc:description, dc:date, xmpRights:Marked, xmpRights:UsageTerms |

Each assertion is JCS-canonicalised, SHA-256 hashed, and its hashed-uri
is added to the claim's `assertions` array. The existing Ed25519 claim
signature transitively covers all three. No extra key material is needed.

## When the assertions appear

`ManifestBuilder::withStandardMetadata($digitalObjectId, $informationObjectId)`
attaches all three. The C2paService entry points wire this up automatically:

- `manifestForDigitalObject()` - always attempts to attach the three,
  used by the DAM upload path so a digital object signed at ingest carries
  its embedded metadata in its provenance manifest.
- `manifestForAiSuggestion(..., digitalObjectId: $id)` - attaches the
  three only when the AI run is anchored to a digital object; for free-text
  AI outputs (no underlying file) the parameter is null and the assertions
  are skipped.

`StandardMetadataLoader` returns `[]` when a sidecar table is missing,
a row is missing, or every relevant column is null. Empty arrays do not
produce an empty assertion - the assertion is simply omitted, keeping the
manifest tight. This is the "clean omit" behaviour the unit tests pin.

## PII gate (issue #751)

`StandardMetadataLoader::loadExif()` checks `ahg_pii_finding_embedded` for
any row with the matching `digital_object_id`, `pii_type='gps_coordinate'`,
and `resolution_status IN ('pending','escalated')`. When such a row exists:

- `Exif/GPSLatitude`, `Exif/GPSLatitudeRef`, `Exif/GPSLongitude`,
  `Exif/GPSLongitudeRef` are stripped from the payload.
- A `_pii_redacted: true` marker is added so downstream verifiers can see
  the redaction was a deliberate policy choice, not data corruption.
- The rest of the EXIF payload (Make, Model, dims, etc.) passes through.

Statuses `cleared` and `redacted` are treated as "no longer a concern" and
do not trigger the gate. Per-finding cleared = GPS flows through.

The gate is defensive: if `ahg_pii_finding_embedded` is absent (Phase 2 of
#751 not yet deployed on this install), the gate fails open with a debug
log line and GPS is emitted as-is. Query errors are caught + logged at
warn level; the manifest still issues.

## Verifier behaviour

`C2paService::verify()` round-trips every assertion - it re-hashes the
canonical bytes and matches the result against the claim's pinned hash,
then verifies the Ed25519 claim signature. Tampering with any `stds.*`
payload after signing produces an "assertion ...: hash mismatch" error.

Unknown assertion labels are ignored (forward-compat). A future C2PA
2.2 release that adds `stds.dcmes` (for example) will not break Heratio
manifests in flight.

## Files

- `packages/ahg-c2pa/src/Manifest/StandardMetadataLoader.php` - the pure
  data loader. Reads the three sidecar tables, applies the PII gate,
  returns three structured arrays. Injectable for testing.
- `packages/ahg-c2pa/src/Manifest/ManifestBuilder.php` - `::withStandardMetadata()`
  takes a loader (defaults to a fresh `StandardMetadataLoader`) and appends
  the non-empty assertions to the manifest.
- `packages/ahg-c2pa/src/Manifest/Assertion.php` - `Assertion::stdsExif()`,
  `Assertion::stdsIptc()`, `Assertion::stdsXmp()` factory methods. Each
  returns an `Assertion` with the correct C2PA 2.1 label.
- `packages/ahg-c2pa/src/Services/C2paService.php` - high-level
  orchestration. `manifestForDigitalObject()` is the DAM-upload entrypoint;
  `manifestForAiSuggestion()` is the AI-suggestion entrypoint with the
  optional `$digitalObjectId` argument.
- `packages/ahg-c2pa/tests/Unit/StandardMetadataLoaderTest.php` - 10
  PHPUnit tests using an in-memory SQLite + Illuminate Capsule to cover
  full data, empty sidecars, PII gate (pending, cleared, table-absent),
  round-trip verify, malformed rows, partial rows.

## Spec links

- C2PA 2.1 specification, "Standard Metadata Assertions" section:
  https://c2pa.org/specifications/specifications/2.1/specs/C2PA_Specification.html
- Exif tag dictionary: https://exiftool.org/TagNames/EXIF.html
- IPTC IIM standard: https://www.iptc.org/standards/iim/
- XMP / Dublin Core: https://www.adobe.com/products/xmp.html

## Test plan (manual + automated)

1. `phpunit packages/ahg-c2pa` - the 14-test suite (4 manifest + 10
   stds-loader) covers the full matrix.
2. `php artisan tinker` -> `(new AhgC2pa\Services\C2paService(...))->manifestForDigitalObject(...)`
   on a real DAM record with EXIF + IPTC - confirm the three assertions
   appear in the returned `assertions` array.
3. With a pending gps_coordinate finding in `ahg_pii_finding_embedded`,
   re-run step 2 - confirm `Exif/GPSLatitude` is absent and
   `_pii_redacted: true` is set on the stds.exif data.
4. Mark the finding as `cleared` via the embedded-findings UI - re-run
   step 2, confirm GPS reappears.
5. `C2paService::verify()` on a signed manifest exported by step 2
   - must return `ok: true`.
