# Per-standard archival descriptions

Heratio keeps the international archival description (ISAD(G) and RiC)
in core and layers national or jurisdiction-specific standards on top
through sidecar tables. This guide explains how to export and import
each standard.

## Supported standards

| Standard | Origin | Sidecar table | Edit page |
|---|---|---|---|
| Dublin Core (Simple) | DCMI / international | none (core fields) | Description -> Dublin Core |
| Dublin Core Qualified | DCMI / international | none (uses `property` table) | Description -> Dublin Core |
| MODS 3.7 | Library of Congress (US) | none (core fields + properties) | not yet edit-only; exported only |
| RAD | Canada | `ahg_io_rad` | (Phase 4) |
| DACS | United States | `ahg_io_dacs` | (Phase 4) |

The sidecar approach means an ISAD(G) description always remains the
canonical record; switching to RAD or DACS does not delete or replace
any core data.

## Exporting a single record

Visit the metadata export dashboard at
`/admin/metadata-export/index`. Pick the standard, then the record. The
download URL pattern is:

```
GET /admin/metadata-export/download/{format}?io={information_object_id}
```

`{format}` is one of `dcterms`, `mods`, `rad`, or `dacs`. The endpoint
streams an XML file. Existing dc / EAD / EAC / MARC exports are
unchanged.

## Importing RAD or DACS XML

```
POST /admin/metadata-export/import/{format}
```

with either an uploaded `xml_file` or a `xml` body field. By default
the call returns a JSON preview that shows every parsed record, the
matched information_object id (or null), and warnings. Pass
`dryRun=0` (or `commit=1`) to persist the parsed values into the
sidecar table.

The importer matches records by:

- RAD: the `<identifier>` element looked up against
  `information_object.identifier`. Falls back to the synthetic
  `heratio-io-{id}` form Heratio uses when an IO has no manual
  identifier.
- DACS: tries `<recordIdentifier>` first, then `<referenceCode>`.

Records that do not match an existing IO are returned in the preview
with a warning and are not committed - this is intentional. RAD / DACS
imports are designed to enrich existing descriptions, not to bulk-load
new ones (use MARC or EAD for that).

## Dublin Core qualified terms

The qualified DC export emits both legacy `dc:*` elements and `dcterms:*`
refinements so downstream consumers that already speak simple DC keep
working. Free-text qualified terms (e.g. `dcterms:license`,
`dcterms:bibliographicCitation`) are stored in the existing `property`
table keyed by the full predicate name (`dcterms:license`,
`dcterms:audience`, etc).

## MODS 3.7 coverage

Phase 3 closes most of the open MODS 3.7 outline:

- `<typeOfResource>` maps the IO level + first digital object MIME to
  the LoC controlled vocabulary (still image / moving image / sound
  recording / text / mixed material).
- `<genre>` carries the LCGFT authority attribute.
- `<language>` emits both the human-readable name and the ISO 639-2b
  code for languages Heratio ships with (en, af, fr, de, pt, es, nl,
  it, zu, xh, st, tn, sn, sw).
- `<physicalDescription>` adds `<form>` (from a `mods:form` property
  list) and `<digitalOrigin>` when a digital object exists.
- `<subject>` now emits topic, geographic, temporal and cartographics
  children. Temporal / cartographics come from `mods:temporal` and
  `mods:cartographics` properties respectively.
- `<recordInfo>` adds `<descriptionStandard>`, `<recordIdentifier>`,
  and uses the IO's actual `created_at` / `updated_at` instead of the
  current timestamp.

## Where show-page integration lives

A toggle to pick "this collection uses RAD" on the visibility form is
tracked under #98 Phase 4. Until then, switch the standard externally
via the `information_object.source_standard` column.
