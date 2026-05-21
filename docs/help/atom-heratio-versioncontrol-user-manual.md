> Heratio Help Center article. Category: User Manual.

# Version Control — User Manual

**Plugin:** `ahgVersionControlPlugin` (AtoM) / `ahg-version-control` (Heratio)
**Version:** 0.1.0
**Audience:** archivists, records managers, descriptive cataloguers

## What this plugin gives you

Every time you save an information object or an authority record (actor), the system captures a full snapshot. You can:

- See the version history of any record on a dedicated page.
- Compare any two versions side-by-side with word-level highlighting of what changed.
- Restore a record to a prior version with a single click — useful if a save went wrong or you need to roll back a vandalised edit.
- Trust that classified records are protected: restoring a Confidential record needs Confidential clearance.

You don't have to do anything to "turn versioning on" — it happens automatically on every save.

## Where to find version history

Three entry points:

### 1. The "Version history (N)" banner on the record view

When you open an information object or actor page, a small banner appears near the top:

> **🕘 Version history (5)**

The number is how many versions exist. Click it to open the full history.

### 2. The standalone history URL

You can navigate directly:

- `https://your-atom-host/version-control/information_object/{id}`
- `https://your-atom-host/version-control/actor/{id}`

### 3. From the version detail page

Once on a version detail page, the breadcrumb has "All versions" to jump back to the list.

## The Versions list page

You'll see a table:

| ☐ | Version | Date | User | Summary | Changes | |
|---|---|---|---|---|---|---|
| ☐ | **v5** | 2026-05-11 14:23 | jsmith | Title revision | 1 field(s) `i18n.en.title` | View |
| ☐ | **v4** ★ restore | 2026-05-10 09:11 | tess | Restored from v2 | 3 field(s) `i18n.en.scope`, ... | View |
| ☐ | **v3** | 2026-05-09 16:02 | tess | Added subject access points | access_points | View |
| ☐ | **v2** | 2026-05-09 11:30 | tess | Initial draft refinement | i18n.en.title | View |
| ☐ | **v1** | 2026-04-15 10:00 | jsmith | Initial backfill (v1 baseline) | — | View |

Reading the columns:

- **Version** — the version number. Higher = newer. Restored versions get a yellow "restore" badge.
- **Date** — when the snapshot was captured.
- **User** — who saved it (blank for backfilled baselines).
- **Summary** — auto-generated description of the save (or the change summary the user entered).
- **Changes** — number of snapshot-tracked fields that differ from the prior version, with the first three field paths shown. "no archival metadata changes" means the save fired but no field we track actually changed (e.g. a publication-status update lands in a related table, not in our snapshot).

## Comparing two versions

1. Tick the checkboxes on **exactly two** versions in the list.
2. Click **Compare selected** at the top of the list.
3. The diff page opens with sections:
   - **Base fields** — primary attributes like identifier, level of description, repository
   - **Localized fields (en, af, fr, …)** — titles, scope, notes etc. per culture
   - **Access points added/removed** — subject, place, name, genre terms
   - **Events added/removed** — creation, accession events
   - **Relations added/removed** — actor links
   - **Physical objects added/removed**
   - **Custom field changes**

Long text fields render with inline word-level diff. Green = inserted, red = deleted. Example:

> The notes are fictional<ins> yet remarkably</ins><del>but</del> detailed and well-documented enough to allow…

If both versions are identical (e.g. you clicked diff on the same version twice) the page shows "No differences between these two versions."

## Viewing a single version

Click the version number or **View** button to see:

- Version metadata: number, date, user, change summary
- The list of **changed fields** versus the prior version
- The full **snapshot** organised by:
  - Base fields (collapsible)
  - Localized fields per culture (collapsible per culture)
  - Counts of access points, events, relations, physical-object links, custom fields

From here you can:

- **Diff vN-1 → vN** — see exactly what made this version different
- **View record** — go back to the live record
- **All versions** — return to the history list
- **Restore this version** — see the next section

## Restoring a previous version

Open the version you want to roll back to and click **🔄 Restore this version (vN)**.

A confirmation modal explains what will happen:

- The current state of the record will be **overwritten** with the snapshot from vN.
- A **new version row** will be created marking the restore. Your action is auditable.
- Scope: base record + descriptive metadata (titles, scope, notes, all cultures) + custom fields **are** restored. Access points, events, relationships, and physical-object links **stay as they are now** (planned enhancement for the next release).

After confirming, you're redirected to the version list with a success message:

> Restored from v2. New version v6 created.

### What can stop a restore

- **You don't have the `version.restore` permission.** By default, Editors and Administrators do; Contributors and Translators don't. You'll see a 403 page.
- **The record is classified** and you don't have matching clearance. You'll see a 403 with a clear message like *"This record is classified Confidential (level 3); your clearance level is 0. Restore is not permitted."* — contact your records officer to be granted the clearance.
- **Both checks combine.** Even an Editor needs `version.restore_classified` AND a sufficient clearance level to restore a classified record.

## What gets versioned

Every save of an information object or an actor through the web UI or via API. Specifically:

- Direct edits via the standard `/{slug}/edit` form
- Quick-edit AJAX endpoints (e.g. update publication status)
- API writes through `ahgAPIPlugin`
- Imports via `ahgIngestPlugin` — note: ingest produces **one v1 per imported record**, not one per intermediate field-fill during the bulk save loop (the ingest pipeline calls `VersionContext::skip()` for the in-loop saves and lets the final state become v1)

What doesn't capture a version:

- Internal indexer touches (search reindex)
- CLI jobs that explicitly opt out
- Reads/views/exports — these go to the audit trail only, not version history

## How the change summary is written

- For web UI saves: auto-generated as `"Updated via {module}"` (e.g. `"UpdatePublicationStatus via informationobject"`).
- For restores: `"Restored from v{N}"` with `is_restore=1` and `restored_from_version=N` so the version row carries the lineage.
- For backfilled v1 baselines: `"Initial backfill (v1 baseline)"`.

There's currently no free-text comment box on every save — that's a planned enhancement.

## Reading the changed-fields list

The **Changes** column on the list and the **Changed fields** entry on the detail use a dotted-path notation:

- `base.identifier` — the record's identifier on the base table changed
- `i18n.en.title` — the English title changed
- `i18n.af.scope_and_content` — the Afrikaans scope changed
- `access_points` — at least one access point was added or removed
- `relations` — at least one relation was added or removed

Curators can mentally translate these to "what did this save actually change to my records?". The full structured diff (with the actual values) is on the Compare page.

## Permissions cheat-sheet

| Role | List | Diff | Restore | Restore classified |
|---|---|---|---|---|
| Administrator | ✓ | ✓ | ✓ | ✓ |
| Editor | ✓ | ✓ | ✓ | ✓ (also needs clearance) |
| Contributor | ✓ | ✓ | — | — |
| Translator | ✓ | — | — | — |
| Authenticated only | — | — | — | — |
| Anonymous | — | — | — | — |

An admin can grant or revoke these per-user via the `acl_permission` table (the AtoM admin UI doesn't yet surface custom version permissions).

## Troubleshooting

**"I edited a record but no new version appeared."**
The save event might not have reached the plugin's listener. Confirm the plugin is enabled (`extension:enable ahgVersionControlPlugin`) and the cache is cleared (`php symfony cc`). If your edit was via an API or bulk-import, check whether `VersionContext::skip()` was called by that code path — if so, that's intentional.

**"The Restore button gave me a 403."**
Two possibilities:
1. You don't have `version.restore` permission. Ask an admin.
2. The record is classified and you don't have matching clearance. Ask the records officer.

**"After a restore some things didn't come back."**
This release restores the base record + i18n + custom fields. Access points, events, relations, and physical-object links are NOT restored — they stay as they were before you clicked Restore. This is in the confirmation modal but easy to miss. The full restore of these is the next planned enhancement.

**"Old versions are taking up too much disk."**
Talk to your admin about setting `version_control.retain_count` or `version_control.retain_days` in the `ahg_settings` table. The v1 baseline is always preserved; the most-recent N versions are always preserved; anything older AND outside the count window gets pruned by the nightly `version:prune` job.

---

*The Archive and Heritage Group (Pty) Ltd · johan@theahg.co.za*
