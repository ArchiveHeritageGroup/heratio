# Research Claim Ledger (Research OS Stage 8, heratio#1223)

The Claim Ledger promotes the existing `research_assertion` +
`research_assertion_evidence` tables into a per-project, first-class ledger of
claims in the `ahg-research` package. It is additive: no schema is altered, and the
extra claim fields live in a new sidecar table.

## Where it lives

- Service: `packages/ahg-research/src/Services/ClaimLedgerService.php`
- Controller: `packages/ahg-research/src/Controllers/ClaimLedgerController.php`
- Routes: `packages/ahg-research/routes/claim-ledger.php` (loaded from
  `AhgResearchServiceProvider::boot()` alongside `routes/web.php`)
- Views: `packages/ahg-research/resources/views/research/claim-ledger/{index,show}.blade.php`
- Sidecar install SQL: `packages/ahg-research/database/install_claim_ledger.sql`
- Help article: `docs/help/research-claim-ledger.md`

## Data model

The core claim - text, status, confidence, project + researcher ownership - stays in
`research_assertion`, which is NEVER altered. The Claim-Ledger-specific fields live
in a new sidecar table `research_claim_meta`, keyed 1:1 by `assertion_id`:

- `evidence_type`, `confidence_level`, `provenance_kind` (original / derived /
  speculative)
- `supporting_sources`, `opposing_sources`
- `quotations` (with page references), `method_theory_link`
- `researcher_notes`, `unresolved_weaknesses`, `ethical_concerns`

The sidecar table is auto-created on boot via the
`Schema::hasTable('research_claim_meta')` + `DB::unprepared(install_claim_ledger.sql)`
pattern, wrapped in one outer try/catch in the service provider (per the
CI-schema-hastable reference pattern). Evidence links reuse
`research_assertion_evidence` unchanged.

## Claim lifecycle (status values)

`idea` -> `working` -> `supported` / `contested` / `weak` / `rejected` ->
`needs_evidence` -> `publishable`. Status is stored in `research_assertion.status`
(varchar(46)); legacy values (`proposed`, `verified`, `disputed`) still render with
their own badge colours.

## Evidence

Evidence candidates come from the project's own bibliography
(`research_bibliography`), annotations (`research_annotation`), and collection items
(`research_collection_item` joined to `research_collection`). Attaching inserts a row
into `research_assertion_evidence` (`source_type` in
bibliography/annotation/collection_item, `relationship` in
supports/opposes/contextualizes). No duplicate links are created.

## Founding principle: no unsupported claim passes silently

The ledger index surfaces two review lists from the service:

- `claimsWithoutCitation($projectId)` - claims with zero evidence rows
  (LEFT JOIN ... WHERE evidence.id IS NULL).
- `claimsOverDependent($projectId)` - claims with >= 2 evidence rows but only ONE
  distinct source (`COUNT(DISTINCT source_type:source_id) = 1`).

## Safety / constraints

- Read-only over the live DB except the boot-time `CREATE TABLE IF NOT EXISTS` of
  the sidecar. No INSERT/UPDATE/ALTER against existing data at install time.
- Every service query is `Schema::hasTable`-guarded and try/catch-wrapped; the
  ledger degrades to empty state rather than 500 on a partial install.
- `research_assertion` is never altered. `getSidebarData` was not edited - the
  Command Centre wiring is handled separately.
- Routes live under the `research.` namespace
  (`research.claims.index`, `research.claims.show`, `research.claims.store`,
  `research.claims.update`, `research.claims.status`, `research.claims.destroy`,
  `research.claims.evidence.attach`, `research.claims.evidence.detach`). All paths
  are two-segment or deeper (`/research/project/{id}/claims/...`) so the locked
  `/{slug}` catch-all never intercepts them.
- International / jurisdiction-neutral; no SA-specific assumptions.
