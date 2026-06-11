# Quick Capture Inbox (Research OS Stage 0)

heratio#1228, part of the Research OS epic (#1222). The **Inbox** is the
frictionless capture front door of the research portal: ideas arrive from
anywhere and land in one timestamped, origin-tagged list so nothing is lost.
Triage (mark-triaged / archive / move-to-project) happens later. Page lives at
**/research/inbox** (`research.inbox.index`), auth-gated and scoped to the
signed-in researcher. International / jurisdiction-neutral; no MySQL ENUM
(VARCHAR + Dropdown Manager). Additive build - no existing tables or sidebar
code were touched.

## What it does
- One-tap capture form (title, note/body, kind, origin, source URL, optional
  file up to 50 MB).
- Generic capture POST endpoint that the web form, the mobile surface, and the
  email-in / web-clipper integration points all share.
- Triage actions: mark-triaged, archive, restore, and move-to-project (links an
  item to one of the researcher's projects and flips status to `triaged`).
- Filters by kind and by status (Inbox / Triaged / Archived) with live counts.
- Empty-state ("Your inbox is empty - capture an idea"); guarded queries never
  500.

## Data model
Table `research_inbox_item` (`database/install_inbox.sql`, CREATE TABLE IF NOT
EXISTS, auto-installed on boot):

| Column | Notes |
|---|---|
| `id` | BIGINT PK |
| `researcher_id` | owning researcher (every read/write is scoped to this) |
| `project_id` | nullable; set when triaged into a project |
| `kind` | VARCHAR - note\|voice\|email\|clip\|photo\|file (`research_inbox_kind` dropdown) |
| `title` | nullable VARCHAR(500) |
| `body` | TEXT - note / transcription slot |
| `origin` | VARCHAR - web\|email-in\|clipper\|mobile (`research_inbox_origin` dropdown) |
| `source_url` | nullable; only persisted when it is a valid http(s) URL |
| `attachment_path` | nullable; path **relative** to config('heratio.storage_path') |
| `status` | VARCHAR - inbox\|triaged\|archived (`research_inbox_status` dropdown) |
| `captured_at`, `created_at` | timestamps |

Dropdown values seed from `database/seed_inbox_dropdowns.sql` (INSERT IGNORE),
run once when the table is first created.

## Where it lives
- `packages/ahg-research/src/Services/InboxService.php` - all data access.
  Every query is `Schema::hasTable`-guarded and try/catch wrapped (degrades to
  empty / false rather than throwing). Researcher-scoped throughout;
  `moveToProject` / capture verify project ownership before linking.
- `packages/ahg-research/src/Controllers/InboxController.php` - index, capture,
  triage, archive, restore, moveToProject. Validation on every write. The
  capture endpoint honours JSON requests (returns `{ok,id}`) so a mobile client
  or browser extension can POST without a full page load.
- `packages/ahg-research/resources/views/research/inbox.blade.php` - Bootstrap 5
  + central theme, `theme::layouts.2col`, research sidebar include.
- Routes in the **new** file `packages/ahg-research/routes/inbox.php`, loaded
  from `AhgResearchServiceProvider::boot()` in the same `web` group. Names:
  `research.inbox.index|capture|triage|archive|restore|move`. All auth-gated.

## File uploads
Attachments are stored under
`config('heratio.storage_path').'/research-inbox/{itemId}/'` - the central
Heratio storage path, never a hardcoded directory. The DB stores only the path
relative to that root, so the install stays portable. Filenames are slugged with
a random suffix; the upload dir is namespaced per item. `attachmentAbsolutePath()`
resolves the stored relative path back to disk (with a `..` traversal guard).

## Voice / email-in / clipper
Deliberately lightweight, as a capture surface rather than full plumbing:
- **Voice** - the `body` column is the transcription slot; the front-end (or a
  future STT job) writes the text into it. No STT engine is bundled.
- **Email-in** and **web clipper** - documented integration points that POST to
  `research.inbox.capture` with `origin=email-in|clipper`. The mail server /
  browser extension itself is out of scope and is not built here.

## Auto-install
`AhgResearchServiceProvider::boot()` has a `$this->app->booted()` block that, on
first boot, creates `research_inbox_item` from `install_inbox.sql` and seeds the
inbox dropdowns - one outer try around `Schema::hasTable` + `DB::unprepared`,
per the `reference_ci_schema_hastable` pattern. The InboxService singleton is
registered in `register()`.

## Constraints honoured
- AHG / Plain Sailing / AGPL headers; @copyright "Plain Sailing Information
  Systems"; no em-dashes.
- No MySQL ENUM (VARCHAR + Dropdown Manager).
- File paths from `config('heratio.*')`, never hardcoded.
- Read-only over the live DB except the boot auto-create and the user's own
  inbox writes to the new table; no ALTER, no touching existing tables' data.
- `getSidebarData` was not edited; the sidebar include was not modified.
