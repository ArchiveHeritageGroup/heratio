> Heratio Help Center article. Category: Identifiers & Citation.

# DOI Management

Heratio can mint, register, update, and tombstone Digital Object Identifiers (DOIs) for archival descriptions through the DataCite REST API, and report usage back to DataCite through the Events API. This guide covers the DOI dashboard, the minting workflow, the work queue, configuration, and the events client.

---

## Overview

A DOI is a persistent, resolvable identifier (for example `10.5072/h/2026/123`) that always points back to a record even if its URL changes. Heratio assigns one DOI per archival description (information object) and keeps a small state machine for each one in the `ahg_doi` table.

DOI lifecycle in Heratio:

- **Mint** - reserve a new DOI string, build DataCite metadata from the record, POST it to DataCite, and store the result locally. A successfully minted DOI is created in the `findable` state.
- **Update** - rebuild the metadata for an existing DOI and PUT it to DataCite (used after a record is edited).
- **Verify** - GET the DOI from DataCite to confirm it still exists and refresh the locally stored state.
- **Deactivate (tombstone)** - hide the DOI on DataCite. The identifier stays resolvable but resolves to a tombstone page; the local state becomes `tombstone`.

Each transition is appended to an activity log (`ahg_doi_log`) so the full history of a DOI is auditable.

DOI states you will see in the interface:

| State | Meaning |
|---|---|
| `draft` | Reserved at DataCite but not yet public |
| `registered` | Registered with DataCite but not indexed for discovery |
| `findable` | Public and discoverable (the normal end state after minting) |
| `failed` | A mint or other action failed (see the log for the error) |
| `tombstone` | Deactivated; resolves to a tombstone page |

### Where the work happens

Most minting does not happen inline. Instead, records are placed on a work queue (`ahg_doi_queue`) and a background process pulls pending items, performs the DataCite call, and records success or failure. This keeps the dashboard responsive and lets failed items retry automatically with a back-off delay.

---

## Key features

- **Dashboard** with live counts of total, findable, registered, draft, failed, and pending DOIs, plus the ten most recently created DOIs.
- **Browse** view with a status filter (draft / registered / findable) and pagination.
- **Work queue** view with tabs for pending, processing, completed, and failed items, including attempt counts and the last error per item.
- **Per-DOI detail** view showing the DOI string, linked record, state, timestamps, and the full activity log.
- **Batch minting** - select up to 100 records that do not yet have a DOI and queue them all at once.
- **Rich DataCite metadata** built automatically from each record: title, creators (with ORCID name identifiers where available), publisher, publication year, resource type, descriptions, subjects, dates, language, alternate identifiers, related identifiers (parent / variant / exhibition links), geo-locations, and funding references.
- **Funding references** stored in a sidecar table (`ahg_io_funding`) and emitted as DataCite `fundingReferences`.
- **DataCite Events API client** that reports view, download, citation, and relation events back to DataCite, with de-duplication so the same event is never submitted twice.
- **Email notifications** to the record owner and a configurable operations mailbox on both successful and failed minting.
- **Reporting** - monthly minting counts (last 24 months) and a breakdown of DOIs by holding repository.

---

## How to use

All DOI screens live under `/admin/doi` and require an administrator account.

### Open the DOI dashboard

Go to **`/admin/doi`** (route `doi.index`). The dashboard shows summary counts and the most recently created DOIs. If the DOI tables have not been created yet, the dashboard shows a setup message instead of statistics.

### Browse existing DOIs

Go to **`/admin/doi/browse`** (route `doi.browse`). Use the status filter to narrow the list to `draft`, `registered`, or `findable`. The list is paginated and ordered by mint date (most recent first). Each row links to the record title and the DOI detail page.

### View a single DOI

From the dashboard or browse list, open **`/admin/doi/view/{id}`** (route `doi.view`). This shows the DOI string, the linked record, the current state, created / minted / updated timestamps, and the complete activity log of every action taken on that DOI.

### Mint DOIs in batch

Go to **`/admin/doi/batch-mint`** (route `doi.batch-mint`).

1. The page lists up to 100 records that do not yet have a DOI.
2. Choose which records to queue and the target state (`findable`, `registered`, or `draft`; defaults to `findable`).
3. Submit. Each selected record is added to the work queue with the `mint` action and `pending` status.
4. A confirmation message reports how many records were queued.

The actual DataCite calls are made by the background queue processor, not at submit time.

### Mint or deactivate a single DOI

- **`POST /admin/doi/{id}/mint`** (route `doi.mint`) - mint action for one record.
- **`POST /admin/doi/{id}/deactivate`** (route `doi.deactivate`) - tombstone one DOI.

These actions are triggered from the relevant detail / browse screens.

### Monitor the work queue

Go to **`/admin/doi/queue`** (route `doi.queue`). The page shows counts for each queue state and a filterable, paginated list of queue items. For each item you can see the linked record, the action (`mint`, `update`, `verify`, `deactivate`), the status, the number of attempts, the scheduled time, and the last error message if it failed.

Queue items that fail are retried automatically: the scheduled time is pushed out by a linear back-off (five minutes multiplied by the attempt number). An item is marked `failed` only after it reaches its maximum attempt count.

### Sync with DataCite

Use **`POST /admin/doi/sync`** (route `doi.sync`) to reconcile local DOI state against DataCite.

### Run reports

Go to **`/admin/doi/report`** (route `doi.report`). The report shows:

- **Monthly minting statistics** - minted and updated counts grouped by month for the last 24 months.
- **By repository** - the number of DOIs grouped by the holding repository.

---

## DataCite metadata that Heratio sends

When a DOI is minted or updated, Heratio assembles a DataCite Kernel-4 metadata payload from the record. Only blocks that have data are included, so DataCite never rejects the record for an empty array.

Always present:

- **DOI** - prefix plus a suffix built from the configured pattern.
- **Title** - the record title (falls back to `Information object {id}` if missing).
- **Creators** - actors linked to the record. Each creator is typed as Personal or Organizational; an ORCID is attached as a name identifier when the actor has one recorded. If no actor is linked, the publisher name is used as the single creator.
- **Publisher** - the configured default publisher.
- **Publication year** - derived from the earliest creation / publication date on the record, falling back to the current year.
- **Resource type** - the configured default resource type (for example `Text`).
- **URL** - the public record URL.

Included when the record has the data:

- **Descriptions** - scope and content (as Abstract), plus archival history and acquisition notes (as Other).
- **Subjects** - subject access points on the record.
- **Dates** - creation and publication dates, using DataCite range syntax where a start and end date are both present.
- **Language** - the record's source language.
- **Alternate identifiers** - the record's local reference code.
- **Related identifiers** - the parent fonds / series (IsPartOf), digital derivatives (IsVariantFormOf), and exhibition placements (IsReferencedBy).
- **Geo-locations** - place access points, with point, bounding box, or polygon coordinates when coordinate data is available.
- **Funding references** - rows from the funding sidecar table.

The same metadata can also be serialised to DataCite Kernel-4 XML for repositories that submit raw XML rather than JSON.

---

## DataCite Events API

In addition to minting, Heratio can report events that happen to a DOI back to DataCite through the Events API. Supported event types include:

- **View** - a record was viewed.
- **Download** - a digital object was downloaded.
- **Citation** - a related identifier was recorded as a citation.
- **Relation** - any related-identifier relationship (for example IsPartOf).

How it works:

- View events are captured automatically by a request middleware on the record show page, so no manual action is required.
- Each event is written to the `ahg_datacite_event` table and queued for submission.
- A unique de-duplication hash (built from subject DOI, relation type, object id, and source) ensures the same logical event is never submitted to DataCite twice. A repeat call updates the existing row rather than creating a new submission.
- A burst of views or downloads is rate-limited so it cannot exceed the configured per-minute cap.
- The endpoint is `https://api.datacite.org` in production, or `https://api.test.datacite.org` when test mode is on.

Events authenticate with a DataCite Bearer token (issued from the DataCite Fabrica console) when one is configured. If no token is set, the client temporarily falls back to the same basic-auth credentials used for minting. Operators should issue a Bearer token for long-term use.

---

## Configuration

DOI settings are managed at **`/admin/doi/config`** (GET route `doi.config`, saved via POST route `doi.configSave`). Settings are stored in the `ahg_settings` table under the `doi` setting group.

Configurable keys:

| Key | Purpose |
|---|---|
| `datacite_prefix` | The DOI prefix assigned to your repository (for example `10.5072`). |
| `datacite_repository_id` | The DataCite repository / account ID used for authentication. |
| `datacite_password` | The DataCite account password / secret. |
| `datacite_url` | The DataCite REST API base URL. |
| `datacite_environment` | `test` or `production`. Test mode routes the Events API to the DataCite test host. |
| `auto_mint` | `0` or `1` - whether records should be queued for minting automatically. |
| `default_publisher` | The publisher name used when no specific publisher is available. |
| `default_resource_type` | The default DataCite resource type (for example `Text`). |

Additional settings used by the service layer (set via the settings table):

- `datacite_api_token` - the DataCite Bearer token for the Events API. When present, it is preferred over basic auth for event submission.
- `doi_failure_notify` - one or more email addresses (comma, semicolon, or whitespace separated) that receive operational notifications when minting succeeds or fails. The record owner is notified in addition to these addresses.

### Per-repository credentials

Minting credentials and the DOI suffix pattern can also be stored per repository in the `ahg_doi_config` table. The service loads the active config row for the record's repository, falling back to the active global (repository-less) row when no repository-specific row exists.

The DOI suffix is built from a configurable pattern supporting these placeholders:

- `{repository_code}` - a short repository code
- `{year}` - the current four-digit year
- `{object_id}` - the record's numeric id

For example, a pattern of `{repository_code}/{year}/{object_id}` produces a suffix like `h/2026/123`, giving a full DOI of `10.5072/h/2026/123`.

### Funding references

Funding data is stored in the `ahg_io_funding` sidecar table, one row per funder. Fields include funder name, funder identifier and identifier type (ROR, ISNI, Crossref Funder ID, GRID, or Other), award number, award URI, and award title. This table is created automatically on first boot if it is missing.

---

## Command-line tools

The package registers artisan commands (run on the server, not in the browser) for bulk and maintenance work:

- A funding-import command to load funding references in bulk.
- An events-flush command to submit any pending or previously failed DataCite events.
- A metrics-backfill command to populate historical usage metrics.

The queue processor and these commands are normally driven on a schedule so minting, event submission, and retries happen without manual intervention.

---

## Troubleshooting

- **Dashboard shows a setup message instead of statistics** - the DOI tables have not been created. The funding and events sidecar tables are created automatically on boot; the core `ahg_doi`, `ahg_doi_queue`, and `ahg_doi_log` tables must be present from the install step.
- **Minting fails with an authentication error** - check the prefix, repository ID, and password in the configuration, and confirm the environment (test vs production) matches the credentials.
- **A DOI is stuck in the queue** - open the queue view and read the last error. Failed items retry with a growing back-off and are only marked `failed` after the maximum attempts.
- **Events are not reaching DataCite** - confirm a Bearer token is configured, or that minting credentials are valid for the fallback path, and run the events-flush command to retry queued events.

---

## References

- Source package: `packages/ahg-doi-manage/`
- Issue: [GH #563](https://github.com/ArchiveHeritageGroup/heratio/issues/563)
- DataCite enrichment and Events API work tracked under GH #654
