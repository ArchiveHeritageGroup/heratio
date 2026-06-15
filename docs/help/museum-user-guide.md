> Heratio Help Center article. Category: User Guide / Museum.

# Museum Cataloguing (CCO)

The Museum module lets you catalogue, browse, search, and report on museum objects (paintings, sculptures, ceramics, textiles, metalwork, and other human-made artefacts) using a rich CCO-style descriptive record. Each museum object is stored as an information object with an extended `museum_metadata` record that captures work type, creator, materials and techniques, physical description, condition and treatment, inscriptions and marks, provenance and ownership history, rights, cataloguing metadata, and stylistic and cultural classification. The module ships with faceted browse, six reporting views, CIDOC-CRM export, Getty AAT and internal authority autocomplete, and a Spectrum 5.0 CSV importer.

## Overview

A museum object record is composed of three layers:

- The shared `information_object` core (identifier, level of description, repository, parent, title, scope and content, extent and medium, access and reproduction conditions, physical characteristics).
- The `museum_metadata` extension (over eighty CCO fields covering object identity, creation, physical description, condition, inscriptions, provenance, rights, and classification).
- An optional `museum_metadata_i18n` translation layer that holds culture-specific values for the translatable text fields. Reads use a COALESCE fallback of current culture, then English, then the parent value, so a record always renders even if a translation is missing.

Records integrate with the rest of Heratio: digital objects, repositories, subject and place access points, publication status, the parent breadcrumb chain, ICIP cultural-sensitivity flags, field-level encryption for access and reproduction conditions, and the security audit log (create, edit, and delete are all captured with before/after snapshots).

## Key features

- Faceted browse with pagination, keyword search, and sorting.
- Full CCO catalogue record with create, edit, and delete.
- Identifier search across the record identifier plus accession number, barcode, and object number.
- Six reporting views: objects, creators, condition, provenance, style/period, and materials.
- A collection dashboard with totals, media coverage, condition coverage, provenance coverage, work-type breakdown, and recent items.
- A data-quality dashboard and per-field "missing data" drill-down.
- CIDOC-CRM export of a single object or the whole collection to RDF/XML, Turtle, or JSON-LD. Museum objects are typed as `crm:E22_Human-Made_Object`.
- Vocabulary autocomplete API: Getty AAT (live SPARQL, cached 24 hours), internal `ahg_dropdown` taxonomy lookups, and internal actor/term authority search.
- Authority linking and unlinking against internal actor and term records.
- Per-object views for condition report, provenance chain, Getty links, GRAP valuation dashboard, loan dashboard, multi-file upload, and object comparison.
- Spectrum 5.0 CSV import via an artisan command, with validate-only and update modes.
- Automatic sector identifier generation when no identifier is supplied and the museum mask is enabled.

## How to use

All routes are under the `/museum` prefix. Browse is public; create, edit, delete, and the dashboards and reports require authentication, and write actions are additionally gated by ACL middleware (`acl:create`, `acl:update`, `acl:delete`).

### Browse and search

- Go to `/museum/browse`.
- Filter using the facet dropdowns: work type, classification, materials, techniques, period, style, school, dynasty, cultural context, and creator identity. The dropdown values are the distinct values present in your own collection.
- Narrow by a creation-date range (`date_from` / `date_to`), which matches objects whose creation period overlaps your range.
- Use the identifier search box to find by record identifier, accession number, barcode, or object number.
- Use the keyword box for a broad search across title, scope and content, creator, materials, techniques, inscriptions, condition notes, provenance, historical/architectural/archaeological context, physical appearance, and current location.
- Sort by title, date modified, identifier, work type, or creator.

### View an object

- Open `/museum/{slug}`. The show page renders the full CCO record together with digital objects, repository, level of description, subject and place access points, publication status, and the parent breadcrumb chain.

### Create, edit, and delete

- Create: `/museum/add`. Title is required; work type, identifier, and the date fields are validated when present. If you leave the identifier blank and a museum sector identifier mask is enabled, one is generated automatically.
- Edit: `/museum/{slug}/edit`.
- Delete: posts to `/museum/{slug}/delete`. Deletion removes the object and its descendants and repairs the nested-set tree.

### Reports and dashboards

- Reports index: `/museum/reports`, with detail views at `/museum/reports/objects`, `/creators`, `/condition`, `/provenance`, `/style-period`, and `/materials`.
- Collection dashboard: `/museum/dashboard`.
- Data-quality dashboard: `/museum/quality-dashboard`, with per-field drill-down at `/museum/quality-dashboard/missing/{field}`.

### Per-object tools

- Condition report: `/museum/{slug}/condition-report`
- Provenance chain: `/museum/{slug}/provenance`
- Getty links: `/museum/{slug}/getty-links`
- GRAP valuation dashboard: `/museum/{slug}/grap-dashboard`
- Loan dashboard: `/museum/{slug}/loan-dashboard`
- Multi-file upload: `/museum/{slug}/multi-upload`
- Object comparison: `/museum/{slug}/object-comparison`
- Authority linking: `/museum/authority/{slug}/link` (with link and unlink actions)

### CIDOC-CRM export

- Go to `/museum/cidoc-export`, choose a format (RDF/XML, Turtle, or JSON-LD), and download.
- With no specific object selected, the whole collection is merged into one graph. Supplying a `slug` or `object_id` exports a single record. The download is named `cidoc-crm-<id>.<ext>` for a single object or `cidoc-crm-museum-<timestamp>.<ext>` for the full collection, and carries an `X-CRM-Version: 7.1.3` header.

### Vocabulary autocomplete (for edit forms)

These authenticated JSON endpoints back the edit-form autocomplete widgets:

- `GET /api/museum/getty-aat?q=...` returns Getty AAT concepts (label, definition, parent terms). Requires at least 2 characters; results are cached for 24 hours.
- `GET /api/museum/vocabulary-search?group=...&q=...` searches one `ahg_dropdown` taxonomy group (active rows, by label or code).
- `GET /api/museum/authority-search?type=actor|term&q=...` searches internal authorities. Requires at least 2 characters.

### CSV import (Spectrum 5.0)

Import museum objects from a Spectrum 5.0 CSV using the artisan command:

```
php artisan sector:museum-csv-import <filename>
```

Options:

- `--validate-only` validate without importing
- `--mapping=<id>` use a saved mapping profile
- `--repository=<slug>` target repository
- `--update=<field>` match field for updates (`identifier` or `legacyId`, default `legacyId`)
- `--update-mode=<mode>` `skip`, `update`, or `merge` (default `skip`)
- `--culture=<code>` default culture for translatable fields (default `en`)
- `--limit=<n>` maximum rows to process
- `--skip=<n>` rows to skip

The minimum required CSV columns are the object number and the object name. The command prints a summary of rows processed, created, updated, skipped, and errored.

## Configuration

The module has no dedicated config file or environment variables. Behaviour is driven by data and platform settings:

- Facet dropdown values are derived from the distinct values in your own `museum_metadata` rows.
- Browse page size follows the platform "hits per page" setting.
- The work-type list on the create/edit form offers Painting, Sculpture, Drawing, Print, Photograph, Textile, Ceramic, Furniture, Metalwork, Glass, Mixed Media, Installation, and Other.
- Autocomplete dropdown taxonomies are managed in the Dropdown Manager (`ahg_dropdown`); do not hardcode values.
- Automatic identifier generation depends on a museum sector identifier mask being enabled.
- Field-level encryption of access and reproduction conditions activates only when the encryption category is enabled; it is transparent to plaintext otherwise.
- Database tables are installed by `packages/ahg-museum/database/install.sql` (`museum_metadata`, `museum_metadata_i18n`). On first boot after an upgrade, the service provider mirrors existing `museum_metadata` rows into the English translation layer once if it is empty.

## References

- Source package: `packages/ahg-museum/`
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/602
