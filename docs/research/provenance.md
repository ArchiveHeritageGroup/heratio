# Provenance in the Research module

This document records the current provenance surface in the Research package, the important gaps observed in code and tests, and an actionable enhancement roadmap with acceptance criteria. It is intentionally grounded in the codebase: file paths and table names are listed where relevant so the guide is traceable rather than aspirational.

Status: very good

---

## 1) What exists today (code-grounded facts)

- Tables and storage
  - ai_provenance — central table used across the product to record AI call provenance (provider, prompt, response, confidence, accepted/rejected).
  - research_activity_log — research-side action log for human actions (where present).
  - research_ai_disclosure_log / research_ai_suggestions — per-slice logs referenced by some flows.

- Key files and call sites (examples)
  - Writing Studio AI-draft endpoint: packages/ahg-research/src/Controllers/WritingStudioController.php (aiDraft / aiSuggest endpoints).
  - Research audit / activity: packages/ahg-research/src/Services/ResearchService.php and research_activity_log related code paths.
  - Bibliography enrichment / PDF auto-extract: packages/ahg-research/src/Services/BibliographyService.php (calls to Crossref/OpenAlex and local enrichers).
  - Question Builder / Argument Builder / Source Triage: various controllers under packages/ahg-research/src/Controllers/ (search for *QuestionBuilder* / *ArgumentBuilder* / *Triage* names).
  - RiC exporter: packages/ahg-ric/src/Exporters and the Research-side RiC glue in packages/ahg-research/src/Services/ExhibitionRicService.php or similar.

- UI
  - A number of slices show recent activity or audit entries in their views (partial support). The project studio and some slices include limited feeds.
  - No standardised, consistent "Provenance" partial is present across claim / writing-section / bibliography show pages.

- Tests
  - Some tests in packages/ahg-research/tests/Feature exist (ResearchUserProvisionerTest, ResearchWorkspaceSmokeTest), but there is no comprehensive provenance test coverage ensuring ai_provenance rows are created across every slice.

---

## 2) Gaps found (concrete)

- Documentation vs code mismatch
  - Existing provenance.md (help doc) describes the model conceptually but does not list precise on-disk tables, blade partials, or the controller call-sites responsible for writes.

- Human-action provenance missing in places
  - Many controllers mutate resources (approve/reject, accept suggestion, manual edit) but do not always write a row to research_activity_log (or do so inconsistently).

- AI calls not consistently recorded
  - Several AI entry points (Writing Studio, Question Builder, Argument Builder, Source Triage, Bibliography enrichment) may not always call a single consistent helper that writes ai_provenance. Without a single helper, fields recorded vary.

- Bulk operations and migrations are not tracked
  - Bulk-import jobs produce many item changes but do not currently group provenance entries by batch, making it harder to trace batch origin.

- RiC export lacks provenance mapping
  - The RiC exporter does not map ai_provenance or research_activity_log rows into RiC Event entities consistently.

- UI visibility and discoverability
  - No standardised provenance card/partial is rendered on claim / writing-section / bibliography views that aggregates the item-level provenance.

- Tests
  - No cross-slice feature tests assert that AI or human actions produce the expected provenance rows.

---

## 3) Incomplete code (concrete file pointers)

- Writing Studio AI-draft contract
  - File(s): packages/ahg-research/src/Controllers/WritingStudioController.php (or the package-specific controller where aiDraft lives).
  - Shortcoming: ai call may not call a central `recordAiCall()` helper; acceptance flow may apply AI output without recording an accepted flag.

- Research audit slice
  - File(s): packages/ahg-research/src/Services/ResearchService.php and places where research_activity_log is referenced.
  - Shortcoming: not all mutating endpoints call a shared ResearchAuditService::record(...) helper.

- Bibliography enrichment
  - File(s): packages/ahg-research/src/Services/BibliographyService.php
  - Shortcoming: external calls to Crossref/OpenAlex not always audited via ai_provenance or an external-call log.

- Cross-slice memory store
  - File(s): packages/ahg-research/src/Services/MemoryService.php (or research_memory write sites)
  - Shortcoming: the memory table is write-heavy but lacks a human-visible provenance feed linking entries to originating slice and user.

- Inbox subscription
  - File(s): packages/ahg-research/src/Services/NotificationService.php and inbox views
  - Shortcoming: provenance events are not consistently emitted to the inbox (partial subscription only).

- RiC exporter
  - File(s): packages/ahg-ric/src/Exporters/ResearchRicExporter.php (or the package export glue)
  - Shortcoming: no mapping for ai_provenance/research_activity_log → RiC Event nodes.

---

## 4) Suggested enhancements (actionable)

Below are recommended enhancements grouped by priority. Each includes where to change and acceptance criteria.

### High priority

1. Centralised AI provenance helper
   - Implementation
     - Create: packages/ahg-research/src/Services/ResearchAiService.php (or extend an existing AI service).
     - Add method: recordAiCall(string $slice, int $targetId = null, array $meta = [], string $prompt, string $model, string $response, float $confidence = null, ?int $userId = null)
     - Ensure every LLM/TTS call in Writing Studio, Question Builder, Argument Builder, Source Triage, Bibliography enrichment calls this helper.
   - Acceptance criteria
     - Feature tests exist that call each slice's AI endpoint and assert a row in ai_provenance with correct slice + targetId + prompt + response.

2. ResearchAuditService (human actions)
   - Implementation
     - Create service: packages/ahg-research/src/Services/ResearchAuditService.php with method record(actorId, action, targetType, targetId, payload)
     - Replace ad-hoc writes to research_activity_log across controllers with calls to the service.
   - Acceptance criteria
     - Tests that perform approve/reject/update actions produce research_activity_log entries with actor and timestamp.

3. UI: Provenance partial
   - Implementation
     - Add partial: packages/ahg-research/resources/views/research/partials/provenance_card.blade.php
     - Include this partial on claim show, writing section show, and bibliography item show pages.
   - Acceptance criteria
     - A user viewing a claim sees a chronological list of provenance entries for that claim; AI suggestions appear under a separate AI section with Accept/Reject controls.

### Medium priority

4. Batch provenance grouping
   - Implementation
     - Add field migration_batch_id to ai_provenance & research_activity_log.
     - When performing bulk imports, create a batch provenance row and link per-row entries to that batch_id.
   - Acceptance criteria
     - Bulk import tests assert creation of a batch provenance record and per-row linkage.

5. RiC mapping
   - Implementation
     - Extend packages/ahg-ric exporter to map ai_provenance / research_activity_log rows to RiC Event entities with agent/time/description.
   - Acceptance criteria
     - A project RiC export contains Event nodes for provenance rows; tests assert presence.

6. Provenance-driven undo & redact
   - Implementation
     - Add a `revert` helper that computes and applies reverse deltas for simple changes (status, text replace) based on stored provenance entries.
     - Add `redact` admin action that anonymises PII while preserving provenance rows (redactor actor + reason saved in payload).
   - Acceptance criteria
     - Admin can revert a recent edit and the data returns to the prior state; redaction test leaves provenance row intact with redactor metadata.

### Low priority

7. Retention policy & prune command
   - Implementation
     - Add settings: research.provenance_retention_days. Add `php artisan research:prune-provenance --dry-run` command.
   - Acceptance criteria
     - Dry-run outputs items that would be pruned; running without --dry-run deletes older records beyond retention while exporting them to a file.

8. Observability & metrics
   - Implementation
     - Emit metrics on LLM calls (count, average latency, acceptance rate) to the observability service.
   - Acceptance criteria
     - Admin dashboard shows AI call count/latency and acceptance rate over time.

9. i18n & accessibility
   - Implementation
     - Move provenance strings to lang/en/research.php and ensure the partial is keyboard navigable and screen-reader friendly.
   - Acceptance criteria
     - ARIA labels present and aXe/pa11y audit shows no high-severity issues.

---

## 5) Minimal implementation plan (first 2 days)

Day 1 (safety + foundation)
- Add ResearchAuditService and ResearchAiService::recordAiCall. Replace 3–4 high-impact controller spots (Writing Studio, Bibliography enrichment, Question Builder) to use the new helpers.
- Add ai_provenance assertions in a single new feature test per slice.

Day 2 (UI & exports)
- Add provenance_card partial and include in 3 key show pages (claim / writing section / bibliography). Add tests to confirm the partial renders provenance entries.
- Add RiC mapping for provenance rows (small exporter change) and a test.

---

## 6) Acceptance checklist (what "done" looks like)

- Unit/feature tests added and passing for: AI call recording (3 slices), human-action audit entries (approve/update), and provenance partial rendering.
- Provenance partial included in claim/section/bibliography views and shows the correct rows.
- RiC export includes Event nodes for provenance entries.
- Provenance partial included in claim/section/bibliography views and shows the correct rows.
- Admin can redact PII and the provenance record remains with redactor actor visible.
- provenance.md is updated to list exact files and tables (this file).

---

If you want me to start, pick one of the first enhancements to implement now:

1. Add ResearchAiService::recordAiCall and wire it into Writing Studio and Bibliography enrichment (with tests).
2. Add ResearchAuditService and replace ad-hoc activity_log writes in the main admin paths (approve/suspend/verify).
3. Add provenance_card partial and include it in the three show pages (claim, writing-section, bibliography).
4. Add RiC mapping for provenance entries and a test.

Status: very good
