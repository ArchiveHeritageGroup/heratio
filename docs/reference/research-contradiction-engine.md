# Research Contradiction Engine (heratio#1236)

Per-project Contradiction Engine in `packages/ahg-research`. Research OS moonshot
17. It scans a project's claim ledger for contradictions no human holds in working
memory, persists findings the user can dismiss/resolve, and offers an optional,
user-triggered AI deepening pass through the AHG AI gateway.

## What it reads (read-only over the live DB)

- `research_assertion` - the claim: `subject_label` / `object_value` / `object_label`
  (text), `status`, `confidence` (decimal), `version`, `predicate`.
- `research_assertion_evidence` - evidence links: `assertion_id`, `source_type`,
  `source_id`, `relationship` (supports / opposes / contextualizes).
- `research_claim_meta` - sidecar: `confidence_level`, `supporting_sources`,
  `opposing_sources`, etc.

None of these tables are ever altered. No `ALTER`, no writes to existing tables.

## What it owns (the only table it writes)

`research_contradiction` - created idempotently with `CREATE TABLE IF NOT EXISTS`
from `database/install_contradiction_engine.sql`, auto-installed in
`AhgResearchServiceProvider::boot()` via the Schema::hasTable + DB::unprepared
pattern.

Columns: `id`, `project_id`, `claim_a_id`, `claim_b_id` (nullable), `kind`
(VARCHAR), `signature` (VARCHAR, stable per-project de-dup key), `detail`,
`severity` (VARCHAR low/medium/high), `status` (VARCHAR open/dismissed/resolved),
`source` (VARCHAR heuristic/ai), `created_at`, `updated_at`. Unique key on
(`project_id`, `signature`) makes re-scans upsert rather than duplicate. All
enumerated columns are VARCHAR - never ENUM (Dropdown Manager pattern).

## Heuristic scan rules (PHP, in `ContradictionEngineService::scan`)

The scan loads all of a project's claims plus their evidence and sidecar meta in
batched queries, then applies four rules:

1. **opposing_status** - pairwise over claims. A claim's status is mapped to a
   polarity (positive: supported/verified/publishable/confirmed; negative:
   rejected/disputed/contested/weak/refuted). A pair with opposite polarities AND
   >= 2 shared significant keywords (length >= 4, stopwords removed) is a finding.
   Severity high when >= 4 shared keywords, else medium.
2. **shared_source_conflict** - buckets `research_assertion_evidence` rows by
   `source_type:source_id`. If a single source has a `supports` link to one claim
   and an `opposes` link to a different claim, that is a high-severity finding.
3. **confidence_drop** - per claim, no history table exists, so it reads current
   state: (i) `version > 1` AND a negative status AND stored `confidence >= 0.70`
   (weakened but confidence not brought down); (ii) numeric `confidence` outside
   the band implied by the sidecar `confidence_level` label.
4. **definition_drift** - indexes claims by their leading significant term; a
   positive/negative pair sharing that lead term but with LOW body overlap (<= 2)
   signals the term carries two meanings. Low severity.

Findings persist via a stable `signature` = `kind:sortedClaimIds`. Re-detected
findings refresh detail/severity; dismissed/resolved findings are never silently
reopened.

## Optional gateway AI path

`ContradictionEngineService::aiDeepen` is called ONLY from the user-triggered
`aiScan` controller action - never automatically. It builds a prompt listing the
project's claims (capped) and calls
`app(\AhgAiServices\Services\LlmService::class)->complete($prompt, [...])` - the
same gateway abstraction the Research Copilot uses. No node port is ever
contacted. The model returns pipe-delimited `ID_A|ID_B|severity|explanation`
lines; the parser rejects any claim id not in the supplied set, so the model
cannot invent claims. Findings persist with `kind='ai_flagged'`, `source='ai'`,
and a `[AI - via gateway]` prefix on the detail. Wrapped in try/catch; gateway
failure returns a friendly message, never a 500.

## Routes (self-contained file)

`routes/contradiction-engine.php` carries its own
`prefix('research')->name('research.')->middleware(['web','auth'])` group, loaded
plainly from the provider so `routes/web.php` is untouched. Names under
`research.contradictions.*`; paths `/research/projects/{projectId}/contradictions/...`
- three segments deep, so the locked `/{slug}` catch-all never intercepts them.

| Name | Method | Path |
|---|---|---|
| research.contradictions.index | GET | /research/projects/{projectId}/contradictions |
| research.contradictions.scan | POST | .../contradictions/scan |
| research.contradictions.aiScan | POST | .../contradictions/ai-scan |
| research.contradictions.dismiss | POST | .../contradictions/{findingId}/dismiss |
| research.contradictions.resolve | POST | .../contradictions/{findingId}/resolve |
| research.contradictions.reopen | POST | .../contradictions/{findingId}/reopen |

## View

`resources/views/research/contradictions/index.blade.php` extends
`theme::layouts.2col`, includes `research::research._sidebar` (sidebarActive
'projects'), and renders Bootstrap 5 with central theme classes. Severity badges,
the two claims side by side linked to the Claim Ledger (`research.claims.show`),
dismiss/resolve/reopen buttons, status filter pills, a **Run scan** button, and
an explicit empty state ("No contradictions detected"). Defensive throughout -
the report never 500s when a claim is missing or a table is absent.

## Files added

- `database/install_contradiction_engine.sql`
- `src/Services/ContradictionEngineService.php`
- `src/Controllers/ContradictionEngineController.php`
- `routes/contradiction-engine.php`
- `resources/views/research/contradictions/index.blade.php`
- `docs/help/research-contradiction-engine.md`
- `docs/reference/research-contradiction-engine.md`

Plus a small additive edit to `AhgResearchServiceProvider::boot()` (install block
+ route-group load). `getSidebarData` is NOT edited; no ALTER of existing tables.
