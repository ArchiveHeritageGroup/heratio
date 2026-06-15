> Heratio Help Center article. Category: Administration / Jobs.

# Background Jobs Management

Heratio runs long-running tasks (exports, imports, bulk updates, AI processing and similar) as background jobs. The Jobs Management screen lets administrators watch those jobs, read their output, and clear out finished or failed entries.

---

## Overview

When you trigger an action that cannot finish instantly inside a single web request, Heratio records it as a job and processes it in the background. Each job has a name, an owner (the user who started it, or "System" for automated tasks), a status, and timestamps for when it was created and when it completed. Some jobs also produce output text and a downloadable result file.

The Jobs Management UI is an administrator-only dashboard for monitoring those jobs. It reads from the platform job table; it does not start jobs itself. You use it to answer questions like "is my export still running?", "why did that import fail?", and "how do I tidy up the job list?".

This package is the management interface. The separate jobs package handles the underlying job records and processing.

---

## Key features

- **Status dashboard** - four count cards at the top of the list show Total, Active, Completed, and Failed jobs at a glance.
- **Job list with filters** - a paged table of all jobs, filterable by status using the All / Active / Completed / Failed pills.
- **Sorting** - sort the list by creation date (newest first, the default) or by job name.
- **Job detail / report** - open any job to see its id, name, status, owner, created and completed times, the associated record (if any), captured output, and an error panel for failed jobs.
- **Result downloads** - when a job produced a file (for example an export), a Download button appears on its detail page.
- **Auto refresh** - a toggle on the list re-loads the page every 15 seconds so you can watch active jobs progress without manual refreshing. It pauses automatically when the browser tab is hidden.
- **Delete a single job** - remove an individual completed or failed job from the list.
- **Clear inactive** - remove all completed and failed jobs in one action, leaving only the active ones.
- **Export to CSV** - download the full job list (name, status, user, created, completed) as a spreadsheet-friendly CSV file.

A safety rule applies to removal: only jobs that have finished (Completed or Failed status) can be deleted. Active jobs are protected so you cannot delete something that is still running.

---

## How to use

### Open the dashboard

1. Sign in with an administrator account.
2. Go to **Admin -> Jobs** (route `/admin/jobs`). The route `/jobs/browse` also reaches the same screen.
3. The four count cards summarise the current job totals. The table below lists individual jobs.

### Filter and sort the list

- Use the pill buttons above the table to switch view:
  - **All** - every job.
  - **Active** - jobs that have not yet completed (still in progress).
  - **Completed** - jobs that finished successfully.
  - **Failed** - jobs that ended in an error.
- Append `?sort=name` to the URL to sort alphabetically by job name; the default `?sort=date` sorts by newest first.
- Long lists are paged. Use the pager at the bottom to move between pages.

Each row shows the job name, a colour-coded status badge (green Completed, red Error, blue In progress), the owning user, the created and completed times, and a link to the related record where one exists.

### Inspect a single job

1. In the Actions column, click the **report** (document) icon for the job you want to inspect, or open `/admin/jobs/{id}` directly.
2. The detail page lists the job id, name, status, user, created and completed timestamps, and the associated record link if the job relates to one.
3. If the job captured output, it appears in an **Output** panel. For failed jobs, any error text appears in a red **Error(s)** panel.
4. If the job produced a result file, a **Download** button is shown. Use **Back to jobs** to return to the list.

### Watch active jobs live

1. On the job list, click **Auto refresh: off** in the button row to switch it on.
2. The page then reloads every 15 seconds, so newly completed jobs and status changes appear without manual refreshing.
3. Click the button again to turn auto refresh off. You can also use the **Refresh** button for a one-time reload.

### Delete a finished job

1. In the Actions column of a Completed or Failed job, click the **trash** icon (this calls `/admin/jobs/delete/{id}`).
2. The job record is removed and you return to the list with a confirmation message.
3. Attempting to delete a job that is still active is rejected with a message; only completed or failed jobs can be removed.

### Clear all inactive jobs

1. When at least one Completed or Failed job exists, a **Clear inactive** button appears in the button row.
2. Click it (route `/admin/jobs/clear-inactive`) to remove every completed and failed job at once.
3. A confirmation message reports how many jobs were cleared. Active jobs are left untouched.

### Export the job list

1. Click **Export CSV** in the button row (route `/admin/jobs/export-csv`).
2. A file named `jobs-export.csv` downloads, containing one row per job with the columns Name, Status, User, Created, and Completed.

### Additional queue views

The package also registers queue-oriented screens at `/admin/jobs/queue-browse`, `/admin/jobs/queue-batches`, `/admin/jobs/queue/{id}`, and `/admin/jobs/report`. In the current build these render their layouts but are not yet populated with live queue data, so they will show empty tables or zero counts. The active monitoring surface is the main job list described above.

---

## Configuration

- **Access** - every route in this package sits behind the `admin` middleware, so only administrators can reach the Jobs Management screens.
- **Page size** - the number of jobs per page follows the platform-wide hits-per-page setting (from AHG Settings); there is no separate setting for this screen.
- **Status mapping** - job status is read from the platform term table. Completed and Failed are fixed internal status values; anything else with no completion time is treated as Active / In progress.
- **Auto-refresh interval** - fixed at 15 seconds in the page script; it is not a user-configurable setting.
- **Storage** - no new database tables are created. The package reads from the existing `job` table and its related records, so no install step beyond enabling the package is required.

---

## References

- Source: `packages/ahg-jobs-manage/`
- Issue: [GH #589](https://github.com/ArchiveHeritageGroup/heratio/issues/589)
- Related: the jobs package (underlying job records and processing) and AHG Settings (hits-per-page).
