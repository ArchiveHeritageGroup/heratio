> Heratio Help Center article. Category: User Guide / Digital Asset Management.

# Digital Asset Management (DAM)

The Digital Asset Management module is Heratio's home for photographs, moving images, audio, datasets and other media assets that need rich descriptive and rights metadata. Every DAM asset is an archival information object, so it inherits Heratio's identifier, title, scope and content, and repository structures, but it adds a dedicated IPTC/XMP metadata layer plus moving-image fields (production company, distributor, broadcast date, series and season, awards), production credits, format holdings across institutions, version links, and external reference links. You manage assets through a dashboard, a browse and search page, full create/edit forms, a set of reports, and a command-line CSV importer.

## Overview

DAM assets live in Heratio's standard archival tables (`object`, `information_object`, `information_object_i18n`, `slug`) and are tagged as DAM through the `display_object_config` table with `object_type = 'dam'`. The descriptive media metadata is stored alongside in `dam_iptc_metadata`, with three companion tables for repeating rows:

- `dam_version_links` - alternative versions of the same work (language dubs, restorations, director's cut, censored cut, format variants).
- `dam_format_holdings` - where physical or digital copies are held, in what format, condition and access status, across one or more institutions.
- `dam_external_links` - links to external authorities and references (film and media databases, encyclopaedias, identifier authorities, video platforms, press and academic sources).

Because a DAM asset is a full information object, it can carry attached digital objects (the actual image, video or audio files), participate in the publication-status workflow, and be governed by the same rights and ICIP cultural-sensitivity controls as any other Heratio record. The schema also provisions media derivatives and watermarking tables (thumbnails, posters, previews, waveforms, and a watermark catalogue with per-object settings) that the wider platform uses when generating and protecting derivative files.

The module is jurisdiction-neutral. It follows international media metadata conventions (IPTC, XMP, Dublin Core, PBCore-style fields for moving images) and does not assume any single country's rights or compliance regime.

## Key features

- **Media-specific metadata.** A complete IPTC/XMP-style metadata set: creator and creator contact block, headline, caption, keywords, subject code, intellectual genre, persons shown, and date and place of creation (city, state or province, sublocation, country and country code).
- **Asset typing.** Each asset can be classified by `asset_type` (for example documentary, feature, short, news) and by film or media genre, and assigned a level of description such as Photograph, Audio, Video, Image or Dataset.
- **Moving-image fields.** Production company, distributor or broadcaster, broadcast date, series title, season and episode number, awards, colour type, audio language and subtitle language, and running time in minutes.
- **Production credits.** A repeating role plus name credit list, stored as structured JSON, so each contributor and their role is captured separately.
- **Rights and licensing.** Credit line, source, copyright notice, usage terms, licence type and URL, licence expiry, and model and property release status and identifiers. Artwork-in-image fields (title, creator, date, source, copyright) are also captured.
- **Format holdings.** Track every holding of a work across institutions: format type, format details, holding institution and location, accession number, condition status, access status, access URL and notes, a verified date, and a primary-holding flag.
- **Version links.** Record related versions of a work with a version type, title, language name and code, year and notes.
- **External links.** Attach reference links with a link type, URL, title, description, associated person and role, a verified date, and a primary flag.
- **Dashboard and reports.** A statistics dashboard plus dedicated reports for assets, IPTC coverage, technical metadata and storage usage.
- **Bulk import.** A command-line CSV importer with validation, mapping profiles, repository targeting, and create, update or merge modes.
- **Audit trail.** Create, edit and delete actions are written to Heratio's security audit log with before-and-after snapshots.

## How to use

### Open the DAM dashboard

Navigate to `/dam` (or `/dam/dashboard`). The dashboard shows total assets, how many have attached digital objects, how many carry IPTC metadata, a breakdown by asset type, and a licence-type breakdown, alongside the ten most recently created assets. The left sidebar gives quick links to create, bulk upload, browse, browse filtered to assets with digital objects, and reports.

### Browse and search

Go to `/dam/browse`. The browse page lists assets with paging and lets you:

- Search across title, keywords, creator, headline and identifier using the search box (the `subquery` parameter).
- Filter by asset type (`asset_type`).
- Sort by title, identifier, date created, or date modified, ascending or descending.

Each result links through to the asset's show page at `/dam/{slug}`, which renders the descriptive metadata, any attached digital objects, related child items, and the holding repository.

### Create a new asset

You must be logged in. From the dashboard sidebar choose "Create new asset", or go to `/dam/create`. The form is organised into sections covering core description, IPTC metadata, the moving-image and production fields, rights and licensing, production credits, version links, format holdings and external links. Required fields are the identifier and the title. If you leave the identifier blank, Heratio auto-generates one using the DAM sector identifier mask.

Save the form (POST to `/dam/store`). On success you are redirected to the new asset's show page with a confirmation message. The save writes the core object, its i18n title and content, the slug, the DAM tag in `display_object_config`, the IPTC metadata row, and any version links, format holdings and external links you added, and it sets the publication status to published.

### Edit an asset

From an asset's show page or browse listing, open the edit form at `/dam/{slug}/edit` (login required). The form repopulates every section, including the production-credit rows decoded from stored JSON, and the version, holdings and external-link rows. Save with the update action (PUT to `/dam/{slug}`). Editing also supports the ICIP cultural-sensitivity field, which is persisted on the information object. Updates are captured in the audit log as a before-and-after diff.

### Delete an asset

From the edit context, use the delete action (POST to `/dam/{slug}/delete`, login required). This removes the IPTC metadata, DAM tag, version links, format holdings, external links, status rows, i18n content, slug and the underlying object, and records the deletion in the audit log. You are returned to the browse page with a confirmation message.

### Reports

Logged-in users can open the reports index at `/dam/reports`, which links to:

- **Assets report** (`/dam/reports/assets`) - recent digital objects with their record title, identifier, MIME type and size.
- **IPTC report** (`/dam/reports/iptc`) - IPTC property values (headline, creator, city, copyright, source) recorded against digital objects.
- **Metadata report** (`/dam/reports/metadata`) - digital objects with MIME type, byte size and creation date.
- **Storage report** (`/dam/reports/storage`) - total storage used, broken down by MIME type.

### Bulk import (command line)

For batch loading, use the Artisan command:

```
php artisan sector:dam-csv-import <file.csv> [options]
```

Useful options:

- `--validate-only` - check the file and report errors and warnings without importing.
- `--mapping=<id>` - apply a saved column-mapping profile.
- `--repository=<slug>` - target a specific repository.
- `--update=<identifier|legacyId>` - the field used to match existing records for updates.
- `--update-mode=<skip|update|merge>` - how to handle matched records.
- `--culture=<code>` - default culture for translated fields (defaults to `en`).
- `--limit=<n>` and `--skip=<n>` - process a window of rows.

The importer validates against Dublin Core and IPTC conventions and prints a summary of rows created, updated, skipped and errored. Run validation first to catch problems before committing the import.

## Configuration

The DAM module itself ships with no application config file or environment variables of its own. It relies on platform-wide settings and seeded data:

- **DAM display term.** Seeded into the display-standard taxonomy as "Photo/DAM (IPTC/XMP)"; the tagging mechanism is the `display_object_config.object_type = 'dam'` marker.
- **Levels of description.** The install script seeds Photograph, Audio, Video, Image and Dataset levels and registers them against the `dam` sector (with Photograph also shared to the gallery sector).
- **Dropdown values.** Enumerated values (asset type, licence type, condition status, access status, format type, link type, version type and similar) follow Heratio's Dropdown Manager and `ahg_dropdown` convention rather than fixed code lists. Do not expect hardcoded option lists.
- **Hits per page.** Browse paging uses the platform-wide hits-per-page setting (`SettingHelper::hitsPerPage()`).
- **Watermarking.** The install script seeds a watermark catalogue and global watermark settings (for example default watermark type, apply on view, apply on download, security override and minimum image size). These govern derivative protection at the platform level rather than being set per asset in the DAM forms.
- **Storage paths.** Attached digital-object files use Heratio's central storage configuration in `config/heratio.php`; the DAM module does not define its own paths.

## References

- Source package: `packages/ahg-dam/`
- Controller: `packages/ahg-dam/src/Controllers/DamController.php`
- Service: `packages/ahg-dam/src/Services/DamService.php`
- CSV importer: `packages/ahg-dam/src/Services/DamCsvImporter.php` and command `sector:dam-csv-import`
- Routes: `packages/ahg-dam/routes/web.php`
- Schema: `packages/ahg-dam/database/install.sql`
- Primary tables: `dam_iptc_metadata`, `dam_version_links`, `dam_format_holdings`, `dam_external_links` (plus `media_derivatives`, `watermark_type`, `watermark_setting`, `custom_watermark`, `object_watermark_setting`)
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/556
