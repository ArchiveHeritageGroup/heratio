# PII scan over embedded image metadata (EXIF / IPTC / XMP)

Heratio Issue #751. PiiScanService Phase 2.

## Summary

Heratio's PII scanner previously only saw free text on archival descriptions. EXIF GPS coordinates, IPTC By-line / Contact (creator name, email, phone, address), and XMP `dc:creator` / `Iptc4xmpCore:CreatorContactInfo` flowed through ingest into the three sidecar tables (`digital_object_metadata`, `dam_iptc_metadata`, `media_metadata`) without ever being scanned. Phase 2 closes that gap.

The pii-detection rules are jurisdiction-neutral. GPS coordinates and creator-contact data count as personal data under GDPR Art 4(1), POPIA s1, CCPA 1798.140(o), CDPA, LGPD, PIPL and every other regime we ship to. Per-market overlays live in `privacy_jurisdiction`, not in the scanner.

## What it scans

| Source table              | Columns scanned                                                                                              | Detected pii_type |
|---------------------------|--------------------------------------------------------------------------------------------------------------|-------------------|
| `digital_object_metadata` | `gps_latitude`, `gps_longitude`, `gps_altitude`                                                              | `gps_coordinate`  |
| `digital_object_metadata` | `creator`, `author`, `artist`                                                                                | `person_name`     |
| `digital_object_metadata` | `date_created`                                                                                               | `sensitive_date`  |
| `dam_iptc_metadata`       | `creator`                                                                                                    | `person_name`     |
| `dam_iptc_metadata`       | `creator_job_title`, `creator_address`, `creator_city`, `creator_state`, `creator_postal_code`, `creator_country`, `creator_phone`, `creator_email`, `creator_website` | `person_contact`  |
| `dam_iptc_metadata`       | `date_created`, `broadcast_date`                                                                             | `sensitive_date`  |
| `media_metadata`          | `artist`                                                                                                     | `person_name`     |
| `media_metadata`          | `gps_coordinates`                                                                                            | `gps_coordinate`  |

The full map is `PiiScanService::EMBEDDED_FIELD_MAP` in `packages/ahg-privacy/src/Services/PiiScanService.php`.

## Storage

Findings land in `ahg_pii_finding_embedded`, one row per (digital_object_id, pii_type, source_table, source_field). A composite UNIQUE on those four columns makes re-scans idempotent: a repeat run refreshes `scanned_at` and `source_value` but never duplicates rows, and never overwrites a `redacted` / `cleared` / `escalated` resolution.

Columns:

- `digital_object_id` - links back to the file the metadata came from
- `pii_type` - ahg_dropdown taxonomy `pii_type_embedded` (gps_coordinate / person_name / person_contact / sensitive_date)
- `source_table`, `source_field` - the column we matched on
- `source_value` - the raw value (TEXT, redactable in the admin UI)
- `confidence` - 0.55 to 0.95 depending on type
- `resolution_status` - ahg_dropdown taxonomy `pii_resolution` (pending / redacted / cleared / escalated)
- `scanned_at`, `resolved_at`, `resolved_by_user_id`, `notes`

Schema lives in `packages/ahg-privacy/database/install-phase2.sql` and is auto-installed by `AhgPrivacyServiceProvider` on first boot.

## Pipeline integration

1. `MetadataExtractionService::extractFromDigitalObject()` persists EXIF / IPTC / XMP / exiftool / ffprobe rows to the three sidecar tables.
2. At the end of that method, the service fires `AhgMetadataExtraction\Events\EmbeddedMetadataExtracted($digitalObjectId)`.
3. `AhgPrivacy\Listeners\ScanEmbeddedMetadataForPii` (queued, 3 tries with 30s backoff) handles the event and calls `PiiScanService::scanEmbeddedMetadata()` + `persistEmbeddedFindings()`.
4. The upload path returns to the user immediately. The PII scan + finding writes run on the queue.

The listener is registered unconditionally in `AhgPrivacyServiceProvider::boot()` and short-circuits cleanly when the Phase 2 schema isn't installed yet, so a partial deploy doesn't break the extraction pipeline.

## Backfill command

```
php artisan ahg:privacy:scan-embedded-backfill [--digital-object-id=N] [--limit=N]
```

- `--digital-object-id=N` - scan one specific digital_object and print the findings table inline. Useful for smoke-testing a known-good GPS-tagged sample.
- `--limit=N` (default 500) - hard cap on the number of digital_objects scanned in one run. Idempotent, safe to chain in cron.

## Admin UI

`/admin/privacy/embedded-findings` lists pending findings, filters by `pii_type` and `resolution_status`, and exposes a per-row modal to mark a finding as `pending` / `redacted` / `cleared` / `escalated` with optional notes. Summary cards at the top show pending + resolved counts per pii_type.

The page is wired through `EmbeddedFindingsController` (`packages/ahg-privacy/src/Controllers/EmbeddedFindingsController.php`).

## Files touched / created

Created:
- `packages/ahg-metadata-extraction/src/Events/EmbeddedMetadataExtracted.php`
- `packages/ahg-privacy/database/install-phase2.sql`
- `packages/ahg-privacy/src/Listeners/ScanEmbeddedMetadataForPii.php`
- `packages/ahg-privacy/src/Console/Commands/ScanEmbeddedBackfillCommand.php`
- `packages/ahg-privacy/src/Controllers/EmbeddedFindingsController.php`
- `packages/ahg-privacy/resources/views/embedded-findings.blade.php`
- `tests/Unit/PiiScanEmbeddedMetadataTest.php`
- `docs/reference/pii-embedded-metadata-scan.md`
- `docs/help/privacy-embedded-metadata.md`

Modified:
- `packages/ahg-metadata-extraction/src/Services/MetadataExtractionService.php` - dispatch `EmbeddedMetadataExtracted` after sidecar writes
- `packages/ahg-privacy/src/Services/PiiScanService.php` - add `scanEmbeddedMetadata()` + `persistEmbeddedFindings()`
- `packages/ahg-privacy/src/Providers/AhgPrivacyServiceProvider.php` - install Phase 2 schema, register listener, register backfill command
- `packages/ahg-privacy/routes/web.php` - admin routes for the findings UI

## Compliance position

- GDPR Art 4(1) / Art 9 - GPS plus identifiable creator name = personal data; possible special-category (location + presence at sensitive site).
- POPIA s1 - both fall under "personal information".
- CCPA 1798.140(o) - geolocation is explicitly enumerated; creator email is "personal information".
- CDPA (Virginia, Colorado, Connecticut, Utah) - identical treatment.
- LGPD Art 5(I), PIPL Art 4 - both regimes treat GPS plus identity as personal data.

Detection rules deliberately do NOT branch on jurisdiction. The configured jurisdiction only shifts confidence weighting in the free-text scanner; embedded metadata always fires regardless.
