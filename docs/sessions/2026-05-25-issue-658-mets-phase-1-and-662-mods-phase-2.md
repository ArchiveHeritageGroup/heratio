# 2026-05-25 — Issue #658 METS Phase 1 + #662 MODS Phase 2 (combined)

Combined session log because both issues share an unlocked edit of
`packages/ahg-information-object-manage/src/Controllers/ExportController.php`.
Implementing them in one worktree avoids a merge conflict on the inline
`buildModsXml()` method and the route file.

## #658 METS Phase 1 — per-IO METS exporter

- New `AhgMetadataExport\Services\Exporters\MetsSerializer` produces a
  METS 1.12 document with `<metsHdr>` + `<dmdSec MDTYPE="DC">` +
  `<amdSec>` + `<fileSec USE="master|preservation|access">` + flat
  `<structMap TYPE="logical">`.
- Root element carries `PROFILE="https://heratio.theahg.co.za/profiles/mets/io-v1"`.
- `<amdSec>` delegates per-event PREMIS emission to the new
  `PremisInMetsBuilder::appendDigiprovMd($xmlWriter, $ioId)` — the helper
  walks the same `preservation_event` rows used by `ProvOSerializer`.
- `<fileSec>` reads from `digital_object`, mapping `usage_id`:
  - 140 → `USE="master"`
  - 143 → `USE="preservation"`
  - 141, 142 → `USE="access"`
  - other → `USE="other"`
- New route `GET /informationobject/{slug}/export/mets` named
  `informationobject.export.mets`.
- `ExportController::mets($slug)` mirrors the existing `provo()` shape:
  resolve IO → serialize → respond with `application/xml` +
  `attachment; filename="{slug}.mets.xml"`.
- Show page surface: small "METS / PROV-O" supplemental card inserted
  immediately after the locked `_right-blocks.blade.php` include. The
  main Export dropdown sits in that locked partial and could not be
  edited from this issue's unlocked scope; a follow-up unlock can fold
  the link into the partial later.
- New `packages/ahg-metadata-export/tests/MetsSerializerTest.php`
  performs a `DOMDocument` parse and asserts presence of `<dmdSec>`,
  `<amdSec>`, `<fileSec>`, `<structMap>`, plus the PROFILE attribute.
  Skips when the heratio DB is unreachable from the runner.

## #662 MODS Phase 2 — originInfo + editable access points + note

- `ModsManageController::edit` GET branch now:
  - splits events into creation (111) / publication (114) sets,
  - reads `mods:publisher` and `mods:note` serialized properties,
  - reads place-of-publication via `relation` type 162 against
    taxonomy-42 terms,
  - returns all of the above to the view.
- POST branch picks up new payload keys
  (`creation_date`, `creation_start_date`, `publication_date`,
  `publication_start_date`, `publisher_id`, `publisher_name`,
  `place_of_publication_id`, `mods_note`) and persists them via the new
  private `saveOriginInfo()` helper. Strategy is delete-then-insert for
  the two managed event types so the form is authoritative for them.
- `resources/views/edit.blade.php`:
  - new **originInfo** accordion with publisher (actor autocomplete +
    free-text fallback), placeOfPublication (term autocomplete filtered
    to taxonomy 42), and the four date inputs;
  - Subject / name access points accordion converted from static
    badges to three `ahg-core::components.autocomplete` widgets in
    multi-select mode;
  - new **note** accordion with a single textarea that maps to
    `mods:note`.
- `AhgMetadataExport\Services\Exporters\ModsSerializer` now emits
  refined `<originInfo>` (publisher actor → free text → repository
  fallback chain; ISO-8601 + display dateIssued / dateCreated;
  placeOfPublication) and `<note type="general">`.
- Inline `ExportController::buildModsXml` mirrors the same refinements
  so the show page's MODS download matches the serializer-backed OAI-PMH
  emitter.
- New `tests/Feature/ModsEditTest.php` round-trips the form: POST
  payload → assert GET MODS XML contains `<originInfo>`, `<dateIssued>`,
  `<placeOfPublication>`, `<note>`. Skips when DB or User model is
  unavailable.

## Help articles

- `docs/help/export/mets-export.md` (slug `export-mets`)
- `docs/help/edit/mods-origininfo.md` (slug `mods-origininfo`)

## Files touched

### #658 METS

- `packages/ahg-metadata-export/src/Services/Exporters/MetsSerializer.php` (new)
- `packages/ahg-metadata-export/src/Services/Exporters/PremisInMetsBuilder.php` (new)
- `packages/ahg-metadata-export/tests/MetsSerializerTest.php` (new)
- `packages/ahg-information-object-manage/src/Controllers/ExportController.php` — added `mets()` method
- `packages/ahg-information-object-manage/routes/web.php` — added route
- `packages/ahg-information-object-manage/resources/views/show.blade.php` — added supplemental METS / PROV-O card
- `docs/help/export/mets-export.md` (new)

### #662 MODS

- `packages/ahg-mods-manage/src/Controllers/ModsManageController.php` — originInfo load + save + note save
- `packages/ahg-mods-manage/resources/views/edit.blade.php` — originInfo + autocomplete + note blocks
- `packages/ahg-metadata-export/src/Services/Exporters/ModsSerializer.php` — refined `<originInfo>` + `<note>`
- `packages/ahg-information-object-manage/src/Controllers/ExportController.php` — refined inline `buildModsXml` + new helpers
- `tests/Feature/ModsEditTest.php` (new)
- `docs/help/edit/mods-origininfo.md` (new)

## Outstanding work (neither issue closed)

- **#658**: Phase 2 (collection-level METS that nests child IOs in the
  structMap), Phase 4 (METS validation harness + Schematron rules) still
  open.
- **#662**: Phase 3 (typeOfResource + genre form drilldowns, multiple
  titleInfo with type), Phase 4 (relatedItem with type=host/series) still
  open.

Both issues stay open per `feedback_close_only_at_100`.
