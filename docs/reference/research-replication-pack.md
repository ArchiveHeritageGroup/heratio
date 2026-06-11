# Research OS - Replication Pack (heratio#1238, moonshot 22)

Per-project "one click produces everything needed to replicate the study", assembled READ-ONLY from existing Research OS slices. Lives in `packages/ahg-research`. No existing table is altered; the only write is an optional audit line.

## What it assembles (slice -> output file)

| Source slice | Tables (read-only) | Output in the ZIP |
|---|---|---|
| Method Studio (#1231) | `research_method_protocol` | `method-protocol.json` |
| Analysis Bridge (#1234) | `research_analysis_result`, `research_analysis_result_claim` | `analysis-results.json` (each result + its provenance + linked claims) |
| Decision Log (#1224) | `research_decision_log` | `decision-log.json`, `decision-log.csv` |
| Claim Ledger (#1223) | `research_assertion`, `research_assertion_evidence` | `claims-and-evidence.json` (each claim + its evidence) |
| Analysis provenance | `research_analysis_result` (`source_data_ref`, `source_data_version`, `code_ref`, `artifact_path`) | `data-code-references.json` (paths/repos only; bytes withheld) |
| (always) | - | `manifest.json`, `README.md` |

Every section is `Schema::hasTable`-guarded and wrapped in try/catch. A missing slice, or a slice with no rows for the project, becomes an entry under `omitted` in the manifest rather than a 500.

## Manifest and omission handling

`manifest.json` lists:
- `included[]` - human-readable lines naming each section that made it in and its row count.
- `omitted[]` - each section that was left out, with the reason (slice not installed, or nothing recorded), plus a standing line that underlying data/code bytes are referenced only.
- `ethics_note` - the standing ethics/consent statement.
- `project{}` - id, title, type, institution, supervisor, funding, grant, ethics approval.

## Ethics / consent

The pack contains metadata, provenance and the reasoning trail. It does NOT bundle data files or code bytes; `data-code-references.json` carries paths/repositories/versions and an `access_note`, with `bytes_included: false`. Restricted/embargoed/consent-limited material is withheld by default and surfaced under `omitted`. This makes the default safe: an ethics or embargo restriction is honoured without the user having to opt out.

## Where the ZIP is built

`config('heratio.storage_path') . '/research-replication/{projectId}/replication-pack-{slug}-{rand}.zip'` - never a hardcoded path. Built with PHP `ZipArchive` (`addFromString` over the in-memory file map), streamed via `response()->download(...)->deleteFileAfterSend(true)`, so the temp file is removed after send. Entry names are traversal-guarded.

## Files added

- `src/Services/ReplicationPackService.php` - summary(), assemble(), build(), zipFiles(), section readers, manifest/README, optional `logBuild()`/`recentBuilds()`.
- `src/Controllers/ReplicationPackController.php` - `index` (page) + `build` (stream ZIP). Self-contained project/researcher resolution; does not touch `getSidebarData`.
- `routes/replication-pack.php` - self-contained `research.replication.*` group under `/research/projects/{projectId}/replication`. Catch-all-safe (the `research` prefix is excluded by the IO slug resolver).
- `resources/views/replication/index.blade.php` - Bootstrap 5 page: what is included (per-section counts + availability), ethics note, Build & download button (disabled when nothing is recorded), recent builds. Empty-states throughout; never 500s.
- `database/install_replication_pack.sql` - optional `research_replication_log` (project_id, built_by, built_at), `CREATE TABLE IF NOT EXISTS`.
- `docs/help/research-replication-pack.md`, this reference.

## Integration notes (reconciled by the integrator)

The slice is self-contained. At integration, wire into `AhgResearchServiceProvider`:
- add `['research_replication_log', 'install_replication_pack.sql', null]` to the ROS auto-install map;
- add `'replication-pack'` to the ROS route-file list;
- (optional) a sidebar/project-page link to `research.replication.index`.

No `getSidebarData` edit is required by the slice itself.
