# Research Writing Studio (Research OS Stage 13)

Per-project write-as-you-go editor in `packages/ahg-research`, connected to the
Claim Ledger and the project bibliography. Part of Research OS epic heratio#1222.
Documents hold ordered sections; a save-version snapshots the whole document as
Markdown into a version history. Citing a claim or pulling a source reads the
existing tables only and never writes them.

## Tables (all NEW, no ALTER on existing tables)

Created by `database/install_writing_studio.sql` (CREATE TABLE IF NOT EXISTS),
auto-installed on boot via the ROS `$installs` map in
`AhgResearchServiceProvider` (entry `research_writing_doc`).

- `research_writing_doc` - id, project_id, title, doc_type (VARCHAR:
  thesis_chapter | article | review | section | other), status (VARCHAR: draft |
  in_review | final | archived), created_by, created_at, updated_at.
- `research_writing_section` - id, doc_id, heading, body (LONGTEXT), sort_order,
  updated_at.
- `research_writing_version` - id, doc_id, version_no, snapshot (LONGTEXT), note,
  created_by, created_at.

doc_type and status are VARCHAR, not ENUM (per the Dropdown Manager rule).

## Read-only over existing tables

- Cite a claim reads `research_assertion` (the Claim Ledger spine) scoped by
  `project_id`. Inserts the claim text plus a `[Claim #N]` marker into a section.
- Pull a source reads `research_bibliography_entry` joined to
  `research_bibliography` on `bibliography_id`, scoped by `b.project_id`. Inserts
  a formatted reference plus a `[Source #N]` marker.

Neither path ever writes those tables. Only the three `research_writing_*` tables
are written, and only by the researcher's own editing.

## Routes

Self-contained `routes/writing-studio.php`
(`Route::prefix('research')->name('research.')->middleware(['web','auth'])`),
loaded from the provider's ROS route `foreach` list (entry `writing-studio`).
All paths are `/research/projects/{projectId}/writing/...` (three-plus segments,
so the locked `/{slug}` catch-all never intercepts them). Route names are
`research.writing.*`: index, store, edit, update, destroy, export,
sections.add/save/delete/ai, cite, source, versions.save, versions, versions.show.

## Service / Controller

- `Services/WritingStudioService.php` - all DB access, every query
  Schema::hasTable-guarded and try/catch wrapped (degrades to empty state, never
  500). Markdown export (`exportMarkdown`) is reused as the version snapshot.
- `Controllers/WritingStudioController.php` - thin; resolves project + researcher
  context defensively, mirrors the Claim Ledger controller shape.

## AI drafting

`WritingStudioService::aiDraftSection()` routes strictly through
`AhgAiServices\Services\LlmService::complete()` (the AHG gateway abstraction),
never a direct node port. The draft is grounded in the project's claims and
sources, labelled `AI-assisted draft (review required before use)`, and shown for
researcher approval in the editor - it is NEVER saved automatically. The studio
is fully usable with AI off (`aiAvailable()` gates the UI).

## Provider edits (the only two additive provider changes)

1. `$installs` map: `['research_writing_doc', 'install_writing_studio.sql', null]`.
2. ROS route `foreach` list: `'writing-studio'`.

No other provider changes. `CommandCentreService`, `_command-centre.blade.php`,
`routes/web.php`, and `getSidebarData` were intentionally not touched.
