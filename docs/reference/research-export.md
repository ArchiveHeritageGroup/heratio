# Research OS: open-format project export (heratio#1237)

Per-project, one-click export of a researcher's whole intellectual record to
open, non-proprietary formats. Implements the founding principle "no lock-in /
the exit door is always open." Read-only over every existing research table; the
only write is an optional audit row. Ships in `packages/ahg-research`.

## Files

- `src/Services/ProjectExportService.php` - assembles the bundle and renders all
  formats.
- `src/Controllers/ProjectExportController.php` - access-gated endpoints (landing
  page, ZIP, and one route per single format).
- `routes/project-export.php` - self-contained route group, names `research.export.*`.
- `resources/views/research/project-export.blade.php` - the export page (BS5 + central theme).
- `database/install_project_export.sql` - optional `research_export_log` audit table.

## Assembly: which tables feed which section

| Section | Tables read (read-only) |
|---|---|
| Project overview | `research_project` |
| Research Design Brief | `research_question_brief`, `research_question_brief_version` |
| Claims, evidence, ledger | `research_assertion`, `research_assertion_evidence`, `research_claim_meta` |
| Decision Log | `research_decision_log` |
| Argument scaffold | `research_argument`, `research_argument_step` (claim labels resolved via `research_assertion`) |
| Method protocol | `research_method_protocol` |
| Research Memory | `research_memory_item` (sliced by `project_id`) |
| Sources | `research_bibliography`, `research_bibliography_entry` (prefers each entry's `csl_data` JSON, falls back to the flat columns) |

Every section is `Schema::hasTable`-guarded and wrapped in try/catch. A missing
table sets that section to `null`; the manifest's `omitted` map records it with a
reason, and the rest of the export proceeds. Nothing ever 500s.

## Formats produced

- **Markdown** (`project.md`) - human-readable narrative of the whole project.
- **JSON** (`project.json`) - the full assembled bundle, machine-readable.
- **BibTeX** (`sources.bib`) - valid `@type{key, ...}` entries, deduplicated keys.
- **RIS** (`sources.ris`) - valid `TY ... ER` records.
- **CSL-JSON** (`sources.json`) - an array of CSL items.
- **manifest.json** + **README.md** - included/omitted map and a guide.

Citation records are normalised once (`normaliseEntry`) into a CSL-shaped array,
then each exporter renders from that shared shape so all three citation files
agree. Author parsing honours `Family, Given`, falls back to last-token-as-family
and finally to a `literal` name.

## Where the ZIP is built

`buildZip()` writes to
`config('heratio.storage_path').'/research-export/'` (via `exportDir()`), using
`ZipArchive` into a uniquely named temp file (`heratio-research-<slug>-<stamp>-<rand>.zip`).
No hardcoded path. The controller streams it with
`response()->download(...)->deleteFileAfterSend(true)`, so the temp file is
removed after the response. If `ZipArchive` is unavailable or the directory is
not writable, the page falls back to the individual-format downloads.

## Routes

All under `research` prefix, names `research.export.*`, paths
`/research/projects/{projectId}/export/...` (two-segment or deeper, so the locked
`/{slug}` catch-all never intercepts them):

`index`, `zip`, `markdown`, `json`, `bibtex`, `ris`, `csl`.

## Audit log (optional)

`research_export_log` (project_id, format, exported_by, exported_at) records each
export. Best-effort insert guarded by `Schema::hasTable`; auto-installed on boot
from `AhgResearchServiceProvider`. This is the only table the slice ever writes
to. No existing table is altered.

## Access

Project owner, collaborators (`research_project_collaborator`), or admins
(`AclService::canAdmin`). Export is read-only and never mutates the project.
