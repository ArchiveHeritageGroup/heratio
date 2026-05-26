# DC qualified + MODS extension + RAD/DACS - issue #662 Phase 3

Heratio per-standard description support gains Dublin Core qualified
terms, the remaining MODS 3.7 elements, and Canadian (RAD) + United
States (DACS) sidecar tables with full XML round-trip importers and
exporters. The information-object show page wiring is deferred to a
documented Phase 4 follow-up because that surface is locked.

## Scope

- Dublin Core (DCMI Metadata Terms 2020-01-20). Phase 1 emitted the 15
  simple elements; Phase 3 expands to the qualified vocabulary at
  https://www.dublincore.org/specifications/dublin-core/dcmi-terms/.
  Backward compatible - every `dc:*` element is still emitted alongside
  the new `dcterms:*` refinement.
- MODS 3.7 (https://www.loc.gov/standards/mods/mods-outline-3-7.html).
  Phase 1+2 covered abstract, originInfo and note. Phase 3 adds
  `typeOfResource`, `genre` (LCGFT), `language` ISO-639-2b codes,
  `physicalDescription` form/extent/digitalOrigin, subject children
  (topic/geographic/temporal/cartographics) and full `recordInfo`
  including `descriptionStandard` + `recordIdentifier`.
- RAD (Rules for Archival Description, Canadian). New sidecar table
  `ahg_io_rad` plus XML serializer and two-phase importer.
- DACS (Describing Archives: A Content Standard, US). New sidecar table
  `ahg_io_dacs` plus XML serializer and two-phase importer.

## Files

| File | Purpose |
|---|---|
| `packages/ahg-metadata-export/src/Services/Exporters/DublinCoreQualifiedSerializer.php` | DC qualified terms emitter |
| `packages/ahg-metadata-export/src/Services/Exporters/ModsSerializer.php` | extended MODS 3.7 emitter (in-place edits) |
| `packages/ahg-metadata-export/src/Services/Exporters/RadSerializer.php` | RAD XML emitter |
| `packages/ahg-metadata-export/src/Services/Exporters/DacsSerializer.php` | DACS XML emitter |
| `packages/ahg-metadata-export/src/Services/Importers/RadXmlImporter.php` | RAD XML preview/commit importer |
| `packages/ahg-metadata-export/src/Services/Importers/DacsXmlImporter.php` | DACS XML preview/commit importer |
| `packages/ahg-metadata-export/database/install.sql` | adds `ahg_io_rad` + `ahg_io_dacs` + format seeds |
| `packages/ahg-metadata-export/src/Controllers/MetadataExportController.php` | adds `downloadStandard` + `importStandard` actions |
| `packages/ahg-metadata-export/routes/web.php` | adds `/admin/metadata-export/download/{format}` + `/import/{format}` |
| `packages/ahg-metadata-export/src/Providers/AhgMetadataExportServiceProvider.php` | auto-installs sidecar tables on first boot |
| `packages/ahg-metadata-export/tests/DublinCoreQualifiedSerializerTest.php` | DC structural assertions |
| `packages/ahg-metadata-export/tests/ModsExtensionTest.php` | MODS 3.7 enrichment assertions |
| `packages/ahg-metadata-export/tests/RadRoundTripTest.php` | RAD round-trip + field-map coverage |
| `packages/ahg-metadata-export/tests/DacsRoundTripTest.php` | DACS round-trip + field-map coverage |

## Endpoints

```
GET  /admin/metadata-export/download/dcterms?io={id}&culture=en
GET  /admin/metadata-export/download/mods?io={id}&culture=en
GET  /admin/metadata-export/download/rad?io={id}&culture=en
GET  /admin/metadata-export/download/dacs?io={id}&culture=en
POST /admin/metadata-export/import/rad     (xml_file or xml body; dryRun=1 default)
POST /admin/metadata-export/import/dacs    (xml_file or xml body; dryRun=1 default)
```

## DC qualified term coverage

Beyond the 15 basic elements, this phase emits these `dcterms:*`
refinements when source data is available: `abstract`, `accessRights`,
`accrualPeriodicity`, `alternative`, `audience`, `available`,
`bibliographicCitation`, `conformsTo`, `created`, `dateAccepted`,
`dateCopyrighted`, `dateSubmitted`, `extent`, `hasFormat`, `hasPart`,
`hasVersion`, `isFormatOf`, `isPartOf`, `isReferencedBy`, `isReplacedBy`,
`isRequiredBy`, `issued`, `isVersionOf`, `license`, `modified`,
`provenance`, `references`, `replaces`, `requires`, `rightsHolder`,
`spatial`, `temporal`, `valid`. Free-text qualified terms operators
need but do not have a dedicated column for live in the existing
`property` table keyed by the leading `dcterms:` prefix.

## RAD / DACS sidecar shape

Both tables key on `information_object_id` with a unique constraint so
each IO has at most one row per standard. The serializers prefer the
sidecar value and fall back to the matching ISAD(G) column from
`information_object` / `information_object_i18n` when blank. Operators
can therefore install the sidecar and start exporting RAD or DACS XML
immediately without backfilling every field.

## What is NOT in Phase 3 (deferred to Phase 4)

- The information-object show page integration for "this collection uses
  RAD / DACS". That surface lives in `ahg-information-object-manage`
  which is locked end-to-end (see `.locked-paths` + memory note
  `feedback_lock_io_show_tree.md`).
- The `visible-elements.blade.php` toggles that gate per-section
  rendering on the show page. Same lock applies (per-issue lock for
  `#98 default_template` wiring).
- Operator-facing edit forms for the RAD / DACS sidecars. The
  `ahg-rad-manage` and `ahg-dacs-manage` packages exist as stubs but
  the show / edit blade work is Phase 4 once the show-page lock can be
  unlocked.

## Test plan

- `vendor/bin/phpunit packages/ahg-metadata-export/tests/RadRoundTripTest.php`
- `vendor/bin/phpunit packages/ahg-metadata-export/tests/DacsRoundTripTest.php`
- `vendor/bin/phpunit packages/ahg-metadata-export/tests/DublinCoreQualifiedSerializerTest.php`
- `vendor/bin/phpunit packages/ahg-metadata-export/tests/ModsExtensionTest.php`
- Manual: hit `GET /admin/metadata-export/download/rad?io=<existing>` and
  re-POST the response body to `/admin/metadata-export/import/rad` with
  `dryRun=1` and confirm the JSON preview matches the original payload.
