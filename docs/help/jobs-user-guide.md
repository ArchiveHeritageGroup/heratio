> Heratio Help Center article. Category: Administration.

# Background Jobs

The Jobs dashboard is the single place to monitor long-running background work in Heratio - exports, imports, AI processing, batch updates, validations, conversions, sync runs and ingests. It shows live counts by status, a filterable list of every job, per-job detail (including error messages and stack traces), a CSV export, and a one-click clean-up of old finished jobs.

---

## Overview

Many actions in Heratio do not finish instantly. A large CSV import, an AI processing pass over thousands of digital objects, a bulk export, or an Elasticsearch sync runs in the background while you carry on working. Each of these is recorded as a **job**.

The Jobs dashboard reads those records and presents them as a monitoring console. It does not start work itself - jobs are created by the feature that needs them (for example the Ingest wizard, the AI services, or a batch update tool). The dashboard is the read-and-housekeeping surface over that activity:

- See how many jobs are pending, running, completed, failed or cancelled at a glance.
- Browse the full job list with status, type, timing and duration.
- Open any job to read its full detail, output, download link, and - when something went wrong - its error message and stack trace.
- Export the job list to CSV for reporting or auditing.
- Clear out old finished jobs to keep the list tidy.

Internally a job spans two tables. The core record lives in the `job` table (name, owning user, linked object, status, output, download path, completion time). Heratio-specific execution metadata - the job **type**, start time, **duration**, error message and stack trace - lives in a companion `ahg_job_execution` table keyed by `job_id`. The dashboard joins both so you see one combined row.

---

## Key features

### Job statuses

Every job is in exactly one of six states. The dashboard colour-codes each with a Bootstrap 5 badge:

| Status | Badge colour | Meaning |
|--------|--------------|---------|
| **Pending** | Warning (yellow) | The job has been created and is waiting to be picked up. |
| **Running** | Primary (blue) | The job is currently executing. |
| **Completed** | Success (green) | The job finished successfully. Any output and download link are available on the detail page. |
| **Failed** | Danger (red) | The job stopped with an error. The detail page shows the error message and, where captured, the full stack trace. |
| **Cancelled** | Secondary (grey) | The job was stopped before it could finish. |

A sixth internal state, **queued**, exists in the job model for work that has been accepted but not yet started; in the dashboard such jobs are summarised within the pending and running counts depending on how the producing feature sets them.

A job that is Pending or Running is considered **active**. A job that is Completed, Failed or Cancelled is considered **inactive** (finished) - this distinction matters for the Clear Old Jobs housekeeping action.

### Job types

Each job carries a **type** describing the kind of work it represents. The types Heratio recognises are:

| Type | Used for |
|------|----------|
| `export` | Bulk exports of descriptions, authorities or other records. |
| `import` | Bulk imports of records. |
| `ingest` | Files processed through the Ingest / Scan pipeline. |
| `ai_processing` | AI tasks such as OCR/HTR, NER, summarisation, condition scans. |
| `batch_update` | Bulk edits applied across many records at once. |
| `validation` | Validation passes over imported or staged data. |
| `conversion` | Format / media conversions. |
| `sync` | Synchronisation work, for example reindexing into Elasticsearch. |

The type is shown as a grey badge in both the list and the detail view, and the list can be filtered by it.

### Statistics cards

The top of the dashboard shows six summary cards, each a live count straight from the `job` table:

- **Total** - every job on record.
- **Pending** - jobs waiting to run.
- **Running** - jobs executing now.
- **Completed** - jobs that finished successfully.
- **Failed** - jobs that ended in error.
- **Cancelled** - jobs that were stopped.

### Filtering and sorting

The list can be narrowed by **status** (All, Pending, Running, Completed, Failed, Cancelled) and ordered by **date** (newest first, the default) or by **name** (A to Z). Filtering by job **type** is also supported by the underlying service.

### Per-job detail

Opening a job shows:

- **Name**, **type** badge and **status** badge.
- **Created**, **Started**, **Completed** timestamps and total **Duration** in seconds.
- **Error Details** - only shown for failed jobs - with the error message and an expandable stack trace panel.
- **Output** - any structured result the job recorded, rendered as formatted JSON or text.
- **Download** - a button to download the job's result file, shown only when the job recorded a download path (typical for exports).

### CSV export

The whole job list can be downloaded as `jobs-export.csv` with one click. The export includes ID, Name, Type, Status, Created, Completed and Duration columns. It is generated as a streamed download so it works even for large lists.

### Clear old jobs (housekeeping)

A single action deletes finished jobs (Completed, Failed or Cancelled) whose completion date is older than a cut-off. The button in the toolbar uses a 30-day cut-off and asks for confirmation first. Active jobs (Pending or Running) are never removed. Removing a job deletes both its `job` row and its underlying `object` row.

---

## How to use

### Open the dashboard

1. Sign in with an administrator or staff account.
2. Go to **`/jobs`** in your browser. The dashboard requires authentication.

You land on the Jobs list with the six statistics cards across the top.

### Find a specific job

1. Use the **status** dropdown to pick a state (for example Failed) and press **Filter**.
2. Use the **sort** dropdown to order by **Date** or **Name**.
3. Press **Clear** (the link beside Filter) to reset back to all jobs, newest first.

### View a job's detail

1. In the list, click the **eye** icon in the Actions column for the job you want.
2. You arrive at **`/jobs/show/{id}`** showing the full record.
3. For a **failed** job, read the red **Error Details** panel; expand the stack trace if you need the technical cause.
4. For a **completed** export, use the **Download Result** button to fetch the output file.
5. Click **Back to Jobs** to return to the list.

### Investigate a failure

1. Filter the list by status **Failed**.
2. Open each failed job and read the **Error Message**.
3. If the message is not enough, expand the **Stack Trace** to see exactly where it stopped.
4. Re-run the originating action (the import, export, AI pass, etc.) from its own screen once the cause is fixed - the dashboard monitors jobs, it does not re-launch them.

### Export the list for reporting

1. On the Jobs list, click **Export CSV** (top right).
2. Your browser downloads `jobs-export.csv`.
3. Open it in a spreadsheet tool for filtering, charting or audit records.

### Clear out old jobs

1. On the Jobs list, click **Clear Old Jobs** in the filter toolbar.
2. Confirm the prompt. All finished jobs older than 30 days are deleted and a success message reports how many were removed.
3. Pending and running jobs are left untouched.

---

## Routes

| Method | URI | Action | Purpose |
|--------|-----|--------|---------|
| GET | `/jobs` | `browse` | List jobs with statistics, filters and sorting. |
| GET | `/jobs/show/{id}` | `show` | View a single job's full detail. |
| POST | `/jobs/clear-inactive` | `clearInactive` | Delete finished jobs older than the day cut-off (default 30). |
| GET | `/jobs/export-csv` | `exportCsv` | Stream the job list as `jobs-export.csv`. |

All routes are under the `web` and `auth` middleware, so you must be signed in to reach any of them.

---

## Configuration

There is little to configure - the dashboard reflects whatever jobs the rest of Heratio creates.

- **Access** - any authenticated user can reach `/jobs`. Restrict it through your normal role and access-control setup if it should be staff-only.
- **List page size** - the list shows 25 jobs per page.
- **Clear Old Jobs cut-off** - the toolbar button uses 30 days. The `clearInactive` action accepts a `days` value, so a different cut-off can be requested by the calling form.
- **Job types and statuses** - these are fixed constants defined in the jobs service, not Dropdown Manager values. New types are introduced by the code that creates jobs, not by configuration.
- **How jobs appear** - other Heratio packages enqueue work by creating job records (a job is created, then marked started, and finally completed, failed or cancelled as the work progresses). The dashboard only reads and tidies those records; it does not start, retry or cancel work from the web interface.

---

## References

- **Source package:** `packages/ahg-jobs/`
- **Controller:** `packages/ahg-jobs/src/Http/Controllers/JobsController.php`
- **Service:** `packages/ahg-jobs/src/Services/JobsService.php`
- **Views:** `packages/ahg-jobs/resources/views/browse.blade.php`, `show.blade.php`
- **Routes:** `packages/ahg-jobs/routes/web.php`
- **Tables:** `job`, `ahg_job_execution`, `object`
- **GitHub issue:** [#588](https://github.com/ArchiveHeritageGroup/heratio/issues/588)
