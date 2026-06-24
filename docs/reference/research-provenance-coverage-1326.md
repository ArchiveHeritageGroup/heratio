# Research module provenance coverage (#1326)

Closes the coverage gap behind the research provenance infrastructure: the
AI/inference layer (`ahg-provenance-ai` / `ahg_ai_inference` + `InferenceService`)
and the human-action layer (`research_activity_log`) already existed, but not
every mutation path wrote to them. This change drains both backlogs.

## Human-action provenance (research_activity_log)

A new dependency-free trait `AhgResearch\Concerns\LogsResearchActivity`
(`packages/ahg-research/src/Concerns/LogsResearchActivity.php`) provides:

```php
protected function logResearchActivity(
    string $activityType,            // create | update | delete | accept | reject | approve | merge | ...
    ?string $entityType = null,      // e.g. 'milestone', 'journal', 'workspace'
    ?int $entityId = null,
    ?string $entityTitle = null,
    ?array $details = null,
    ?int $projectId = null
): void
```

It resolves the acting researcher from the auth user, captures
session/IP/user-agent, writes one `research_activity_log` row, and **never
throws** (provenance must not break the mutation it records).

All 28 previously-unlogged research controllers now `use` the trait and call it
on the success path of every state-mutating method (store/update/destroy/
accept/reject/approve/merge and variants). Read-only methods
(index/show/list/view/export/search) and AI-suggestion endpoints that persist
nothing are intentionally not logged.

## Enrichment provenance (ahg_ai_inference)

`FieldAlertService` and `ImpactTrackingService` call public bibliographic APIs
(Crossref, OpenAlex, Crossref Event Data). These are **direct, deterministic
public-API calls and are correctly NOT routed through the AHG AI gateway** - but
every external enrichment now records an auditable inference row via
`InferenceService::record(new InferenceRecord(serviceName: 'ENRICHMENT', ...))`.
Logging is best-effort and wrapped so a provenance failure never breaks the
enrichment.

## Ratchet test

`tests/Feature/ResearchProvenanceCoverageTest.php` (a static-source ratchet, no
DB) guards both invariants and only allows the backlog to shrink:

- a new research controller that mutates state without logging
  (`research_activity_log`, `logResearchActivity`, or the `LogsResearchActivity`
  trait) fails the build;
- a new service that calls Crossref/OpenAlex without `InferenceService` /
  `ahg_ai_inference` fails the build.

Both `$activityLogBacklog` and `$enrichmentProvenanceBacklog` are now empty. The
guard is the safety net that keeps coverage at 100% going forward.
