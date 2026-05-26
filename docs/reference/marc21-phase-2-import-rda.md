# MARC21 Phase 2: MARCXML import + 001-008 control fields + RDA 336/337/338

Phase 2 of Heratio issue #663 extends the standalone MARC21/MARCXML support
that shipped in v1.69.0 (Phase 1) with three additions:

1. A MARCXML importer (file upload, schema validation, preview, commit).
2. Population of the MARC21 control fields 001 (control number), 003 (control
   number identifier), 005 (timestamp), 006 (additional material chars),
   007 (physical description fixed) and 008 (fixed-length data elements) on
   every emitted record.
3. RDA 336 content type, 337 media type and 338 carrier type, driven by an
   operator-extensible mapping table (`ahg_marc_rda_mapping`).

Z39.50 client + binary `.mrc` codec stay on the Phase 3 backlog. Z39.50 is a
network protocol (TCP, BER encoding) and needs its own session manager;
binary MARC needs a separate ISO 2709 codec. Neither is a fit for the same
release as the schema work above.

## Package surface

All Phase 2 work lives in `packages/ahg-metadata-export/`:

```
src/Services/Importers/MarcXmlImporter.php
src/Services/Rda/RdaCarrierMapper.php
src/Controllers/MarcImportController.php
src/Services/Exporters/MarcxmlSerializer.php   (extended)
resources/schemas/MARC21slim.xsd                (vendored from LoC)
resources/views/marc-import.blade.php
database/install.sql                            (new ahg_marc_rda_mapping table + seeds)
routes/web.php                                  (3 new admin routes)
tests/MarcXmlImporterTest.php
tests/RdaMappingTest.php
```

## Admin routes

| Method | Path                              | Name                                   |
|--------|-----------------------------------|----------------------------------------|
| GET    | `/admin/marc/import`              | `ahgmetadataexport.marc.import`        |
| POST   | `/admin/marc/import/preview`      | `ahgmetadataexport.marc.import.preview`|
| POST   | `/admin/marc/import/commit`       | `ahgmetadataexport.marc.import.commit` |

The import dashboard is linked from the existing `/admin/metadata-export/index`
page via a new "MARCXML Import" button.

## Importer flow

1. **Upload**: user uploads a MARCXML file (up to 50 MB). The importer
   validates the payload against the vendored
   `resources/schemas/MARC21slim.xsd` (canonical copy from
   `https://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd`).
2. **Preview**: every `<record>` is parsed and each is matched against an
   existing `information_object` by MARC 001 - either string match on
   `io.identifier` or, as a fallback, numeric match on `io.id`. The preview
   table shows CREATE vs UPDATE per record plus any warnings (missing 245,
   missing 001).
3. **Commit**: writes are applied per record - either creating a new
   `object` + `information_object` + `information_object_i18n` triple, or
   updating the existing i18n + identifier row. Each successful write emits a
   chained audit row via `AhgAuditTrail\Services\AuditLogger` so import events
   join the same Ed25519/JCS tamper-evidence chain as edit events (#676
   Phase 5).

## Control field 001-008 emit rules

| Tag | Source                                                                  |
|-----|-------------------------------------------------------------------------|
| 001 | `information_object.identifier` if set, else stringified `io.id`        |
| 003 | `ahg_settings.marc_003_control_number_identifier` else `config('app.name')` |
| 005 | UTC YYYYMMDDhhmmss.0 from `object.updated_at` (or created_at, or now)   |
| 006 | Only when an IO has a `digital_object` - pos 00='m', 06='a'             |
| 007 | Only when an IO has a `digital_object` - pos 00='c', 01='r' (electronic, remote) |
| 008 | 40-char fixed-length: entry date + 's' + first-event year + 3-letter lang code |

The importer reads 001 + 005 directly. 006/007/008 are not currently mapped
back to columns - they're informational on the import side.

## RDA 336/337/338 mapping

The mapping table `ahg_marc_rda_mapping` is operator-extensible. Each row
declares a match (MIME prefix, MIME exact, or physical carrier code) plus the
three RDA tags + their source vocabulary subfield `$2`.

Default seeds cover:

| match_kind  | match_value      | 336                                | 337         | 338              |
|-------------|------------------|------------------------------------|-------------|------------------|
| mime_prefix | `text/`          | text                               | computer    | online resource  |
| mime_exact  | `application/pdf`| text                               | computer    | online resource  |
| mime_prefix | `image/`         | still image                        | computer    | online resource  |
| mime_prefix | `audio/`         | spoken word                        | computer    | online resource  |
| mime_prefix | `video/`         | two-dimensional moving image       | computer    | online resource  |
| mime_prefix | `model/`         | three-dimensional moving image     | computer    | online resource  |
| mime_prefix | `application/x-3d`| three-dimensional moving image    | computer    | online resource  |
| mime_prefix | `application/`   | computer dataset                   | computer    | online resource  |
| mime_exact  | `*`              | computer dataset                   | computer    | online resource  |
| carrier     | `volume`         | text                               | unmediated  | volume           |
| carrier     | `sheet`          | text                               | unmediated  | sheet            |
| carrier     | `audio-disc`     | performed music                    | audio       | audio disc       |
| carrier     | `film-reel`      | two-dimensional moving image       | video       | film reel        |

Operators can switch e.g. `audio/*` from "spoken word" to "performed music"
by updating the matching row - no code changes needed. The serializer reads
the table on every emit (cached per-request).

## Settings keys consumed

| Key                                       | Default              | Purpose                          |
|-------------------------------------------|----------------------|----------------------------------|
| `marc_003_control_number_identifier`      | `config('app.name')` | 003 controlfield value           |
| `marc_physical_carrier_default`           | `volume`             | RDA carrier when no digital_object exists and the IO has no carrier metadata |

These are best-effort lookups in `ahg_settings` - missing rows fall back to
the defaults so the exporter keeps producing valid MARC21 in a fresh install.

## Audit chain integration

`MarcXmlImporter::commit()` invokes
`AhgAuditTrail\Services\AuditLogger::logAction()` per record. The action
name is `marcxml_create` or `marcxml_update` depending on whether a new IO
was inserted or an existing one was overwritten. Metadata payload includes
the 001 control number, 245 title, the parsed 650/651/655 subjects/places/
genres, and 100/110/111 creators. The audit row is chained Ed25519/JCS so
tamper-evidence covers import events too.

## Phase 3 backlog (not in this release)

- Z39.50 client (TCP session, BER encoding, search/retrieve, holdings).
- Binary `.mrc` export + import (ISO 2709 codec).
- Inferred 006/007 mapping back to digital_object columns on the import path.
