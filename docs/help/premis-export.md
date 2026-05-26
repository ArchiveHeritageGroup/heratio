# PREMIS XML export

> Audience: archivist, digital preservation officer, sysadmin

PREMIS (Preservation Metadata: Implementation Strategies) is the standard maintained by the Library of Congress for describing digital preservation events, the objects they act on, the agents that performed them, and the rights regime that governs them. Heratio can emit a full PREMIS 3.0 XML document for any archival description (information object) and its attached digital objects.

## When to use it

- Ingesting a Heratio export into a downstream preservation system (Archivematica, Preservica, Rosetta).
- Producing audit evidence for an OAIS / ISO 14721 trustworthy-digital-repository review.
- Generating a snapshot of preservation history for a specific record at a fixed point in time.

If you need a packaged AIP (Archival Information Package), use the BagIt bag in the Preservation Packages section instead - that bag wraps the same PREMIS XML inside a Bag with manifest checksums.

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
- Issue #653 tracks the Phase 2+ items (real PRONOM signature sync, JHOVE validation, replication).
