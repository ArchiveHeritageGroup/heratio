> Heratio reference: DataCite v4.5 metadata enrichment, Phase 2.

# DataCite v4.5 Metadata Enrichment - Phase 2 (#654)

**Shipped:** 2026-05-26 (heratio v1.98.0+).
**Scope:** Creator-ORCID + RelatedIdentifier + GeoLocation + FundingReference.
**Phase 3 (still open under #654):** DataCite Events API client + per-collection accuracy work.

## Summary

`DoiService::buildMetadata()` now emits four additional DataCite Kernel-4 blocks alongside the Phase 1 enrichments. The `creators[]` block was upgraded from a single placeholder publisher to real authors sourced from `event.actor_id`, each carrying an `ORCID` nameIdentifier when one is on file. A new XML serialiser (`DoiService::buildXml()`) renders the JSON:API payload as Kernel-4 XML for legacy MDS consumers.

## Block-by-block

### Creator-ORCID

- **Source:** `event.actor_id` join `actor` join `actor_i18n` (`culture='en'`) on the IO. Deduplicated by `actor.id`.
- **nameType:** `Organizational` when `actor.entity_type_id = 132` (AtoM corporate-body type), otherwise `Personal`.
- **ORCID source:** `ahg_actor_identifier` rows with `identifier_type = 'orcid'`. Chosen over a hypothetical `actor.orcid_id` column because:
  1. `actor` is base AtoM and is read-only per `feedback_atom_base_readonly.md` - we do not ALTER it.
  2. `ahg_actor_identifier` already exists (`packages/ahg-actor-manage/database/install.sql`) and already supports ORCID alongside Wikidata/VIAF/ULAN/LCNAF/ISNI/GND.
  3. The same row carries a verifiable `uri`, `is_verified`, `verified_at`, `verified_by` - useful for DataCite audit trails later.
- **Validation:** `DoiService::normaliseOrcid()` strips noise (URI prefix, hyphens, whitespace), validates 16-char digit-or-X form (final char may be `X` per ISO 27729 checksum), returns canonical `NNNN-NNNN-NNNN-NNNX`. Malformed input silently returns `null` rather than emitting invalid XML.
- **Fallback:** When no actor is linked to the IO, a single `creator` containing the publisher name is emitted (preserves Phase 1 behaviour, keeps DataCite minimum required).

### RelatedIdentifier

Three relation types are populated automatically:

| relationType       | Source                                                                                  | relatedIdentifierType |
|--------------------|-----------------------------------------------------------------------------------------|-----------------------|
| `IsPartOf`         | `information_object.parent_id` (skipped when parent is root id=1)                       | `DOI` if parent minted, else `URL` |
| `IsVariantFormOf`  | `digital_object.object_id = ${io}` count > 1 (master + derivatives present)             | `URL` to `/informationobject/{id}/digitalobjects` |
| `IsReferencedBy`   | `exhibition_object.information_object_id = ${io}` (when `ahg-exhibition` is installed)  | `URL` to `/exhibitions/{id}` |

DOI lookup for IsPartOf accepts `findable`, `registered`, or `draft` parent states so a fonds being drafted in parallel can still link.

### GeoLocation

- **Source:** `object_term_relation` join `term` (`taxonomy_id = 42`, Place) join `term_i18n` (`culture='en'`). Each place name becomes a `<geoLocationPlace>`.
- **Coordinates:** Heratio has no canonical lat/long column on `term`. The serialiser looks for an optional `ahg_place_coords` sidecar (`term_id`, `latitude`, `longitude`, `box_*`, `polygon_json`) and emits the simplest applicable shape:
  - `geoLocationPolygon` when `polygon_json` is set,
  - `geoLocationBox` when all four `box_*` columns are set,
  - `geoLocationPoint` when `latitude` and `longitude` are set,
  - otherwise place name only (DataCite accepts a bare `<geoLocationPlace>`).
- **Defensive:** missing `ahg_place_coords` table never breaks the build - geolocations degrade to place-name-only.

### FundingReference

- **New sidecar:** `ahg_io_funding` (auto-installed via `AhgDoiManageServiceProvider::ensureFundingTable()` on first boot when missing). Schema in `packages/ahg-doi-manage/database/install.sql`.
- **Columns:** `information_object_id`, `funder_name`, `funder_identifier`, `funder_identifier_type` (ROR / ISNI / Crossref Funder ID / GRID / Other), `award_number`, `award_uri`, `award_title`.
- **Capture path:** the IO edit page is locked end-to-end (see `memory/feedback_lock_io_show_tree.md`). Phase 2 therefore ships the operator command `php artisan doi:funding-import path/to/funding.csv` (`--dry-run` + `--delimiter=,` supported, idempotent on `(io, funder, award_number)`). A funding panel in the IO edit UI is a Phase 3 follow-up once that page is unlocked.
- **DataCite mapping:** `funderName` is mandatory; `funderIdentifier` + `funderIdentifierType` default to ROR when an identifier is supplied without explicit type; `awardURI` is emitted as an attribute on `<awardNumber>` per the Kernel-4 schema.

## XML serialiser

`DoiService::buildXml(array $payload): string` renders the JSON:API attributes block as Kernel-4 XML. The DataCite REST API normally consumes JSON:API directly (`Mint`/`Update` already use `application/vnd.api+json`) - the XML form is provided for legacy MDS / OAI-PMH consumers that ingest the metadata XML record directly.

Schema header always declares `xmlns="http://datacite.org/schema/kernel-4"` and `xsi:schemaLocation=".../kernel-4.5/metadata.xsd"`. All values are XML-escaped via `htmlspecialchars(ENT_XML1 | ENT_QUOTES)`. Unit-tested for well-formedness via `simplexml_load_string()` in `DoiServicePhase2Test`.

## Test coverage

`packages/ahg-doi-manage/tests/Unit/DoiServicePhase2Test.php` (7 tests, 46 assertions):

- ORCID normaliser (3 tests): canonical form, terminal `X` checksum, malformed inputs dropped.
- One per new block (4 tests): `nameIdentifiers`, `relatedIdentifiers`, `geoLocations` (point + box + polygon), `fundingReferences`.

Run: `php vendor/bin/phpunit packages/ahg-doi-manage/tests/Unit/DoiServicePhase2Test.php --no-coverage`.

## Files

- `packages/ahg-doi-manage/src/Services/DoiService.php` - +5 builders, +XML serialiser, +ORCID validator.
- `packages/ahg-doi-manage/src/Providers/AhgDoiManageServiceProvider.php` - first-boot auto-install + command registration.
- `packages/ahg-doi-manage/src/Console/DoiFundingImportCommand.php` - CSV bulk-import.
- `packages/ahg-doi-manage/database/install.sql` - `ahg_io_funding` schema.
- `packages/ahg-doi-manage/tests/Unit/DoiServicePhase2Test.php` - structural assertions.
- `docs/help/datacite-enrichment.md` - end-user-facing help article.
- `docs/reference/datacite-phase-2-enrichment.md` - this file (KM auto-ingest).
- `phpunit.xml` - registers the new package test directory.

## Locked-path callouts

- `packages/ahg-information-object-manage/` is locked - no IO edit panel added; CSV import is the documented Phase 2 path.
- `packages/ahg-doi-manage/` is NOT locked - all changes land directly in that package.
