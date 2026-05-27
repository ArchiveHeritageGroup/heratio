# OCFL `ahg-embedded-metadata` Extension (Heratio)

Status: shipped in v1.90.0 (issue #753).
Spec file: `packages/ahg-ocfl/extensions/ahg-embedded-metadata/0.1.md`.
OCFL: v1.1 (https://ocfl.io/1.1/spec/).

## What it is

A Heratio vendor extension to the OCFL v1.1 inventory.json that captures the EXIF / IPTC / XMP fields extracted from a digital object at the time the version was sealed. Lives under the spec-defined `extensions` top-level key in inventory.json, namespaced as `ahg-embedded-metadata`.

The EXIF audit found `packages/ahg-ocfl/src/Layout/Inventory.php` had no references to embedded image metadata. Capturing EXIF/IPTC/XMP as an OCFL extension makes that metadata preservable across format-migrations and traceable in fixity audits - if a TIFF master is later migrated to JP2, the inventory still remembers the original camera Make/Model, IPTC byline, XMP rights statement, etc., even if the migrated file does not.

## Block shape

```json
{
  "extensions": {
    "ahg-embedded-metadata": {
      "exif":              { "Make": "NIKON", ... },
      "iptc":              { "byline": "...", ... },
      "xmp":               { "rights": "...", ... },
      "captured_at":       "2026-05-27T08:00:00+02:00",
      "extractor_version": "ahg-metadata-extraction@0.1"
    }
  }
}
```

All three sub-blocks (`exif` / `iptc` / `xmp`) are optional. `captured_at` and `extractor_version` are always present. If all three sub-blocks would be empty, the whole `ahg-embedded-metadata` key is omitted - the spec's byte-stability requirement is preserved when there is no metadata to record.

## Where the data comes from

Default resolver: `DbEmbeddedMetadataSource` (`packages/ahg-ocfl/src/Metadata/DbEmbeddedMetadataSource.php`). It walks:

1. `property` (with `scope='metadata_extraction'`) + `property_i18n` - the catch-all key/value sink populated by `MetadataExtractionService::extractFromDigitalObject()`. Keys with a `"section:field"` prefix (`exif:Make`, `iptc:byline`, `xmp:title`) are grouped into the three OCFL sub-blocks.
2. `dam_iptc_metadata` - the typed mirror used by the DAM module. Used as an IPTC fallback when the property table has nothing for the IO.

The OCFL object id (`urn:heratio:io:{id}`) is reversed back to the information_object id via `ahg_ocfl_object_map` (upserted by `ocfl:ingest`), falling back to URN parsing if the map row is missing.

The resolver contract is `EmbeddedMetadataSource` - tests can stub it without booting MySQL.

## How it gets onto new versions

`StorageRoot::write()` now consults the wired source after staging content and, if the resolver returns anything, calls `EmbeddedMetadataExtension::build()` to canonicalise the block, then `$inventory->withExtension('ahg-embedded-metadata', $block)`. The PII gate runs in between (see below). Failures inside the resolver / builder are caught and logged; OCFL write never breaks on a metadata-side outage.

The service provider auto-wires `DbEmbeddedMetadataSource` + `DbEmbeddedMetadataPiiGate` into the `StorageRoot` singleton at boot, so every `ocfl:ingest` run inherits the behaviour without any extra config.

## Backfill

Existing OCFL objects ingested before the extension shipped do not have the block. The backfill command retrofits it as a metadata-only version bump:

```bash
# Dry run across the whole storage root.
php artisan ahg:ocfl:backfill-embedded-metadata-extension --dry-run

# Restrict to one OCFL object.
php artisan ahg:ocfl:backfill-embedded-metadata-extension --object=urn:heratio:io:42

# Bare digits get URN-wrapped automatically.
php artisan ahg:ocfl:backfill-embedded-metadata-extension --object=42

# Stop after the first N rewrites.
php artisan ahg:ocfl:backfill-embedded-metadata-extension --limit=50
```

Each rewrite:

1. Reads the existing inventory.
2. Skips it if `ahg-embedded-metadata` is already present (idempotent).
3. Resolves the sidecar data via the wired source. Skips with `[skip]` if nothing comes back.
4. Bumps the inventory to a fresh `vN+1` whose state + manifest are inherited from the prior head (no new content files).
5. Writes the per-version inventory.json + sidecar AND the canonical inventory.json + sidecar at the object root.
6. Updates `ahg_ocfl_object_map.head_version`.

Per-object summary lines (`[bump]` / `[skip]` / `[dry]` / `[error]`) plus a final totals line tell the operator what happened.

## PII gate

GPS coordinates are stripped from the EXIF sub-block when `ahg_pii_finding_embedded` (issue #751) has a `pending` or `escalated` finding of `pii_type='gps_coordinate'` against any digital_object that belongs to the IO. The implementation is `DbEmbeddedMetadataPiiGate`.

Gate behaviour:

* If the table is absent (#751 not yet shipped, stripped CI DB), the gate fails open and logs a warning - the "fail open with audit trail" pattern used by `EmbeddedMetadataContextService` in `ahg-ai-services`.
* GPS-shaped key matching is case-insensitive prefix match against `gps`, `geolocation`, and `location` - intentionally broad so a vendor-renamed field cannot smuggle the coordinate through.
* The redaction is non-destructive: only the next inventory excludes the coordinate. If the finding is later cleared, the next OCFL version will carry the coordinate again.
* If redaction removes EVERY useful field from the block (all-GPS EXIF, no IPTC, no XMP), the block is omitted entirely.

## Test coverage

`packages/ahg-ocfl/tests/Unit/InventoryEmbeddedMetadataTest.php` covers:

* `withExtension()` + `toJson()` round-trips byte-for-byte through `fromJson()` with the extension present.
* All-empty sidecar produces no `extensions` key (no `{}` artifact).
* `StorageRoot::write()` emits the extension when a fixture source is wired.
* PII gate strips `GPS*`, `Geolocation*`, `Location*` EXIF keys when flagged; passive when unflagged.
* `DbEmbeddedMetadataPiiGate::stripGpsFromBlock()` (the pure helper) returns null when the block is emptied of all useful sub-blocks.
* Resolver failure is swallowed (extension omitted, OCFL write completes).
* `applyEmbeddedMetadataExtension()` is idempotent: applying the same block twice to one inventory produces byte-identical JSON.

## Files touched / created

* `packages/ahg-ocfl/src/Layout/Inventory.php` (extensions block now in `toJson()` + `fromJson()`)
* `packages/ahg-ocfl/src/Layout/StorageRoot.php` (wires source + gate into `write()`; new `applyEmbeddedMetadataExtension()` helper)
* `packages/ahg-ocfl/src/Metadata/EmbeddedMetadataPiiGate.php` (new interface)
* `packages/ahg-ocfl/src/Metadata/DbEmbeddedMetadataPiiGate.php` (new default impl + pure helper)
* `packages/ahg-ocfl/src/Console/Commands/OcflBackfillEmbeddedMetadataExtensionCommand.php` (new artisan command)
* `packages/ahg-ocfl/src/Providers/AhgOcflServiceProvider.php` (registers the new command + container bindings)
* `packages/ahg-ocfl/extensions/ahg-embedded-metadata/0.1.md` (OCFL extension spec)
* `packages/ahg-ocfl/tests/Unit/InventoryEmbeddedMetadataTest.php` (9 new test methods)
* `docs/reference/ocfl-embedded-metadata-extension.md` (this file - KM)

## Related

* `EmbeddedMetadataExtension` + `EmbeddedMetadataSource` + `DbEmbeddedMetadataSource` were scaffolded earlier; this issue ties them into the inventory write path, adds the privacy gate, and supplies the spec file + backfill command.
* AI-side parallel: `packages/ahg-ai-services/src/Services/EmbeddedMetadataContextService.php` consumes the same sidecar tables for inference grounding and uses the same #751 GPS gate semantics.
