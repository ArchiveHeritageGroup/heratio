# PREMIS XML export

> Audience: archivist, digital preservation officer, sysadmin

PREMIS (Preservation Metadata: Implementation Strategies) is the standard maintained by the Library of Congress for describing digital preservation events, the objects they act on, the agents that performed them, and the rights regime that governs them. Heratio can emit a full PREMIS 3.0 XML document for any archival description (information object) and its attached digital objects.

## When to use it

- Ingesting a Heratio export into a downstream preservation system (Archivematica, Preservica, Rosetta).
- Producing audit evidence for an OAIS / ISO 14721 trustworthy-digital-repository review.
- Generating a snapshot of preservation history for a specific record at a fixed point in time.

If you need a packaged AIP (Archival Information Package), use the BagIt bag in the Preservation Packages section instead - that bag wraps the same PREMIS XML inside a Bag with manifest checksums.

## Two ways to get a PREMIS document

Heratio exposes PREMIS in two complementary places:

1. **Web download (Metadata Export)** - a one-click PREMIS 3.0 XML download for a single record, served from the Metadata Export module alongside the other standards (METS, CIDOC-CRM, MODS, DACS, EAD). Use this for a quick, read-only snapshot of a record's preservation state straight from the browser. No command line needed. See "Web download" below.
2. **Command line (`premis:export`)** - the full preservation-officer tool. Adds ODRL-to-PREMIS rights projection, XSD validation, and file output. Use this for AIP-building and audit workflows. See "Running the export" below.

Both emit the same PREMIS namespace (`http://www.loc.gov/premis/v3`, version 3.0) and draw fixity, size, and format from the same tables, so a record exported either way is interoperable downstream. The web download is rights-light (it focuses on object / event / agent evidence); the CLI export adds the `<rights>` block.

## Web download

From the **Metadata Export** dashboard (`/admin/metadata-export/index`), the PREMIS 3.0 card sits alongside the other format cards. To download a single record's PREMIS document directly:

```
/admin/metadata-export/premis?io=1234
```

or the explicit extension variant:

```
/admin/metadata-export/premis.xml?io=1234
```

- `io` (required) is the numeric `information_object.id`.
- `culture` (optional) selects the i18n culture for labels (default `en`).

The response is an `application/xml` attachment named `heratio-premis-<id>.xml`. The endpoint sits under the authenticated `/admin/metadata-export` prefix (operator access), so it can emit unpublished records for staff review. A published-records gate is built into the serializer for any future public preservation-metadata surface.

What the web download contains, per record:

| PREMIS entity | Source in Heratio |
|---|---|
| `<object xsi:type="file">` | One per attached `digital_object` (fixity, size, format, originalName) |
| `<object xsi:type="representation">` | Fallback anchor when the record has no digital objects yet |
| `<event>` | Every `preservation_event` row linked to the IO or its digital objects |
| `<agent>` | One per distinct responsible agent / system named on those events |

Fixity (`messageDigestAlgorithm` + `messageDigest`) comes from `digital_object.checksum` / `checksum_type`; `size` from `byte_size`; format name / version / PRONOM PUID from `preservation_object_format` when a format identification has run, otherwise the stored MIME type; `originalName` from the stored file name. Anything not yet recorded is silently omitted and the document stays well-formed.

## Running the export

From the Heratio command line:

```bash
# Print the XML to stdout
php artisan premis:export 1234

# Write to disk
php artisan premis:export 1234 --out=/var/exports/io-1234.premis.xml

# Re-project ODRL policies into the PREMIS rights table before exporting
php artisan premis:export 1234 --refresh-rights

# Validate against the bundled PREMIS XSD
php artisan premis:export 1234 --validate
```

The IO id is the numeric `information_object.id` for the record. You can find it from the archival description URL or from the admin search.

## What the document contains

| PREMIS entity | Source in Heratio |
|---|---|
| `<object xsi:type="intellectualEntity">` | The IO itself (one per export) |
| `<object xsi:type="representation">` | One representation row per IO |
| `<object xsi:type="file">` | One per attached `digital_object` |
| `<event>` | Every `preservation_event` row linked to the IO or its digital objects |
| `<agent>` | A system agent for Heratio plus one per distinct user / org actor referenced by events |
| `<rights>` | Every `ahg_premis_rights` row (projected from your ODRL policy) |

Fixity (checksum), size, format name / version, and PRONOM PUID are all included in the file objects when available. Missing data is silently omitted - the XML stays well-formed.

## Rights coverage

Heratio stores access rules in two complementary places:

- **ODRL policies** (`research_rights_policy`) - the editable source of truth, used by the runtime access middleware.
- **PREMIS rights** (`ahg_premis_rights`) - a projection of ODRL specifically for export.

The `--refresh-rights` flag triggers `PremisRightsService::createFromOdrl()`, which:

- Maps ODRL `action_type` to PREMIS `rightsGranted/act` (e.g. ODRL `reproduce` -> PREMIS `replicate`).
- Derives `rightsBasis` from constraints (donor agreements, statute, license, default `policy`).
- Carries `dateStart` / `dateEnd` constraints across to `<termOfGrant>`.

If you edit an ODRL policy and want the next export to reflect it, pass `--refresh-rights` once.

## Validation

`--validate` runs libxml's `schemaValidate` against the PREMIS 3.0 XSD vendored at `packages/ahg-preservation/resources/schemas/premis-3-0.xsd`. Output shape:

```
XSD validation: PASSED
```

or up to the first 10 errors with line numbers.

Phase 1 ships a permissive stand-in XSD; it will pass any well-formed PREMIS document that uses the namespace correctly. The full strict LoC XSD (which checks element ordering and cardinality) is a planned upgrade.

## Troubleshooting

- **"ioId must be a positive integer"** - the first argument has to be the numeric IO id, not a slug.
- **Empty `<event>` section** - the IO has no `preservation_event` rows. Run `php artisan preservation:scan <ioId>` first to populate them.
- **Empty `<rights>` section** - either the IO has no ODRL policy attached, or you forgot `--refresh-rights` after editing one.
- **"XSD not found"** - the package install is incomplete; verify `packages/ahg-preservation/resources/schemas/premis-3-0.xsd` exists.

## Related

- See "Preservation scan" for populating events before export.
- See the BagIt packages screen for a wrapped AIP that includes this XML.
- See the Metadata Export module for the matching METS, CIDOC-CRM, MODS, DACS, and EAD downloads.
- Issue #653 tracks the Phase 2+ items (real PRONOM signature sync, JHOVE validation, replication).
- The web download advances issues #1197 (unified metadata) and #1244 / #1243 (digital preservation).
