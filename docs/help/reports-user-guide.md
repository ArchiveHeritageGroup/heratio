# Reports

> Heratio Help Center article. Category: Reports and Dashboards.

Run ready-made reports across descriptions, authorities, accessions, donors, repositories, storage, activity, and taxonomy; view high-level dashboards on collection health, growth, data quality, rights, preservation, and AI usage; and build, save, schedule, and share your own custom reports with the Report Builder.

---

## Overview

The Reports area is the platform's central reporting hub. It groups three kinds of reporting:

- **Standard reports** answer common operational questions (what was accessioned, what has been described, who the donors are, where things are stored, who changed what).
- **Dashboards** give management-level overviews (how the collection is growing, how complete its descriptions are, how rights and preservation are covered, how much AI has assisted cataloguing).
- **The Report Builder** lets you assemble your own report from the data tables, choose columns, filters and joins, save it, schedule it, and share it.

The reports dashboard is at `/reports`. Most standard reports and dashboards are administrator-gated; the main dashboard itself is available to any signed-in user.

---

## Key features

### Standard reports

Reached under **Admin > Reports** (`/admin/reports/...`). Each report can be filtered (for example by date range, publication status, level of description, or culture) and many can be exported to CSV.

- **Accessions** - accession records with identifier, title, scope, and dates.
- **Descriptions** - archival descriptions with their core ISAD(G) fields, filterable by publication status and level.
- **Authorities** - authority (actor) records with name, entity type, and dates.
- **Donors** - donor records with contact details.
- **Repositories** - repositories with identifier, name, and holdings.
- **Physical storage** - storage containers and locations.
- **User activity** - who did what and when, with the affected record.
- **Recent updates** - recently changed records across all record types.
- **Taxonomy** - terms and how heavily each taxonomy is used.
- **Spatial analysis** - records with coordinates, exportable as GeoJSON for mapping.
- **Checksums integrity** - the status of file-integrity and merge jobs.

### Dashboards

- **Collections health** - cross-collection key figures: total records, published share, digital-surrogate share, actor and repository counts.
- **Catalogue growth** - headline totals plus a records-created-per-month trend and composition by level, repository, and digital presence.
- **Data quality** - ISAD(G) descriptive completeness across published records: for each core element, how many records are missing it, plus an overall completeness gauge.
- **Rights and access** - breakdown by publication status, rights-statement coverage, copyright status, and policy governance.
- **Preservation health** - fixity pass/fail and never-checked counts, missing-file flags, format-identification coverage, virus-scan posture, and a recent failures list.
- **AI usage** - how much AI has assisted the catalogue: total inferences, distinct records touched, breakdown by type and model, and the human-reviewed share.
- **North Star cockpit** and **Trust and transparency console** - single overview pages that link out to the platform's capability and trust surfaces (they link to each feature rather than re-implementing it).

### Report Builder

Build a custom report from scratch:

- **Choose a data source** - select a table such as archival descriptions, authority records, accessions, repositories, donors, terms, physical storage, digital objects, events, relations, status, notes, properties, or contact information.
- **Pick columns** - choose exactly which columns appear.
- **Filter and join** - add conditions, join related tables, set sort order, and limit the result count.
- **Visualise** - add charts (grouped counts), tables, text sections, and widgets.
- **Save and organise** - save reports as draft, active, archived, or published; mark them private, shared, or public; add a name, description, and category.
- **Templates** - save a layout and filter set as a template and apply it to other reports.
- **Versions** - snapshot a report and restore an earlier version later.
- **Clone** - duplicate an existing report as a starting point.
- **Schedule** - set a daily, weekly, or monthly run with a time, a recipient email list, and an export format.
- **Share** - generate a tokenised share link that lets others view the report without signing in; deactivate the link when you are done.
- **Export** - download a report definition as JSON or its data as CSV.
- **Attachments, comments, and links** - attach files, leave comments, and bookmark related URLs on a report.

---

## How to use

### Run a standard report

1. Go to **Reports** (`/reports`) and open the report list, or go directly to **Admin > Reports**.
2. Choose a report (for example **Descriptions** or **Accessions**).
3. Apply any filters offered (date range, publication status, level, culture).
4. Review the results on screen.
5. Where an export is offered, use the CSV export to download the data. Spatial analysis can also be exported as GeoJSON.

### View a dashboard

1. From the reports area, open the dashboard you need (for example **Collections health**, **Data quality**, or **Preservation health**).
2. Read the headline figures and charts. Dashboards are read-only and safe to open on a fresh, empty catalogue.

### Build a custom report

1. Go to **Admin > Reports > Builder** (`/admin/reports/builder`).
2. Click **Create**.
3. Give the report a name, description, and category, and choose its visibility (private, shared, or public).
4. Pick a data source table, then choose the columns you want.
5. Add filter conditions, joins, sort order, and a result limit as needed.
6. Add charts, table sections, or widgets to lay out the report.
7. Save the report.
8. Optionally **Schedule** it (frequency, time, recipient emails, export format), **Share** it via a link, **Clone** it, snapshot a **version**, or **Export** it to CSV or JSON.

---

## Configuration

- **Access.** The main reports dashboard is open to any signed-in user. Standard reports, dashboards, and most Report Builder actions require an administrator account. A shared report opened through a share link does not require sign-in.
- **Public-user mode.** A platform setting can restrict reporting for non-privileged use: some reports are blocked entirely in this mode, and the descriptions report is forced to published records only. This protects unpublished and sensitive data.
- **Report visibility.** Each custom report carries its own private, shared, or public flag and records who created it.
- **Scheduling and email delivery.** Schedules (frequency, time, recipients, and format) are stored on the report. Delivery is carried out by the platform's scheduled-task runner.
- **Result limits.** Custom-report queries are capped at a maximum row count to keep large reports responsive.

---

## References

- Source: packages/ahg-reports/
- GH Issue: https://github.com/ArchiveHeritageGroup/heratio/issues/615
