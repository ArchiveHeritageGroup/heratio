# EAD Import and Finding-Aid Generation

> Native EAD 2002 and EAD 3 XML import, plus PDF finding-aid generation (issue #657 Phase 1).

## Overview

Heratio can now ingest EAD finding aids as native XML, not just as CSV
mappings. The importer detects which EAD variant (EAD 2002 or EAD 3) the
uploaded file uses, validates it against a vendored XSD, and walks the
`<archdesc>` hierarchy mapping every `<c01>` / `<c02>` / `<c>` component
to an `information_object` row. Existing IOs are matched by `unitid` so
re-imports update in place rather than creating duplicates.

A complementary PDF finding-aid generator produces a print-ready document
in Library of Congress finding-aid style from any IO plus its descendants.

## EAD XML Import

Open **Admin -> Metadata Export -> EAD Import** (or navigate directly to
`/admin/ead/import`). The workflow is upload -> preview -> commit:

1. **Upload** an EAD XML file (up to 100 MB). The file may use either
   EAD 2002 (`urn:isbn:1-931666-22-9`) or EAD 3
   (`http://ead3.archivists.org/schema/`) - Heratio detects the variant
   automatically from the root element's namespace.
2. **Preview** shows the parsed tree: each archival description is
   marked CREATE (new) or UPDATE with the matched IO id. The variant
   badge confirms the detection. Schema-validation notes appear above
   the tree.
3. **Commit** persists the tree. Each created/updated row writes a
   chained audit log entry (when `ahg-audit-trail` is installed) so the
   import is tamper-evident.

### Field mapping (recovered fields)

| EAD element                             | Heratio column                          |
|-----------------------------------------|-----------------------------------------|
| `<unitid>`                              | `information_object.identifier`         |
| `<unittitle>`                           | `i18n.title`                            |
| `<physdesc>` / `<physdescstructured>`   | `i18n.extent_and_medium`                |
| `<scopecontent>`                        | `i18n.scope_and_content`                |
| `<arrangement>`                         | `i18n.arrangement`                      |
| `<accessrestrict>`                      | `i18n.access_conditions`                |
| `<userestrict>`                         | `i18n.reproduction_conditions`          |
| `<custodhist>`                          | `i18n.archival_history`                 |
| `<acqinfo>`                             | `i18n.acquisition`                      |
| `<appraisal>`                           | `i18n.appraisal`                        |
| `<accruals>`                            | `i18n.accruals`                         |
| `<phystech>`                            | `i18n.physical_characteristics`         |
| `<otherfindaid>`                        | `i18n.finding_aids`                     |
| `<originalsloc>`                        | `i18n.location_of_originals`            |
| `<altformavail>`                        | `i18n.location_of_copies`               |
| `<relatedmaterial>`                     | `i18n.related_units_of_description`    |
| `<legalstatus>` (EAD 3)                 | Read on preview, sourced from `status`  |
| `<relations>/<relation>` (EAD 3)        | Read on preview, sourced from `relation`|

## EAD 3 export additions

The EAD 3 exporter now also emits:

- `<accessrestrict><legalstatus>` - reflects the IO's publication status
  row (`status.type_id = 158`). Published rows emit `Published`; Draft
  emits `Draft`; any other taxonomy value emits the term's display name.
- `<relations><relation relationtype="..." href="..."><relationentry>...`
  for every IO-to-IO cross reference in the generic `relation` table,
  in both directions. The `href` points at the related object's slug
  (preferred) or `#io-{id}` when no slug exists.

## PDF finding aid generator

The artisan command `ead:finding-aid` produces a print-friendly PDF
finding aid from an IO and all its descendants:

```bash
php artisan ead:finding-aid 12345
# writes to storage/app/finding-aids/12345.pdf

php artisan ead:finding-aid 12345 --out=/tmp/smith.pdf --culture=en
```

Layout follows the Library of Congress finding-aid template:

1. **Title block** - collection title + repository name.
2. **Summary** - identifier, creator, dates, extent, repository.
3. **Scope and content note**, custodial history, acquisition,
   arrangement, access and use restrictions, physical characteristics,
   related materials, other finding aids, originals / copies locations.
4. **Container list** - every descendant in MPTT order, indented by
   tree depth, with `unitid`, title, and extent.

`dompdf/dompdf` is already required by Heratio. When it is missing
(unusual but possible in CI containers), the command writes the styled
HTML form alongside instead so the operator can still print it.

## OAI-PMH bridge

The existing OAI-PMH endpoint at `/oai` already advertises `ead` and
`ead3` as valid `metadataPrefix` values via `ListMetadataFormats`. To
fetch a record as EAD 3 wrapped in the OAI envelope:

```
/oai?verb=GetRecord&identifier=oai:example.org:100002&metadataPrefix=ead3
```

The body inside `<metadata>` is produced by the same `Ead3Serializer`
the standalone download uses, which means `<relation>` and
`<legalstatus>` are now harvestable too.

## References

- Source: `packages/ahg-metadata-export/`, `packages/ahg-oai/`
- Importer: `Services/Importers/EadXmlImporter.php`
- Exporter: `Services/Exporters/Ead3Serializer.php`
- Artisan: `Console/Commands/EadFindingAidCommand.php`
- Issue: [GH #657](https://github.com/ArchiveHeritageGroup/heratio/issues/657)
- EAD 3 tag library: <https://www.loc.gov/ead/EAD3taglib/>
