> Heratio Help Center article. Category: Platform / Core.

# Core Platform Services

The Core package is Heratio's foundation layer. It supplies the shared data models, pagination, the clipboard, multilingual reading, collection insight dashboards, preservation and quality tooling, and a large set of public discovery pages that the rest of the platform builds on.

---

## Overview

Most packages in Heratio (archival descriptions, actors, repositories, digital objects, research, and so on) sit on top of the Core package. You rarely "open" Core as a single screen. Instead, you meet it through the pages and tools it provides:

- Public discovery pages such as Explore, Collection Overview, Open Data, Recently Added, and Ask the Collection.
- Multilingual reading surfaces that let any visitor read a published record in their own language.
- The clipboard, which lets you gather records across the catalogue and export them.
- Admin dashboards for data quality, preservation maturity, fixity (integrity), accessibility coverage, alt-text curation, and capture priority.
- A large library of background commands that handle indexing, preservation, backups, statistics, and scheduled maintenance.

Core is jurisdiction-neutral. Any market-specific compliance behaviour lives in separate pluggable modules, not in Core itself.

---

## Key features

| Area | What it provides |
|------|------------------|
| Shared models and pagination | The data layer for archival descriptions, actors, repositories, and digital objects, plus the standard pager used on every browse and list page. |
| Clipboard | Gather records from anywhere in the catalogue, save named sets, and export to CSV. |
| Discovery pages | Explore hub, Collection Overview, Open Data index, Recently Added (with JSON and Atom feeds), and Ask the Collection. |
| Multilingual reading | On-demand translation of a record's key metadata, a per-visitor reading-language preference, and language-coverage dashboards. |
| Collection insight | At-a-glance statistics on collection size, levels, digitisation coverage, and contributing actors, subjects, and places. |
| Quality and preservation | Data quality (metadata gaps), preservation maturity, fixity verification, accessibility coverage, and alt-text curation dashboards. |
| Capture priority | A transparent at-risk register and an actionable capture workflow queue. |
| Background tasks | Indexing, preservation sweeps, backups, statistics, and scheduled maintenance commands. |

---

## How to use

### Discover the collection

These pages are public and need no login:

- **Explore** at `/explore` is the discovery hub. It shows cards for the features your installation has enabled (Ask the Collection, multilingual access, and similar).
- **Collection Overview** at `/collection-overview` gives an at-a-glance summary: total records, breakdown by level of description, percentage digitised, languages present, and counts of actors, repositories, subjects, and places.
- **Recently Added** at `/recent` shows the newest published records as a grid. The same list is available as JSON at `/recent.json` and as an Atom feed at `/recent.atom`.
- **Open Data** at `/open-data` links to the open-data endpoints your installation exposes (bulk exports, OAI-PMH, and similar).
- **Ask the Collection** at `/ask-the-collection` answers plain-language questions grounded in your own catalogue.

### Read a record in your own language

1. Open any published record.
2. Use the translation link to view the record in your chosen language at `/read/{record}`.
3. Heratio prefers an official human translation when one exists. If none is available, it shows a machine translation, clearly labelled, with the original always presented as authoritative.

You can set a preferred reading language that persists for a year. Per-language coverage of the published catalogue is shown at `/language-coverage`.

### Use the clipboard

1. While browsing, add records to the clipboard from a record or list view.
2. Open the clipboard at `/clipboard` to review your gathered items.
3. Save the current set under a name to return to it later, or load a previously saved set.
4. Export the clipboard to CSV for use outside Heratio.

The clipboard count is shown in the interface so you always know how many items you have gathered.

### Run the admin insight dashboards

These pages require an authenticated staff or admin account and live under `/admin`:

- **Data Quality** at `/admin/data-quality` lists published descriptions that are missing key fields such as title, scope and content, level, date, creator, subjects, or a master surrogate. It is a read-only gap report.
- **Preservation Maturity** at `/admin/preservation-maturity` scores your repository against the NDSA Levels of Digital Preservation using evidence computed from your records.
- **Preservation Self-Assessment** at `/admin/preservation-self-assessment` lets staff record a human-entered maturity assessment (NDSA Levels or DPC RAM), then view a maturity profile and export it.
- **Fixity** at `/admin/fixity` reports how many digital objects have a checksum baseline and rolls up the latest integrity sweep (match, mismatch, or missing file).
- **Accessibility** at `/admin/accessibility` is a heuristic coverage report for image alt-text, captions, transcripts, 3D alt-text, and multilingual coverage. It is a coverage report, not a formal WCAG audit.
- **Alt-Text** at `/admin/alt-text` is a worklist of published image surrogates missing alt-text in your working language, with an inline form to add and edit text.

### Plan and track digitisation

- **At-Risk Register** at `/admin/capture-priority` ranks records that most need digitisation using a transparent, hand-tuned score (for example, no master surrogate, poor condition, endangerment, or high value), with a readable reason on each entry.
- **Capture Queue** at `/admin/capture-priority/queue` turns that register into an actionable workflow: add a record to the queue, set its status, assign it to an operator, add notes, and export the queue to CSV.

---

## Configuration

- **Settings** are read from the `ahg_settings` table and managed in the AHG Settings area. These include interface labels and per-element visibility toggles.
- **Cron monitoring** is configured in `config/cron-monitoring.php`, which lists high-priority scheduled commands, the notification recipient, and the miss-detection threshold.
- **Background commands** are registered automatically and cover search indexing, preservation sweeps, integrity verification, backups, statistics aggregation, and many more. They run on the Laravel schedule, which Core wraps with run-tracking and a missed-run detector. Operators run these from the command line; they are not user-facing screens.
- Storage paths and Elasticsearch settings are centralised in the platform configuration and are never hard-coded.

---

## References

- Source: `packages/ahg-core/`
- Issue: [GH #553](https://github.com/ArchiveHeritageGroup/heratio/issues/553)
