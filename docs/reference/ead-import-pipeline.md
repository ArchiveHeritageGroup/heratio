# EAD Import + Finding-Aid Pipeline (Issue #657 Phase 1)

> Reference doc for `EadXmlImporter`, the Ead3 export extensions, the PDF
> finding-aid artisan command, and the OAI-PMH bridge. Auto-ingested into
> KM via the docs/ inotify watcher within ~2-3 minutes of save.

## What shipped (2026-05-26)

| Item                                              | Path                                                                            |
|---------------------------------------------------|---------------------------------------------------------------------------------|
| Native EAD2002 + EAD3 importer                    | `packages/ahg-metadata-export/src/Services/Importers/EadXmlImporter.php`        |
| Variant detection (`detectVariant`)               | Same file; routes on root namespace, falls back to structural sniff             |
| Upload/preview/commit controller                  | `packages/ahg-metadata-export/src/Controllers/EadImportController.php`          |
| Blade upload UI                                   | `packages/ahg-metadata-export/resources/views/ead-import.blade.php`             |
| Round-trip + variant detect tests                 | `packages/ahg-metadata-export/tests/EadXmlImporterTest.php`                     |
| EAD3 exporter: `<relation>` + `<legalstatus>`     | `packages/ahg-metadata-export/src/Services/Exporters/Ead3Serializer.php`        |
| PDF finding-aid artisan command                   | `packages/ahg-metadata-export/src/Console/Commands/EadFindingAidCommand.php`    |
| Vendored XSDs (permissive, replace for strict)    | `packages/ahg-metadata-export/resources/schemas/ead{2002,3}.xsd`                |
| Help article                                      | `docs/help/ead-import-and-finding-aid.md`                                       |

## EAD variant detection

`EadXmlImporter::detectVariant($xml)` returns one of:

- `'ead3'` when the root element is `<ead xmlns="http://ead3.archivists.org/schema/">` or `<control>` is present
- `'ead2002'` when the root is `<ead xmlns="urn:isbn:1-931666-22-9">` or `<eadheader>` is present
- `null` when neither structure is recognised

The structural sniff (`<control>` vs `<eadheader>`) covers exports that
drop the default namespace.

## Field mapping

Single source of truth - the round-trip-safe set. EAD3 export emits;
the importer reads them back into the same Heratio columns:

```
unittitle                     -> i18n.title
unitid                        -> information_object.identifier
physdesc / physdescstructured -> i18n.extent_and_medium
scopecontent/p                -> i18n.scope_and_content
arrangement/p                 -> i18n.arrangement
accessrestrict/p              -> i18n.access_conditions
userestrict/p                 -> i18n.reproduction_conditions
custodhist/p                  -> i18n.archival_history
acqinfo/p                     -> i18n.acquisition
appraisal/p                   -> i18n.appraisal
accruals/p                    -> i18n.accruals
phystech/p                    -> i18n.physical_characteristics
otherfindaid/p                -> i18n.finding_aids
originalsloc/p                -> i18n.location_of_originals
altformavail/p                -> i18n.location_of_copies
relatedmaterial/p             -> i18n.related_units_of_description
```

## Hierarchical persistence

`commit()` walks the parse tree pre-order, writing each node via
`persistOne()` and passing its newly-assigned `information_object.id`
to its children as `parent_id`. MPTT `lft`/`rgt` are left at 0 - the
caller is expected to run `php artisan ahg:rebuild-tree` after a large
import to renumber. Existing rows match by `information_object.identifier`
(string) first, then by numeric `id` fallback if the unitid is all digits.

## Round-trip integrity test

`EadXmlImporterTest::test_round_trip_recovers_core_fields` picks any IO
in the DB, serializes it via `Ead3Serializer`, runs it back through
`EadXmlImporter`, and asserts the title survived. Skips cleanly when
no DB / no IO available (CI). The hand-crafted static-fixture tests
cover both EAD2002 and EAD3 variants regardless of DB state.

## OAI-PMH bridge status

`packages/ahg-oai/src/Controllers/OaiPmhController.php` already advertises
`oai_ead` and `oai_ead3` in `ListMetadataFormats`, and dispatches
`GetRecord?metadataPrefix=ead3` to `Ead3Serializer`. With the
`<relation>` + `<legalstatus>` additions in this release, harvesters
now see those elements in the OAI-wrapped EAD3 metadata. No OAI
controller code change was needed - it's a transparent extension via
the exporter the controller already calls.

## Vendored XSDs

`ead2002.xsd` and `ead3.xsd` under `resources/schemas/` are permissive
structural stand-ins (root `<ead>` + `<xs:any>`). They confirm
namespace + root-element shape without pulling in the ~200 KB upstream
schema. To enforce strict validation, drop the upstream XSDs in place -
no code change needed; the importer reads whatever lives at those paths.

## Operator commands

```bash
# Upload EAD via UI
https://heratio.example.org/admin/ead/import

# PDF finding aid for IO 12345 -> storage/app/finding-aids/12345.pdf
php artisan ead:finding-aid 12345

# PDF finding aid to custom path + culture
php artisan ead:finding-aid 12345 --out=/tmp/smith.pdf --culture=en
```

## References

- EAD 3 tag library: <https://www.loc.gov/ead/EAD3taglib/>
- LoC finding-aid examples: <https://findingaids.loc.gov/>
- OAI-PMH 2.0: <https://www.openarchives.org/OAI/openarchivesprotocol.html>
- Issue: [GH #657](https://github.com/ArchiveHeritageGroup/heratio/issues/657)
