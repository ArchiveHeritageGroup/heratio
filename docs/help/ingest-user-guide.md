> Heratio Help Center article. Category: User Guide / Data Ingest.

# Data Ingest Wizard

The Data Ingest Wizard is Heratio's batch-import tool for bringing records and digital objects into the catalogue in bulk. It walks you through six steps - Configure, Upload, Map, Validate, Preview, and Commit - that turn a spreadsheet, an XML/EAD export, a ZIP bundle, or files pulled from a connected document library into archival descriptions (and, where files are supplied, attached digital objects). Each ingest is saved as a session so you can leave the wizard and resume it later, and an optional set of processing actions (virus scan, OCR, named-entity extraction, summarisation, spell check, format identification, face detection, and translation) can be run automatically against the new records once they are committed.

## Overview

The wizard lives under the `/ingest` route prefix and is reached from the admin area at **Admin -> Ingestion Manager**. Every step requires an authenticated user (the routes are wrapped in the `auth` middleware). Work is persisted in a row of the `ingest_session` table, and the session moves through a fixed sequence of statuses as you advance: `configure` -> `upload` -> `map` -> `validate` -> `preview` -> `commit` -> `completed`. You can stop at any point and pick the session back up from the Ingestion Manager dashboard, which routes you straight to the step the session is currently sitting on.

When you commit, the wizard creates information objects (or accession records, depending on the record type you chose), optionally attaches digital objects, optionally builds OAIS packages (SIP / AIP / DIP), and optionally fires the configured AI and processing steps against each new record. A commit job is tracked in the `ingest_job` table, so the Commit page can show live progress and a final report.

## Key features

- **Six-step guided wizard** with a visible progress bar on every page.
- **Multiple source formats:** CSV, ZIP (a CSV plus the digital-object files it references), XML, and EAD finding aids. A server directory path can be supplied instead of an upload for very large batches.
- **Optional document-library import:** when the SharePoint connector package is installed and a tenant is configured, an extra "From SharePoint" tab lets you browse sites, drives, and folders and pick files to import directly into the session.
- **Sector and standard aware:** choose a sector (Archive, Museum, Library, Gallery, DAM) and a descriptive standard (ISAD(G), Dublin Core, RAD, DACS, MODS, SPECTRUM, CCO). The standard you pick drives which target fields are offered in the Map step.
- **Column mapping** with per-column ignore, default value, and value transforms (trim, uppercase, lowercase, title case, ISO date coercion, strip HTML).
- **Hierarchy placement:** put records at the top level, under an existing record, under a brand-new parent you create on the fly, or build the tree from `legacyId`/`parentId` columns in your CSV.
- **Validation report** with per-row issues, an inline fix, and the option to exclude problem rows before committing.
- **Preview before commit** so nothing is written until you approve.
- **Queued or synchronous commit:** large batches are dispatched to a queue worker so the web request does not time out; small batches run inline.
- **OAIS packaging:** optionally generate SIP, AIP, and DIP packages for each new record.
- **Post-commit processing actions:** virus scan, OCR, NER, summarisation, spell check, format identification, face detection, and translation, each individually toggled and run through the AI service. These steps fail soft - a problem in one of them never rolls back the records you have already created.
- **Downloadable CSV templates** tailored to the selected sector.

## How to use

### Open the Ingestion Manager

Go to **Admin -> Ingestion Manager** (route `ingest.index`, URL `/ingest`). The dashboard lists your ingest sessions; administrators see every user's sessions, while other users see only their own. From here you can download a CSV template, start a **New Ingest**, resume an in-progress session, cancel one, or view the report for a completed one.

### Step 1 - Configure

Route `ingest.configure` (URL `/ingest/configure`, or `/ingest/configure/{id}` to edit an existing session). Here you set:

- **Session Title** - a label for the batch.
- **Record Type** - Archival Descriptions or Accessions. Choosing Accessions hides the sector/standard and hierarchy controls.
- **Sector** - Archive, Museum, Library, Gallery, or DAM.
- **Descriptive Standard** - the list is filtered to the standards valid for the chosen sector (for example MODS for Library, SPECTRUM for Museum).
- **Repository** - the destination repository.
- **Hierarchy Placement** - top level, under an existing record (with a type-ahead search), create a new parent record, or use the hierarchy expressed by `legacyId`/`parentId` in your CSV.
- **Output Options** - create records, generate SIP / AIP / DIP packages, and generate thumbnail and reference-image derivatives.
- **Processing Options** - the eight post-commit actions (Virus Scan, OCR, NER, Summarize, Spell Check, Format ID, Face Detection, Translate).

New sessions are pre-filled from the operator's configured defaults (see Configuration). Saving advances the session to the Upload step.

### Step 2 - Upload

Route `ingest.upload` (URL `/ingest/{id}/upload`). Drag and drop a file or browse to select it. Supported file types are **CSV, ZIP (a CSV plus its digital-object files), and EAD XML**; plain XML is also parsed. For large batches you can instead enter a server directory path rather than uploading. A side panel shows the session's sector, standard, and placement, lists any files already attached, and offers a sector-specific CSV template download.

If the SharePoint connector is installed and a tenant is configured, a **From SharePoint** tab appears. Select a tenant, then a site, then a drive (document library), browse the folder tree, tick the files you want, and import them. Imported files are downloaded into the session, parsed, and the session advances to the Map step.

### Step 3 - Map

Route `ingest.map` (URL `/ingest/{id}/map`). The wizard parses every uploaded file into rows and lists each source column it found. For each column you choose a **target field** (the fields offered depend on the descriptive standard you selected), or mark it **ignored**. You can also set a **default value** and apply a **transform**. A few sample rows are shown to help you map correctly. Saving applies your mapping to every row (projecting source data into enriched data) and advances to Validate.

### Step 4 - Validate

Route `ingest.validate` (URL `/ingest/{id}/validate`). A summary shows total, valid, warning, and error counts, followed by a table of any issues found. For each issue you can open an inline **Fix** dialog to correct a value, or **Exclude** the problem row from the import. When you are satisfied, choose **Preview** to continue. Rows that are invalid or excluded are not committed.

### Step 5 - Preview

Route `ingest.preview` (URL `/ingest/{id}/preview`). A final confirmation of what will be created. Approving advances the session to the Commit step.

### Step 6 - Commit

Route `ingest.commit` (URL `/ingest/{id}/commit`). Click **Start Commit** to begin. Each valid, non-excluded row creates an information object; if the row carries a digital-object path that points to a real file, the file is attached as a digital object as well. When any of the SIP / AIP / DIP output options were enabled, the OAIS packager builds those packages for each new record. When any processing actions were enabled, they run against each new record (file-based actions only run where a file is present; text-based actions such as NER, summarisation, spell check, and translation always run).

Large batches (at or above the queue threshold) are dispatched to a background queue worker, and the page auto-refreshes as progress lands; smaller batches run inline. When the job finishes you get a report with records created, digital objects created, error count, and elapsed time, plus a link to browse the new records and notices for any packages generated. Administrators can also run a commit from the command line with `php artisan ahg:ingest-commit <session_id>`.

## Configuration

There is no dedicated `config/ingest.php` file; behaviour is driven by application settings plus a small number of `heratio.*` config keys.

**Operator default settings** (read through the settings service and used to pre-fill the Configure form for new sessions). These are the `ingest_*` setting keys:

| Setting key | Purpose | Default |
|---|---|---|
| `ingest_default_sector` | Default sector | `archive` |
| `ingest_default_standard` | Default descriptive standard | `isadg` |
| `ingest_create_records` | Create records on commit | on |
| `ingest_generate_sip` / `ingest_generate_aip` / `ingest_generate_dip` | Generate OAIS packages | on |
| `ingest_sip_path` / `ingest_aip_path` / `ingest_dip_path` | Export paths for the packages | empty |
| `ingest_thumbnails` / `ingest_reference` | Generate derivatives | on |
| `ingest_virus_scan` | Virus scan files | on |
| `ingest_ocr` | OCR text extraction | off |
| `ingest_ner` | Named-entity extraction | off |
| `ingest_summarize` | Auto-summarise | off |
| `ingest_spellcheck` | Spell check | off |
| `ingest_spellcheck_lang` | Spell-check language (global only) | `en_ZA` |
| `ingest_translate` | Translate | off |
| `ingest_translate_from` / `ingest_translate_to` | Translation language pair | `en` / `af` |
| `ingest_format_id` | File-format identification | off |
| `ingest_face_detect` | Face detection | off |

**Config keys:**

| Config key | Purpose | Default |
|---|---|---|
| `heratio.ingest.queue_threshold` | Row count at or above which a commit is dispatched to the queue instead of run inline | `500` |
| `ahg-ingest.upload_dir` | Where uploaded / imported files are staged | `storage/app/ingest` |
| `heratio.uploads_path` | Base path where attached digital objects are stored on commit | (see central storage config) |

The text-based processing steps (summarise, spell check, translate) also respect operator-global master switches in the AI services settings; both the per-session toggle and the global switch must be on for a step to run. All processing actions are performed by the AI service and fail soft, so a problem there cannot break the ingest itself.

### Database tables

The package installs these tables (see `database/install.sql`): `ingest_session` (wizard state), `ingest_file` (uploaded/imported files), `ingest_mapping` (column-to-field mapping), `ingest_row` (parsed and enriched rows), `ingest_validation` (validation issues), and `ingest_job` (commit job tracking and progress).

### Programmatic entry point

For streaming, per-file ingestion (used by the scanner / watched-folder pipeline), `IngestService::ingestFile()` resolves or creates an information object and attaches a digital object against a long-lived session. Repository storage quotas are enforced up front, so an over-quota repository rejects the file before anything is written.

## References

- Source package: `packages/ahg-ingest/`
  - Routes: `packages/ahg-ingest/routes/web.php`
  - Controller: `packages/ahg-ingest/src/Controllers/IngestController.php`
  - Services: `packages/ahg-ingest/src/Services/IngestService.php`, `IngestCommitRunner.php`, `OaisPackagerService.php`
  - Console command: `packages/ahg-ingest/src/Console/IngestCommitCommand.php` (`php artisan ahg:ingest-commit`)
  - Views: `packages/ahg-ingest/resources/views/` (configure, upload, map, validate, preview, commit, index)
  - Schema: `packages/ahg-ingest/database/install.sql`
- GitHub issue: https://github.com/ArchiveHeritageGroup/heratio/issues/585
