# Compliance Autopilot - DPIA auto-draft (heratio#1199)

The compliance autopilot in `packages/ahg-privacy` auto-drafts the data-protection
paperwork from a catalogue PII scan, for a data-protection officer (DPO) to review
and sign off. Three slices ship in `ComplianceAutopilotService`: ROPA (Article 30),
retention schedule, and - this slice - the DPIA (Data Protection Impact Assessment).

## What the DPIA slice does

From the same catalogue PII scan (`scanCatalogue()`), the autopilot decides whether
a DPIA is required and, if so, auto-drafts one for sign-off.

1. The scanned categories of personal data are folded into a synthetic
   processing-activity payload.
2. That payload is screened by the EXISTING `DpiaRiskService::screen()` (GDPR
   Article 35(3) / WP29 high-risk triggers, from #1109). Risk scoring is NOT
   reinvented - the autopilot reuses the established screen. The four triggers are:
   special-category (Art 9/10) data, large-scale profiling / systematic monitoring,
   biometric/genetic processing, and unsafeguarded cross-border transfer.
3. If the screen returns `high_risk = false`, the autopilot reports "No DPIA
   required on screening grounds" and persists nothing.
4. If `high_risk = true`, a draft `Dpia` (status `draft`) is persisted into the
   existing `ahg_dpia` register via `DpiaService::create()`, with the risk findings
   and a recommendation pre-filled into the standard Article 35 fields
   (`description`, `necessity_proportionality`, `risks_to_subjects`,
   `measures_to_mitigate`, `residual_risks`).

The narrative is enriched by the gateway LLM (`AhgAiServices\Services\LlmService`,
purpose `compliance.dpia_draft`) when reachable, grounded only in the category and
trigger labels - never inventing record content. A deterministic fallback narrative
is used when the gateway is unavailable. Crucially, the LLM never decides whether a
DPIA is required - that determination stays with the deterministic screen.

## Idempotency and DPO sign-off

- The draft is upserted by a stable name (`Archival catalogue personal data
  (auto-drafted)`), so a re-scan refreshes the same draft rather than spawning
  duplicates.
- A draft a DPO has already advanced (status `review` / `completed` / `archived`)
  is never clobbered by a re-scan.
- "Accept (move to review)" on the autopilot card calls
  `DpiaService::moveToReview()`, advancing the draft into the formal DPIA workflow.
  From there the DPO completes and signs it off on the DPIA register
  (`/admin/privacy/dpia`), which back-fills any linked ROPA row.

## Wiring

- Service: `ComplianceAutopilotService::draftDpia(array $scan)`,
  `latestAutoDpia()`, `acceptDpia(int $id)`.
- Controller: `ComplianceAutopilotController::draftDpia()` and `acceptDpia()`.
- Routes (load via `Route::middleware('web')->group(...)` in the provider):
  - `POST /admin/privacy/autopilot/dpia` -> `ahgprivacy.autopilot.dpia`
  - `POST /admin/privacy/autopilot/dpia/{id}/accept` -> `ahgprivacy.autopilot.dpia.accept`
- UI: DPIA card on `/admin/privacy/autopilot` (`resources/views/autopilot.blade.php`),
  with a "Screen and draft DPIA" button, the verdict (required / not required), the
  high-risk triggers, the drafted assessment fields, an "Open in DPIA register" link,
  and an "Accept (move to review)" button.

## Jurisdiction neutrality

All narrative text stays generic ("the applicable data-protection regime"); no single
country's law is named. The per-market compliance module (POPIA / GDPR / IPSAS / etc.)
supplies the concrete statute. Consistent with Heratio's international, market-pluggable
positioning.

## No new tables

The DPIA slice reuses the existing `ahg_dpia` register (and `privacy_dpia_log` via
`DpiaService`). No schema change was needed for this slice.
