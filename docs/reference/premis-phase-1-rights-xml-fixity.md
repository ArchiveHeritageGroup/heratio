# PREMIS Phase 1 - Rights entity, XML export, fixity wrappers

> Issue: ArchiveHeritageGroup/heratio#653 (PREMIS preservation gaps)
> Package: `packages/ahg-preservation/`
> Status: Phase 1 shipped. Phase 2+ items (real PRONOM sync, JHOVE, replication) still open.

## What Phase 1 adds

Three independent things landed in `ahg-preservation`:

1. A **PREMIS Rights entity** projected from the existing ODRL policy layer.
2. A **PREMIS 3.0 XML serializer** that emits a full four-entity document for an IO.
3. A **fixer-binary wrapper interface** (`FixityToolInterface`) with concrete adapters for Siegfried (PRONOM identification) and ClamAV (malware scan), plus a no-op fallback for dev hosts.

Each piece is independently usable. The XML serializer reads the Rights rows produced in (1); the scan service writes `preservation_event` rows that the serializer surfaces under `<event>`.

## Database

New table `ahg_premis_rights`:

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | |
| `information_object_id` | INT FK information_object(id) | ON DELETE CASCADE |
| `rights_basis` | VARCHAR(32) | `copyright \| license \| statute \| donor \| policy \| other` |
| `rights_granted_act` | VARCHAR(64) | `replicate \| migrate \| disseminate \| delete \| modify \| use` |
| `rights_granted_restriction` | TEXT | Free text restriction note |
| `applicable_dates_start` | DATE NULL | |
| `applicable_dates_end` | DATE NULL | |
| `source_xml` | LONGTEXT | JSON snapshot of the source ODRL row |
| `created_at` | DATETIME | |

Auto-seeded by `AhgPreservationServiceProvider::bootPremisRightsTable()` on first request when `Schema::hasTable('ahg_premis_rights') === false`.

The table is a **projection**, not a replacement, of `research_rights_policy`. ODRL stays the editing surface; PREMIS rights are produced by `PremisRightsService::createFromOdrl($ioId)` on demand or via the `premis:export --refresh-rights` flag.

## Services

| Class | Purpose |
|---|---|
| `AhgPreservation\Services\PremisRightsService` | ODRL -> PREMIS projection. Idempotent: existing rows are refreshed, stale (basis, act) pairs purged. |
| `AhgPreservation\Services\PremisXmlSerializer` | Emits `<premis:premis>` document with `<object>` (IE + Representation + per-file File), `<event>`, `<agent>`, `<rights>`. |
| `AhgPreservation\Services\FixityScanService` | Wraps an array of `FixityToolInterface` implementations, runs `identify()` + `scan()` across every digital object of an IO, writes one `preservation_event` per check. |

The XML serializer uses `XMLWriter` directly (mirrors `PremisInMetsBuilder` in `ahg-metadata-export`) so memory stays flat regardless of how many digital objects / events / rights statements an IO carries.

## XSD validation

`PremisXmlSerializer::validate($xml)` runs `DOMDocument::schemaValidate()` against the vendored XSD at `packages/ahg-preservation/resources/schemas/premis-3-0.xsd`.

The vendored XSD is a **Phase 1 stand-in**: permissive `xsd:any` containers plus the four `xsi:type` subtypes (`intellectualEntity`, `representation`, `file`, `bitstream`) needed to resolve the type attributes our serializer emits. The full LoC schema (~5000 lines plus xlink import) is the Phase 2+ replacement; the serializer output is already namespace-correct so no code changes are needed when the canonical XSD is dropped in.

## Fixity wrappers

`AhgPreservation\Tools\FixityToolInterface`:

```php
public function identify(string $path): array; // returns format_id, format_name, format_version, format_pronom, mime_type
public function scan(string $path): array;     // returns clean, threats[], scanner_version
public function name(): string;
public function isAvailable(): bool;
```

Three concrete implementations:

- **`SiegfriedTool`** - shells out to `sf -json` (https://www.itforarchivists.com/siegfried/). Real PRONOM identification.
- **`ClamAVTool`** - shells out to `clamscan -i --no-summary`. Exit 0 = clean, exit 1 = infected.
- **`NullFixityTool`** - always returns "unknown" + clean. Dev fallback.

`FixityScanService` accepts any combination. First tool whose `identify()` returns a non-unknown format wins; first tool whose `scan()` reports a real scanner wins. Always end the list with `NullFixityTool` so the service can never starve.

## Artisan commands

```bash
# Export a PREMIS 3.0 XML doc for one IO
php artisan premis:export 1234
php artisan premis:export 1234 --out=/tmp/io-1234.xml --refresh-rights --validate

# Run identify + malware scan
php artisan preservation:scan 1234                         # one IO
php artisan preservation:scan --stale-days=90 --limit=50    # sweep stale IOs
php artisan preservation:scan 1234 --tools=siegfried,clamav # explicit tool list
```

Both commands are registered via `AhgPreservationServiceProvider::boot()` and only register when `$this->app->runningInConsole()`.

## Operator dependencies

- `sf` (Siegfried) - optional. Install: `go install github.com/richardlehane/siegfried/cmd/sf@latest` or apt package on Debian-derivatives. Missing = `SiegfriedTool::isAvailable()` returns false and the service falls through.
- `clamscan` (ClamAV) - optional. Install: `apt install clamav` + `freshclam`. Same fallthrough behaviour.

Both are detected at runtime; nothing in Heratio's PHP code requires them.

## What stays in Phase 2+

- **Real PRONOM signature sync** - `PronomIdentificationService` is still placeholder; Siegfried covers identification but the local signature DB sync is open.
- **JHOVE format validation** - format conformance / well-formedness checks (separate from identification).
- **Replication** - `preservation_replication_target` + `preservation_replication_log` tables exist but no executor wired.
- **Full vendored PREMIS XSD** - replace the Phase 1 stand-in with the LoC canonical bundle when network access can be guaranteed at install time.
- **UI** - Phase 1 is XML / CLI. A `premis-rights` browse + edit screen is a separate UI task; right now the source of truth for editing is the ODRL policy admin.

## Cross-package links

- `packages/ahg-metadata-export/src/Services/Exporters/PremisInMetsBuilder.php` - the existing METS / PREMIS-in-METS builder. The new `PremisXmlSerializer` produces a standalone PREMIS document; the two share the `preservation_event` data source and remain compatible.
- `packages/ahg-research/database/install.sql` - source of the `research_rights_policy` table (ODRL).
- `packages/ahg-integrity/` - upstream `FixityService::batchVerify()` used by the scheduled fixity runner. Phase 1 wrappers add identification + malware scan; they don't replace integrity verification.
