> Heratio Help Center article. Category: User Guide / Gallery.

# Gallery Management

The Gallery module catalogues artworks and art objects using the CCO (Cataloguing Cultural Objects) standard, and manages the people and processes that surround a collection of art: artists, loans in and out, valuations, insurance, exhibition spaces and venues, and facility reports. Artworks are stored as standard Heratio archival descriptions (information objects) tagged with the `gallery` display type, so they reuse the platform's hierarchy, digital objects, access points, publication status, and search, while gaining a set of art-specific fields drawn from CCO. This guide explains how to browse, create, edit and manage gallery records, and how to run the supporting loan, valuation and reporting workflows.

## Overview

A gallery artwork in Heratio is an information object with three companion records:

- An entry in `display_object_config` with `object_type = 'gallery'`, which marks the record as belonging to the Gallery module.
- A row in `museum_metadata` holding the shared CCO fields (work type, classification, creator identity and role, creation dates and place, style, period, movement, school, measurements, dimensions, materials, techniques, inscription, mark description, condition, provenance, current location, rights and cataloguer details).
- A `slug` used in the public URL.

Because artworks are real information objects, they inherit the standard description fields (title, scope and content, extent and medium, archival history, access conditions, and so on), the nested-set hierarchy (`lft`/`rgt`), digital objects and thumbnails, taxonomy access points (subjects, places, genres), and publication status. New artworks start as **Draft** and must be published before they appear to the public.

Alongside artwork cataloguing, the module keeps dedicated tables for artists (`gallery_artist`), loans (`gallery_loan` and `gallery_loan_object`), valuations (`gallery_valuation`), insurance policies (`gallery_insurance_policy`), spaces (`gallery_space`), and facility reports (`gallery_facility_report`).

## Key features

- **CCO artwork cataloguing** - capture art-specific descriptive metadata on top of standard archival fields, with automatic slug generation and draft publication status.
- **Browse and search** - paginated browse with free-text search across title, identifier, creator, materials, techniques, classification and work type; filter by repository; sort by title, date modified, identifier or artist. Master images and thumbnails are shown where digital objects exist.
- **Artist register** - maintain an artist directory with biography, dates, nationality, representation terms, contact details and social links. Each artist page lists the artworks linked to them (matched on creator identity) and any associated authority (actor) record.
- **Loans** - track incoming and outgoing loans, the objects on each loan, condition in and out, insurance values, agreements and facility-report status.
- **Valuations and insurance** - record valuations (insurance, market, replacement, auction estimate, probate, donation) with appraiser details, currency and validity dates, and hold insurance policy records.
- **Venues and spaces** - record exhibition venues and the display spaces within them (area, wall length, height, lighting, climate control, weight limits).
- **Facility reports** - capture the security, fire, climate, handling and insurance conditions of a borrowing or lending institution.
- **Gallery reports** - a reporting dashboard summarising exhibitions, artists, loans, valuations, spaces and facility reports.
- **CSV import** - bulk-import artworks from CSV with CCO column mapping and a validate-only mode.
- **Audit logging** - artwork, loan, valuation and venue create/edit/delete actions are written to the security audit log.

## How to use

### Open the Gallery dashboard

Sign in, then go to **/gallery/dashboard**. The dashboard shows counts for total items, items with media, media coverage, artists and active loans, a Quick Actions panel, and a list of recent items.

### Browse artworks

- Public browse: **/gallery/browse**
- Use the search box (`subquery`), the repository filter, and the sort selector (Title, Date modified, Identifier, Artist).
- Click an item to open its detail page at **/gallery/{slug}**.

### View an artwork

Open **/gallery/{slug}**. The detail page assembles the full record: descriptive fields, repository, digital objects, creation events and creators, notes, subject / place / genre access points, publication status, physical storage locations, parent breadcrumbs, any linked artist record, and a linked marketplace listing if the Marketplace module is installed.

### Add or edit an artwork

These actions require you to be signed in.

- Add: **/gallery/add** (form), submitted to **/gallery/store**.
- Edit: **/gallery/{slug}/edit** (form), submitted as an update to **/gallery/{slug}**.
- Delete: posted to **/gallery/{slug}/delete**.

The create and edit form offers built-in choice lists for work type, creator role and level of description, plus repositories, display standards and physical storage containers pulled from the database. Only **Title** is required; all other fields are optional. Saving a new artwork creates the information object, its i18n record, the `museum_metadata` row, the `display_object_config` (gallery) row, a unique slug, and a Draft publication status. Deleting an artwork removes it and all of its descendants and repairs the hierarchy.

### Manage artists

- List: **/gallery/artists** (public), with search and sort (Name, Date modified, Nationality). Only active artists are shown by default.
- View: **/gallery/artists/{id}** - shows the artist, their linked artworks and any associated authority record.
- Add: **/gallery/artists/create**, submitted to **/gallery/artists/store** (requires sign-in). **Display name** is required.

### Manage loans, valuations and venues

All of the following require sign-in.

- Loans list: **/gallery/loans**; view a loan at **/gallery/loans/{id}**; create at **/gallery/loans/create**, submitted to **/gallery/loans/store**.
- Valuations list: **/gallery/valuations**; view at **/gallery/valuations/{id}**; create at **/gallery/valuations/create**, submitted to **/gallery/valuations/store**.
- Venues list: **/gallery/venues**; view at **/gallery/venues/{id}**; create at **/gallery/venues/create**, submitted to **/gallery/venues/store**.
- Facility report: **/gallery/facility-report/{id}**.

### Gallery reports

Go to **/gallery-reports** for the reporting dashboard. Individual reports:

- **/gallery-reports/exhibitions**
- **/gallery-reports/facility-reports**
- **/gallery-reports/loans**
- **/gallery-reports/spaces**
- **/gallery-reports/valuations**

The legacy path **/gallery/reports** redirects to **/gallery-reports**.

### Import artworks from CSV

Run the import command from the application root:

```
php artisan sector:gallery-csv-import <filename>
```

Useful options:

- `--validate-only` - check the file without writing any records.
- `--mapping=<id>` - apply a saved mapping profile.
- `--repository=<slug>` - assign imported records to a target repository.
- `--update=<field>` - match field for updates (`identifier` or `legacyId`).
- `--update-mode=<mode>` - `skip`, `update` or `merge`.
- `--culture=<code>` - default language for translatable fields (default `en`).
- `--limit=<n>` and `--skip=<n>` - process a subset of rows.

The importer maps common CSV headers (for example `artist`, `maker` and `author` all map to creator; `medium`, `object_type` and `work_type` all map to work type) to CCO fields.

### Seed demonstration content

A demo seeder is available for evaluation environments:

```
php artisan gallery:seed-demo
```

It loads a set of sample images shipped under `docs/` as published gallery items. Options include `--source=<dir>`, `--force` (re-seed existing) and `--dry-run`.

## Configuration

The Gallery module has no dedicated configuration file or settings keys. It relies on platform-wide settings and services:

- **Results per page** comes from the global Heratio search/display setting (`SettingHelper::hitsPerPage()`).
- **Identifiers** are taken from the value you enter; if left blank, an identifier is generated only when the gallery-sector identifier mask is enabled in the central sector-identifier configuration.
- **Storage and digital objects** use the standard Heratio storage paths and digital-object pipeline.
- **Access and reproduction conditions** are encrypted at rest only when the access-restrictions encryption category is enabled; otherwise they round-trip as plain text.
- **Choice lists** for work type, creator role and artist type are provided by the module; level of description, display standards and repositories come from the existing taxonomies and authority records.

Database tables are created idempotently from `database/install.sql`, and the module registers its routes, views and console commands on boot. No environment variables are specific to this module.

## References

- Source package: `packages/ahg-gallery/`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/576
- Related modules: `ahg-museum` (shared CCO `museum_metadata`), `ahg-display` (GLAM browse and advanced search), `ahg-exhibition` (exhibitions and venues), `ahg-marketplace` (optional listing links).
